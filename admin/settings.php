<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'owner')) {
    header('Location: ../authentication/unauthorized');
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
    header('Location: ../authentication/unauthorized');
    exit();
}

$g = new GoogleAuthenticator();
$secret = $user['totp_secret'];
$qrCodeUrl = $secret ? GoogleQrUrl::generate("LuckyNest", $secret, "LuckyNestApp") : '';

if ($secret) {
    $_SESSION['2fa_verified'] = true;
}

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
                unset($_SESSION['2fa_verified']);
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
    <title>Admin Security Settings</title>
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <?php include '../include/admin_navbar.php'; ?>

    <div class="blur-layer"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard">LuckyNest Admin</a></h1>
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

                <div class="two-factor-auth-container">
                    <div class="two-factor-buttons">
                        <?php if ($secret && isset($_SESSION['2fa_verified'])): ?>
                            <!-- Show enabled status when 2FA is set up and verified -->
                            <p class="status-enabled">2FA is currently enabled</p>
                            <button onclick="LuckyNest.showPasswordPrompt('disable')"
                                class="update-button danger-button">Disable 2FA</button>
                        <?php elseif ($secret): ?>
                            <!-- This condition should never happen with the updated logic, but keeping it as a fallback -->
                            <p class="status-warning">2FA requires validation</p>
                        <?php else: ?>
                            <!-- Show disabled status when no 2FA is set up -->
                            <p class="status-disabled">2FA is currently disabled</p>
                            <br>
                            <button onclick="LuckyNest.showPasswordPrompt('enable')" class="update-button"
                                style="width: 41.5%;">Enable
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
                                    <label for="totp_code">Enter the 6-digit code from your Authenticator app:</label>

                                    <div class="otp-container" id="otp-boxes-setup">
                                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                                    </div>

                                    <!-- Hidden input to store the actual value -->
                                    <input type="text" id="totp_code" name="totp_code" class="otp-hidden" minlength="6"
                                        maxlength="6" pattern="[0-9]{6}" required>

                                    <button type="submit" class="update-button verify-button">Verify and Enable 2FA</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="security-info">
                                <?php if ($secret): ?>
                                    <p><strong>2FA is active on your account</strong></p>
                                    <p>Your admin account is protected with two-factor authentication.</p>
                                    <p>If you need to change your authenticator app or device, you can disable 2FA and set it up
                                        again.</p>
                                <?php else: ?>
                                    <p><strong>Why enable Two-factor Authentication (2FA)?</strong></p>
                                    <p>Two-factor authentication adds an extra layer of security to your admin account.</p>
                                    <p>Even if someone discovers your password, they won't be able to access your account
                                        without your authenticator app.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.SettingsModule && typeof window.SettingsModule.setupOtpInputs === 'function') {
            window.SettingsModule.setupOtpInputs();
        } else {
            setupOtpInput('otp-boxes-setup', 'totp_code');
            
            function setupOtpInput(containerID, hiddenInputID) {
                const container = document.getElementById(containerID);
                if (!container) return;

                const otpInputs = container.querySelectorAll('.otp-input');
                const hiddenInput = document.getElementById(hiddenInputID);
                if (!hiddenInput) return;

                if (otpInputs.length > 0) {
                    otpInputs[0].focus();
                }

                function updateHiddenInput() {
                    let otpValue = '';
                    otpInputs.forEach(input => {
                        otpValue += input.value;
                    });
                    hiddenInput.value = otpValue;

                    return otpValue.length === 6 && /^\d{6}$/.test(otpValue);
                }

                otpInputs.forEach((input, index) => {
                    input.addEventListener('input', function(e) {
                        this.value = this.value.replace(/[^0-9]/g, '');

                        if (this.value.length > 1) {
                            this.value = this.value.charAt(0);
                        }

                        if (this.value && index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        }

                        updateHiddenInput();
                    });

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace') {
                            if (!this.value && index > 0) {
                                otpInputs[index - 1].focus();
                                otpInputs[index - 1].value = '';
                            } else if (this.value) {
                                this.value = '';
                            }
                            updateHiddenInput();
                            e.preventDefault();
                        } else if (e.key === 'ArrowLeft' && index > 0) {
                            otpInputs[index - 1].focus();
                            e.preventDefault();
                        } else if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                            e.preventDefault();
                        }
                    });

                    input.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const pasteData = (e.clipboardData || window.clipboardData).getData('text');

                        if (/^\d+$/.test(pasteData)) {
                            for (let i = 0; i < Math.min(pasteData.length, otpInputs.length); i++) {
                                otpInputs[i].value = pasteData.charAt(i);
                            }

                            const nextIndex = Math.min(pasteData.length, otpInputs.length - 1);
                            otpInputs[nextIndex].focus();

                            updateHiddenInput();
                        }
                    });
                });

                const form = container.closest('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!updateHiddenInput()) {
                            e.preventDefault();
                            alert('Please enter a valid 6-digit code');
                        }
                    });
                }
            }
        }
        
        if (typeof LuckyNest === 'undefined' || !LuckyNest.showPasswordPrompt) {
            window.LuckyNest = window.LuckyNest || {};
            window.LuckyNest.showPasswordPrompt = function(action) {
                document.getElementById('action-type').value = action;
                document.getElementById('password-prompt').style.display = 'block';
            };
        }
    });
    </script>
</body>

</html>