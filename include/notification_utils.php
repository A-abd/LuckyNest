<?php
include __DIR__ . '/mail_config.php';
include __DIR__ . '/sms_config.php';

/**
 * Send notifications to a user based on their preferences
 *
 * @param PDO $conn
 * @param int $user_id the user we are notifying
 * @param string $subject the emails subject
 * @param string $html_message email html content
 * @param string $sms_message sms plantext content
 * @param string $notification_message in app notification msg
 * @param string $notification_type
 * @return array results of notification attempt
 */
function sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, $notification_type = '')
{
    $results = [
        'email' => false,
        'sms' => false,
        'notification' => false
    ];

    try {
        // getting user details and their preferences
        $stmt = $conn->prepare("SELECT email, phone, email_notifications, sms_notifications, forename, surname 
                               FROM users WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['error' => 'User not found'];
        }

        // always logging to notifications table regardless of the user's preferences
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':message', $notification_message, PDO::PARAM_STR);
        $stmt->execute();
        $results['notification'] = true;

        // output JavaScript to play notification sound if this is a web request
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
            echo "<script>
                const sound = new Audio('/LuckyNest/assets/sounds/notification.mp3');
                sound.play().catch(error => {
                    console.error('Failed to play notification sound:', error);
                });
            </script>";
            flush();
        }

        // send email if enabled
        if ($user['email_notifications'] && !empty($user['email'])) {
            try {
                $mail = getConfiguredMailer();
                $mail->addAddress($user['email'], $user['forename'] . ' ' . $user['surname']);
                $mail->Subject = $subject;
                $mail->Body = $html_message;
                $mail->send();
                $results['email'] = true;
            } catch (Exception $e) {
                error_log("Failed to send email notification: " . $e->getMessage());
            }
        }

        // send sms if enabled
        if ($user['sms_notifications'] && !empty($user['phone'])) {
            try {
                $formatted_phone = $user['phone'];
                // If the phone number doesn't start with +, add +44
                if (substr($formatted_phone, 0, 1) !== '+') {
                    $formatted_phone = preg_replace('/^0/', '', $formatted_phone);

                    $formatted_phone = '+44' . $formatted_phone;
                }

                sendSMS($formatted_phone, $sms_message);
                $results['sms'] = true;
            } catch (Exception $e) {
                error_log("Failed to send SMS notification: " . $e->getMessage());
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error in sendUserNotifications: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 *
 * @param PDO $conn
 * @param int $user_id the user we are notifying
 * @param string $payment_type rent, meal_plan or laundry
 * @param float $amount
 * @param string $due_date
 * @param int $reference_id
 * @return array
 */
function sendPaymentReminder($conn, $user_id, $payment_type, $amount, $due_date, $reference_id)
{
    $payment_type_display = ucfirst(str_replace('_', ' ', $payment_type));

    // the email content:
    $subject = "Payment Reminder: Your {$payment_type_display} Payment is Due Soon";
    $html_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { color: #2c3e50; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                .button { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <h1 class='header'>Payment Reminder</h1>
            <p>This is a friendly reminder that you have an upcoming payment due.</p>
            
            <div class='details'>
                <h3>Payment Details</h3>
                <p><strong>Payment Type:</strong> {$payment_type_display}</p>
                <p><strong>Amount Due:</strong> £" . number_format($amount, 2) . "</p>
                <p><strong>Due Date:</strong> {$due_date}</p>
                <p><strong>Reference ID:</strong> {$reference_id}</p>
            </div>
            
            <p>Please ensure your payment is made on time to avoid any late fees.</p>
            
            <p><a href='https://your-website.com/payment.php?type={$payment_type}&ref={$reference_id}' class='button'>Make Payment Now</a></p>
            
            <div class='footer'>
                <p>If you have already made this payment, please disregard this message.</p>
                <p>Thank you for choosing LuckyNest!</p>
            </div>
        </body>
        </html>
    ";

    // SMS content (shorter)
    $sms_message = "LuckyNest: Your {$payment_type_display} payment of £" . number_format($amount, 2) . " is due on {$due_date}. Please log in to make your payment.";

    // In-app notification
    $notification_message = "Your {$payment_type_display} payment of £" . number_format($amount, 2) . " is due on {$due_date}.";

    return sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'payment_reminder');
}

/**
 * send late payment notifications
 *
 * @param PDO $conn
 * @param int $user_id the user we are notifying
 * @param string $payment_type rent, meal_plan or laundry
 * @param float $amount
 * @param string $due_date
 * @param int $reference_id
 * @param int $days_late
 * @return array results of notif attempts
 */
function sendLatePaymentNotice($conn, $user_id, $payment_type, $amount, $due_date, $reference_id, $days_late)
{
    $payment_type_display = ucfirst(str_replace('_', ' ', $payment_type));

    // the email content
    $subject = "IMPORTANT: Your {$payment_type_display} Payment is Overdue";
    $html_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { color: #c0392b; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                .button { background-color: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                .overdue { color: #c0392b; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1 class='header'>Overdue Payment Notice</h1>
            <p>Our records indicate that your payment is now <span class='overdue'>{$days_late} days overdue</span>.</p>
            
            <div class='details'>
                <h3>Payment Details</h3>
                <p><strong>Payment Type:</strong> {$payment_type_display}</p>
                <p><strong>Amount Due:</strong> £" . number_format($amount + 200, 2) . "</p>
                <p><strong>Due Date:</strong> {$due_date}</p>
                <p><strong>Reference ID:</strong> {$reference_id}</p>
            </div>
            
            <p>Please make your payment as soon as possible to avoid any additional late fees or service interruptions.</p>
            
            <p><a href='https://your-website.com/payment.php?type={$payment_type}&ref={$reference_id}' class='button'>Make Payment Now</a></p>
            
            <div class='footer'>
                <p>If you have already made this payment, please disregard this message.</p>
                <p>If you're experiencing financial difficulties, please contact us to discuss payment options.</p>
                <p>Thank you for choosing LuckyNest!</p>
            </div>
        </body>
        </html>
    ";

    $sms_message = "IMPORTANT: Your {$payment_type_display} payment of £" . number_format($amount, 2) . " is {$days_late} days overdue. Please log in immediately to make your payment.";

    $notification_message = "OVERDUE: Your {$payment_type_display} payment of £" . number_format($amount, 2) . " was due on {$due_date} and is now {$days_late} days late.";

    return sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'late_payment');
}