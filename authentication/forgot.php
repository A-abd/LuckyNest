<?php
session_start();
include __DIR__ . "/../include/db.php";
include __DIR__ . "/../include/reset.php";

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email']) && isset($_POST['notification_method'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $notification_method = $_POST['notification_method'];

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (handlePasswordResetRequest($email, $conn, $notification_method)) {
            if ($notification_method == 'email') {
                $message = "If an account exists with this email, a password reset link has been sent. Please check your email.";
            } else {
                $message = "If an account exists with this email, a password reset link has been sent via SMS.";
            }
            $status = "success";
        } else {
            $message = "Error processing your request. Please try again later.";
            $status = "error";
        }
    } else {
        $message = "Please enter a valid email address.";
        $status = "error";
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles.css">
    <style>
        .notification-options {
            margin-bottom: 15px;
        }

        .notification-option {
            display: block;
            margin: 5px 0;
        }

        .notification-option input {
            margin-right: 5px;
        }
    </style>
</head>

<body class="login">
    <div class="blur-layer-4"></div>
    <h1 class="title">LuckyNest</h1>
    <div class="login-container">
        <div class="wrapper">
            <form method="POST" action="forgot.php">
                <h1>Forgot Password</h1>
                <p>Enter your email address and select how you'd like to receive the reset link.</p>

                <?php if ($message): ?>
                    <p style="color: <?php echo $status === 'success' ? 'green' : 'red'; ?>;"><?php echo $message; ?></p>
                <?php endif; ?>

                <div class="input-box">
                    <input type="email" id="email" name="email" placeholder="Email Associated with your Account" required>
                </div>

                <div class="notification-options">
                    <p>How would you like to receive your reset link?</p>
                    <label class="notification-option">
                        <input type="radio" name="notification_method" value="email" checked>
                        Email
                    </label>
                    <label class="notification-option">
                        <input type="radio" name="notification_method" value="sms">
                        SMS to the number associated with the email address
                    </label>
                </div>

                <button type="submit" class="btn">Send Reset Link</button>

                <div class="back-to-login">
                    <a href="login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>