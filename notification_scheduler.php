<?php
date_default_timezone_set('Europe/London');

include __DIR__ . '/include/db.php';
include __DIR__ . '/include/notification_utils.php';

$logs_dir = __DIR__ . '/logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logs_dir . '/notifications_logs.php');
error_log("Running notification scheduler at " . date('Y-m-d H:i:s'));

/**
 * @param PDO $conn
 * @param int $days_before
 * @return array
 */
function sendRentPaymentReminders($conn, $days_before = 7)
{
    $results = [];

    $target_date = date('Y-m-d', strtotime("+{$days_before} days"));

    try {
        $stmt = $conn->prepare("
            SELECT b.booking_id, b.guest_id, b.total_price, 
                   DATE_FORMAT(DATE_ADD(b.check_in_date, INTERVAL TIMESTAMPDIFF(MONTH, b.check_in_date, CURRENT_DATE()) MONTH), '%Y-%m-%d') as next_payment_date,
                   r.room_number, u.forename, u.surname
            FROM bookings b
            JOIN rooms r ON b.room_id = r.room_id
            JOIN users u ON b.guest_id = u.user_id
            WHERE b.booking_is_cancelled = 0 
            AND b.booking_is_paid = 1
            AND DATE_FORMAT(DATE_ADD(b.check_in_date, INTERVAL TIMESTAMPDIFF(MONTH, b.check_in_date, CURRENT_DATE()) MONTH), '%Y-%m-%d') = :target_date
        ");
        $stmt->bindParam(':target_date', $target_date);
        $stmt->execute();

        while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as reminder_count 
                FROM notifications 
                WHERE user_id = :user_id 
                AND message LIKE :message_pattern
                AND created_at >= :recent_date
            ");

            $message_pattern = "%rent payment of £" . number_format($booking['total_price'], 2) . " is due on {$booking['next_payment_date']}%";
            $recent_date = date('Y-m-d H:i:s', strtotime('-3 days'));

            $check_stmt->bindParam(':user_id', $booking['guest_id']);
            $check_stmt->bindParam(':message_pattern', $message_pattern);
            $check_stmt->bindParam(':recent_date', $recent_date);
            $check_stmt->execute();

            $reminder_check = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($reminder_check['reminder_count'] == 0) {
                $result = sendPaymentReminder(
                    $conn,
                    $booking['guest_id'],
                    'rent',
                    $booking['total_price'],
                    $booking['next_payment_date'],
                    $booking['booking_id']
                );

                $results[] = [
                    'user' => $booking['forename'] . ' ' . $booking['surname'],
                    'type' => 'rent',
                    'room' => $booking['room_number'],
                    'amount' => $booking['total_price'],
                    'due_date' => $booking['next_payment_date'],
                    'result' => $result
                ];

                error_log("Sent rent payment reminder to user {$booking['guest_id']} for room {$booking['room_number']}");
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error sending rent payment reminders: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * @param PDO $conn
 * @param int $days_late
 * @return array
 */
function sendLatePaymentNotifications($conn, $days_late = 1)
{
    $results = [];

    $target_date = date('Y-m-d', strtotime("-{$days_late} days"));

    try {
        $stmt = $conn->prepare("
            SELECT b.booking_id, b.guest_id, b.total_price, 
                   DATE_FORMAT(DATE_ADD(b.check_in_date, INTERVAL TIMESTAMPDIFF(MONTH, b.check_in_date, CURRENT_DATE()) MONTH), '%Y-%m-%d') as payment_date,
                   r.room_number, u.forename, u.surname
            FROM bookings b
            JOIN rooms r ON b.room_id = r.room_id
            JOIN users u ON b.guest_id = u.user_id
            WHERE b.booking_is_cancelled = 0
            AND DATE_FORMAT(DATE_ADD(b.check_in_date, INTERVAL TIMESTAMPDIFF(MONTH, b.check_in_date, CURRENT_DATE()) MONTH), '%Y-%m-%d') = :target_date
            AND NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.reference_id = b.booking_id 
                AND p.payment_type = 'rent'
                AND DATE(p.payment_date) >= :payment_month_start
                AND DATE(p.payment_date) <= :payment_month_end
            )
        ");

        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');

        $stmt->bindParam(':target_date', $target_date);
        $stmt->bindParam(':payment_month_start', $month_start);
        $stmt->bindParam(':payment_month_end', $month_end);
        $stmt->execute();

        while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as notice_count 
                FROM notifications 
                WHERE user_id = :user_id 
                AND message LIKE :message_pattern
                AND created_at >= :recent_date
            ");

            $message_pattern = "%OVERDUE: Your rent payment of £" . number_format($booking['total_price'], 2) . "%";
            $recent_date = date('Y-m-d H:i:s', strtotime('-1 day'));

            $check_stmt->bindParam(':user_id', $booking['guest_id']);
            $check_stmt->bindParam(':message_pattern', $message_pattern);
            $check_stmt->bindParam(':recent_date', $recent_date);
            $check_stmt->execute();

            $notice_check = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($notice_check['notice_count'] == 0) {
                $result = sendLatePaymentNotice(
                    $conn,
                    $booking['guest_id'],
                    'rent',
                    $booking['total_price'],
                    $booking['payment_date'],
                    $booking['booking_id'],
                    $days_late
                );

                $results[] = [
                    'user' => $booking['forename'] . ' ' . $booking['surname'],
                    'type' => 'rent',
                    'room' => $booking['room_number'],
                    'amount' => $booking['total_price'],
                    'due_date' => $booking['payment_date'],
                    'days_late' => $days_late,
                    'result' => $result
                ];

                error_log("Sent late payment notice to user {$booking['guest_id']} for room {$booking['room_number']} ({$days_late} days late)");
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error sending late payment notifications: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * @param PDO $conn
 * @param int $days_before check in to send a reminder
 * @return array
 */
function sendBookingPaymentReminders($conn, $days_before = 3) 
{
    $results = [];
    
    $target_date = date('Y-m-d', strtotime("+{$days_before} days"));
    
    if ($days_before == 0) {
        $target_date = date('Y-m-d');
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT b.booking_id, b.guest_id, b.total_price, b.check_in_date,
                   r.room_number, u.forename, u.surname, rt.deposit_amount
            FROM bookings b
            JOIN rooms r ON b.room_id = r.room_id
            JOIN room_types rt ON r.room_type_id = rt.room_type_id
            JOIN users u ON b.guest_id = u.user_id
            WHERE b.booking_is_cancelled = 0 
            AND b.booking_is_paid = 0
            AND b.check_in_date = :target_date
            AND NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.reference_id = b.booking_id 
                AND p.payment_type = 'deposit'
            )
        ");
        
        $stmt->bindParam(':target_date', $target_date);
        $stmt->execute();
        
        while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as reminder_count 
                FROM notifications 
                WHERE user_id = :user_id 
                AND message LIKE :message_pattern
                AND created_at >= :recent_date
            ");
            
            $message_pattern = "%booking deposit of £" . number_format($booking['deposit_amount'], 2) . " is due%";
            $recent_date = date('Y-m-d H:i:s', strtotime('-1 day'));
            
            $check_stmt->bindParam(':user_id', $booking['guest_id']);
            $check_stmt->bindParam(':message_pattern', $message_pattern);
            $check_stmt->bindParam(':recent_date', $recent_date);
            $check_stmt->execute();
            
            $reminder_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reminder_check['reminder_count'] == 0) {
                $result = sendBookingDepositReminder(
                    $conn,
                    $booking['guest_id'],
                    $booking['deposit_amount'],
                    $booking['check_in_date'],
                    $booking['booking_id'],
                    $days_before
                );
                
                $results[] = [
                    'user' => $booking['forename'] . ' ' . $booking['surname'],
                    'type' => 'booking_deposit',
                    'room' => $booking['room_number'],
                    'amount' => $booking['deposit_amount'],
                    'due_date' => $booking['check_in_date'],
                    'result' => $result
                ];
                
                error_log("Sent booking deposit reminder to user {$booking['guest_id']} for room {$booking['room_number']}");
            }
        }
        
        return $results;
    } catch (Exception $e) {
        error_log("Error sending booking payment reminders: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * @param PDO $conn
 * @param int $days_late days after check-in to send reminder
 * @return array
 */
function sendLateBookingPaymentReminders($conn, $days_late = 1) 
{
    $results = [];
    
    $target_date = date('Y-m-d', strtotime("-{$days_late} days"));
    
    try {
        $stmt = $conn->prepare("
            SELECT b.booking_id, b.guest_id, b.total_price, b.check_in_date,
                   r.room_number, u.forename, u.surname, rt.deposit_amount
            FROM bookings b
            JOIN rooms r ON b.room_id = r.room_id
            JOIN room_types rt ON r.room_type_id = rt.room_type_id
            JOIN users u ON b.guest_id = u.user_id
            WHERE b.booking_is_cancelled = 0 
            AND b.booking_is_paid = 0
            AND b.check_in_date = :target_date
            AND NOT EXISTS (
                SELECT 1 FROM payments p 
                WHERE p.reference_id = b.booking_id 
                AND p.payment_type = 'deposit'
            )
        ");
        
        $stmt->bindParam(':target_date', $target_date);
        $stmt->execute();
        
        while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as reminder_count 
                FROM notifications 
                WHERE user_id = :user_id 
                AND message LIKE :message_pattern
                AND created_at >= :recent_date
            ");
            
            $message_pattern = "%OVERDUE: Your booking deposit of £" . number_format($booking['deposit_amount'], 2) . "%";
            $recent_date = date('Y-m-d H:i:s', strtotime('-1 day'));
            
            $check_stmt->bindParam(':user_id', $booking['guest_id']);
            $check_stmt->bindParam(':message_pattern', $message_pattern);
            $check_stmt->bindParam(':recent_date', $recent_date);
            $check_stmt->execute();
            
            $reminder_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reminder_check['reminder_count'] == 0) {
                $result = sendLateBookingDepositNotice(
                    $conn,
                    $booking['guest_id'],
                    $booking['deposit_amount'],
                    $booking['check_in_date'],
                    $booking['booking_id'],
                    $days_late
                );
                
                $results[] = [
                    'user' => $booking['forename'] . ' ' . $booking['surname'],
                    'type' => 'late_booking_deposit',
                    'room' => $booking['room_number'],
                    'amount' => $booking['deposit_amount'],
                    'due_date' => $booking['check_in_date'],
                    'days_late' => $days_late,
                    'result' => $result
                ];
                
                error_log("Sent late booking deposit notice to user {$booking['guest_id']} for room {$booking['room_number']} ({$days_late} days late)");
            }
        }
        
        return $results;
    } catch (Exception $e) {
        error_log("Error sending late booking payment reminders: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * @param PDO $conn
 * @param int $days_before start date to send a reminder
 * @return array
 */
function sendMealPlanReminders($conn, $days_before = 1)
{
    $results = [];

    $target_date = date('Y-m-d', strtotime("+{$days_before} days"));
    
    if ($days_before == 0) {
        $target_date = date('Y-m-d');
    }

    try {
        $stmt = $conn->prepare("
            SELECT mpl.meal_plan_user_link as reference_id, 
                   mpl.user_id, 
                   mp.price,
                   mp.meal_plan_id,
                   mp.name,
                   mpl.start_date,
                   DATE_ADD(mpl.start_date, INTERVAL mp.duration_days DAY) as expiry_date,
                   u.forename, u.surname
            FROM meal_plan_user_link mpl
            JOIN meal_plans mp ON mpl.meal_plan_id = mp.meal_plan_id
            JOIN users u ON mpl.user_id = u.user_id
            WHERE mpl.is_cancelled = 0
            AND mpl.start_date = :target_date
        ");

        $stmt->bindParam(':target_date', $target_date);
        $stmt->execute();

        while ($meal_plan = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as reminder_count 
                FROM notifications 
                WHERE user_id = :user_id 
                AND message LIKE :message_pattern
                AND created_at >= :recent_date
            ");

            $message_pattern = "%Meal Plan payment of £" . number_format($meal_plan['price'], 2) . "%";
            $recent_date = date('Y-m-d H:i:s', strtotime('-1 day'));

            $check_stmt->bindParam(':user_id', $meal_plan['user_id']);
            $check_stmt->bindParam(':message_pattern', $message_pattern);
            $check_stmt->bindParam(':recent_date', $recent_date);
            $check_stmt->execute();

            $reminder_check = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($reminder_check['reminder_count'] == 0) {
                $result = sendPaymentReminder(
                    $conn,
                    $meal_plan['user_id'],
                    'meal_plan',
                    $meal_plan['price'],
                    $meal_plan['start_date'],
                    $meal_plan['reference_id']
                );

                $results[] = [
                    'user' => $meal_plan['forename'] . ' ' . $meal_plan['surname'],
                    'type' => 'meal_plan',
                    'plan_name' => $meal_plan['name'],
                    'amount' => $meal_plan['price'],
                    'due_date' => $meal_plan['start_date'],
                    'result' => $result
                ];

                error_log("Sent meal plan payment reminder to user {$meal_plan['user_id']} for plan {$meal_plan['name']}");
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error sending meal plan reminders: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * @param PDO $conn
 * @param int $hours_before slot to send a reminder
 * @return array
 */
function sendLaundryReminders($conn, $hours_before = 2)
{
    $results = [];

    $start_time = date('Y-m-d H:i:s', strtotime("+{$hours_before} hours"));
    $end_time = date('Y-m-d H:i:s', strtotime("+" . ($hours_before + 1) . " hours"));
    
    $tomorrow = date('Y-m-d', strtotime("+1 day"));
    $is_day_before = ($hours_before == 24);

    try {
        if ($is_day_before) {
            $stmt = $conn->prepare("
                SELECT lsul.laundry_slot_user_link_id as reference_id, 
                       lsul.user_id,
                       ls.laundry_slot_id,
                       ls.price,
                       CONCAT(ls.date, ' ', ls.start_time) as slot_datetime,
                       u.forename, u.surname
                FROM laundry_slot_user_link lsul
                JOIN laundry_slots ls ON lsul.laundry_slot_id = ls.laundry_slot_id
                JOIN users u ON lsul.user_id = u.user_id
                WHERE lsul.is_cancelled = 0
                AND lsul.is_paid = 0
                AND ls.date = :tomorrow_date
            ");
            
            $stmt->bindParam(':tomorrow_date', $tomorrow);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT lsul.laundry_slot_user_link_id as reference_id, 
                       lsul.user_id,
                       ls.laundry_slot_id,
                       ls.price,
                       CONCAT(ls.date, ' ', ls.start_time) as slot_datetime,
                       u.forename, u.surname
                FROM laundry_slot_user_link lsul
                JOIN laundry_slots ls ON lsul.laundry_slot_id = ls.laundry_slot_id
                JOIN users u ON lsul.user_id = u.user_id
                WHERE lsul.is_cancelled = 0
                AND lsul.is_paid = 0
                AND CONCAT(ls.date, ' ', ls.start_time) BETWEEN :start_time AND :end_time
            ");
            
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->execute();
        }

        while ($laundry = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as reminder_count 
                FROM notifications 
                WHERE user_id = :user_id 
                AND message LIKE :message_pattern
                AND created_at >= :recent_date
            ");

            $message_pattern = "%Laundry payment of £" . number_format($laundry['price'], 2) . "%";
            $recent_time = $is_day_before ? '-8 hours' : '-1 hour';
            $recent_date = date('Y-m-d H:i:s', strtotime($recent_time));

            $check_stmt->bindParam(':user_id', $laundry['user_id']);
            $check_stmt->bindParam(':message_pattern', $message_pattern);
            $check_stmt->bindParam(':recent_date', $recent_date);
            $check_stmt->execute();

            $reminder_check = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($reminder_check['reminder_count'] == 0) {
                $due_date = date('Y-m-d', strtotime($laundry['slot_datetime']));

                $result = sendLaundryReminder(
                    $conn,
                    $laundry['user_id'],
                    $laundry['price'],
                    $laundry['slot_datetime'],
                    $laundry['reference_id'],
                    $is_day_before ? 24 : $hours_before
                );

                $results[] = [
                    'user' => $laundry['forename'] . ' ' . $laundry['surname'],
                    'type' => 'laundry',
                    'amount' => $laundry['price'],
                    'slot_time' => $laundry['slot_datetime'],
                    'result' => $result
                ];

                error_log("Sent laundry reminder to user {$laundry['user_id']} for slot at {$laundry['slot_datetime']}");
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error sending laundry reminders: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * @param PDO $conn
 * @param int $days_before check-out to send a reminder
 * @return array
 */
function sendCheckoutReminders($conn, $days_before = 7)
{
    $results = [];

    $target_date = date('Y-m-d', strtotime("+{$days_before} days"));

    try {
        $stmt = $conn->prepare("
            SELECT b.booking_id, b.guest_id, b.check_out_date,
                   r.room_number, u.forename, u.surname, u.email
            FROM bookings b
            JOIN rooms r ON b.room_id = r.room_id
            JOIN users u ON b.guest_id = u.user_id
            WHERE b.booking_is_cancelled = 0
            AND b.check_out_date = :target_date
        ");

        $stmt->bindParam(':target_date', $target_date);
        $stmt->execute();

        while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as reminder_count 
                FROM notifications 
                WHERE user_id = :user_id 
                AND message LIKE :message_pattern
                AND created_at >= :recent_date
            ");

            $message_pattern = "%check-out date on {$booking['check_out_date']}%";
            $recent_date = date('Y-m-d H:i:s', strtotime('-2 days'));

            $check_stmt->bindParam(':user_id', $booking['guest_id']);
            $check_stmt->bindParam(':message_pattern', $message_pattern);
            $check_stmt->bindParam(':recent_date', $recent_date);
            $check_stmt->execute();

            $reminder_check = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($reminder_check['reminder_count'] == 0) {
                $mail = getConfiguredMailer();

                $mail->addAddress($booking['email'], $booking['forename'] . ' ' . $booking['surname']);
                $mail->Subject = "Checkout Reminder: Your Stay at LuckyNest is Ending Soon";

                $html_message = "
                    <html>
                    <head>

                    </head>
                    <body>
                        <h1 class='header'>Checkout Reminder</h1>
                        <p>Dear {$booking['forename']},</p>
                        <p>This is a friendly reminder that your stay at LuckyNest is scheduled to end soon.</p>
                        
                        <div class='details'>
                            <h3>Checkout Details</h3>
                            <p><strong>Room Number:</strong> {$booking['room_number']}</p>
                            <p><strong>Checkout Date:</strong> {$booking['check_out_date']}</p>
                            <p><strong>Checkout Time:</strong> 11:00 AM</p>
                        </div>
                        
                        <p>Please ensure that your belongings are packed and ready for departure by the checkout time. 
                           Our staff will be available to assist you with your checkout process.</p>
                        
                        <p>If you wish to extend your stay, please contact the reception desk as soon as possible.</p>
                        
                        <div class='footer'>
                            <p>Thank you for choosing LuckyNest. We hope you enjoyed your stay!</p>
                        </div>
                    </body>
                    </html>
                ";

                $mail->Body = $html_message;

                $sms_message = "LuckyNest: Reminder that your checkout date is {$booking['check_out_date']} at 11:00 AM. Please ensure all belongings are packed and ready for departure.";

                $notification_message = "Reminder: Your check-out date is scheduled for {$booking['check_out_date']} at 11:00 AM.";

                $result = sendUserNotifications(
                    $conn,
                    $booking['guest_id'],
                    $mail->Subject,
                    $html_message,
                    $sms_message,
                    $notification_message,
                    'checkout_reminder'
                );

                $results[] = [
                    'user' => $booking['forename'] . ' ' . $booking['surname'],
                    'type' => 'checkout',
                    'room' => $booking['room_number'],
                    'checkout_date' => $booking['check_out_date'],
                    'result' => $result
                ];

                error_log("Sent checkout reminder to user {$booking['guest_id']} for room {$booking['room_number']}");
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log("Error sending checkout reminders: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

try {
    $rent_reminders = sendRentPaymentReminders($conn);
    $late_payments = sendLatePaymentNotifications($conn);

    $booking_reminders_3_days = sendBookingPaymentReminders($conn, 3);
    $booking_reminders_0_days = sendBookingPaymentReminders($conn, 0);

    $late_booking_reminders_1_day = sendLateBookingPaymentReminders($conn, 1);
    $late_booking_reminders_3_days = sendLateBookingPaymentReminders($conn, 3);

    $meal_plan_reminders_1_day = sendMealPlanReminders($conn, 1);
    $meal_plan_reminders_0_days = sendMealPlanReminders($conn, 0);

    $laundry_reminders_day_before = sendLaundryReminders($conn, 24);
    $laundry_reminders_2_hours = sendLaundryReminders($conn, 2);

    $checkout_reminders = sendCheckoutReminders($conn);

    error_log("Notification scheduler completed at " . date('Y-m-d H:i:s'));
    error_log("Rent reminders sent: " . count($rent_reminders));
    error_log("Late payment notices sent: " . count($late_payments));
    error_log("Booking reminders (3 days) sent: " . count($booking_reminders_3_days));
    error_log("Booking reminders (day of) sent: " . count($booking_reminders_0_days));
    error_log("Late booking reminders (1 day) sent: " . count($late_booking_reminders_1_day));
    error_log("Late booking reminders (3 days) sent: " . count($late_booking_reminders_3_days));
    error_log("Meal plan reminders (1 day) sent: " . count($meal_plan_reminders_1_day));
    error_log("Meal plan reminders (day of) sent: " . count($meal_plan_reminders_0_days));
    error_log("Laundry reminders (day before) sent: " . count($laundry_reminders_day_before));
    error_log("Laundry reminders (2 hours) sent: " . count($laundry_reminders_2_hours));
    error_log("Checkout reminders sent: " . count($checkout_reminders));
} catch (Exception $e) {
    error_log("CRITICAL ERROR in notification scheduler: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

$conn = null;
?>