<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: unauthorized.php');
    exit();
}

require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../include/db.php';
require_once __DIR__ . '/../include/notification_utils.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT totp_secret, password, email_notifications, sms_notifications, push_notifications, email, phone FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$g = new GoogleAuthenticator();
$secret = $user['totp_secret'];
$qrCodeUrl = $secret ? GoogleQrUrl::generate("LuckyNest", $secret, "LuckyNestApp") : '';

$error = '';
$success_message = '';

// Handle test notification send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_notification'])) {
    $notification_type = $_POST['notification_type'];

    try {
        if ($notification_type === 'payment_reminder') {
            $result = sendPaymentReminder(
                $conn,
                $user_id,
                'rent',
                450.00,
                date('d F Y', strtotime('+7 days')),
                123
            );
            $success_message = "Test payment reminder sent.";
        } elseif ($notification_type === 'late_payment') {
            $result = sendLatePaymentNotice(
                $conn,
                $user_id,
                'rent',
                450.00,
                date('d F Y', strtotime('-5 days')),
                123,
                5
            );
            $success_message = "Test late payment notice sent.";
        }

        // For debugging
        if (isset($result['error'])) {
            $error = "Error sending test notification: " . $result['error'];
        } else {
            $success_message .= " Email: " . ($result['email'] ? "Sent" : "Not sent");
            $success_message .= ", SMS: " . ($result['sms'] ? "Sent" : "Not sent");
        }
    } catch (Exception $e) {
        $error = "Error sending test notification: " . $e->getMessage();
    }
}

// Handle notification preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;

    try {
        $stmt = $conn->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ?, push_notifications = ? WHERE user_id = ?");
        $stmt->execute([$email_notifications, $sms_notifications, $push_notifications, $user_id]);

        // Update the user data after changes
        $stmt = $conn->prepare("SELECT email_notifications, sms_notifications, push_notifications FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $updated_prefs = $stmt->fetch();
        $user['email_notifications'] = $updated_prefs['email_notifications'];
        $user['sms_notifications'] = $updated_prefs['sms_notifications'];
        $user['push_notifications'] = $updated_prefs['push_notifications'];

        $success_message = "Notification preferences updated successfully.";
    } catch (PDOException $e) {
        $error = "Error updating notification preferences: " . $e->getMessage();
    }
}

// Handle 2FA changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_password'])) {
    $password = $_POST['password'];

    if (password_verify($password, $user['password'])) {
        if (isset($_POST['toggle_2fa'])) {
            if ($secret) {
                $stmt = $conn->prepare("UPDATE users SET totp_secret = NULL WHERE user_id = ?");
                $stmt->execute([$user_id]);
                header("Location: settings.php");
                exit();
            } else {
                $secret = $g->generateSecret();
                $qrCodeUrl = GoogleQrUrl::generate("LuckyNest", $secret, "LuckyNestApp");
                $_SESSION['new_secret'] = $secret;
            }
        }
    } else {
        $error = "Incorrect password.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    $code = $_POST['totp_code'];
    $verificationSecret = isset($_SESSION['new_secret']) ? $_SESSION['new_secret'] : $secret;

    if ($g->checkCode($verificationSecret, $code)) {
        if (isset($_SESSION['new_secret'])) {
            $stmt = $conn->prepare("UPDATE users SET totp_secret = ? WHERE user_id = ?");
            $stmt->execute([$_SESSION['new_secret'], $user_id]);
            unset($_SESSION['new_secret']);
        }
        $_SESSION['2fa_verified'] = true;
        header('Location: settings.php');
        exit();
    } else {
        $error = "Invalid authentication code.";
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/styles.css">
    <style>
        .settings-section {
            margin-bottom: 40px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .notification-option {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .notification-description {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
            margin-left: 25px;
        }

        .status-enabled {
            color: #27ae60;
            font-weight: bold;
        }

        .status-disabled {
            color: #e74c3c;
            font-weight: bold;
        }

        .danger-button {
            background-color: #e74c3c;
        }

        .success-message {
            padding: 10px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .error-message {
            padding: 10px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .test-section {
            background-color: #fffde7;
            border: 1px dashed #ffc107;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }

        .test-section h3 {
            color: #ff9800;
        }

        .test-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .test-button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .test-button.payment-reminder {
            background-color: #3498db;
            color: white;
        }

        .test-button.late-payment {
            background-color: #e74c3c;
            color: white;
        }
    </style>
    <script>
        function showPasswordPrompt(action) {
            document.getElementById('action-type').value = action;
            document.getElementById('password-prompt').style.display = 'block';
        }

        function confirmDisable2FA() {
            if (confirm("Are you sure you want to disable 2FA?")) {
                document.getElementById('disable-2fa-form').submit();
            }
        }
    </script>
    <title>Account Settings</title>
</head>

<body>
    <?php include '../include/guest_navbar.php'; ?>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Account Settings</h1>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <div class="settings-section">
                <h2>Security Settings</h2>

                <h3>Two-Factor Authentication</h3>
                <?php if ($secret): ?>
                    <p class="status-enabled">2FA is currently enabled</p>
                    <button onclick="showPasswordPrompt('disable')" class="update-button danger-button">Disable 2FA</button>
                <?php else: ?>
                    <p class="status-disabled">2FA is currently disabled</p>
                    <button onclick="showPasswordPrompt('enable')" class="update-button">Enable 2FA</button>
                <?php endif; ?>

                <div id="password-prompt" style="display: none;">
                    <form method="POST">
                        <input type="hidden" name="toggle_2fa" value="1">
                        <input type="hidden" id="action-type" name="action_type" value="">
                        <div>
                            <label for="password">Enter your password to continue:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" name="verify_password" class="update-button">Continue</button>
                    </form>
                </div>

                <?php if (isset($_SESSION['new_secret'])): ?>
                    <div id="2fa-setup">
                        <p>Scan this QR code with your Authenticator app:</p>
                        <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code">
                        <p>Or enter this secret manually:
                            <strong><?php echo htmlspecialchars($_SESSION['new_secret']); ?></strong></p>
                        <form method="POST">
                            <label for="totp_code">Enter Code from your Authenticator app:</label>
                            <input type="text" id="totp_code" name="totp_code" required>
                            <button type="submit" class="update-button">Verify and Enable 2FA</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="settings-section">
                <h2>Notification Preferences</h2>

                <form method="POST" action="">
                    <div class="notification-option">
                        <label for="email_notifications">
                            <input type="checkbox" id="email_notifications" name="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                            <span>Email Notifications</span>
                        </label>
                        <p class="notification-description">Receive payment reminders, late payment notices, and booking
                            information via email.</p>
                        <p class="notification-description">Current email:
                            <strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
                    </div>

                    <div class="notification-option">
                        <label for="sms_notifications">
                            <input type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo $user['sms_notifications'] ? 'checked' : ''; ?>>
                            <span>SMS Notifications</span>
                        </label>
                        <p class="notification-description">Receive payment reminders and important updates via text
                            message.</p>
                        <p class="notification-description">Current phone:
                            <strong><?php echo htmlspecialchars($user['phone']); ?></strong></p>
                    </div>

                    <div class="notification-option">
                        <label for="push_notifications">
                            <input type="checkbox" id="push_notifications" name="push_notifications" <?php echo $user['push_notifications'] ? 'checked' : ''; ?>>
                            <span>Push Notifications</span>
                        </label>
                        <p class="notification-description">Receive notifications through the app (when available).</p>
                    </div>

                    <button type="submit" name="update_notifications" class="update-button">Save Notification
                        Settings</button>
                </form>

                <div class="test-section">
                    <h3>Test Notifications</h3>
                    <p>Send test notifications to verify your current settings:</p>

                    <div class="test-buttons">
                        <form method="POST" action="">
                            <input type="hidden" name="notification_type" value="payment_reminder">
                            <button type="submit" name="send_test_notification"
                                class="test-button payment-reminder">Send Test Payment Reminder</button>
                        </form>

                        <form method="POST" action="">
                            <input type="hidden" name="notification_type" value="late_payment">
                            <button type="submit" name="send_test_notification" class="test-button late-payment">Send
                                Test Late Payment Notice</button>
                        </form>
                    </div>

                    <p style="margin-top: 10px; font-size: 0.8em; color: #888;">
                        Note: These are test notifications and will not affect any actual bookings or payments.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>