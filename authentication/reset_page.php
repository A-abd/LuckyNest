<?php
session_start();
include __DIR__ . "/../include/db.php";
include __DIR__ . "/../include/reset.php";

$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';
$user_id = false;
$error = '';
$success = '';

if (!empty($token) && !empty($email)) {
    $user_id = verifyResetToken($token, $email, $conn);
    if (!$user_id) {
        $error = "Invalid or expired reset link. Please request a new password reset.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
    $token = $_POST['token'];
    $email = $_POST['email'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $user_id = verifyResetToken($token, $email, $conn);
    if (!$user_id) {
        $error = "Invalid or expired reset link. Please request a new password reset.";
    } else {
        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $passwordErrors = validatePassword($new_password);
            if (!empty($passwordErrors)) {
                $error = implode("<br>", $passwordErrors);
            } else {
                if (updatePassword($user_id, $new_password, $conn)) {
                    $success = "Your password has been successfully reset. You can now <a href='../authentication/login'>log in</a> with your new password.";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
</head>

<body class="login">
    <div class="blur-layer-4"></div>
    <h1 class="title">LuckyNest</h1>
    <div class="login-container">
        <div class="wrapper">
            <?php if ($success): ?>
                <div class="success-message-reset">
                    <?php echo $success; ?>
                </div>
            <?php elseif (!$user_id): ?>
                <?php if ($error): ?>
                    <div class="error-message-reset">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <p>This password reset link is invalid or has expired. Please <a href="../authentication/login">return
                        to login</a> and
                    request a new password reset.</p>
            <?php else: ?>
                <form id="reset-form" method="POST" action="reset_page" onsubmit="return LuckyNest.validateResetForm()">
                    <h1>Reset Your Password</h1>

                    <?php if ($error): ?>
                        <div class="error-message-reset">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div id="form-errors" class="form-errors"></div>

                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                    <div class="input-box">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required
                            onfocus="LuckyNest.showPasswordRequirements()" onblur="LuckyNest.hidePasswordRequirements()"
                            onkeyup="LuckyNest.validatePasswordStrength()">
                        <div id="password-requirements" class="password-requirements">
                            <div id="min-length" class="requirement">At least 5 characters</div>
                            <div id="uppercase" class="requirement">At least one uppercase letter</div>
                            <div id="lowercase" class="requirement">At least one lowercase letter</div>
                            <div id="special" class="requirement">At least one special character</div>
                            <div id="password-match" class="requirement">Passwords match</div>
                        </div>
                    </div>

                    <div class="input-box">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                            onfocus="LuckyNest.showConfirmPasswordTip()" onblur="LuckyNest.hideConfirmPasswordTip()"
                            onkeyup="LuckyNest.validatePasswordStrength()">
                        <div id="confirm-password-tip" class="confirm-password-tip">
                            <div id="confirm-password-match" class="requirement">Passwords match</div>
                        </div>
                    </div>

                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>