<?php
session_start();

if ($_SESSION['role'] !== 'guest') {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_booking'])) {
        $bookingId = $_POST['booking_id'];
        $stmt = $conn->prepare("UPDATE bookings SET booking_is_cancelled = 1 WHERE booking_id = :booking_id AND guest_id = :user_id");
        $stmt->bindParam(':booking_id', $bookingId);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();

        $message = "Your booking #$bookingId has been cancelled.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $notifStmt->bindParam(':user_id', $_SESSION['user_id']);
        $notifStmt->bindParam(':message', $message);
        $notifStmt->execute();

        header("Location: dashboard.php");
        exit();
    }

    if (isset($_POST['cancel_meal_plan'])) {
        $mealPlanUserLinkId = $_POST['meal_plan_user_link_id'];
        $stmt = $conn->prepare("UPDATE meal_plan_user_link SET is_cancelled = 1 
                               WHERE meal_plan_user_link = :meal_plan_user_link_id AND user_id = :user_id");
        $stmt->bindParam(':meal_plan_user_link_id', $mealPlanUserLinkId);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();

        $message = "Your meal plan has been cancelled.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $notifStmt->bindParam(':user_id', $_SESSION['user_id']);
        $notifStmt->bindParam(':message', $message);
        $notifStmt->execute();

        header("Location: dashboard.php");
        exit();
    }

    if (isset($_POST['cancel_laundry'])) {
        $laundryDate = $_POST['laundry_date'];
        $laundryTime = $_POST['laundry_time'];
        $stmt = $conn->prepare("UPDATE laundry_slot_user_link SET is_cancelled = 1 
                               WHERE user_id = :user_id AND laundry_slot_id IN 
                               (SELECT laundry_slot_id FROM laundry_slots WHERE date = :date AND start_time = :time)");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':date', $laundryDate);
        $stmt->bindParam(':time', $laundryTime);
        $stmt->execute();

        $message = "Your laundry booking on $laundryDate at $laundryTime has been cancelled.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $notifStmt->bindParam(':user_id', $_SESSION['user_id']);
        $notifStmt->bindParam(':message', $message);
        $notifStmt->execute();

        header("Location: dashboard.php");
        exit();
    }

    if (isset($_POST['submit_meal_rating'])) {
        $mealPlanId = $_POST['meal_plan_id'];
        $rating = $_POST['rating'];
        $review = $_POST['review'];

        $stmt = $conn->prepare("INSERT INTO meal_plan_ratings (user_id, meal_plan_id, rating, review) 
                              VALUES (:user_id, :meal_plan_id, :rating, :review)
                              ON CONFLICT(user_id, meal_plan_id) 
                              DO UPDATE SET rating = :rating, review = :review");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':meal_plan_id', $mealPlanId);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':review', $review);
        $stmt->execute();

        header("Location: dashboard.php");
        exit();
    }

    if (isset($_POST['submit_room_rating'])) {
        $bookingId = $_POST['booking_id'];
        $rating = $_POST['rating'];
        $review = $_POST['review'];

        $stmt = $conn->prepare("INSERT INTO room_ratings (user_id, booking_id, rating, review) 
                              VALUES (:user_id, :booking_id, :rating, :review)
                              ON CONFLICT(user_id, booking_id) 
                              DO UPDATE SET rating = :rating, review = :review");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':booking_id', $bookingId);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':review', $review);
        $stmt->execute();

        header("Location: dashboard.php");
        exit();
    }
}

try {
    $userId = $_SESSION['user_id'];

    $bookingsQuery = $conn->prepare("SELECT b.booking_id, r.room_number, b.check_in_date, b.check_out_date, b.total_price, b.booking_is_paid,
                                   (SELECT COUNT(*) FROM room_ratings WHERE booking_id = b.booking_id AND user_id = :user_id) as has_rating
                                   FROM bookings b 
                                   JOIN rooms r ON b.room_id = r.room_id 
                                   WHERE b.guest_id = :user_id AND b.booking_is_cancelled = 0 
                                   ORDER BY b.check_in_date DESC");
    $bookingsQuery->bindParam(':user_id', $userId);
    $bookingsQuery->execute();
    $bookings = $bookingsQuery->fetchAll(PDO::FETCH_ASSOC);

    $mealPlansQuery = $conn->prepare("SELECT mp.name, mp.meal_plan_type, mp.duration_days, mpul.is_paid, mp.meal_plan_id, mpul.meal_plan_user_link,
                                    (SELECT COUNT(*) FROM meal_plan_ratings WHERE meal_plan_id = mp.meal_plan_id AND user_id = :user_id) as has_rating
                                    FROM meal_plan_user_link mpul
                                    JOIN meal_plans mp ON mpul.meal_plan_id = mp.meal_plan_id
                                    WHERE mpul.user_id = :user_id AND mpul.is_cancelled = 0");
    $mealPlansQuery->bindParam(':user_id', $userId);
    $mealPlansQuery->execute();
    $mealPlans = $mealPlansQuery->fetchAll(PDO::FETCH_ASSOC);

    $laundryQuery = $conn->prepare("SELECT ls.date, ls.start_time, ls.price, lsul.is_paid, ls.laundry_slot_id 
        FROM laundry_slot_user_link lsul
        JOIN laundry_slots ls ON lsul.laundry_slot_id = ls.laundry_slot_id
        WHERE lsul.user_id = :user_id AND lsul.is_cancelled = 0");
    $laundryQuery->bindParam(':user_id', $userId);
    $laundryQuery->execute();
    $laundrySlots = $laundryQuery->fetchAll(PDO::FETCH_ASSOC);

    $notificationsQuery = $conn->prepare("SELECT message, created_at, is_read 
        FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 5");
    $notificationsQuery->bindParam(':user_id', $userId);
    $notificationsQuery->execute();
    $notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);

    $paymentsQuery = $conn->prepare("SELECT payment_type, amount, payment_date 
        FROM payments 
        WHERE user_id = :user_id 
        ORDER BY payment_date DESC 
        LIMIT 5");
    $paymentsQuery->bindParam(':user_id', $userId);
    $paymentsQuery->execute();
    $payments = $paymentsQuery->fetchAll(PDO::FETCH_ASSOC);

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
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
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
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo $booking['booking_id']; ?></td>
                                <td><?php echo $booking['room_number']; ?></td>
                                <td><?php echo $booking['check_in_date']; ?></td>
                                <td><?php echo $booking['check_out_date']; ?></td>
                                <td>$<?php echo number_format($booking['total_price'], 2); ?></td>
                                <td><?php echo $booking['booking_is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <button type="submit" name="cancel_booking" class="button cancel-button">Cancel</button>
                                    </form>
                                    <?php if (!$booking['booking_is_paid']): ?>
                                        <a href="payments_page.php?type=rent&id=<?php echo $booking['booking_id']; ?>"
                                            class="button">Pay</a>
                                    <?php endif; ?>

                                    <?php if ($booking['booking_is_paid']): ?>
                                        <?php if ($booking['has_rating'] > 0): ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('roomRatingForm'); document.getElementById('ratingBookingId').value=<?php echo $booking['booking_id']; ?>">Edit Rating</button>
                                        <?php else: ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('roomRatingForm'); document.getElementById('ratingBookingId').value=<?php echo $booking['booking_id']; ?>">Rate Room</button>
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
                        <button type="button" onclick="window.LuckyNest.toggleForm('roomRatingForm')" class="button cancel-button">Cancel</button>
                    </form>
                </div>
            <?php else: ?>
                <p>You have no active bookings.</p>
                <a href="book_room.php" class="button">Book a Room</a>
            <?php endif; ?>

            <h2>My Meal Plans</h2>
            <?php if (count($mealPlans) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Meal Plan</th>
                            <th>Type</th>
                            <th>Duration (days)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mealPlans as $plan): ?>
                            <tr>
                                <td><?php echo $plan['name']; ?></td>
                                <td><?php echo $plan['meal_plan_type']; ?></td>
                                <td><?php echo $plan['duration_days']; ?></td>
                                <td><?php echo $plan['is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="meal_plan_user_link_id"
                                            value="<?php echo $plan['meal_plan_user_link']; ?>">
                                        <button type="submit" name="cancel_meal_plan"
                                            class="button cancel-button">Cancel</button>
                                    </form>
                                    <?php if (!$plan['is_paid']): ?>
                                        <a href="payments_page.php?type=meal_plan&id=<?php echo $plan['meal_plan_id']; ?>"
                                            class="button">Pay</a>
                                    <?php endif; ?>

                                    <?php if ($plan['is_paid']): ?>
                                        <?php if ($plan['has_rating'] > 0): ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('mealRatingForm'); document.getElementById('ratingMealPlanId').value=<?php echo $plan['meal_plan_id']; ?>">Edit Rating</button>
                                        <?php else: ?>
                                            <button class="button"
                                                onclick="window.LuckyNest.toggleForm('mealRatingForm'); document.getElementById('ratingMealPlanId').value=<?php echo $plan['meal_plan_id']; ?>">Rate Plan</button>
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
                        <button type="button" onclick="window.LuckyNest.toggleForm('mealRatingForm')" class="button cancel-button">Cancel</button>
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
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laundrySlots as $slot): ?>
                            <tr>
                                <td><?php echo $slot['date']; ?></td>
                                <td><?php echo $slot['start_time']; ?></td>
                                <td>$<?php echo number_format($slot['price'], 2); ?></td>
                                <td><?php echo $slot['is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="laundry_date" value="<?php echo $slot['date']; ?>">
                                        <input type="hidden" name="laundry_time" value="<?php echo $slot['start_time']; ?>">
                                        <button type="submit" name="cancel_laundry" class="button cancel-button">Cancel</button>
                                    </form>
                                    <?php if (!$slot['is_paid']): ?>
                                        <a href="payments_page.php?type=laundry&date=<?php echo urlencode($slot['date']); ?>&time=<?php echo urlencode($slot['start_time']); ?>"
                                            class="button">Pay</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have no active laundry bookings.</p>
            <?php endif; ?>

            <h2>Recent Notifications</h2>
            <?php if (count($notifications) > 0): ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                            <p><?php echo $notification['message']; ?></p>
                            <small><?php echo $notification['created_at']; ?></small>
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
                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo $payment['payment_date']; ?></td>
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