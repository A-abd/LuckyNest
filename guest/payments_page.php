<?php
//TODO have success thingy come from stripe api
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: unauthorized.php");
    exit();
}

$guest_id = $_SESSION["user_id"];

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../include/db.php";

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

function getActiveStripeKey($conn)
{
    try {
        $stmt = $conn->prepare("SELECT key_reference, valid_until FROM stripe_keys WHERE is_active = 1 ORDER BY valid_until DESC LIMIt 1");
        $stmt->execute();
        $key = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$key || strtotime($key["valid_until"]) < time()) {
            $stmt = $conn->prepare("SELECT key_id, key_reference FROM stripe_keys WHERE valid_until > NOW() ORDER BY valid_until ASC LIMIt 1");
            $stmt->execute();
            $newKey = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($newKey) {
                $stmt = $conn->prepare("UPDATE stripe_keys SET is_active = 0 WHERE is_active = 1");
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE stripe_keys SET is_active = 1 WHERE key_id =:key_id");
                $stmt->bindValue(':key_id', $newKey['key_id'], PDO::PARAM_INT);
                $stmt->execute();

                return $_ENV[$newKey['key_reference']];
            } else {
                return $_ENV["stripe_key_fallback"];
            }
        }

        return $_ENV[$key['key_reference']];
    } catch (PDOException $e) {
        error_log("Error fetching Stripe key: " . $e->getMessage());
        return $_ENV["stripe_key_fallback"];
    }
}

$stripe_key = getActiveStripeKey($conn);
\Stripe\Stripe::setApiKey($stripe_key);

try {
    $stmt = $conn->prepare("SELECT booking_id, guest_id, room_id, check_in_date, check_out_date 
                           FROM bookings 
                           WHERE guest_id = :guest_id 
                           ANd booking_is_paid = 0");
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
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

try {
    $stmt = $conn->prepare("SELECT mpl.meal_plan_user_link, mp.meal_plan_id, mp.name, mp.meal_plan_type
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["payment_type"]) || empty($_POST["payment_type"])) {
        die("Payment type is required.");
    }

    $paymentType = $_POST["payment_type"];
    $description = $_POST["description"];

    $referenceId = null;
    $amount = 0;

    if ($paymentType == 'rent') {
        if (!isset($_POST["booking_id"]) || empty($_POST["booking_id"])) {
            die("Booking ID is required for rent payments.");
        }

        $referenceId = $_POST["booking_id"];
        $checkInDate = $_POST["check_in_date"];
        $checkOutDate = $_POST["check_out_date"];
        $roomId = $_POST["room_id"];

        try {
            $stmt = $conn->prepare("SELECT rt.rate_monthly 
                                   FROM rooms r 
                                   JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                                   WHERE r.room_id = :room_id");
            $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $amount = $result ? floatval($result['rate_monthly']) : 0;
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    } elseif ($paymentType == 'meal_plan') {
        if (!isset($_POST["meal_plan_id"]) || empty($_POST["meal_plan_id"])) {
            die("Meal plan ID is required for meal plan payments.");
        }

        $referenceId = $_POST["meal_plan_id"];
        $amount = $_POST["amount"];
    } elseif ($paymentType == 'laundry') {
        if (!isset($_POST["laundry_slot_id"]) || empty($_POST["laundry_slot_id"])) {
            die("Laundry slot ID is required for laundry payments.");
        }

        $referenceId = $_POST["laundry_slot_id"];
        $amount = $_POST["amount"];
    }

    try {
        $amountRounded = round($amount, 2);

        $checkoutSession = \Stripe\Checkout\Session::create([
            "payment_method_types" => ["card"],
            "line_items" => [
                [
                    "price_data" => [
                        "currency" => "gbp",
                        "product_data" => [
                            "name" => $description,
                            "description" => $description,
                        ],
                        "unit_amount" => $amountRounded * 100,
                    ],
                    "quantity" => 1,
                ]
            ],
            "mode" => "payment",
            "success_url" => "http://localhost/LuckyNest/guest_dashboard/success.php?session_id={CHECKOUT_SESSION_ID}&reference_id=" . $referenceId . "&payment_type=" . $paymentType,
            "cancel_url" => "http://localhost/LuckyNest/guest_dashboard/payments_page.php",
        ]);

        header("Location: " . $checkoutSession->url);
        exit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
    exit();
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <script>
        // roomRates has to be defined here because it depends on php data
        const roomRates = <?php echo json_encode($roomRates); ?>;
        const mealPlanPrices = <?php echo json_encode($mealPlanPrices); ?>;
        const laundryPrices = <?php echo json_encode(array_column($laundrySlots, 'price', 'laundry_slot_id')); ?>;
    </script>
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <h2>Make a Payment</h2>

    <div>
        <button onclick="showPaymentForm('rent')">Pay for Accommodation</button>
        <button onclick="showPaymentForm('meal_plan')">Pay for Meal Plans</button>
        <button onclick="showPaymentForm('laundry')">Pay for Laundry</button>
    </div>

    <div id="rent_form">
        <h3>Pay for Accommodation</h3>
        <?php if (empty($bookings)): ?>
            <p>No unpaid bookings found for this guest.</p>
        <?php else: ?>
            <form action="" method="POST">
                <label for="booking_selection">Select Booking:</label>
                <select id="booking_selection" name="booking_id" onchange="updateBookingDetails()" required>
                    <option value="">-- Select a booking --</option>
                    <?php foreach ($bookings as $booking): ?>
                        <option value="<?php echo $booking['booking_id']; ?>" data-room-id="<?php echo $booking['room_id']; ?>"
                            data-check-in="<?php echo $booking['check_in_date']; ?>"
                            data-check-out="<?php echo $booking['check_out_date']; ?>">
                            Booking #<?php echo $booking['booking_id']; ?>
                            (<?php echo $booking['check_in_date']; ?> to <?php echo $booking['check_out_date']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select><br>

                <label for="description">Description:</label>
                <input type="text" name="description" value="Accommodation Payment" required><br>

                <label for="amount">Amount (£):</label>
                <input type="number" id="amount" name="amount" readonly><br>

                <input type="hidden" id="check_in_date" name="check_in_date">
                <input type="hidden" id="check_out_date" name="check_out_date">
                <input type="hidden" id="user_id" name="user_id" value="<?php echo $guest_id; ?>">
                <input type="hidden" id="room_id" name="room_id">
                <input type="hidden" id="payment_type_hidden" name="payment_type" value="rent">

                <button type="submit">Pay with Stripe</button>
            </form>
        <?php endif; ?>
    </div>

    <div id="meal_plan_form" style="display: none;">
        <h3>Pay for Meal Plans</h3>
        <?php if (empty($mealPlans)): ?>
            <p>No unpaid meal plans found for this guest.</p>
        <?php else: ?>
            <form action="" method="POST">
                <label for="meal_plan_selection">Select Meal Plan:</label>
                <select id="meal_plan_selection" name="meal_plan_id" onchange="calculateAmount()" required>
                    <option value="">-- Select a meal plan --</option>
                    <?php foreach ($mealPlans as $mealPlan): ?>
                        <option value="<?php echo $mealPlan['meal_plan_id']; ?>" data-price="<?php echo $mealPlan['price']; ?>">
                            <?php echo $mealPlan['name']; ?> (<?php echo $mealPlan['meal_plan_type']; ?>) -
                            £<?php echo number_format($mealPlan['price'], 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select><br>

                <label for="description">Description:</label>
                <input type="text" name="description" value="Meal Plan Payment" required><br>

                <label for="amount">Amount (£):</label>
                <input type="number" id="amount" name="amount" readonly><br>

                <input type="hidden" id="payment_type_hidden" name="payment_type" value="meal_plan">
                <input type="hidden" id="user_id" name="user_id" value="<?php echo $guest_id; ?>">

                <button type="submit">Pay with Stripe</button>
            </form>
        <?php endif; ?>
    </div>

    <div id="laundry_form" style="display: none;">
        <h3>Pay for Laundry</h3>
        <?php if (empty($laundrySlots)): ?>
            <p>No unpaid laundry slots found for this guest.</p>
        <?php else: ?>
            <form action="" method="POST">
                <label for="laundry_selection">Select Laundry Slot:</label>
                <select id="laundry_selection" name="laundry_slot_id" onchange="calculateAmount()" required>
                    <option value="">-- Select a laundry slot --</option>
                    <?php foreach ($laundrySlots as $slot): ?>
                        <option value="<?php echo $slot['laundry_slot_id']; ?>" data-price="<?php echo $slot['price']; ?>">
                            <?php echo date('F j, Y', strtotime($slot['date'])); ?> at <?php echo $slot['start_time']; ?> -
                            £<?php echo number_format($slot['price'], 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select><br>

                <label for="description">Description:</label>
                <input type="text" name="description" value="Laundry Service Payment" required><br>

                <label for="amount">Amount (£):</label>
                <input type="number" id="amount" name="amount" readonly><br>

                <input type="hidden" id="payment_type_hidden" name="payment_type" value="laundry">
                <input type="hidden" id="user_id" name="user_id" value="<?php echo $guest_id; ?>">

                <button type="submit">Pay with Stripe</button>
            </form>
        <?php endif; ?>
    </div>
    <a href="dashboard.php" class="button">Back to Dashboard</a>
</body>

</html>