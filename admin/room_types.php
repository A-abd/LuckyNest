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
$roomTypeData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // This if elseif elseif is the CRUD Logic
        if ($action === 'add') {
            $roomTypeName = $_POST['room_type_name'];
            $rateMonthly = $_POST['rate_monthly'];
            $depositAmount = $_POST['deposit_amount'];

            $stmt = $conn->prepare("INSERT INTO room_types (room_type_name, rate_monthly, deposit_amount) VALUES (:roomTypeName, :rateMonthly, :depositAmount)");
            $stmt->bindParam(':roomTypeName', $roomTypeName, PDO::PARAM_STR);
            $stmt->bindParam(':rateMonthly', $rateMonthly, PDO::PARAM_STR);
            $stmt->bindParam(':depositAmount', $depositAmount, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Room type added successfully!';
            } else {
                $feedback = 'Error adding room type.';
            }
        } elseif ($action === 'edit') {
            $id = $_POST['room_type_id'];
            $roomTypeName = $_POST['room_type_name'];
            $rateMonthly = $_POST['rate_monthly'];
            $depositAmount = $_POST['deposit_amount'];

            $stmt = $conn->prepare("UPDATE room_types SET room_type_name = :roomTypeName, rate_monthly = :rateMonthly, deposit_amount = :depositAmount WHERE room_type_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':roomTypeName', $roomTypeName, PDO::PARAM_STR);
            $stmt->bindParam(':rateMonthly', $rateMonthly, PDO::PARAM_STR);
            $stmt->bindParam(':depositAmount', $depositAmount, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Room type updated successfully!';
            } else {
                $feedback = 'Error updating thee room type.';
            }
        } elseif ($action === 'delete') {
            $id = $_POST['room_type_id'];

            $stmt = $conn->prepare("DELETE FROM room_types WHERE room_type_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Room type deleted successfully!';
            } else {
                $feedback = 'Error deleting the room type.';
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM room_types LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$roomTypeData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM room_types");
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
    <title>Manage Room Types</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Manage Room Types</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Add Room Type Button -->
            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Room Type</button>
            </div>

            <!-- Add Room Type Form -->
            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Add New Room Type</h2>
                <form method="POST" action="room_types.php">
                    <input type="hidden" name="action" value="add">
                    <label for="room_type_name">Room Type Name:</label>
                    <input type="text" id="room_type_name" name="room_type_name" required>
                    <label for="rate_monthly">Monthly Rate:</label>
                    <input type="number" step="0.01" id="rate_monthly" name="rate_monthly" required>
                    <label for="deposit_amount">Security Deposit:</label>
                    <input type="number" step="0.01" id="deposit_amount" name="deposit_amount" required>
                    <button type="submit" class="update-button">Add Room Type</button>
                </form>
            </div>

            <!-- Room Type List -->
            <h2>Room Type List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Room Type Name</th>
                        <th>Monthly Rate</th>
                        <th>Security Deposit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roomTypeData as $roomType): ?>
                        <tr>
                            <td><?php echo $roomType['room_type_id']; ?></td>
                            <td><?php echo $roomType['room_type_name']; ?></td>
                            <td><?php echo $roomType['rate_monthly']; ?></td>
                            <td><?php echo $roomType['deposit_amount']; ?></td>
                            <td>
                                <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $roomType['room_type_id']; ?>')"
                                    class="update-button">Edit</button>
                                <!-- Edit Form -->
                                <div id='edit-form-<?php echo $roomType['room_type_id']; ?>' class="edit-form">
                                    <button type="button" class="close-button"
                                        onclick="LuckyNest.toggleForm('edit-form-<?php echo $roomType['room_type_id']; ?>')">✕</button>
                                    <form method="POST" action="room_types.php" style="display:inline;">
                                        <h2>Edit Room</h2>
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="room_type_id"
                                            value="<?php echo $roomType['room_type_id']; ?>">
                                        <label for="room_type_name_<?php echo $roomType['room_type_id']; ?>">Room Type
                                            Name:</label>
                                        <input type="text" id="room_type_name_<?php echo $roomType['room_type_id']; ?>"
                                            name="room_type_name" value="<?php echo $roomType['room_type_name']; ?>"
                                            required>
                                        <label for="rate_monthly_<?php echo $roomType['room_type_id']; ?>">Monthly
                                            Rate:</label>
                                        <input type="number" step="0.01"
                                            id="rate_monthly_<?php echo $roomType['room_type_id']; ?>" name="rate_monthly"
                                            value="<?php echo $roomType['rate_monthly']; ?>" required>
                                        <label for="deposit_amount_<?php echo $roomType['room_type_id']; ?>">Security
                                            Deposit:</label>
                                        <input type="number" step="0.01"
                                            id="deposit_amount_<?php echo $roomType['room_type_id']; ?>"
                                            name="deposit_amount" value="<?php echo $roomType['deposit_amount']; ?>"
                                            required>
                                        <div class="button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button"
                                                onclick="document.getElementById('delete-form-<?php echo $roomType['room_type_id']; ?>').submit(); return false;">Delete</button>
                                        </div>
                                    </form>

                                    <form id="delete-form-<?php echo $roomType['room_type_id']; ?>" method="POST"
                                        action="room_types.php" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="room_type_id"
                                            value="<?php echo $roomType['room_type_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $url = 'room_types.php';
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>

        </div>
        <div id="form-overlay"></div>
</body>

</html>