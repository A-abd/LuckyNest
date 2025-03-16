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
$roomData = [];
$roomTypeOptions = [];
$amenityOptions = [];

$recordsPerPage = 20;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$stmt = $conn->query("SELECT room_type_id, room_type_name FROM room_types");
$roomTypeOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT amenity_id, amenity_name FROM amenities");
$amenityOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // This if elseif elseif is the CRUD Logic
        if ($action === 'add') {
            $roomNumber = $_POST['room_number'];
            $roomTypeId = $_POST['room_type_id'];
            $status = $_POST['status'];
            $roomIsAvailable = isset($_POST['room_is_available']) ? 1 : 0;
            $amenities = $_POST['amenities'] ?? [];

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type_id, status, room_is_available) 
                                      VALUES (:roomNumber, :roomTypeId, :status, :roomIsAvailable)");
                $stmt->bindValue(':roomNumber', $roomNumber, PDO::PARAM_STR);
                $stmt->bindValue(':roomTypeId', $roomTypeId, PDO::PARAM_INT);
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
                $stmt->bindValue(':roomIsAvailable', $roomIsAvailable, PDO::PARAM_INT);
                $stmt->execute();

                $roomId = $conn->lastInsertId();

                foreach ($amenities as $amenityId) {
                    $stmt = $conn->prepare("INSERT INTO room_amenities (room_id, amenity_id) VALUES (:roomId, :amenityId)");
                    $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                    $stmt->bindValue(':amenityId', $amenityId, PDO::PARAM_INT);
                    $stmt->execute();
                }

                $conn->commit();
                $feedback = 'Room has been added successfully!';
            } catch (Exception $e) {
                $conn->rollBack();
                $feedback = 'Error adding room.';
            }
        } elseif ($action === 'edit') {
            $roomId = $_POST['room_id'];
            $roomNumber = $_POST['room_number'];
            $roomTypeId = $_POST['room_type_id'];
            $status = $_POST['status'];
            $roomIsAvailable = isset($_POST['room_is_available']) ? 1 : 0;
            $amenities = $_POST['amenities'] ?? [];

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("UPDATE rooms 
                                      SET room_number = :roomNumber, 
                                          room_type_id = :roomTypeId, 
                                          status = :status, 
                                          room_is_available = :roomIsAvailable 
                                      WHERE room_id = :roomId");
                $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->bindValue(':roomNumber', $roomNumber, PDO::PARAM_STR);
                $stmt->bindValue(':roomTypeId', $roomTypeId, PDO::PARAM_INT);
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
                $stmt->bindValue(':roomIsAvailable', $roomIsAvailable, PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $conn->prepare("DELETE FROM room_amenities WHERE room_id = :roomId");
                $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->execute();

                foreach ($amenities as $amenityId) {
                    $stmt = $conn->prepare("INSERT INTO room_amenities (room_id, amenity_id) VALUES (:roomId, :amenityId)");
                    $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                    $stmt->bindValue(':amenityId', $amenityId, PDO::PARAM_INT);
                    $stmt->execute();
                }

                $conn->commit();
                $feedback = 'Room updated successfully!';
            } catch (Exception $e) {
                $conn->rollBack();
                $feedback = 'Error updating room.';
            }
        } elseif ($action === 'delete') {
            $roomId = $_POST['room_id'];

            $stmt = $conn->prepare("DELETE FROM rooms WHERE room_id = :roomId");
            $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Room deleted successfully!';
            } else {
                $feedback = 'Error deleting room.';
            }
        }
    }
}

$stmt = $conn->query("SELECT r.room_id, r.room_number, r.room_is_available, r.status, rt.room_type_name 
                      FROM rooms r 
                      JOIN room_types rt ON r.room_type_id = rt.room_type_id
                      LIMIT $recordsPerPage OFFSET $offset");
$roomData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM rooms");
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Manage Rooms</title> 
</head>

<body>
    <div class="manage-default">
        <h1>Manage Rooms</h1>
        <?php if ($feedback): ?>
            <p style="color: green;"><?php echo $feedback; ?></p>
        <?php endif; ?>

        <button onclick="toggleAddForm()" class="update-button">Add Room</button>

        <div id="add-form">
            <h2>Add New Room</h2>
            <form method="POST" action="rooms.php">
                <input type="hidden" name="action" value="add">
                <label for="room_number">Room Number:</label>
                <input type="text" id="room_number" name="room_number" required>
                <label for="room_type_id">Room Type:</label>
                <select id="room_type_id" name="room_type_id" required>
                    <?php foreach ($roomTypeOptions as $roomType): ?>
                        <option value="<?php echo $roomType['room_type_id']; ?>">
                            <?php echo $roomType['room_type_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="Available">Available</option>
                    <option value="Occupied">Occupied</option>
                </select>
                <label for="room_is_available">
                    <input type="checkbox" id="room_is_available" name="room_is_available"> Available
                </label>
                <label>Amenities:</label>
                <?php foreach ($amenityOptions as $amenity): ?>
                    <label>
                        <input type="checkbox" name="amenities[]" value="<?php echo $amenity['amenity_id']; ?>">
                        <?php echo $amenity['amenity_name']; ?>
                    </label>
                <?php endforeach; ?>
                <button type="submit" class="update-button">Add Room</button>
            </form>
        </div>

        <h2>Room List</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>Room ID</th>
                    <th>Room Number</th>
                    <th>Room Type</th>
                    <th>Status</th>
                    <th>Amenities</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roomData as $room): ?>
                    <?php
                    $stmt = $conn->prepare("SELECT a.amenity_name 
                                          FROM room_amenities ra 
                                          JOIN amenities a ON ra.amenity_id = a.amenity_id 
                                          WHERE ra.room_id = :roomId");
                    $stmt->bindValue(':roomId', $room['room_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $roomAmenities = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <tr>
                        <td><?php echo $room['room_id']; ?></td>
                        <td><?php echo $room['room_number']; ?></td>
                        <td><?php echo $room['room_type_name']; ?></td>
                        <td><?php echo $room['status']; ?></td>
                        <td><?php echo implode(', ', $roomAmenities); ?></td>
                        <td>
                            <button onclick="toggleEditForm(<?php echo $room['room_id']; ?>)" class="update-button">Edit</button>
                            <div id="edit-form-<?php echo $room['room_id']; ?>" class="edit-form">
                                <form method="POST" action="rooms.php" style="display:inline;">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
                                    <label for="room_number_<?php echo $room['room_id']; ?>">Room Number:</label>
                                    <input type="text" id="room_number_<?php echo $room['room_id']; ?>" name="room_number" value="<?php echo $room['room_number']; ?>" required>
                                    <label for="room_type_id_<?php echo $room['room_id']; ?>">Room Type:</label>
                                    <select id="room_type_id_<?php echo $room['room_id']; ?>" name="room_type_id" required>
                                        <?php foreach ($roomTypeOptions as $roomType): ?>
                                            <option value="<?php echo $roomType['room_type_id']; ?>" 
                                                <?php echo $roomType['room_type_name'] === $room['room_type_name'] ? 'selected' : ''; ?>>
                                                <?php echo $roomType['room_type_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="status_<?php echo $room['room_id']; ?>">Status:</label>
                                    <select id="status_<?php echo $room['room_id']; ?>" name="status" required>
                                        <option value="Available" <?php echo $room['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="Occupied" <?php echo $room['status'] === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                                    </select>

                                    <?php foreach ($amenityOptions as $amenity): ?>
                                        <label>
                                            <input type="checkbox" name="amenities[]" value="<?php echo $amenity['amenity_id']; ?>"
                                                <?php echo in_array($amenity['amenity_id'], $roomAmenities) ? 'checked' : ''; ?>>
                                            <?php echo $amenity['amenity_name']; ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <button type="submit" class="update-button">Update</button>
                                </form>

                                <form method="POST" action="rooms.php" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
                                    <button type="submit" class="update-button" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $url = 'rooms.php';
        echo generatePagination($page, $totalPages, $url);
        ?>
        <br>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>
</body>

</html>