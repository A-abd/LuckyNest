<?php
session_start();

if ($_SESSION['role'] !== 'guest') {
    header('Location: ../authentication/../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_booking'])) {
        $bookingId = $_POST['booking_id'];
        $stmt = $conn->prepare("UPDATE bookings SET booking_is_cancelled = 1 WHERE booking_id = ? AND guest_id = ?");
        $stmt->execute([$bookingId, $_SESSION['user_id']]);

        $message = "Your booking #$bookingId has been cancelled.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notifStmt->execute([$_SESSION['user_id'], $message]);

        header("Location: dashboard");
        exit();
    }

    if (isset($_POST['cancel_meal_plan'])) {
        $mealPlanUserLinkId = $_POST['meal_plan_user_link_id'];
        $stmt = $conn->prepare("UPDATE meal_plan_user_link SET is_cancelled = 1 
                               WHERE meal_plan_user_link_id = ? AND user_id = ?");
        $stmt->execute([$mealPlanUserLinkId, $_SESSION['user_id']]);

        $message = "Your meal plan has been cancelled.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notifStmt->execute([$_SESSION['user_id'], $message]);

        header("Location: dashboard");
        exit();
    }

    if (isset($_POST['cancel_laundry'])) {
        $laundrySlotUserLinkId = $_POST['laundry_slot_user_link_id'];
        $stmt = $conn->prepare("UPDATE laundry_slot_user_link SET is_cancelled = 1 
                               WHERE laundry_slot_user_link_id = ? AND user_id = ?");
        $stmt->execute([$laundrySlotUserLinkId, $_SESSION['user_id']]);

        $message = "Your laundry booking has been cancelled.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notifStmt->execute([$_SESSION['user_id'], $message]);

        header("Location: dashboard");
        exit();
    }

    if (isset($_POST['submit_meal_rating'])) {
        $mealPlanId = $_POST['meal_plan_id'];
        $rating = $_POST['rating'];
        $review = $_POST['review'];

        $stmt = $conn->prepare("INSERT INTO meal_plan_ratings (user_id, meal_plan_id, rating, review) 
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE rating = ?, review = ?");
        $stmt->execute([$_SESSION['user_id'], $mealPlanId, $rating, $review, $rating, $review]);

        header("Location: dashboard");
        exit();
    }

    if (isset($_POST['submit_room_rating'])) {
        $bookingId = $_POST['booking_id'];
        $rating = $_POST['rating'];
        $review = $_POST['review'];

        $stmt = $conn->prepare("INSERT INTO room_ratings (user_id, booking_id, rating, review) 
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE rating = ?, review = ?");
        $stmt->execute([$_SESSION['user_id'], $bookingId, $rating, $review, $rating, $review]);

        header("Location: dashboard");
        exit();
    }
}

try {
    $userId = $_SESSION['user_id'];

    $bookingsQuery = $conn->prepare("
        SELECT b.booking_id, r.room_id, r.room_number, b.check_in_date, b.check_out_date, 
               b.total_price, b.booking_is_paid,
               (SELECT COUNT(*) FROM room_ratings 
                WHERE booking_id = b.booking_id AND user_id = ?) as has_rating
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.room_id 
        WHERE b.guest_id = ? AND b.booking_is_cancelled = 0 
        ORDER BY b.check_in_date DESC
        LIMIT 3
    ");
    $bookingsQuery->execute([$userId, $userId]);
    $bookings = $bookingsQuery->fetchAll();

    $mealPlansQuery = $conn->prepare("
        SELECT mp.name, mp.meal_plan_type, mp.duration_days, mpul.is_paid, 
               mp.meal_plan_id, mpul.meal_plan_user_link_id, mpul.start_date,
               (SELECT COUNT(*) FROM meal_plan_ratings 
                WHERE meal_plan_id = mp.meal_plan_id AND user_id = ?) as has_rating,
               (SELECT SUM(m.price) FROM meal_plan_items_link mpil 
                JOIN meals m ON mpil.meal_id = m.meal_id 
                WHERE mpil.meal_plan_id = mp.meal_plan_id) as price
        FROM meal_plan_user_link mpul
        JOIN meal_plans mp ON mpul.meal_plan_id = mp.meal_plan_id
        WHERE mpul.user_id = ? AND mpul.is_cancelled = 0
        ORDER BY mpul.start_date DESC
        LIMIT 3
    ");
    $mealPlansQuery->execute([$userId, $userId]);
    $mealPlans = $mealPlansQuery->fetchAll();

    $laundryQuery = $conn->prepare("
        SELECT ls.date, ls.start_time, ls.price, lsul.is_paid, ls.laundry_slot_id, lsul.laundry_slot_user_link_id
        FROM laundry_slot_user_link lsul
        JOIN laundry_slots ls ON lsul.laundry_slot_id = ls.laundry_slot_id
        WHERE lsul.user_id = ? AND lsul.is_cancelled = 0
        ORDER BY ls.date DESC, ls.start_time DESC
        LIMIT 3
    ");
    $laundryQuery->execute([$userId]);
    $laundrySlots = $laundryQuery->fetchAll();

    $depositsQuery = $conn->prepare("
    SELECT d.deposit_id, d.booking_id, d.amount, d.status, b.check_in_date, b.check_out_date, r.room_number, rt.deposit_amount
        FROM bookings b
        JOIN rooms r ON b.room_id = r.room_id
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        LEFT JOIN deposits d ON b.booking_id = d.booking_id
        WHERE b.guest_id = ? 
        AND b.booking_is_cancelled = 0
        AND rt.deposit_amount > 0
        ORDER BY 
            CASE
                WHEN d.status = 'pending' OR d.status IS NULL THEN 1
                WHEN d.status = 'paid' THEN 2
                WHEN d.status = 'partially_refunded' THEN 3
                WHEN d.status = 'fully_refunded' THEN 4
                WHEN d.status = 'withheld' THEN 5
            END ASC,
            b.check_in_date DESC
        LIMIT 10
    ");
    $depositsQuery->execute([$userId]);
    $deposits = $depositsQuery->fetchAll();

    $notificationsQuery = $conn->prepare("
        SELECT message, created_at, is_read 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $notificationsQuery->execute([$userId]);
    $notifications = $notificationsQuery->fetchAll();

    $paymentsQuery = $conn->prepare("
        SELECT payment_type, amount, payment_date 
        FROM payments 
        WHERE user_id = ? 
        ORDER BY payment_date DESC 
        LIMIT 3
    ");
    $paymentsQuery->execute([$userId]);
    $payments = $paymentsQuery->fetchAll();

    function formatDate($dateString)
    {
        $date = new DateTime($dateString);
        return $date->format('d/m/Y');
    }

    function formatDateTime($dateTimeString)
    {
        $dateTime = new DateTime($dateTimeString);
        return $dateTime->format('d/m/Y H:i');
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap" />
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Guest Dashboard</title>
    #<style>
        /* Dashboard button standardization */
        .content-container .button {
            display: inline-block;
            width: 120px;
            height: 40px;
            margin: 5px;
            padding: 8px 12px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            background: #507878;
            color: #fed7ca;
            border: 2px solid #507878;
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s ease-in-out;
        }

        .content-container .button:hover {
            transform: translateY(-3px);
            transition: transform 0.4s ease;
        }

        .content-container .button:active {
            background-color: #648686;
        }

        .content-container .cancel-button {
            background-color: #e74c3c;
            border-color: #e74c3c;
            color: white;
        }

        .content-container .cancel-button:hover {
            transform: translateY(-3px);
        }

        .content-container .cancel-button:active {
            background-color: #c0392b;
        }

        #roomRatingForm .button,
        #mealRatingForm .button {
            display: inline-block;
            width: 120px;
            height: 40px;
            margin: 5px;
            padding: 8px 12px;
            text-align: center;
            font-size: 14px;
        }

        .actions-column form {
            display: inline-block;
        }

        .actions-column .button {
            margin: 2px;
        }

        form .button {
            width: 120px !important;
        }
    </style>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>My Dashboard</h1>

            <div class="dashboard-stats" style="display: flex; flex-wrap: wrap; justify-content: space-between;">
                <div class="stat-card button-center" style="flex: 1; min-width: 200px; margin: 10px 5px;">
                    <h3>My Bookings</h3>
                    <p><?php echo count($bookings); ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; min-width: 200px; margin: 10px 5px;">
                    <h3>My Meal Plans</h3>
                    <p><?php echo count($mealPlans); ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; min-width: 200px; margin: 10px 5px;">
                    <h3>My Laundry Bookings</h3>
                    <p><?php echo count($laundrySlots); ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; min-width: 200px; margin: 10px 5px;">
                    <h3>Notifications</h3>
                    <p><?php echo count($notifications); ?></p>
                </div>
            </div>

            <h2>My Bookings</h2>
            <?php if (count($bookings) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Room Number</th>
                            <th>Check-in Date</th>
                            <th>Check-out Date</th>
                            <th>Total Price</th>
                            <th class="status-column">Status</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo $booking['booking_id']; ?></td>
                                <td><?php echo $booking['room_number']; ?></td>
                                <td><?php echo formatDate($booking['check_in_date']); ?></td>
                                <td><?php echo formatDate($booking['check_out_date']); ?></td>
                                <td>£<?php echo number_format($booking['total_price'], 2); ?></td>
                                <td class="status-column"><?php echo $booking['booking_is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                                <td class="actions-column">
                                    <?php if (!$booking['booking_is_paid']): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <button type="submit" name="cancel_booking" class="button cancel-button">Cancel</button>
                                        </form>
                                        <form method="post" action="../include/checkout.php" style="display: inline;">
                                            <input type="hidden" name="payment_type" value="rent">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="room_id" value="<?php echo $booking['room_id']; ?>">
                                            <input type="hidden" name="check_in_date"
                                                value="<?php echo $booking['check_in_date']; ?>">
                                            <input type="hidden" name="check_out_date"
                                                value="<?php echo $booking['check_out_date']; ?>">
                                            <input type="hidden" name="description"
                                                value="Room payment for booking #<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="amount" value="<?php echo $booking['total_price']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                                            <button type="submit" class="button">Pay Now</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($booking['booking_is_paid']): ?>
                                        <?php if ($booking['has_rating'] > 0): ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('roomRatingForm'); document.getElementById('ratingBookingId').value=<?php echo $booking['booking_id']; ?>">Edit
                                                Rating</button>
                                        <?php else: ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('roomRatingForm'); document.getElementById('ratingBookingId').value=<?php echo $booking['booking_id']; ?>">Rate
                                                Room</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Room Rating Form (Hidden by default) -->
                <div id="roomRatingForm"
                    style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border: 1px solid #ccc; z-index: 1000;">
                    <h3>Rate Your Room</h3>
                    <form method="post">
                        <input type="hidden" name="booking_id" id="ratingBookingId">
                        <div>
                            <label for="rating">Rating (1-5):</label>
                            <select name="rating" required>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Very Good</option>
                                <option value="3">3 - Good</option>
                                <option value="2">2 - Fair</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>
                        <div>
                            <label for="review">Review:</label>
                            <textarea name="review" rows="4" cols="50"></textarea>
                        </div>
                        <button type="submit" name="submit_room_rating" class="button">Submit Rating</button>
                        <button type="button" onclick="window.LuckyNest.toggleForm('roomRatingForm')"
                            class="button cancel-button">Cancel</button>
                    </form>
                </div>
            <?php else: ?>
                <p>You have no active bookings.</p>
            <?php endif; ?>

            <h2>My Meal Plans</h2>
            <?php if (count($mealPlans) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Meal Plan</th>
                            <th>Type</th>
                            <th>Duration (days)</th>
                            <th>Start Date</th>
                            <th class="status-column">Status</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mealPlans as $plan): ?>
                            <tr>
                                <td><?php echo $plan['name']; ?></td>
                                <td><?php echo $plan['meal_plan_type']; ?></td>
                                <td><?php echo $plan['duration_days']; ?></td>
                                <td><?php echo formatDate($plan['start_date']); ?></td>
                                <td class="status-column"><?php echo $plan['is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                                <td class="actions-column">
                                    <?php if (!$plan['is_paid']): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="meal_plan_user_link_id"
                                                value="<?php echo $plan['meal_plan_user_link_id']; ?>">
                                            <button type="submit" name="cancel_meal_plan"
                                                class="button cancel-button">Cancel</button>
                                        </form>
                                        <form method="post" action="../include/checkout.php" style="display: inline;">
                                            <input type="hidden" name="payment_type" value="meal_plan">
                                            <input type="hidden" name="meal_plan_id" value="<?php echo $plan['meal_plan_id']; ?>">
                                            <input type="hidden" name="description"
                                                value="Payment for meal plan: <?php echo $plan['name']; ?>">
                                            <input type="hidden" name="amount" value="<?php echo $plan['price']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                                            <button type="submit" class="button">Pay Now</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($plan['is_paid']): ?>
                                        <?php if ($plan['has_rating'] > 0): ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('mealRatingForm'); document.getElementById('ratingMealPlanId').value=<?php echo $plan['meal_plan_id']; ?>">Edit
                                                Rating</button>
                                        <?php else: ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('mealRatingForm'); document.getElementById('ratingMealPlanId').value=<?php echo $plan['meal_plan_id']; ?>">Rate
                                                Plan</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Meal Plan Rating Form (Hidden by default) -->
                <div id="mealRatingForm"
                    style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border: 1px solid #ccc; z-index: 1000;">
                    <h3>Rate Your Meal Plan</h3>
                    <form method="post">
                        <input type="hidden" name="meal_plan_id" id="ratingMealPlanId">
                        <div>
                            <label for="rating">Rating (1-5):</label>
                            <select name="rating" required>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Very Good</option>
                                <option value="3">3 - Good</option>
                                <option value="2">2 - Fair</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>
                        <div>
                            <label for="review">Review:</label>
                            <textarea name="review" rows="4" cols="50"></textarea>
                        </div>
                        <button type="submit" name="submit_meal_rating" class="button">Submit Rating</button>
                        <button type="button" onclick="window.LuckyNest.toggleForm('mealRatingForm')"
                            class="button cancel-button">Cancel</button>
                    </form>
                </div>
            <?php else: ?>
                <p>You have no active meal plans.</p>
            <?php endif; ?>

            <h2>My Laundry Bookings</h2>
            <?php if (count($laundrySlots) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Price</th>
                            <th class="status-column">Status</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laundrySlots as $slot): ?>
                            <tr>
                                <td><?php echo formatDate($slot['date']); ?></td>
                                <td><?php echo $slot['start_time']; ?></td>
                                <td>£<?php echo number_format($slot['price'], 2); ?></td>
                                <td class="status-column"><?php echo $slot['is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                                <td class="actions-column">
                                    <?php if (!$slot['is_paid']): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="laundry_slot_user_link_id"
                                                value="<?php echo $slot['laundry_slot_user_link_id']; ?>">
                                            <button type="submit" name="cancel_laundry" class="button cancel-button">Cancel</button>
                                        </form>
                                        <form method="post" action="../include/checkout.php" style="display: inline;">
                                            <input type="hidden" name="payment_type" value="laundry">
                                            <input type="hidden" name="laundry_slot_id"
                                                value="<?php echo $slot['laundry_slot_id']; ?>">
                                            <input type="hidden" name="description"
                                                value="Laundry slot payment for <?php echo formatDate($slot['date']); ?> at <?php echo $slot['start_time']; ?>">
                                            <input type="hidden" name="amount" value="<?php echo $slot['price']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                                            <button type="submit" class="button">Pay Now</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have no active laundry bookings.</p>
            <?php endif; ?>

            <h2>My Deposits</h2>
            <?php if (count($deposits) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Room Number</th>
                            <th>Check-in Date</th>
                            <th>Check-out Date</th>
                            <th>Amount</th>
                            <th class="status-column">Status</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposits as $deposit): ?>
                            <?php
                            $needsPayment = !isset($deposit['status']) || $deposit['status'] === null || $deposit['status'] === 'pending';
                            $depositAmount = isset($deposit['deposit_amount']) ? $deposit['deposit_amount'] :
                                (isset($deposit['amount']) ? $deposit['amount'] : 0);
                            ?>
                            <tr>
                                <td><?php echo $deposit['booking_id']; ?></td>
                                <td><?php echo $deposit['room_number']; ?></td>
                                <td><?php echo formatDate($deposit['check_in_date']); ?></td>
                                <td><?php echo formatDate($deposit['check_out_date']); ?></td>
                                <td>£<?php echo number_format($depositAmount, 2); ?></td>
                                <td class="status-column">
                                    <?php
                                    $status = isset($deposit['status']) ? $deposit['status'] : 'pending';
                                    $statusText = '';

                                    switch ($status) {
                                        case 'pending':
                                            $statusText = 'Payment Required';
                                            break;
                                        case 'paid':
                                            $statusText = 'Paid';
                                            break;
                                        case 'partially_refunded':
                                            $statusText = 'Partially Refunded';
                                            break;
                                        case 'fully_refunded':
                                            $statusText = 'Fully Refunded';
                                            break;
                                        case 'withheld':
                                            $statusText = 'Withheld';
                                            break;
                                        default:
                                            $statusText = 'Payment Required';
                                    }
                                    echo $statusText;
                                    ?>
                                </td>
                                <td class="actions-column">
                                    <?php if ($needsPayment): ?>
                                        <form method="post" action="../include/checkout.php" style="display: inline;">
                                            <input type="hidden" name="payment_type" value="deposit">
                                            <input type="hidden" name="booking_id" value="<?php echo $deposit['booking_id']; ?>">
                                            <input type="hidden" name="description"
                                                value="Security deposit for booking #<?php echo $deposit['booking_id']; ?>">
                                            <input type="hidden" name="amount" value="<?php echo $depositAmount; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                                            <button type="submit" class="button">Pay Now</button>
                                        </form>
                                    <?php elseif ($status === 'withheld'): ?>
                                        <button class="button info-button"
                                            onclick="alert('Deposit was withheld. Please contact administration for details.')">Details</button>
                                    <?php elseif ($status === 'partially_refunded'): ?>
                                        <button class="button info-button"
                                            onclick="alert('Your deposit was partially refunded. Refunded amount: £<?php echo number_format($deposit['refunded_amount'] ?? 0, 2); ?>')">Details</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have no security deposits.</p>
            <?php endif; ?>

            <h2>Recent Notifications</h2>
            <?php if (count($notifications) > 0): ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                            <p><?php echo $notification['message']; ?></p>
                            <small><?php echo formatDateTime($notification['created_at']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>You have no notifications.</p>
            <?php endif; ?>

            <h2>Recent Payments</h2>
            <?php if (count($payments) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?></td>
                                <td>£<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo formatDateTime($payment['payment_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have no payment history.</p>
            <?php endif; ?>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>