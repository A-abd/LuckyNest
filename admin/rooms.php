<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$roomData = [];
$roomTypeOptions = [];
$amenityOptions = [];
$locationOptions = ['North Wing', 'South Wing'];

$recordsPerPage = 20;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$stmt = $conn->query("SELECT room_type_id, room_type_name FROM room_types");
$roomTypeOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT amenity_id, amenity_name FROM amenities");
$amenityOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$uploadDir = __DIR__ . '/../assets/room_images/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // This if elseif elseif is the CRUD Logic
        if ($action === 'add') {
            $roomNumber = $_POST['room_number'];
            $roomTypeId = $_POST['room_type_id'];
            $status = $_POST['status'];
            $location = $_POST['location'];
            $roomIsAvailable = isset($_POST['room_is_available']) ? 1 : 0;
            $amenities = $_POST['amenities'] ?? [];

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type_id, status, room_is_available, location) 
                                      VALUES (:roomNumber, :roomTypeId, :status, :roomIsAvailable, :location)");
                $stmt->bindValue(':roomNumber', $roomNumber, PDO::PARAM_STR);
                $stmt->bindValue(':roomTypeId', $roomTypeId, PDO::PARAM_INT);
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
                $stmt->bindValue(':roomIsAvailable', $roomIsAvailable, PDO::PARAM_INT);
                $stmt->bindValue(':location', $location, PDO::PARAM_STR);
                $stmt->execute();

                $roomId = $conn->lastInsertId();

                foreach ($amenities as $amenityId) {
                    $stmt = $conn->prepare("INSERT INTO room_amenities (room_id, amenity_id) VALUES (:roomId, :amenityId)");
                    $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                    $stmt->bindValue(':amenityId', $amenityId, PDO::PARAM_INT);
                    $stmt->execute();
                }

                if (!empty($_FILES['room_images']['name'][0])) {
                    foreach ($_FILES['room_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['room_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileExtension = pathinfo($_FILES['room_images']['name'][$key], PATHINFO_EXTENSION);
                            $fileName = uniqid() . '.' . $fileExtension;
                            $targetPath = $uploadDir . $fileName;

                            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                            if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                                if (move_uploaded_file($_FILES['room_images']['tmp_name'][$key], $targetPath)) {
                                    $imagePath = 'assets/room_images/' . $fileName;
                                    
                                    $stmt = $conn->prepare("INSERT INTO room_images (room_id, image_path) VALUES (:roomId, :imagePath)");
                                    $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                                    $stmt->bindValue(':imagePath', $imagePath, PDO::PARAM_STR);
                                    $stmt->execute();
                                } else {
                                    $feedback = 'Error uploading one or more images.';
                                }
                            } else {
                                $feedback = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                            }
                        }
                    }
                }

                $conn->commit();
                $feedback = 'Room has been added successfully!';
            } catch (Exception $e) {
                $conn->rollBack();
                $feedback = 'Error adding room: ' . $e->getMessage();
            }
        } elseif ($action === 'edit') {
            $roomId = $_POST['room_id'];
            $roomNumber = $_POST['room_number'];
            $roomTypeId = $_POST['room_type_id'];
            $status = $_POST['status'];
            $location = $_POST['location'];
            $roomIsAvailable = isset($_POST['room_is_available']) ? 1 : 0;
            $amenities = $_POST['amenities'] ?? [];
            $deleteImages = isset($_POST['delete_images']) ? $_POST['delete_images'] : [];

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("UPDATE rooms 
                                      SET room_number = :roomNumber, 
                                          room_type_id = :roomTypeId, 
                                          status = :status, 
                                          location = :location,
                                          room_is_available = :roomIsAvailable 
                                      WHERE room_id = :roomId");
                $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->bindValue(':roomNumber', $roomNumber, PDO::PARAM_STR);
                $stmt->bindValue(':roomTypeId', $roomTypeId, PDO::PARAM_INT);
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
                $stmt->bindValue(':location', $location, PDO::PARAM_STR);
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

                if (!empty($deleteImages)) {
                    foreach ($deleteImages as $imageId) {
                        $stmt = $conn->prepare("SELECT image_path FROM room_images WHERE image_id = :imageId");
                        $stmt->bindValue(':imageId', $imageId, PDO::PARAM_INT);
                        $stmt->execute();
                        $imagePath = $stmt->fetchColumn();

                        $stmt = $conn->prepare("DELETE FROM room_images WHERE image_id = :imageId");
                        $stmt->bindValue(':imageId', $imageId, PDO::PARAM_INT);
                        $stmt->execute();

                        if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
                            unlink(__DIR__ . '/../' . $imagePath);
                        }
                    }
                }

                if (!empty($_FILES['room_images']['name'][0])) {
                    foreach ($_FILES['room_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['room_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileExtension = pathinfo($_FILES['room_images']['name'][$key], PATHINFO_EXTENSION);
                            $fileName = uniqid() . '.' . $fileExtension;
                            $targetPath = $uploadDir . $fileName;

                            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                            if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                                if (move_uploaded_file($_FILES['room_images']['tmp_name'][$key], $targetPath)) {
                                    $imagePath = 'assets/room_images/' . $fileName;
                                    
                                    $stmt = $conn->prepare("INSERT INTO room_images (room_id, image_path) VALUES (:roomId, :imagePath)");
                                    $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                                    $stmt->bindValue(':imagePath', $imagePath, PDO::PARAM_STR);
                                    $stmt->execute();
                                } else {
                                    $feedback = 'Error uploading one or more images.';
                                }
                            } else {
                                $feedback = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                            }
                        }
                    }
                }

                $conn->commit();
                $feedback = 'Room updated successfully!';
            } catch (Exception $e) {
                $conn->rollBack();
                $feedback = 'Error updating room: ' . $e->getMessage();
            }
        } elseif ($action === 'delete') {
            $roomId = $_POST['room_id'];

            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("SELECT image_path FROM room_images WHERE room_id = :roomId");
                $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->execute();
                $imagePaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($imagePaths as $imagePath) {
                    if (file_exists(__DIR__ . '/../' . $imagePath)) {
                        unlink(__DIR__ . '/../' . $imagePath);
                    }
                }

                $stmt = $conn->prepare("DELETE FROM room_images WHERE room_id = :roomId");
                $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $conn->prepare("DELETE FROM room_amenities WHERE room_id = :roomId");
                $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $conn->prepare("DELETE FROM rooms WHERE room_id = :roomId");
                $stmt->bindValue(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->execute();

                $conn->commit();
                $feedback = 'Room deleted successfully!';
            } catch (Exception $e) {
                $conn->rollBack();
                $feedback = 'Error deleting room: ' . $e->getMessage();
            }
        }
    }
}

$stmt = $conn->prepare("SELECT r.room_id, r.room_number, r.room_is_available, r.status, r.location, rt.room_type_name 
                      FROM rooms r 
                      JOIN room_types rt ON r.room_type_id = rt.room_type_id
                      LIMIT ?, ?");
$stmt->bindValue(1, $offset, PDO::PARAM_INT);
$stmt->bindValue(2, $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap" />
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Manage Rooms</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer"></div>
    <h1><a href="../admin/dashboard.php" class="title">LuckyNest</a></h1>
    
    <div class="content-container">
        <h1 class="rooms-title">Manage Rooms</h1>
        
        <?php if ($feedback): ?>
            <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
        <?php endif; ?>

        <!-- Add Room Button -->
        <div class="button-center">
            <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Room</button>
        </div>

        <!-- Add Room Form -->
        <div id="add-form" class="add-form">
            <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
            <h2>Add New Room</h2>
            <form method="POST" action="rooms.php" enctype="multipart/form-data">
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
                
                <label for="location">Location:</label>
                <select id="location" name="location" required>
                    <?php foreach ($locationOptions as $location): ?>
                        <option value="<?php echo $location; ?>"><?php echo $location; ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="Available">Available</option>
                    <option value="Occupied">Occupied</option>
                </select>
                
                <div class="checkbox-container">
                    <input type="checkbox" id="room_is_available" name="room_is_available"> 
                    <label for="room_is_available">Available</label>
                </div>
                
                <label for="room_images">Room Images:</label>
                <input type="file" id="room_images" name="room_images[]" accept="image/*" multiple>
                <small>You can select multiple images (JPG, JPEG, PNG, GIF)</small>
                
                <label>Amenities:</label>
                <?php foreach ($amenityOptions as $amenity): ?>
                    <div class="checkbox-container">
                        <input type="checkbox" id="amenity_<?php echo $amenity['amenity_id']; ?>" 
                               name="amenities[]" 
                               value="<?php echo $amenity['amenity_id']; ?>">
                        <label for="amenity_<?php echo $amenity['amenity_id']; ?>">
                            <?php echo $amenity['amenity_name']; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="update-button">Add Room</button>
            </form>
        </div>

        <h2 class="rooms-title">Room List</h2>
        <div class="rooms-table-container">
            <table class="rooms-table">
                <thead>
                    <tr>
                        <th>Room ID</th>
                        <th>Room Number</th>
                        <th>Room Type</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Images</th>
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
                        
                        $stmt = $conn->prepare("SELECT image_id, image_path FROM room_images WHERE room_id = :roomId");
                        $stmt->bindValue(':roomId', $room['room_id'], PDO::PARAM_INT);
                        $stmt->execute();
                        $roomImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <tr>
                            <td><?php echo $room['room_id']; ?></td>
                            <td><?php echo $room['room_number']; ?></td>
                            <td><?php echo $room['room_type_name']; ?></td>
                            <td><?php echo $room['location']; ?></td>
                            <td><?php echo $room['status']; ?></td>
                            <td>
                                <?php if (count($roomImages) > 0): ?>
                                    <div>
                                        <?php foreach ($roomImages as $index => $image): ?>
                                            <?php if ($index < 1): ?>
                                                <img src="../<?php echo $image['image_path']; ?>" alt="Room image" style="max-width: 100px; max-height: 100px;">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php if (count($roomImages) > 1): ?>
                                            <div>(+<?php echo count($roomImages) - 1; ?> more)</div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    No images
                                <?php endif; ?>
                            </td>
                            <td><?php echo implode(', ', $roomAmenities); ?></td>
                            <td>
                                <button onclick="LuckyNest.toggleForm('edit-room-form-<?php echo $room['room_id']; ?>')" class="update-button">Edit</button>
                                <div id="edit-room-form-<?php echo $room['room_id']; ?>" class="edit-form">
                                    <button type="button" class="close-button" onclick="LuckyNest.toggleForm('edit-room-form-<?php echo $room['room_id']; ?>')">✕</button>
                                    <form method="POST" action="rooms.php" enctype="multipart/form-data">
                                        <h2>Edit Room</h2>
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
                                        
                                        <label for="location_<?php echo $room['room_id']; ?>">Location:</label>
                                        <select id="location_<?php echo $room['room_id']; ?>" name="location" required>
                                            <?php foreach ($locationOptions as $location): ?>
                                                <option value="<?php echo $location; ?>" 
                                                    <?php echo $location === $room['location'] ? 'selected' : ''; ?>>
                                                    <?php echo $location; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <label for="status_<?php echo $room['room_id']; ?>">Status:</label>
                                        <select id="status_<?php echo $room['room_id']; ?>" name="status" required>
                                            <option value="Available" <?php echo $room['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="Occupied" <?php echo $room['status'] === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                                        </select>
                                        
                                        <label for="room_is_available_<?php echo $room['room_id']; ?>">
                                            <input type="checkbox" id="room_is_available_<?php echo $room['room_id']; ?>" 
                                                name="room_is_available" <?php echo $room['room_is_available'] ? 'checked' : ''; ?>> Available
                                        </label>
                                        
                                        <?php if (count($roomImages) > 0): ?>
                                            <label>Current Images:</label>
                                            <div>
                                                <?php foreach ($roomImages as $image): ?>
                                                    <div style="margin-bottom: 10px;">
                                                        <img src="../<?php echo $image['image_path']; ?>" alt="Room image" style="max-width: 100px; max-height: 100px; display: block;">
                                                        <div>
                                                            <input type="checkbox" id="delete_image_<?php echo $image['image_id']; ?>" 
                                                                name="delete_images[]" value="<?php echo $image['image_id']; ?>">
                                                            <label for="delete_image_<?php echo $image['image_id']; ?>">Delete this image</label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <label for="room_images_<?php echo $room['room_id']; ?>">Add New Images:</label>
                                        <input type="file" id="room_images_<?php echo $room['room_id']; ?>" name="room_images[]" accept="image/*" multiple>
                                        <small>You can select multiple images (JPG, JPEG, PNG, GIF)</small>
                                        
                                        <label>Amenities:</label>
                                        <?php foreach ($amenityOptions as $amenity): ?>
                                            <div class="checkbox-container">
                                                <input type="checkbox" 
                                                       id="amenity_<?php echo $room['room_id'] . '_' . $amenity['amenity_id']; ?>" 
                                                       name="amenities[]" 
                                                       value="<?php echo $amenity['amenity_id']; ?>"
                                                       <?php echo in_array($amenity['amenity_name'], $roomAmenities) ? 'checked' : ''; ?>>
                                                <label for="amenity_<?php echo $room['room_id'] . '_' . $amenity['amenity_id']; ?>">
                                                    <?php echo $amenity['amenity_name']; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button" onclick="document.getElementById('delete-form-<?php echo $room['room_id']; ?>').submit(); return false;">Delete</button>
                                        </div>
                                    </form>
                                    
                                    <form id="delete-form-<?php echo $room['room_id']; ?>" method="POST" action="rooms.php" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>  
        <?php
        $url = 'rooms.php';
        echo generatePagination($page, $totalPages, $url);
        ?>

    </div>

    <div id="form-overlay"></div>

    
</body>

</html>