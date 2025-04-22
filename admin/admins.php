<?php
session_start();

if ($_SESSION['role'] != 'owner') {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$adminData = [];
$errors = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            $forename = $_POST['forename'];
            $surname = $_POST['surname'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $emergency_contact = $_POST['emergency_contact'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = 'admin';
            
            $isValid = true;
            
            if (strlen($phone) < 9 || strlen($phone) > 11) {
                $errors['phone'] = 'Phone number must be between 9 and 11 digits';
                $isValid = false;
            } elseif (!preg_match('/^[0-9+]+$/', $phone)) {
                $errors['phone'] = 'Phone number must contain only digits and possibly a + sign';
                $isValid = false;
            }
            
            if (strlen($emergency_contact) < 9 || strlen($emergency_contact) > 11) {
                $errors['emergency_contact'] = 'Emergency contact number must be between 9 and 11 digits';
                $isValid = false;
            } elseif (!preg_match('/^[0-9+]+$/', $emergency_contact)) {
                $errors['emergency_contact'] = 'Emergency contact number must contain only digits and possibly a + sign';
                $isValid = false;
            }
            
            if (!$isValid) {
                $feedback = 'Error: Please correct the errors in the form.';
            } else {
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                $checkStmt->bindParam(':email', $email, PDO::PARAM_STR);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    $feedback = 'Error: Email already exists.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (forename, surname, email, phone, address, emergency_contact, role, password) 
                                           VALUES (:forename, :surname, :email, :phone, :address, :emergency_contact, :role, :password)");

                    $stmt->bindParam(':forename', $forename, PDO::PARAM_STR);
                    $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);
                    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                    $stmt->bindParam(':address', $address, PDO::PARAM_STR);
                    $stmt->bindParam(':emergency_contact', $emergency_contact, PDO::PARAM_STR);
                    $stmt->bindParam(':role', $role, PDO::PARAM_STR);
                    $stmt->bindParam(':password', $password, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        $feedback = 'Admin user added successfully!';
                    } else {
                        $feedback = 'Error adding admin user.';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $id = $_POST['user_id'];
            $forename = $_POST['forename'];
            $surname = $_POST['surname'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $emergency_contact = $_POST['emergency_contact'];
            
            $isValid = true;
            
            if (strlen($phone) < 9 || strlen($phone) > 11) {
                $errors['phone'] = 'Phone number must be between 9 and 11 digits';
                $isValid = false;
            } elseif (!preg_match('/^[0-9+]+$/', $phone)) {
                $errors['phone'] = 'Phone number must contain only digits and possibly a + sign';
                $isValid = false;
            }
            
            if (strlen($emergency_contact) < 9 || strlen($emergency_contact) > 11) {
                $errors['emergency_contact'] = 'Emergency contact number must be between 9 and 11 digits';
                $isValid = false;
            } elseif (!preg_match('/^[0-9+]+$/', $emergency_contact)) {
                $errors['emergency_contact'] = 'Emergency contact number must contain only digits and possibly a + sign';
                $isValid = false;
            }
            
            if (!$isValid) {
                $feedback = 'Error: Please correct the errors in the form.';
            } else {
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :id");
                $checkStmt->bindParam(':email', $email, PDO::PARAM_STR);
                $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    $feedback = 'Error: Email already exists.';
                } else {
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET forename = :forename, surname = :surname, email = :email, 
                                               phone = :phone, address = :address, emergency_contact = :emergency_contact, 
                                               password = :password WHERE user_id = :id");
                        $stmt->bindParam(':password', $password, PDO::PARAM_STR);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET forename = :forename, surname = :surname, email = :email, 
                                               phone = :phone, address = :address, emergency_contact = :emergency_contact 
                                               WHERE user_id = :id");
                    }

                    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                    $stmt->bindParam(':forename', $forename, PDO::PARAM_STR);
                    $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);
                    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                    $stmt->bindParam(':address', $address, PDO::PARAM_STR);
                    $stmt->bindParam(':emergency_contact', $emergency_contact, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        $feedback = 'Admin user updated successfully!';
                    } else {
                        $feedback = 'Error updating admin user.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = $_POST['user_id'];

            // Prevent deletion of own account
            if ($id == $_SESSION['user_id']) {
                $feedback = 'Error: You cannot delete your own account.';
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = :id AND role = 'admin'");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $feedback = 'Admin user deleted successfully!';
                } else {
                    $feedback = 'Error deleting admin user.';
                }
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE role = 'admin' OR user_id = :current_user_id ORDER BY role DESC, surname ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':current_user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$adminData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' OR user_id = :current_user_id");
$totalRecordsQuery->bindValue(':current_user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$totalRecordsQuery->execute();
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

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
    <title>Manage Admin Users</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Manage Admin Users</h1>
            <?php if ($feedback): ?>
                <div class="admin-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Add Admin User Button -->
            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Admin User</button>
            </div>

            <!-- Add Admin User Form -->
            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Add New Admin User</h2>
                <form method="POST" action="admins.php">
                    <input type="hidden" name="action" value="add">

                    <label for="forename">First Name:</label>
                    <input type="text" id="forename" name="forename" required>

                    <label for="surname">Last Name:</label>
                    <input type="text" id="surname" name="surname" required>

                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>

                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" required minlength="9" maxlength="11" pattern="[0-9+]{9,11}">
                    <?php if (isset($errors['phone'])): ?>
                        <small class="error-text"><?php echo $errors['phone']; ?></small>
                    <?php endif; ?>

                    <label for="emergency_contact">Emergency Contact:</label>
                    <input type="tel" id="emergency_contact" name="emergency_contact" required minlength="9" maxlength="11" pattern="[0-9+]{9,11}">
                    <?php if (isset($errors['emergency_contact'])): ?>
                        <small class="error-text"><?php echo $errors['emergency_contact']; ?></small>
                    <?php endif; ?>

                    <label for="address">Address:</label>
                    <textarea id="address" name="address" required></textarea>

                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit" class="update-button">Add Admin User</button>
                </form>
            </div>

            <!-- Admin User List -->
            <h2>Admin User List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Role</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminData as $admin): ?>
                        <tr>
                            <td><?php echo $admin['user_id']; ?></td>
                            <td><?php echo ucfirst($admin['role']); ?></td>
                            <td><?php echo $admin['forename'] . ' ' . $admin['surname']; ?></td>
                            <td><?php echo $admin['email']; ?></td>
                            <td><?php echo $admin['phone']; ?></td>
                            <td>
                                <?php if ($admin['role'] === 'admin' || $admin['user_id'] === $_SESSION['user_id']): ?>
                                    <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $admin['user_id']; ?>')"
                                        class="update-button">Edit</button>

                                    <!-- Edit Form -->
                                    <div id='edit-form-<?php echo $admin['user_id']; ?>' class="edit-form">
                                        <button type="button" class="close-button"
                                            onclick="LuckyNest.toggleForm('edit-form-<?php echo $admin['user_id']; ?>')">✕</button>
                                        <form method="POST" action="admins.php" style="display:inline;">
                                            <h2>Edit Admin User</h2>
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="user_id" value="<?php echo $admin['user_id']; ?>">

                                            <label for="forename_<?php echo $admin['user_id']; ?>">First Name:</label>
                                            <input type="text" id="forename_<?php echo $admin['user_id']; ?>" name="forename"
                                                value="<?php echo $admin['forename']; ?>" required>

                                            <label for="surname_<?php echo $admin['user_id']; ?>">Last Name:</label>
                                            <input type="text" id="surname_<?php echo $admin['user_id']; ?>" name="surname"
                                                value="<?php echo $admin['surname']; ?>" required>

                                            <label for="email_<?php echo $admin['user_id']; ?>">Email:</label>
                                            <input type="email" id="email_<?php echo $admin['user_id']; ?>" name="email"
                                                value="<?php echo $admin['email']; ?>" required>

                                            <label for="phone_<?php echo $admin['user_id']; ?>">Phone Number:</label>
                                            <input type="tel" id="phone_<?php echo $admin['user_id']; ?>" name="phone"
                                                value="<?php echo $admin['phone']; ?>" required minlength="9" maxlength="11" pattern="[0-9+]{9,11}">
                                            <?php if (isset($errors['phone'])): ?>
                                                <small class="error-text"><?php echo $errors['phone']; ?></small>
                                            <?php endif; ?>

                                            <label for="emergency_contact_<?php echo $admin['user_id']; ?>">Emergency Contact:</label>
                                            <input type="tel" id="emergency_contact_<?php echo $admin['user_id']; ?>"
                                                name="emergency_contact" value="<?php echo $admin['emergency_contact']; ?>"
                                                required minlength="9" maxlength="11" pattern="[0-9+]{9,11}">
                                            <?php if (isset($errors['emergency_contact'])): ?>
                                                <small class="error-text"><?php echo $errors['emergency_contact']; ?></small>
                                            <?php endif; ?>

                                            <label for="address_<?php echo $admin['user_id']; ?>">Address:</label>
                                            <textarea id="address_<?php echo $admin['user_id']; ?>" name="address"
                                                required><?php echo $admin['address']; ?></textarea>

                                            <label for="password_<?php echo $admin['user_id']; ?>">New Password (leave blank to
                                                keep current):</label>
                                            <input type="password" id="password_<?php echo $admin['user_id']; ?>"
                                                name="password">

                                            <div class="button-group">
                                                <button type="submit" class="update-button">Update</button>
                                                <?php if ($admin['role'] === 'admin' && $admin['user_id'] !== $_SESSION['user_id']): ?>
                                                    <button type="button" class="button"
                                                        onclick="if(confirm('Are you sure you want to delete this admin user?')) document.getElementById('delete-form-<?php echo $admin['user_id']; ?>').submit(); return false;">Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>

                                        <?php if ($admin['role'] === 'admin' && $admin['user_id'] !== $_SESSION['user_id']): ?>
                                            <form id="delete-form-<?php echo $admin['user_id']; ?>" method="POST"
                                                action="admins.php" style="display:none;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $admin['user_id']; ?>">
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $url = 'admins.php';
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>
        </div>
        <div id="form-overlay"></div>
</body>

</html>