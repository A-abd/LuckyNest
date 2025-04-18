<?php
session_start();

if ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'admin' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$feedback = '';
$recentUsers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'invite') {
            $email = trim($_POST['email']);

            if (empty($email)) {
                $feedback = 'Email address is required.';
            } else {
                $stmt = $conn->prepare("SELECT email FROM users WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    $feedback = 'Email already used.';
                } else {
                    $token = bin2hex(random_bytes(32));

                    $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                    $stmt = $conn->prepare("INSERT INTO invitations (email, token, expires_at, created_by) VALUES (:email, :token, :expires, :created_by)");
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':token', $token);
                    $stmt->bindParam(':expires', $expires);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);

                    if ($stmt->execute()) {
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = $_ENV['SMTP_HOST'];
                            $mail->SMTPAuth = true;
                            $mail->Username = $_ENV['SMTP_USERNAME'];
                            $mail->Password = $_ENV['SMTP_PASSWORD'];
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = $_ENV['SMTP_PORT'];

                            $mail->setFrom($_ENV['SMTP_USERNAME'], 'LuckyNest');
                            $mail->addAddress($email);

                            $mail->isHTML(true);
                            $mail->Subject = 'Invitation to Register at LuckyNest';

                            $registrationLink = 'http://' . $_SERVER['HTTP_HOST'] . '/LuckyNest/registration.php?token=' . $token;

                            $mail->Body = "
                            <html>
                            <head>
                                <title>Welcome to LuckyNest</title>
                            </head>
                            <body>
                                <h2>Welcome to LuckyNest!</h2>
                                <p>You have been invited to create an account.</p>
                                <p>Please click the link below to register (this link will expire in 30 minutes):</p>
                                <p><a href='$registrationLink'>Register Now</a></p>
                                <p>If you did not request this invitation, please ignore this email.</p>
                            </body>
                            </html>";

                            $mail->send();
                            $feedback = "Invitation email sent successfully to $email!";
                        } catch (Exception $e) {
                            $feedback = "Error sending email: {$mail->ErrorInfo}";
                        }
                    } else {
                        $feedback = 'Error creating invitation.';
                    }
                }
            }
        } elseif ($action === 'change_role') {
            if (isset($_POST['user_id'], $_POST['new_role'], $_POST['confirmed'])) {
                $userId = $_POST['user_id'];
                $newRole = $_POST['new_role'];
                $confirmed = $_POST['confirmed'];

                if ($confirmed === 'yes') {
                    $stmt = $conn->prepare("UPDATE users SET role = :role WHERE user_id = :id");
                    $stmt->bindParam(':role', $newRole);
                    $stmt->bindParam(':id', $userId);

                    if ($stmt->execute()) {
                        $feedback = "User role updated successfully to " . ucfirst($newRole) . "!";
                    } else {
                        $feedback = "Error updating user role.";
                    }
                }
            }
        }
    }
}

$stmt = $conn->prepare("
    SELECT * FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
    ORDER BY created_at DESC
");
$stmt->execute();
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$conn = null;
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap" />
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Create Users</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Create Users</h1>
            <?php if ($feedback): ?>
                <div class="rooms-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Add User Button -->
            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Send New
                    Invitation</button>
            </div>

            <!-- Add User Form -->
            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Send Registration Invitation</h2>
                <form method="POST" action="create_users.php">
                    <input type="hidden" name="action" value="invite">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" required>
                    <button type="submit" class="update-button">Send Invitation</button>
                </form>
            </div>

            <!-- Recently Created Accounts List -->
            <h2>Recently Created Accounts (Last 7 Days)</h2>
            <?php if (empty($recentUsers)): ?>
                <p>No new accounts created in the last 7 days.</p>
            <?php else: ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo $user['forename'] . ' ' . $user['surname']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <?php if ($user['role'] === 'guest' || $user['role'] === 'admin'): ?>
                                        <form id="role_<?php echo $user['user_id']; ?>_form" method="POST"
                                            action="create_users.php">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="new_role"
                                                value="<?php echo $user['role'] === 'guest' ? 'admin' : 'guest'; ?>">
                                            <input type="hidden" id="role_<?php echo $user['user_id']; ?>_confirmed"
                                                name="confirmed" value="no">
                                            <button type="button"
                                                onclick="LuckyNest.confirmRoleChange(<?php echo $user['user_id']; ?>, '<?php echo $user['role']; ?>')"
                                                class="update-button">
                                                Change to <?php echo $user['role'] === 'guest' ? 'Admin' : 'Guest'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span>Owner (Cannot change)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <br>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>