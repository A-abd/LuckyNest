<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/unauthorized");
    exit();
}

$guest_id = $_SESSION["user_id"];

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../include/db.php";

$payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : null;
$reference_id = isset($_GET['reference_id']) ? $_GET['reference_id'] : null;

if ($payment_type && $reference_id) {
    try {
        $stmt = $conn->prepare("INSERT INTO payment_cancellations (user_id, reference_id, payment_type, cancelled_at) 
                               VALUES (:user_id, :reference_id, :payment_type, NOW())");
        $stmt->bindValue(':user_id', $guest_id, PDO::PARAM_INT);
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->bindValue(':payment_type', $payment_type, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error logging payment cancellation: " . $e->getMessage());
    }
}

$user_name = "";
try {
    $stmt = $conn->prepare("SELECT forename FROM users WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_name = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching user name: " . $e->getMessage());
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
    <title>Payment Cancelled</title>
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>

    <div class="blur-layer-2"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Payment Cancelled</h1>

            <div class="details-box">
                <?php if (!empty($user_name)): ?>
                    <p>Hello <?php echo htmlspecialchars($user_name); ?>,</p>
                <?php endif; ?>

                <p>Your payment process has been cancelled. No charges have been made to your account.</p>

                <?php if ($payment_type): ?>
                    <p>Payment type: <?php echo htmlspecialchars(ucfirst($payment_type)); ?></p>
                <?php endif; ?>
            </div>

            <p>If you cancelled by mistake or would like to try again, you can return to the payments page.</p>
            <p>If you're experiencing any issues with the payment system, please contact our support team for
                assistance.</p>

            <div class="button-center">
                <a href="dashboard" class="update-button">Go to Dashboard</a>
            </div>
        </div>
    </div>
</body>

</html>