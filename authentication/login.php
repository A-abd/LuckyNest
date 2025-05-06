<?php
session_start();
include __DIR__ . "/../include/db.php";
include __DIR__ . '/../vendor/autoload.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;

$g = new GoogleAuthenticator();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, password, role, totp_secret FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['temp_user_id'] = $user['user_id'];
        $_SESSION['temp_role'] = $user['role'];
        $_SESSION['totp_secret'] = $user['totp_secret'];

        if (!empty($user['totp_secret'])) {
            $_SESSION['2fa_pending'] = true;
        } else {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            header('Location: ' . ($user['role'] === 'owner' || $user['role'] === 'admin' ? '../admin/dashboard' : '../guest/dashboard'));
            exit();
        }
    } else {
        $error = "Invalid email or password.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['totp_code']) && isset($_SESSION['2fa_pending'])) {
    $code = $_POST['totp_code'];
    if ($g->checkCode($_SESSION['totp_secret'], $code)) {
        $_SESSION['user_id'] = $_SESSION['temp_user_id'];
        $_SESSION['role'] = $_SESSION['temp_role'];
        unset($_SESSION['2fa_pending'], $_SESSION['temp_user_id'], $_SESSION['temp_role'], $_SESSION['totp_secret']);
        header('Location: ' . ($_SESSION['role'] === 'owner' || $_SESSION['role'] === 'admin' ? '../admin/dashboard' : '../guest/dashboard'));
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
    <title>Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <style>
        .otp-container {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            gap: 8px;
        }

        .otp-input {
            width: 45px;
            height: 50px;
            font-size: 24px;
            text-align: center;
            border: 2px solid rgba(0, 0, 0, 0.2);
            border-radius: 2px;
            background-color: #f7d8ce;
            color: #2b4949;
            font-weight: bold;
        }

        .otp-input:focus {
            border-color: #507878;
            outline: none;
            box-shadow: 0 0 5px rgba(80, 120, 120, 0.5);
        }

        .otp-hidden {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .error-message {
            color: red;
            margin-bottom: 15px;
            font-weight: normal;
            font-size: 14px;
        }

        /* Prevent up/down arrows on number inputs */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>

<body class="login">
    <div class="login-container">
        <h1 class="title">LuckyNest</h1>
        <div class="wrapper">
            <?php if (isset($_SESSION['2fa_pending'])): ?>
                <script>LuckyNest.toggleForms(true);</script>
                <form id="2fa-form" method="POST" action="login">
                    <h1>Two-Factor Authentication</h1>
                    <p>Enter the 6-digit code from your authentication app:</p>

                    <?php if (isset($error)): ?>
                        <p class="error-message"><?php echo $error; ?></p>
                    <?php endif; ?>

                    <div class="otp-container" id="otp-boxes">
                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                        <input type="number" class="otp-input" maxlength="1" pattern="[0-9]" min="0" max="9" required>
                    </div>

                    <!-- Hidden input to store the actual value -->
                    <input type="text" name="totp_code" id="totp_code" class="otp-hidden" minlength="6" maxlength="6"
                        pattern="[0-9]{6}" required>

                    <button type="submit" class="btn">Verify</button>
                </form>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const otpInputs = document.querySelectorAll('.otp-input');
                        const hiddenInput = document.getElementById('totp_code');

                        // Auto-focus the first input on page load
                        if (otpInputs.length > 0) {
                            otpInputs[0].focus();
                        }

                        // Function to update the hidden input with all values
                        function updateHiddenInput() {
                            let otpValue = '';
                            otpInputs.forEach(input => {
                                otpValue += input.value;
                            });
                            hiddenInput.value = otpValue;

                            // Validate for form submission
                            return otpValue.length === 6 && /^\d{6}$/.test(otpValue);
                        }

                        // Add event listeners to each input box
                        otpInputs.forEach((input, index) => {
                            // Handle input
                            input.addEventListener('input', function (e) {
                                // Only allow numbers
                                this.value = this.value.replace(/[^0-9]/g, '');

                                // Only keep the first digit if more are entered
                                if (this.value.length > 1) {
                                    this.value = this.value.charAt(0);
                                }

                                // Auto-focus next input when a digit is entered
                                if (this.value && index < otpInputs.length - 1) {
                                    otpInputs[index + 1].focus();
                                }

                                updateHiddenInput();
                            });

                            // Handle keydown for backspace navigation
                            input.addEventListener('keydown', function (e) {
                                if (e.key === 'Backspace') {
                                    if (!this.value && index > 0) {
                                        // If empty and backspace is pressed, focus previous input
                                        otpInputs[index - 1].focus();
                                        otpInputs[index - 1].value = '';
                                    } else if (this.value) {
                                        // Clear current input if it has a value
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

                            // Handle paste event for the entire code
                            input.addEventListener('paste', function (e) {
                                e.preventDefault();
                                const pasteData = (e.clipboardData || window.clipboardData).getData('text');

                                // Only allow numeric paste data
                                if (/^\d+$/.test(pasteData)) {
                                    // Fill in the inputs with the pasted digits
                                    for (let i = 0; i < Math.min(pasteData.length, otpInputs.length); i++) {
                                        otpInputs[i].value = pasteData.charAt(i);
                                    }

                                    // Focus the next empty input or the last input
                                    const nextIndex = Math.min(pasteData.length, otpInputs.length - 1);
                                    otpInputs[nextIndex].focus();

                                    updateHiddenInput();
                                }
                            });
                        });

                        // Handle form submission
                        document.getElementById('2fa-form').addEventListener('submit', function (e) {
                            if (!updateHiddenInput()) {
                                e.preventDefault();
                                alert('Please enter a valid 6-digit code');
                            }
                        });
                    });
                </script>
            <?php else: ?>
                <script>LuckyNest.toggleForms(false);</script>
                <form id="login-form" method="POST" action="login">
                    <h1>Login</h1>
                    <?php if (isset($error)): ?>
                        <p style="color: red;"><?php echo $error; ?></p>
                    <?php endif; ?>
                    <div class="input-box">
                        <input type="email" id="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="input-box">
                        <input type="password" id="password" name="password" placeholder="Password" required>
                    </div>
                    <div class="remember-forgot">
                        <a href="forgot">Forgot your password?</a>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <img src="../assets/images/sven-brandsma-GZ5cKOgeIB0-unsplash.jpg" alt="room with a sofa" class="right-img" />
</body>

</html>