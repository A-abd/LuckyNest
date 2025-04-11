<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../unauthorized.php");
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
        $bookingId = $_POST["booking_id"];
        $checkInDate = $_POST["check_in_date"];
        $checkOutDate = $_POST["check_out_date"];
        $roomId = $_POST["room_id"];

        try {
            $stmt = $conn->prepare("SELECT rt.deposit_amount, d.status 
                                  FROM bookings b
                                  JOIN rooms r ON b.room_id = r.room_id
                                  JOIN room_types rt ON r.room_type_id = rt.room_type_id
                                  LEFT JOIN deposits d ON b.booking_id = d.booking_id
                                  WHERE b.booking_id = :booking_id");
            $stmt->bindValue(':booking_id', $bookingId, PDO::PARAM_INT);
            $stmt->execute();
            $depositInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $depositInfo &&
                floatval($depositInfo['deposit_amount']) > 0 &&
                (!isset($depositInfo['status']) || $depositInfo['status'] != 'paid')
            ) {
                die("Security deposit must be paid before making rent payment. Please pay the security deposit first.");
            }
        } catch (PDOException $e) {
            die("Database error checking deposit status: " . $e->getMessage());
        }

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
    } elseif ($paymentType == 'deposit') {
        if (!isset($_POST["booking_id"]) || empty($_POST["booking_id"])) {
            die("Booking ID is required for deposit payments.");
        }

        $referenceId = $_POST["booking_id"];
        $amount = $_POST["amount"];
    }

    try {
        $amount = floatval($amount);
        $amountRounded = round($amount, 2);

        $returnUrl = isset($_POST["return_url"]) ? $_POST["return_url"] : "../guest/payments_page.php";

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
            "success_url" => "http://localhost/LuckyNest/guest/success.php?session_id={CHECKOUT_SESSION_ID}&reference_id=" . $referenceId . "&payment_type=" . $paymentType,
            "cancel_url" => "http://localhost/LuckyNest/guest/cancel.php",
        ]);

        header("Location: " . $checkoutSession->url);
        exit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
    exit();
}
?>