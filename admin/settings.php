<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'owner')) {
    header('Location: ../authentication/unauthorized.php');
    exit();
}

include __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../include/db.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT totp_secret, password, email FROM users WHERE user_id = :user_id AND (role = 'admin' OR role = 'owner')");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../authentication/unauthorized.php');
    exit();
}

$g = new GoogleAuthenticator();
$secret = $user['totp_secret'];
$qrCodeUrl = $secret ? GoogleQrUrl::generate("LuckyNest", $secret, "LuckyNestApp") : '';

$error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_password'])) {
    $password = $_POST['password'];

    if (password_verify($password, $user['password'])) {
        if (isset($_POST['toggle_2fa'])) {
            if ($secret) {
                $stmt = $conn->prepare("UPDATE users SET totp_secret = NULL WHERE user_id = :user_id");
                $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $success_message = "Two-factor authentication has been disabled.";
                $secret = null;
                $qrCodeUrl = '';
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
            
            $success_message = "Two-factor authentication has been enabled successfully.";
            $secret = $_SESSION['new_secret'];
            unset($_SESSION['new_secret']);
        }
        $_SESSION['2fa_verified'] = true;
    } else {
        $error = "Invalid authentication code. Please try again.";
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/styles.css">
    <title>Admin Security Settings</title>
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <?php include '../include/admin_navbar.php'; ?>

    <div class="blur-layer"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest Admin</a></h1>
        <div class="content-container">
            <h1>Admin Security Settings</h1>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <div class="settings-section">
                <h2>Two-Factor Authentication</h2>

                <div class="admin-info">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($_SESSION['role'])); ?></p>
                </div>

                <?php if ($secret): ?>
                    <p class="status-enabled">2FA is currently enabled</p>
                    <button onclick="LuckyNest.showPasswordPrompt('disable')" class="update-button danger-button">Disable 2FA</button>
                <?php else: ?>
                    <p class="status-disabled">2FA is currently disabled</p>
                    <button onclick="LuckyNest.showPasswordPrompt('enable')" class="update-button">Enable 2FA</button>
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
                        <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code" class="qr-code">
                        <p>Or enter this secret manually: <strong><?php echo htmlspecialchars($_SESSION['new_secret']); ?></strong></p>
                        <form method="POST">
                            <label for="totp_code">Enter Code from your Authenticator app:</label>
                            <input type="text" id="totp_code" name="totp_code" required>
                            <button type="submit" class="update-button">Verify and Enable 2FA</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        if (typeof LuckyNest === 'undefined') {
            var LuckyNest = {};
        }
        
        LuckyNest.showPasswordPrompt = function(action) {
            document.getElementById('password-prompt').style.display = 'block';
            document.getElementById('action-type').value = action;
        };
    </script>
</body>

</html>