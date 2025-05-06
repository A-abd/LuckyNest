<?php
include __DIR__ . '/mail_config.php';
include __DIR__ . '/sms_config.php';

$logs_dir = __DIR__ . '/../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

if (!ini_get('error_log')) {
    ini_set('error_log', $logs_dir . '/notifications_logs.php');
}

/**
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
            error_log("Failed to send notification: User ID {$user_id} not found");
            return ['error' => 'User not found'];
        }

        // always logging to notifications table regardless of the user's preferences
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':message', $notification_message, PDO::PARAM_STR);
        $stmt->execute();
        $results['notification'] = true;

        // send email if enabled
        if ($user['email_notifications'] && !empty($user['email'])) {
            try {
                $mail = getConfiguredMailer();
                $mail->addAddress($user['email'], $user['forename'] . ' ' . $user['surname']);
                $mail->Subject = $subject;
                $mail->Body = $html_message;
                $mail->send();
                $results['email'] = true;
                error_log("Email notification sent to {$user['email']} for user {$user_id}");
            } catch (Exception $e) {
                error_log("Failed to send email notification to {$user['email']} for user {$user_id}: " . $e->getMessage());
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
                error_log("SMS notification sent to {$formatted_phone} for user {$user_id}");
            } catch (Exception $e) {
                error_log("Failed to send SMS notification to {$user['phone']} for user {$user_id}: " . $e->getMessage());
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error in sendUserNotifications for user {$user_id}: " . $e->getMessage());
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
            
            <p><a href='localhost/LuckyNest/guest/payments_page?type={$payment_type}&ref={$reference_id}' class='button'>Make Payment Now</a></p>
            
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

    error_log("Sending payment reminder to user {$user_id} for {$payment_type_display} payment of £" . number_format($amount, 2));
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
            
            <p><a href='localhost/LuckyNest/guest/payments_page?type={$payment_type}&ref={$reference_id}' class='button'>Make Payment Now</a></p>
            
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

    error_log("Sending late payment notice to user {$user_id} for {$payment_type_display} payment of £" . number_format($amount, 2) . " ({$days_late} days late)");
    return sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'late_payment');
}

/**
 * @param PDO $conn
 * @param int $user_id The user to notify
 * @param float $amount Deposit amount
 * @param string $check_in_date Check-in date
 * @param int $booking_id Booking reference
 * @param int $days_before Days before check-in (0 for day of)
 * @return array Results of notification attempts
 */
function sendBookingDepositReminder($conn, $user_id, $amount, $check_in_date, $booking_id, $days_before)
{
    // Determine urgency in message based on days before
    $urgency = ($days_before == 0) ? "TODAY" : "SOON";
    
    // The email content
    $subject = "Booking Deposit Required: Payment Due {$urgency}";
    $html_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { color: #2c3e50; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                .button { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                .urgent { color: #e74c3c; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1 class='header'>Booking Deposit Reminder</h1>";
            
    if ($days_before == 0) {
        $html_message .= "
            <p class='urgent'>Your check-in is TODAY and your booking deposit is due immediately.</p>";
    } else {
        $html_message .= "
            <p>This is a friendly reminder that your booking deposit is due soon. Your check-in date is in {$days_before} days.</p>";
    }
            
    $html_message .= "
            <div class='details'>
                <h3>Payment Details</h3>
                <p><strong>Payment Type:</strong> Booking Deposit</p>
                <p><strong>Amount Due:</strong> £" . number_format($amount, 2) . "</p>
                <p><strong>Check-in Date:</strong> {$check_in_date}</p>
                <p><strong>Booking ID:</strong> {$booking_id}</p>
            </div>
            
            <p>Please ensure your deposit is paid promptly to secure your booking.</p>
            
            <p><a href='localhost/LuckyNest/guest/payments_page?type=deposit&ref={$booking_id}' class='button'>Pay Deposit Now</a></p>
            
            <div class='footer'>
                <p>If you have already made this payment, please disregard this message.</p>
                <p>Thank you for choosing LuckyNest!</p>
            </div>
        </body>
        </html>
    ";

    // SMS content (shorter)
    if ($days_before == 0) {
        $sms_message = "URGENT - LuckyNest: Your booking deposit of £" . number_format($amount, 2) . " is due TODAY for your check-in on {$check_in_date}. Please pay immediately to secure your booking.";
    } else {
        $sms_message = "LuckyNest: Your booking deposit of £" . number_format($amount, 2) . " is due soon. Your check-in date is {$check_in_date} ({$days_before} days away). Please log in to make your payment.";
    }

    // In-app notification
    if ($days_before == 0) {
        $notification_message = "URGENT: Your booking deposit of £" . number_format($amount, 2) . " is due TODAY for your check-in.";
    } else {
        $notification_message = "Your booking deposit of £" . number_format($amount, 2) . " is due soon. Your check-in date is in {$days_before} days.";
    }

    error_log("Sending booking deposit reminder to user {$user_id} for booking {$booking_id} ({$days_before} days before check-in)");
    return sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'booking_deposit_reminder');
}

/**
 * @param PDO $conn
 * @param int $user_id The user to notify
 * @param float $amount Deposit amount
 * @param string $check_in_date Check-in date
 * @param int $booking_id Booking reference
 * @param int $days_late Days after check-in
 * @return array Results of notification attempts
 */
function sendLateBookingDepositNotice($conn, $user_id, $amount, $check_in_date, $booking_id, $days_late)
{
    $subject = "URGENT: Your Booking Deposit is Overdue";
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
            <h1 class='header'>Overdue Booking Deposit Notice</h1>
            <p>Our records indicate that your booking deposit is now <span class='overdue'>{$days_late} days overdue</span>. 
               Your check-in date was {$check_in_date}.</p>
            
            <div class='details'>
                <h3>Payment Details</h3>
                <p><strong>Payment Type:</strong> Booking Deposit</p>
                <p><strong>Amount Due:</strong> £" . number_format($amount, 2) . "</p>
                <p><strong>Check-in Date:</strong> {$check_in_date} (PASSED)</p>
                <p><strong>Booking ID:</strong> {$booking_id}</p>
            </div>
            
            <p>Please make your payment <span class='overdue'>immediately</span> to avoid potential booking cancellation 
               or additional late fees.</p>
            
            <p><a href='localhost/LuckyNest/guest/payments_page?type=deposit&ref={$booking_id}' class='button'>Pay Deposit Now</a></p>
            
            <div class='footer'>
                <p>If you have already made this payment, please disregard this message.</p>
                <p>If you're experiencing financial difficulties, please contact us to discuss payment options.</p>
                <p>Thank you for choosing LuckyNest!</p>
            </div>
        </body>
        </html>
    ";

    $sms_message = "URGENT - LuckyNest: Your booking deposit of £" . number_format($amount, 2) . " is {$days_late} days overdue. Your check-in date was {$check_in_date}. Please pay immediately to avoid cancellation.";

    $notification_message = "OVERDUE: Your booking deposit of £" . number_format($amount, 2) . " was due on {$check_in_date} and is now {$days_late} days late. Immediate payment required.";

    error_log("Sending late booking deposit notice to user {$user_id} for booking {$booking_id} ({$days_late} days after check-in)");
    return sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'late_booking_deposit');
}

/**
 * @param PDO $conn
 * @param int $user_id The user to notify
 * @param float $amount Payment amount
 * @param string $slot_datetime Date and time of the laundry slot
 * @param int $reference_id Reference ID
 * @param int $hours_before Hours before slot (24 for day before)
 * @return array Results of notification attempts
 */
function sendLaundryReminder($conn, $user_id, $amount, $slot_datetime, $reference_id, $hours_before)
{
    $slot_date = date('Y-m-d', strtotime($slot_datetime));
    $slot_time = date('H:i', strtotime($slot_datetime));
    
    $is_day_before = ($hours_before == 24);
    
    if ($is_day_before) {
        $subject = "Reminder: Your Laundry Slot is Tomorrow";
    } else {
        $subject = "Reminder: Your Laundry Slot is in {$hours_before} Hours";
    }
    
    $html_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { color: #2c3e50; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                .button { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                .time { font-weight: bold; color: #e74c3c; }
            </style>
        </head>
        <body>
            <h1 class='header'>Laundry Slot Reminder</h1>";
            
    if ($is_day_before) {
        $html_message .= "
            <p>This is a friendly reminder that you have a laundry slot booked for <span class='time'>tomorrow, {$slot_date}</span> at <span class='time'>{$slot_time}</span>.</p>";
    } else {
        $html_message .= "
            <p>This is a friendly reminder that you have a laundry slot booked in <span class='time'>{$hours_before} hours</span> at <span class='time'>{$slot_time}</span> today.</p>";
    }
            
    $html_message .= "
            <div class='details'>
                <h3>Laundry Details</h3>
                <p><strong>Date:</strong> {$slot_date}</p>
                <p><strong>Time:</strong> {$slot_time}</p>
                <p><strong>Payment Required:</strong> £" . number_format($amount, 2) . "</p>
                <p><strong>Reference ID:</strong> {$reference_id}</p>
            </div>
            
            <p>Please note that payment is required before using the laundry facilities.</p>
            
            <p><a href='localhost/LuckyNest/guest/payments_page?type=laundry&ref={$reference_id}' class='button'>Make Payment Now</a></p>
            
            <div class='footer'>
                <p>If you have already made this payment, please disregard the payment reminder.</p>
                <p>Thank you for choosing LuckyNest!</p>
            </div>
        </body>
        </html>
    ";

    if ($is_day_before) {
        $sms_message = "LuckyNest: Reminder that your laundry slot is tomorrow ({$slot_date}) at {$slot_time}. Payment of £" . number_format($amount, 2) . " is required. Please log in to make your payment.";
    } else {
        $sms_message = "LuckyNest: Reminder that your laundry slot is in {$hours_before} hours at {$slot_time} today. Payment of £" . number_format($amount, 2) . " is required. Please log in to make your payment.";
    }

    if ($is_day_before) {
        $notification_message = "Reminder: Your laundry slot is tomorrow ({$slot_date}) at {$slot_time}. Payment of £" . number_format($amount, 2) . " is required.";
    } else {
        $notification_message = "Reminder: Your laundry slot is in {$hours_before} hours at {$slot_time} today. Payment of £" . number_format($amount, 2) . " is required.";
    }

    error_log("Sending laundry reminder to user {$user_id} for slot at {$slot_datetime} ({$hours_before} hours before)");
    return sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'laundry_reminder');
}

/**
 * @param PDO $conn Database connection
 * @param int $user_id The user to notify
 * @param string $change_type Type of change ('booking', 'meal_plan', 'laundry')
 * @param array $old_details Old details before change
 * @param array $new_details New details after change
 * @param int $reference_id Reference ID for the booking/meal plan/laundry
 * @return array Results of notification attempts
 */
function sendChangeNotification($conn, $user_id, $change_type, $old_details, $new_details, $reference_id)
{
    $changes_list = generateChangesList($change_type, $old_details, $new_details);
    
    if (empty($changes_list)) {
        error_log("No significant changes detected for {$change_type} with reference ID {$reference_id}");
        return ['error' => 'No significant changes detected'];
    }
    
    $type_display = ucwords(str_replace('_', ' ', $change_type));
    
    $subject = "Important: Changes to Your {$type_display}";
    $html_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { color: #2c3e50; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                .button { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                .change { color: #e74c3c; }
                ul.changes { padding-left: 20px; }
                ul.changes li { margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <h1 class='header'>Changes to Your {$type_display}</h1>
            <p>This is to inform you that changes have been made to your {$type_display} (Reference ID: {$reference_id}).</p>
            
            <div class='details'>
                <h3>The following changes have been made:</h3>
                <ul class='changes'>
                    {$changes_list}
                </ul>
            </div>
            
            <p>If you have any questions about these changes, please contact our support team.</p>
            
            <p><a href='https://your-website.com/view?type={$change_type}&ref={$reference_id}' class='button'>View Details</a></p>
            
            <div class='footer'>
                <p>Thank you for choosing LuckyNest!</p>
            </div>
        </body>
        </html>
    ";

    $sms_message = "LuckyNest: Changes have been made to your {$type_display} (Ref: {$reference_id}). Please check your email or log in to view the details.";

    $notification_message = "Important: Changes have been made to your {$type_display} (Reference ID: {$reference_id}).";

    error_log("Sending change notification to user {$user_id} for {$change_type} with reference ID {$reference_id}");
    return sendUserNotifications($conn, $user_id, $subject, $html_message, $sms_message, $notification_message, 'change_notification');
}

/**
 * Generate a formatted HTML list of changes between old and new details
 * 
 * @param string $change_type Type of change ('booking', 'meal_plan', 'laundry')
 * @param array $old_details Old details before change
 * @param array $new_details New details after change
 * @return string HTML list of changes
 */
function generateChangesList($change_type, $old_details, $new_details)
{
    $changes = [];
    
    switch ($change_type) {
        case 'booking':
            // Check for room change
            if (isset($old_details['room_number']) && isset($new_details['room_number']) && 
                $old_details['room_number'] !== $new_details['room_number']) {
                $changes[] = "<li>Room changed from <strong>{$old_details['room_number']}</strong> to <strong class='change'>{$new_details['room_number']}</strong></li>";
            }
            
            // Check for check-in date change
            if (isset($old_details['check_in_date']) && isset($new_details['check_in_date']) && 
                $old_details['check_in_date'] !== $new_details['check_in_date']) {
                $changes[] = "<li>Check-in date changed from <strong>{$old_details['check_in_date']}</strong> to <strong class='change'>{$new_details['check_in_date']}</strong></li>";
            }
            
            // Check for check-out date change
            if (isset($old_details['check_out_date']) && isset($new_details['check_out_date']) && 
                $old_details['check_out_date'] !== $new_details['check_out_date']) {
                $changes[] = "<li>Check-out date changed from <strong>{$old_details['check_out_date']}</strong> to <strong class='change'>{$new_details['check_out_date']}</strong></li>";
            }
            
            // Check for price change
            if (isset($old_details['total_price']) && isset($new_details['total_price']) && 
                $old_details['total_price'] !== $new_details['total_price']) {
                $old_price = number_format($old_details['total_price'], 2);
                $new_price = number_format($new_details['total_price'], 2);
                $changes[] = "<li>Price changed from <strong>£{$old_price}</strong> to <strong class='change'>£{$new_price}</strong></li>";
            }
            
            // Check for booking status change
            if (isset($old_details['booking_is_cancelled']) && isset($new_details['booking_is_cancelled']) && 
                $old_details['booking_is_cancelled'] !== $new_details['booking_is_cancelled']) {
                $status = $new_details['booking_is_cancelled'] ? 'cancelled' : 'reactivated';
                $changes[] = "<li>Your booking has been <strong class='change'>{$status}</strong></li>";
            }
            break;
            
        case 'meal_plan':
            // Check for plan name change
            if (isset($old_details['name']) && isset($new_details['name']) && 
                $old_details['name'] !== $new_details['name']) {
                $changes[] = "<li>Meal plan changed from <strong>{$old_details['name']}</strong> to <strong class='change'>{$new_details['name']}</strong></li>";
            }
            
            // Check for start date change
            if (isset($old_details['start_date']) && isset($new_details['start_date']) && 
                $old_details['start_date'] !== $new_details['start_date']) {
                $changes[] = "<li>Start date changed from <strong>{$old_details['start_date']}</strong> to <strong class='change'>{$new_details['start_date']}</strong></li>";
            }
            
            // Check for end date change
            if (isset($old_details['end_date']) && isset($new_details['end_date']) && 
                $old_details['end_date'] !== $new_details['end_date']) {
                $changes[] = "<li>End date changed from <strong>{$old_details['end_date']}</strong> to <strong class='change'>{$new_details['end_date']}</strong></li>";
            }
            
            // Check for price change
            if (isset($old_details['price']) && isset($new_details['price']) && 
                $old_details['price'] !== $new_details['price']) {
                $old_price = number_format($old_details['price'], 2);
                $new_price = number_format($new_details['price'], 2);
                $changes[] = "<li>Price changed from <strong>£{$old_price}</strong> to <strong class='change'>£{$new_price}</strong></li>";
            }
            
            // Check for meal plan status change
            if (isset($old_details['is_cancelled']) && isset($new_details['is_cancelled']) && 
                $old_details['is_cancelled'] !== $new_details['is_cancelled']) {
                $status = $new_details['is_cancelled'] ? 'cancelled' : 'reactivated';
                $changes[] = "<li>Your meal plan has been <strong class='change'>{$status}</strong></li>";
            }
            break;
            
        case 'laundry':
            // Check for date change
            if (isset($old_details['date']) && isset($new_details['date']) && 
                $old_details['date'] !== $new_details['date']) {
                $changes[] = "<li>Date changed from <strong>{$old_details['date']}</strong> to <strong class='change'>{$new_details['date']}</strong></li>";
            }
            
            // Check for time change
            if (isset($old_details['start_time']) && isset($new_details['start_time']) && 
                $old_details['start_time'] !== $new_details['start_time']) {
                $changes[] = "<li>Time changed from <strong>{$old_details['start_time']}</strong> to <strong class='change'>{$new_details['start_time']}</strong></li>";
            }
            
            // Check for price change
            if (isset($old_details['price']) && isset($new_details['price']) && 
                $old_details['price'] !== $new_details['price']) {
                $old_price = number_format($old_details['price'], 2);
                $new_price = number_format($new_details['price'], 2);
                $changes[] = "<li>Price changed from <strong>£{$old_price}</strong> to <strong class='change'>£{$new_price}</strong></li>";
            }
            
            // Check for laundry slot status change
            if (isset($old_details['is_cancelled']) && isset($new_details['is_cancelled']) && 
                $old_details['is_cancelled'] !== $new_details['is_cancelled']) {
                $status = $new_details['is_cancelled'] ? 'cancelled' : 'reactivated';
                $changes[] = "<li>Your laundry slot has been <strong class='change'>{$status}</strong></li>";
            }
            break;
    }
    
    return implode("\n", $changes);
}