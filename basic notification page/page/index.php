<?php
require __DIR__ . "/../include/db.php";
session_start();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("You must be logged in to view this page.");
}

if (!$conn) {
    die("Database connection failed.");
}

$query = $conn->prepare("SELECT email, phone FROM users WHERE user_id = :user_id");
$query->bindParam(':user_id', $user_id);
$query->execute();
$user = $query->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$email = $user['email'];
$phone_number = $user['phone'];

$autoload = realpath(__DIR__ . '/../vendor/autoload.php');
if (!$autoload) {
    die("Failed to find autoload.php at: " . __DIR__ . '/../vendor/autoload.php');
}
require $autoload;

$env_path = __DIR__ . '/../.env';
if (!file_exists($env_path)) {
    die("Environment file not found at: " . $env_path);
}

$env_lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($env_lines as $line) {
    if (strpos(trim($line), '#') === 0) {
        continue;
    }

    list($name, $value) = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value);

    putenv("$name=$value");
    $_ENV[$name] = $value;
}

use Twilio\Rest\Client;

$account_sid = getenv('TWILIO_ACCOUNT_SID');
$auth_token = getenv('TWILIO_AUTH_TOKEN');
$twilio_number = getenv('TWILIO_NUMBER');

if (!$account_sid || !$auth_token || !$twilio_number) {
    die("Twilio configuration missing in environment file.");
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_SERVER["HTTP_CONTENT_TYPE"]) &&
    strpos($_SERVER["HTTP_CONTENT_TYPE"], "application/json") !== false
) {

    $data = json_decode(file_get_contents("php://input"), true);
    $status = $data['status'] ?? '';

    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    $phone_number = filter_var($phone_number, FILTER_SANITIZE_STRING);

    if (!$email || !$status || !$phone_number) {
        http_response_code(400);
        echo "Invalid input";
        exit;
    }

    $lastStatusFile = __DIR__ . "/last_status_{$email}.txt";
    $lastStatus = file_exists($lastStatusFile) ? trim(file_get_contents($lastStatusFile)) : "";

    if ($status === $lastStatus) {
        echo "No change – already processed.";
        exit;
    }

    file_put_contents($lastStatusFile, $status);

    $messages = [
        'success' => '✅ Your booking has been completed.',
        'error' => '❌ Your booking has not been made, please double-check your form.',
        'warning' => '⚠️ Warning! Something is wrong with your booking, please call us.'
    ];
    $sms_body = $messages[$status] ?? "Booking status update.";

    try {
        $twilio = new Client($account_sid, $auth_token);
        $message = $twilio->messages->create(
            $phone_number,
            [
                'from' => $twilio_number,
                'body' => $sms_body
            ]
        );
        echo "Status updated + SMS sent. SID: " . $message->sid;
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error sending SMS: " . $e->getMessage();
    }

    exit;
}
?>

<!DOCTYPE html>
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
    <title>Notification Page</title>

    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <script>
        (function () {
            emailjs.init("5STI1Aa0CICG0e9bY");
        })();
    </script>

    <link rel="stylesheet" href="styles.css" />
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-2"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">

            <h2>Notification Center</h2>

            <div id="notification" class="notification warning">
                Warning! Something is wrong with your booking, please call us at +441234567890.
            </div>

            <p class="logged-in">Logged in as: <?= htmlspecialchars($email) ?></p>

            <form method="POST">
                <div class="preferences">
                    <div class="checkbox-container">
                        <label class="checkbox-box">
                            <input type="checkbox" name="notificationMethod[]" value="push" />
                            <span>Push Notifications</span>
                        </label>
                        <label class="checkbox-box">
                            <input type="checkbox" name="notificationMethod[]" value="sms" />
                            <span>SMS Notifications</span>
                        </label>
                        <label class="checkbox-box">
                            <input type="checkbox" name="notificationMethod[]" value="email" />
                            <span>Email Notifications</span>
                        </label>
                    </div>

                    <button type="submit">Save Preferences</button>
                </div>
            </form>
        </div>

        <script>
            const userEmail = "<?= $email ?>";
        </script>

    </div>

</body>

</html>

