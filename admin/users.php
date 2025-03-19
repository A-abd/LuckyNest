<?php
// TODO Add the following:
// 1. A search function
// 2. A filter function

session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$userData = [];

$recordsPerPage = 15;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // This if elseif elseif is the CRUD Logic
        if ($action === 'edit') {
            if (isset($_POST['user_id'], $_POST['forename'], $_POST['surname'], $_POST['email'], $_POST['phone'], $_POST['emergency_contact'], $_POST['address'], $_POST['role'])) {
                $id = $_POST['user_id'];
                $forename = $_POST['forename'];
                $surname = $_POST['surname'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $emergencyContact = $_POST['emergency_contact'];
                $address = $_POST['address'];
                $role = $_POST['role'];

                $stmt = $conn->prepare("UPDATE users SET forename = :forename, surname = :surname, email = :email, phone = :phone, emergency_contact = :emergencyContact, address = :address, role = :role WHERE user_id = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->bindValue(':forename', $forename, PDO::PARAM_STR);
                $stmt->bindValue(':surname', $surname, PDO::PARAM_STR);
                $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindValue(':emergencyContact', $emergencyContact, PDO::PARAM_STR);
                $stmt->bindValue(':address', $address, PDO::PARAM_STR);
                $stmt->bindValue(':role', $role, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $feedback = 'User updated successfully!';
                } else {
                    $feedback = 'Error updating user.';
                }
            }
        } elseif ($action === 'delete') {
            if (isset($_POST['user_id'])) {
                $id = $_POST['user_id'];

                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $feedback = 'User deleted successfully!';
                } else {
                    $feedback = 'Error deleting user.';
                }
            } else {
                $feedback = 'Missing user ID for deletion.';
            }
        }
    }
}

$stmt = $conn->query("SELECT * FROM users LIMIT $recordsPerPage OFFSET $offset");
$userData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM users");
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$conn = null;
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Manage Users</title>
</head>

<body>
    <div class="manage-default">
        <h1>Manage Users</h1>
        <?php if ($feedback): ?>
            <p style="color: green;" class="feedback-message"><?php echo $feedback; ?></p>
        <?php endif; ?>

        <h3>User List</h3>
        <table border="1">
            <thead class="table-columns">
                <tr>
                    <th>ID</th>
                    <th>Forename</th>
                    <th>Surname</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Emergency Contact</th>
                    <th>Address</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userData as $user): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo $user['forename']; ?></td>
                        <td><?php echo $user['surname']; ?></td>
                        <td><?php echo $user['email']; ?></td>
                        <td><?php echo $user['phone']; ?></td>
                        <td><?php echo $user['emergency_contact']; ?></td>
                        <td><?php echo $user['address']; ?></td>
                        <td><?php echo ucfirst($user['role']); ?></td>
                        <td>
                            <button onclick="toggleEditForm(<?php echo $user['user_id']; ?>)"
                                class="update-button">Edit</button>
                            <!-- Edit Form -->
                            <div id="edit-form-<?php echo $user['user_id']; ?>" class="edit-form">
                                <form method="POST" action="users.php" style="display:inline;">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <label for="forename_<?php echo $user['user_id']; ?>">Forename:</label>
                                    <input type="text" id="forename_<?php echo $user['user_id']; ?>" name="forename"
                                        value="<?php echo $user['forename']; ?>" required>
                                    <label for="surname_<?php echo $user['user_id']; ?>">Surname:</label>
                                    <input type="text" id="surname_<?php echo $user['user_id']; ?>" name="surname"
                                        value="<?php echo $user['surname']; ?>" required>
                                    <label for="email_<?php echo $user['user_id']; ?>">Email:</label>
                                    <input type="email" id="email_<?php echo $user['user_id']; ?>" name="email"
                                        value="<?php echo $user['email']; ?>" required>
                                    <label for="phone_<?php echo $user['user_id']; ?>">Phone:</label>
                                    <input type="text" id="phone_<?php echo $user['user_id']; ?>" name="phone"
                                        value="<?php echo $user['phone']; ?>" required>
                                    <label for="emergency_contact_<?php echo $user['user_id']; ?>">Emergency
                                        Contact:</label>
                                    <input type="text" id="emergency_contact_<?php echo $user['user_id']; ?>"
                                        name="emergency_contact" value="<?php echo $user['emergency_contact']; ?>" required>
                                    <label for="address_<?php echo $user['user_id']; ?>">Address:</label>
                                    <input type="text" id="address_<?php echo $user['user_id']; ?>" name="address"
                                        value="<?php echo $user['address']; ?>" required>
                                    <label for="role_<?php echo $user['user_id']; ?>">Role:</label>
                                    <select id="role_<?php echo $user['user_id']; ?>" name="role" required>
                                        <option value="guest" <?php echo $user['role'] === 'guest' ? 'selected' : ''; ?>>Guest
                                        </option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin
                                        </option>
                                        <option value="owner" <?php echo $user['role'] === 'owner' ? 'selected' : ''; ?>>Owner
                                        </option>
                                    </select>
                                    <button type="submit" class="update-button">Update</button>
                                </form>

                                <!-- Delete Form -->
                                <form method="POST" action="users.php" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="update-button"
                                        onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $url = 'users.php';
        echo generatePagination($page, $totalPages, $url);
        ?>

        <br>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>
</body>

</html>