<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$visitorData = [];

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
            $visit_time = $_POST['visit_time'];
            $leave_time = $_POST['leave_time'];

            $stmt = $conn->prepare("INSERT INTO visitors (forename, surname, email, phone, visit_time, leave_time) VALUES (:forename, :surname, :email, :phone, :visit_time, :leave_time)");
            $stmt->bindParam(':forename', $forename, PDO::PARAM_STR);
            $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':visit_time', $visit_time, PDO::PARAM_STR);
            $stmt->bindParam(':leave_time', $leave_time, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Visitor added successfully!';
            } else {
                $feedback = 'Error adding visitor.';
            }
        } elseif ($action === 'edit') {
            $visitor_id = $_POST['visitor_id'];
            $forename = $_POST['forename'];
            $surname = $_POST['surname'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $visit_time = $_POST['visit_time'];
            $leave_time = $_POST['leave_time'];

            $stmt = $conn->prepare("UPDATE visitors SET forename = :forename, surname = :surname, email = :email, phone = :phone, visit_time = :visit_time, leave_time = :leave_time WHERE visitor_id = :visitor_id");
            $stmt->bindParam(':visitor_id', $visitor_id, PDO::PARAM_INT);
            $stmt->bindParam(':forename', $forename, PDO::PARAM_STR);
            $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':visit_time', $visit_time, PDO::PARAM_STR);
            $stmt->bindParam(':leave_time', $leave_time, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Visitor updated successfully!';
            } else {
                $feedback = 'Error updating the visitor.';
            }
        } elseif ($action === 'delete') {
            $visitor_id = $_POST['visitor_id'];

            $stmt = $conn->prepare("DELETE FROM visitors WHERE visitor_id = :visitor_id");
            $stmt->bindParam(':visitor_id', $visitor_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Visitor deleted successfully!';
            } else {
                $feedback = 'Error deleting the visitor.';
            }
        }
    }
}

$result = $conn->query("SELECT * FROM visitors LIMIT $recordsPerPage OFFSET $offset");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $visitorData[] = $row;
}

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM visitors");
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
    <title>Manage Visitors</title>
</head>

<body>
    <div class="manage-default">
        <h1>Manage Visitors</h1>
        <?php if ($feedback): ?>
            <p style="color: green;"><?php echo $feedback; ?></p>
        <?php endif; ?>

        <button onclick="toggleAddForm()" class="update-button">Add Visitor</button>

        <div id="add-form">
            <h2>Add New Visitor</h2>
            <form method="POST" action="visitors.php">
                <input type="hidden" name="action" value="add">
                <label for="forename">Forename:</label>
                <input type="text" id="forename" name="forename" required>
                <label for="surname">Surname:</label>
                <input type="text" id="surname" name="surname" required>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                <label for="phone">Phone:</label>
                <input type="text" id="phone" name="phone" required>
                <label for="visit_time">Visit Time:</label>
                <input type="datetime-local" id="visit_time" name="visit_time" required>
                <label for="leave_time">Leave Time:</label>
                <input type="datetime-local" id="leave_time" name="leave_time" required>
                <button type="submit" class="update-button">Add Visitor</button>
            </form>
        </div>

        <h2>Visitor List</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Forename</th>
                    <th>Surname</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Visit Time</th>
                    <th>Leave Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visitorData as $visitor): ?>
                    <tr>
                        <td><?php echo $visitor['visitor_id']; ?></td>
                        <td><?php echo $visitor['forename']; ?></td>
                        <td><?php echo $visitor['surname']; ?></td>
                        <td><?php echo $visitor['email']; ?></td>
                        <td><?php echo $visitor['phone']; ?></td>
                        <td><?php echo $visitor['visit_time']; ?></td>
                        <td><?php echo $visitor['leave_time']; ?></td>
                        <td>
                            <button onclick="toggleEditForm(<?php echo $visitor['visitor_id']; ?>)"
                                class="update-button">Edit</button>
                            <div id="edit-form-<?php echo $visitor['visitor_id']; ?>" class="edit-form">
                                <form method="POST" action="visitors.php" style="display:inline;">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="visitor_id" value="<?php echo $visitor['visitor_id']; ?>">
                                    <label for="forename_<?php echo $visitor['visitor_id']; ?>">Forename:</label>
                                    <input type="text" id="forename_<?php echo $visitor['visitor_id']; ?>" name="forename"
                                        value="<?php echo $visitor['forename']; ?>" required>
                                    <label for="surname_<?php echo $visitor['visitor_id']; ?>">Surname:</label>
                                    <input type="text" id="surname_<?php echo $visitor['visitor_id']; ?>" name="surname"
                                        value="<?php echo $visitor['surname']; ?>" required>
                                    <label for="email_<?php echo $visitor['visitor_id']; ?>">Email:</label>
                                    <input type="email" id="email_<?php echo $visitor['visitor_id']; ?>" name="email"
                                        value="<?php echo $visitor['email']; ?>" required>
                                    <label for="phone_<?php echo $visitor['visitor_id']; ?>">Phone:</label>
                                    <input type="text" id="phone_<?php echo $visitor['visitor_id']; ?>" name="phone"
                                        value="<?php echo $visitor['phone']; ?>" required>
                                    <label for="visit_time_<?php echo $visitor['visitor_id']; ?>">Visit Time:</label>
                                    <input type="datetime-local" id="visit_time_<?php echo $visitor['visitor_id']; ?>"
                                        name="visit_time"
                                        value="<?php echo date('Y-m-d\TH:i', strtotime($visitor['visit_time'])); ?>"
                                        required>
                                    <label for="leave_time_<?php echo $visitor['visitor_id']; ?>">Leave Time:</label>
                                    <input type="datetime-local" id="leave_time_<?php echo $visitor['visitor_id']; ?>"
                                        name="leave_time"
                                        value="<?php echo date('Y-m-d\TH:i', strtotime($visitor['leave_time'])); ?>"
                                        required>
                                    <button type="submit" class="update-button">Update</button>
                                </form>

                                <form method="POST" action="visitors.php" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="visitor_id" value="<?php echo $visitor['visitor_id']; ?>">
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
        $url = 'visitors.php';
        echo generatePagination($page, $totalPages, $url);
        ?>

        <br>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>
</body>

</html>