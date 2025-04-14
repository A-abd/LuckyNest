<?php
session_start();
require __DIR__ . "/include/db.php";

$error = '';
$success = '';
$validToken = false;
$email = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT * FROM invitations WHERE token = :token AND expires_at > NOW() AND used = 0");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invitation) {
        $validToken = true;
        $email = $invitation['email'];
    } else {
        $error = 'Invalid or expired invitation link. Please request a new invitation.';
    }
} else {
    $error = 'Registration requires a valid invitation link. Please contact an administrator.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $forename = trim($_POST['forename']);
    $surname = trim($_POST['surname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $emergency_contact = trim($_POST['emergency_contact']);
    $address = trim($_POST['address']);
    $role = 'guest';
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $token = $_POST['token'];

    if (empty($forename) || empty($surname) || empty($email) || empty($phone) || empty($emergency_contact) || empty($address) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Verify the token again for security
        $stmt = $conn->prepare("SELECT * FROM invitations WHERE token = :token AND email = :email AND expires_at > NOW() AND used = 0");
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $error = 'Invalid or expired invitation.';
        } else {
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = 'Email already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $conn->beginTransaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO users (forename, surname, email, phone, emergency_contact, address, role, password, created_at) VALUES (:forename, :surname, :email, :phone, :emergency_contact, :address, :role, :password, NOW())");
                    $stmt->bindParam(':forename', $forename);
                    $stmt->bindParam(':surname', $surname);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':phone', $phone);
                    $stmt->bindParam(':emergency_contact', $emergency_contact);
                    $stmt->bindParam(':address', $address);
                    $stmt->bindParam(':role', $role);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE invitations SET used = 1, used_at = NOW() WHERE token = :token");
                    $stmt->bindParam(':token', $token);
                    $stmt->execute();

                    $conn->commit();
                    $success = 'Registration successful! You can now <a href="index.php">login</a>.';
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
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
    <link rel="stylesheet" href="assets/styles.css">
    <title>Register</title>
</head>

<body class="registration">
    <div class="blur-layer"></div>
    <div class="center-container-registration">
        <h1 class="title">LuckyNest</h1>
        <div class="wrapper">
            <h1>Register</h1>
            <?php if ($error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo $success; ?></p>
            <?php elseif ($validToken): ?>
                <form method="POST" action="registration.php?token=<?php echo htmlspecialchars($_GET['token']); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">

                    <div class="input-box">
                        <input type="text" id="forename" name="forename" placeholder="Forename" required><br><br>
                    </div>

                    <div class="input-box">
                        <input type="text" id="surname" name="surname" placeholder="Surname" required>
                    </div>

                    <div class="input-box">
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly
                            required>
                        <small>Email from invitation cannot be changed</small>
                    </div>

                    <div class="input-box">
                        <input type="text" id="phone" name="phone" placeholder="Phone Number" required>
                    </div>

                    <div class="input-box">
                        <input type="text" id="emergency_contact" name="emergency_contact"
                            placeholder="Emergency Contact Number" required>
                    </div>

                    <div class="input-box">
                        <input type="text" id="address" name="address" placeholder="Home Address" required>
                    </div>

                    <div class="input-box">
                        <input type="password" id="password" name="password" placeholder="Password" required>
                    </div>

                    <div class="input-box">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password"
                            required>
                    </div>

                    <button type="submit" class="btn">Register</button>

                    <div class="login-link">
                        <p>Have an account already? <a href="index.php">Login</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>