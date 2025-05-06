<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/notification_utils.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT totp_secret, password, email_notifications, sms_notifications, email, phone FROM users WHERE user_id = :user_id");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch();

$g = new GoogleAuthenticator();
$secret = $user['totp_secret'];
$qrCodeUrl = $secret ? GoogleQrUrl::generate("LuckyNest", $secret, "LuckyNestApp") : '';

$error = '';
$success_message = '';

// Handle notification preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;

    try {
        $stmt = $conn->prepare("UPDATE users SET email_notifications = :email_notifications, sms_notifications = :sms_notifications WHERE user_id = :user_id");
        $stmt->bindValue(':email_notifications', $email_notifications, PDO::PARAM_INT);
        $stmt->bindValue(':sms_notifications', $sms_notifications, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        // Update the user data after changes
        $stmt = $conn->prepare("SELECT email_notifications, sms_notifications FROM users WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $updated_prefs = $stmt->fetch();
        $user['email_notifications'] = $updated_prefs['email_notifications'];
        $user['sms_notifications'] = $updated_prefs['sms_notifications'];

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
                $stmt = $conn->prepare("UPDATE users SET totp_secret = NULL WHERE user_id = :user_id");
                $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                unset($_SESSION['2fa_verified']); // Clear the verification session variable when disabling 2FA
                header("Location: settings");
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
            $stmt = $conn->prepare("UPDATE users SET totp_secret = :totp_secret WHERE user_id = :user_id");
            $stmt->bindValue(':totp_secret', $_SESSION['new_secret'], PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            unset($_SESSION['new_secret']);
        }
        $_SESSION['2fa_verified'] = true;
        header('Location: settings');
        exit();
    } else {
        $error = "Invalid authentication code.";
        // Keep the QR code and setup visible when verification fails
        if (isset($_SESSION['new_secret'])) {
            $secret = $_SESSION['new_secret'];
            $qrCodeUrl = GoogleQrUrl::generate("LuckyNest", $secret, "LuckyNestApp");
        }
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/styles.css">
    <title>Account Settings</title>
    <script src="../assets/scripts.js"></script>
    <style>
        .two-factor-auth-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 20px;
        }

        .two-factor-buttons {
            flex: 1;
            padding-right: 20px;
        }

        .two-factor-qr {
            flex: 1;
            text-align: center;
            border-left: 1px solid rgba(43, 73, 73, 0.2);
            padding-left: 20px;
        }

        .status-enabled {
            background-color: #27ae60;
            color: #fff;
            font-weight: 600;
            display: inline-block;
            padding: 10px 15px;
            border-radius: 0px;
            border: 2px solid #27ae60;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .status-disabled {
            background-color: #e74c3c;
            color: #fff;
            font-weight: 600;
            display: inline-block;
            padding: 10px 15px;
            border-radius: 0px;
            border: 2px solid #e74c3c;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .status-warning {
            background-color: #f39c12;
            color: #fff;
            font-weight: 600;
            display: inline-block;
            padding: 10px 15px;
            border-radius: 0px;
            border: 2px solid #f39c12;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .security-info {
            background-color: #f7d8ce;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            text-align: left;
        }

        .security-info p {
            margin-bottom: 10px;
        }

        .security-info strong {
            color: #2b4949;
        }

        .verify-button {
            width: 49%;
        }

        #2fa-setup {
            background-color: #f7d8ce;
            padding: 15px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php include '../include/guest_navbar.php'; ?>

    <div class="blur-layer"></div>
    <div class="manage-default">
        <h1><a class="title" href="../authentication/login">LuckyNest</a></h1>
        <div class="content-container">
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

                <div class="two-factor-auth-container">
                    <div class="two-factor-buttons">
                        <?php if ($secret && isset($_SESSION['2fa_verified'])): ?>
                            <!-- Only show status and disable button if 2FA is verified -->
                            <p class="status-enabled">2FA is currently enabled</p>
                            <button onclick="LuckyNest.showPasswordPrompt('disable')"
                                class="update-button danger-button">Disable 2FA</button>
                        <?php elseif ($secret): ?>
                            <!-- Show validation required message if 2FA is enabled but not verified in this session -->
                            <p class="status-warning">2FA has not been validated</p>
                            <!-- No disable button here as user hasn't verified 2FA in this session -->
                        <?php else: ?>
                            <!-- Show disabled status and enable button if no 2FA is set up -->
                            <p class="status-disabled">2FA is currently disabled</p>
                            <button onclick="LuckyNest.showPasswordPrompt('enable')" class="update-button">Enable
                                2FA</button>
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
                    </div>

                    <div class="two-factor-qr">
                        <?php if (isset($_SESSION['new_secret'])): ?>
                            <div id="2fa-setup">
                                <p>Scan this QR code with your Authenticator app:</p>
                                <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code">
                                <p>Or enter this secret manually:
                                    <strong><?php echo htmlspecialchars($_SESSION['new_secret']); ?></strong>
                                </p>
                                <form method="POST">
                                    <label for="totp_code">Enter Code from your Authenticator app:</label>
                                    <input type="text" id="totp_code" name="totp_code" required>
                                    <button type="submit" class="update-button verify-button">Verify and Enable 2FA</button>
                                </form>
                            </div>
                        <?php elseif ($secret && !isset($_SESSION['2fa_verified'])): ?>
                            <div class="security-info">
                                <p><strong>2FA requires validation</strong></p>
                                <p>Two-factor authentication is set up on your account but needs validation in this session
                                    before you can manage it.</p>
                                <p>Please verify your identity by logging in with your authenticator app code.</p>
                                <form method="POST">
                                    <label for="totp_verify">Enter verification code from your authenticator app:</label>
                                    <input type="text" id="totp_verify" name="totp_code" required>
                                    <button type="submit" class="update-button verify-button">Verify</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="security-info">
                                <p><strong>Why enable 2FA?</strong></p>
                                <p>Two-factor authentication adds an extra layer of security to your account.</p>
                                <p>Even if someone discovers your password, they won't be able to access your account
                                    without your authenticator app.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <h2>Notification Preferences</h2>

                <form method="POST" action="">
                    <div class="notification-option-settings">
                        <label for="email_notifications">
                            <input type="checkbox" id="email_notifications" name="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                            <span>Email Notifications</span>
                        </label>
                        <p class="notification-description">Receive payment reminders, late payment notices, and booking
                            information via email.</p>
                        <p class="notification-description">Your email address:
                            <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                        </p>
                    </div>

                    <div class="notification-option-settings">
                        <label for="sms_notifications">
                            <input type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo $user['sms_notifications'] ? 'checked' : ''; ?>>
                            <span>SMS Notifications</span>
                        </label>
                        <p class="notification-description">Receive payment reminders and important updates via text
                            message.</p>
                        <p class="notification-description">Your phone number:
                            <strong><?php echo htmlspecialchars($user['phone']); ?></strong>
                        </p>
                    </div>

                    <button type="submit" name="update_notifications" class="update-button">Save Notification
                        Settings</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>