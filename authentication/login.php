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
            header('Location: ' . ($user['role'] === 'owner' || $user['role'] === 'admin' ? '../admin/dashboard.php' : '../guest/dashboard.php'));
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
        header('Location: ' . ($_SESSION['role'] === 'owner' || $_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : '../guest/dashboard.php'));
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
</head>

<body class="login">
    <div class="login-container">
        <h1 class="title">LuckyNest</h1>
        <div class="wrapper">
            <?php if (isset($_SESSION['2fa_pending'])): ?>
                <script>LuckyNest.toggleForms(true);</script>
                <form id="2fa-form" method="POST" action="login.php">
                    <h1>Two-Factor Authentication</h1>
                    <p>Enter the authentication code from your app:</p>
                    <div class="input-box">
                        <input type="text" name="totp_code" placeholder="Authentication Code" required>
                    </div>
                    <button type="submit" class="btn">Verify</button>
                </form>
            <?php else: ?>
                <script>LuckyNest.toggleForms(false);</script>
                <form id="login-form" method="POST" action="login.php">
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
                        <label><input type="checkbox"> Remember me</label>
                        <a href="forgot.php">Forgot your password?</a>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <img src="../assets/images/sven-brandsma-GZ5cKOgeIB0-unsplash.jpg" alt="room with a sofa" class="right-img" />
</body>

</html>