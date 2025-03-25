<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';

$feedback = '';
$userData = [];

//$userId = $_SESSION['user_id'];
$userId = 1;
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'update_profile') {
            $forename = filter_input(INPUT_POST, 'forename', FILTER_SANITIZE_STRING);
            $surname = filter_input(INPUT_POST, 'surname', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            $emergencyContact = filter_input(INPUT_POST, 'emergency_contact', FILTER_SANITIZE_STRING);
            $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

            $stmt = $conn->prepare("UPDATE users SET forename = :forename, surname = :surname, email = :email, phone = :phone, emergency_contact = :emergencyContact, address = :address WHERE user_id = :userId");
            $stmt->bindValue(':forename', $forename, PDO::PARAM_STR);
            $stmt->bindValue(':surname', $surname, PDO::PARAM_STR);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindValue(':emergencyContact', $emergencyContact, PDO::PARAM_STR);
            $stmt->bindValue(':address', $address, PDO::PARAM_STR);
            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Profile updated successfully!';
            } else {
                $feedback = 'Error updating profile.';
            }
        } elseif ($action === 'update_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            if (password_verify($currentPassword, $userData['password'])) {
                if ($newPassword === $confirmPassword) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("UPDATE users SET password = :password WHERE user_id = :userId");
                    $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
                    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $feedback = 'Password updated successfully!';
                    } else {
                        $feedback = 'Error updating password.';
                    }
                } else {
                    $feedback = 'New password and confirm password do not match.';
                }
            } else {
                $feedback = 'Current password is incorrect.';
            }
        }
    }
}

$conn = null;
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap" />
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <div class="blur-layer"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="centering">
            <h2 class="manage-profile">Manage Profile</h2>
            <?php if ($feedback): ?>
    <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
<?php endif; ?>

            <div class="update-wrapper">
                <div class="update-profile-wrapper">
                    <h2>Update Profile</h2>
                    <form method="POST" action="manage_profile.php">
                        <div class="input-space">
                            <input type="hidden" name="action" value="update_profile">
                        </div>

                        <label for="forename">Forename:</label>
                        <div class="input-space">
                            <input type="text" id="forename" name="forename"
                                value="<?php echo htmlspecialchars($userData['forename']); ?>" required>
                        </div>

                        <label for="surname">Surname:</label>
                        <div class="input-space">
                            <input type="text" id="surname" name="surname"
                                value="<?php echo htmlspecialchars($userData['surname']); ?>" required>
                        </div>

                        <label for="email">Email:</label>
                        <div class="input-space">
                            <input type="email" id="email" name="email"
                                value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                        </div>

                        <label for="phone">Phone:</label>
                        <div class="input-space">
                            <input type="text" id="phone" name="phone"
                                value="<?php echo htmlspecialchars($userData['phone']); ?>" required>
                        </div>

                        <label for="emergency_contact">Emergency Contact:</label>
                        <div class="input-space">
                            <input type="text" id="emergency_contact" name="emergency_contact"
                                value="<?php echo htmlspecialchars($userData['emergency_contact']); ?>" required>
                        </div>

                        <label for="address">Address:</label>
                        <div class="input-space">
                            <input type="text" id="address" name="address"
                                value="<?php echo htmlspecialchars($userData['address']); ?>" required>
                        </div>

                        <button type="submit" class="update-button">Update Profile</button>
                    </form>
                </div>

                <div class="update-password-wrapper">
                    <div class="update-password-title">
                        <h2>Update Password</h2>
                    </div>
                    <form method="POST" action="manage_profile.php">
                        <div class="input-space">
                            <input type="hidden" name="action" value="update_password">
                        </div>

                        <div class="form-gap">
                            <label for="current_password">Current Password:</label>
                            <div class="input-space">
                                <input type="password" id="current_password" name="current_password" required>
                            </div>

                            <label for="new_password">New Password:</label>
                            <div class="input-space">
                                <input type="password" id="new_password" name="new_password" required>
                            </div>

                            <label for="confirm_password">Confirm New Password:</label>
                            <div class="input-space">
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <button type="submit" class="update-button">Update Password</button>
                    </form>

                </div>
            </div>
            <br>
            <div class="button-center"><a href="dashboard.php" class="button">&larr; Back to Dashboard</a></div>
        </div>
    </div>
</body>

</html>
