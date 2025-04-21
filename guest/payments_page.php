<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../unauthorized.php");
    exit();
}

$guest_id = $_SESSION["user_id"];

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../include/db.php";

try {
    $stmt = $conn->prepare("SELECT b.booking_id, b.guest_id, b.room_id, b.check_in_date, b.check_out_date 
                           FROM bookings b
                           JOIN rooms r ON b.room_id = r.room_id
                           JOIN room_types rt ON r.room_type_id = rt.room_type_id
                           LEFT JOIN deposits d ON b.booking_id = d.booking_id
                           WHERE b.guest_id = :guest_id 
                           AND b.booking_is_paid = 0
                           AND b.booking_is_cancelled = 0
                           AND (
                               (rt.deposit_amount > 0 AND d.status = 'paid') 
                               OR rt.deposit_amount = 0
                           )");
    $stmt->bindValue(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $roomRates = [];
    foreach ($bookings as $booking) {
        $stmt = $conn->prepare("SELECT rt.rate_monthly 
                               FROM rooms r 
                               JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                               WHERE r.room_id = :room_id");
        $stmt->bindValue(':room_id', $booking['room_id'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $roomRates[$booking['room_id']] = $result ? floatval($result['rate_monthly']) : 0;
    }

    $stmt = $conn->prepare("SELECT b.booking_id, b.check_in_date, b.check_out_date, r.room_number, rt.room_type_name 
                           FROM bookings b
                           JOIN rooms r ON b.room_id = r.room_id
                           JOIN room_types rt ON r.room_type_id = rt.room_type_id
                           LEFT JOIN deposits d ON b.booking_id = d.booking_id
                           WHERE b.guest_id = :guest_id 
                           AND b.booking_is_paid = 0
                           AND b.booking_is_cancelled = 0
                           AND rt.deposit_amount > 0
                           AND (d.deposit_id IS NULL OR d.status != 'paid')");
    $stmt->bindValue(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $bookingsNeedingDeposit = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

try {
    $stmt = $conn->prepare("SELECT mpl.meal_plan_user_link_id, mp.meal_plan_id, mp.name, mp.meal_plan_type
                           FROM meal_plan_user_link mpl
                           JOIN meal_plans mp ON mpl.meal_plan_id = mp.meal_plan_id
                           WHERE mpl.user_id = :user_id
                           AND mpl.is_paid = 0
                           AND mpl.is_cancelled = 0");
    $stmt->bindValue(':user_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $mealPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mealPlanPrices = [];
    foreach ($mealPlans as &$mealPlan) {
        $stmt = $conn->prepare("SELECT SUM(m.price) as total_price
                               FROM meal_plan_items_link mpil
                               JOIN meals m ON mpil.meal_id = m.meal_id
                               WHERE mpil.meal_plan_id = :meal_plan_id");
        $stmt->bindValue(':meal_plan_id', $mealPlan['meal_plan_id'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $mealPlanPrices[$mealPlan['meal_plan_id']] = $result ? floatval($result['total_price']) : 0;
        $mealPlan['price'] = $mealPlanPrices[$mealPlan['meal_plan_id']];
    }
} catch (PDOException $e) {
    die("Database error fetching meal plans: " . $e->getMessage());
}

try {
    $stmt = $conn->prepare("SELECT lsul.laundry_slot_user_link_id, ls.laundry_slot_id, ls.date, ls.start_time, ls.price
                           FROM laundry_slot_user_link lsul
                           JOIN laundry_slots ls ON lsul.laundry_slot_id = ls.laundry_slot_id
                           WHERE lsul.user_id = :user_id
                           AND lsul.is_paid = 0
                           AND lsul.is_cancelled = 0");
    $stmt->bindValue(':user_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $laundrySlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error fetching laundry slots: " . $e->getMessage());
}

try {
    $stmt = $conn->prepare("SELECT b.booking_id, b.check_in_date, b.check_out_date, r.room_number, rt.room_type_name, rt.deposit_amount
                           FROM bookings b
                           JOIN rooms r ON b.room_id = r.room_id
                           JOIN room_types rt ON r.room_type_id = rt.room_type_id
                           LEFT JOIN deposits d ON b.booking_id = d.booking_id
                           WHERE b.guest_id = :guest_id
                           AND rt.deposit_amount > 0
                           AND (d.deposit_id IS NULL OR d.status = 'pending')
                           AND b.booking_is_cancelled = 0");
    $stmt->bindValue(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $unpaidDeposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error fetching unpaid deposits: " . $e->getMessage());
}

$depositBooking = null;
$depositAmount = 0;
if (isset($_GET['deposit']) && isset($_GET['booking_id'])) {
    try {
        $stmt = $conn->prepare("SELECT b.booking_id, b.check_in_date, b.check_out_date, r.room_number, rt.room_type_name, rt.deposit_amount
                               FROM bookings b
                               JOIN rooms r ON b.room_id = r.room_id
                               JOIN room_types rt ON r.room_type_id = rt.room_type_id
                               LEFT JOIN deposits d ON b.booking_id = d.booking_id
                               WHERE b.booking_id = :booking_id
                               AND b.guest_id = :guest_id
                               AND (d.deposit_id IS NULL OR d.status = 'pending')");
        $stmt->bindValue(':booking_id', $_GET['booking_id'], PDO::PARAM_INT);
        $stmt->bindValue(':guest_id', $guest_id, PDO::PARAM_INT);
        $stmt->execute();
        $depositBooking = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($depositBooking) {
            $depositAmount = $depositBooking['deposit_amount'];
        }
    } catch (PDOException $e) {
        die("Database error fetching deposit information: " . $e->getMessage());
    }
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
    <title>Payments</title>
    <script>
        const roomRates = <?php echo json_encode($roomRates); ?>;
        const mealPlanPrices = <?php echo json_encode($mealPlanPrices); ?>;
        const laundryPrices = <?php echo json_encode(array_column($laundrySlots, 'price', 'laundry_slot_id')); ?>;
    </script>
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <?php include '../include/guest_navbar.php'; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Make a Payment</h1>
            <div class="button-center">
                <button onclick="LuckyNest.showPaymentForm('rent')" class="update-add-button">Pay for
                    Accommodation</button>
                <button onclick="LuckyNest.showPaymentForm('meal_plan')" class="update-add-button">Pay for Meal
                    Plans</button>
                <button onclick="LuckyNest.showPaymentForm('laundry')" class="update-add-button">Pay for
                    Laundry</button>
                <button onclick="LuckyNest.showPaymentForm('deposit')" class="update-add-button">Pay Security
                    Deposit</button>
            </div>

            <div id="rent_form" class="add-form">
                <h2>Pay for Accommodation</h2>
                <?php if (empty($bookings)): ?>
                    <?php if (!empty($bookingsNeedingDeposit)): ?>
                        <p>You need to pay the security deposit for the following bookings before you can pay rent:</p>
                        <table border="1">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Room Number</th>
                                    <th>Room Type</th>
                                    <th>Check-in Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookingsNeedingDeposit as $booking): ?>
                                    <tr>
                                        <td><?php echo $booking['booking_id']; ?></td>
                                        <td><?php echo $booking['room_number']; ?></td>
                                        <td><?php echo $booking['room_type_name']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($booking['check_in_date'])); ?></td>
                                        <td>
                                            <a href="payments_page.php?deposit=1&booking_id=<?php echo $booking['booking_id']; ?>"
                                                class="update-button">Pay Deposit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No unpaid bookings found for this guest.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <form action="../include/checkout.php" method="POST">
                        <label for="booking_selection">Select Booking:</label>
                        <select id="booking_selection" name="booking_id" onchange="LuckyNest.updateBookingDetails()"
                            required>
                            <option value="">-- Select a booking --</option>
                            <?php foreach ($bookings as $booking): ?>
                                <option value="<?php echo $booking['booking_id']; ?>"
                                    data-room-id="<?php echo $booking['room_id']; ?>"
                                    data-check-in="<?php echo $booking['check_in_date']; ?>"
                                    data-check-out="<?php echo $booking['check_out_date']; ?>">
                                    Booking #<?php echo $booking['booking_id']; ?>
                                    (<?php echo $booking['check_in_date']; ?> to <?php echo $booking['check_out_date']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="description">Description:</label>
                        <input type="text" name="description" value="Accommodation Payment" required>

                        <label for="amount">Amount (£):</label>
                        <input type="number" id="amount" name="amount" readonly>

                        <input type="hidden" id="check_in_date" name="check_in_date">
                        <input type="hidden" id="check_out_date" name="check_out_date">
                        <input type="hidden" id="user_id" name="user_id" value="<?php echo $guest_id; ?>">
                        <input type="hidden" id="room_id" name="room_id">
                        <input type="hidden" id="payment_type_hidden" name="payment_type" value="rent">
                        <input type="hidden" name="return_url" value="payments_page.php">

                        <button type="submit" class="update-button">Pay with Stripe</button>
                    </form>
                <?php endif; ?>
            </div>

            <div id="meal_plan_form" class="add-form" style="display: none;">
                <h2>Pay for Meal Plans</h2>
                <?php if (empty($mealPlans)): ?>
                    <p>No unpaid meal plans found for this guest.</p>
                <?php else: ?>
                    <form action="../include/checkout.php" method="POST">
                        <label for="meal_plan_selection">Select Meal Plan:</label>
                        <select id="meal_plan_selection" name="meal_plan_id" onchange="LuckyNest.calculateAmount()"
                            required>
                            <option value="">-- Select a meal plan --</option>
                            <?php foreach ($mealPlans as $mealPlan): ?>
                                <option value="<?php echo $mealPlan['meal_plan_id']; ?>"
                                    data-price="<?php echo $mealPlan['price']; ?>">
                                    <?php echo $mealPlan['name']; ?> (<?php echo $mealPlan['meal_plan_type']; ?>) -
                                    £<?php echo number_format($mealPlan['price'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="description">Description:</label>
                        <input type="text" name="description" value="Meal Plan Payment" required>

                        <label for="amount">Amount (£):</label>
                        <input type="number" id="amount" name="amount" readonly>

                        <input type="hidden" id="payment_type_hidden" name="payment_type" value="meal_plan">
                        <input type="hidden" id="user_id" name="user_id" value="<?php echo $guest_id; ?>">
                        <input type="hidden" name="return_url" value="payments_page.php">

                        <button type="submit" class="update-button">Pay with Stripe</button>
                    </form>
                <?php endif; ?>
            </div>

            <div id="laundry_form" class="add-form" style="display: none;">
                <h2>Pay for Laundry</h2>
                <?php if (empty($laundrySlots)): ?>
                    <p>No unpaid laundry slots found for this guest.</p>
                <?php else: ?>
                    <form action="../include/checkout.php" method="POST">
                        <label for="laundry_selection">Select Laundry Slot:</label>
                        <select id="laundry_selection" name="laundry_slot_id" onchange="LuckyNest.calculateAmount()"
                            required>
                            <option value="">-- Select a laundry slot --</option>
                            <?php foreach ($laundrySlots as $slot): ?>
                                <option value="<?php echo $slot['laundry_slot_id']; ?>"
                                    data-price="<?php echo $slot['price']; ?>">
                                    <?php echo date('F j, Y', strtotime($slot['date'])); ?> at
                                    <?php echo $slot['start_time']; ?> -
                                    £<?php echo number_format($slot['price'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="description">Description:</label>
                        <input type="text" name="description" value="Laundry Service Payment" required>

                        <label for="amount">Amount (£):</label>
                        <input type="number" id="amount" name="amount" readonly>

                        <input type="hidden" id="payment_type_hidden" name="payment_type" value="laundry">
                        <input type="hidden" id="user_id" name="user_id" value="<?php echo $guest_id; ?>">
                        <input type="hidden" name="return_url" value="payments_page.php">

                        <button type="submit" class="update-button">Pay with Stripe</button>
                    </form>
                <?php endif; ?>
            </div>

            <div id="deposit_form" class="add-form"
                style="display: <?php echo ($depositBooking || count($unpaidDeposits) > 0) ? 'block' : 'none'; ?>;">
                <h2>Pay Security Deposit</h2>
                <?php if ($depositBooking): ?>
                    <form action="../include/checkout.php" method="POST">
                        <label for="booking_info">Booking Information:</label>
                        <div class="booking-info">
                            <p><strong>Booking ID:</strong> <?php echo $depositBooking['booking_id']; ?></p>
                            <p><strong>Room Number:</strong> <?php echo $depositBooking['room_number']; ?></p>
                            <p><strong>Room Type:</strong> <?php echo $depositBooking['room_type_name']; ?></p>
                            <p><strong>Check-in Date:</strong>
                                <?php echo date('d/m/Y', strtotime($depositBooking['check_in_date'])); ?></p>
                            <p><strong>Check-out Date:</strong>
                                <?php echo date('d/m/Y', strtotime($depositBooking['check_out_date'])); ?></p>
                        </div>

                        <label for="description">Description:</label>
                        <input type="text" name="description" value="Security Deposit Payment" required>

                        <label for="amount">Deposit Amount (£):</label>
                        <input type="number" id="amount" name="amount" value="<?php echo $depositAmount; ?>" readonly>

                        <input type="hidden" name="booking_id" value="<?php echo $depositBooking['booking_id']; ?>">
                        <input type="hidden" name="payment_type" value="deposit">
                        <input type="hidden" name="user_id" value="<?php echo $guest_id; ?>">
                        <input type="hidden" name="return_url" value="payments_page.php">

                        <button type="submit" class="update-button">Pay Deposit with Stripe</button>
                    </form>
                <?php elseif (count($unpaidDeposits) > 0): ?>
                    <h3>Required Deposits</h3>
                    <p>The following bookings require security deposits:</p>
                    <table border="1">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Room Number</th>
                                <th>Room Type</th>
                                <th>Check-in Date</th>
                                <th>Deposit Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unpaidDeposits as $deposit): ?>
                                <tr>
                                    <td><?php echo $deposit['booking_id']; ?></td>
                                    <td><?php echo $deposit['room_number']; ?></td>
                                    <td><?php echo $deposit['room_type_name']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($deposit['check_in_date'])); ?></td>
                                    <td>£<?php echo number_format($deposit['deposit_amount'], 2); ?></td>
                                    <td>
                                        <a href="payments_page.php?deposit=1&booking_id=<?php echo $deposit['booking_id']; ?>"
                                            class="update-button">Pay Deposit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No security deposits pending payment. You can view your deposits on the <a
                            href="deposits.php">deposits page</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="form-overlay"></div>
</body>

</html>