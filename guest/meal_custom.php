<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$mealCustomData = [];
$userId = $_SESSION['user_id'];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            $mealName = $_POST['meal_name'];
            $mealDescription = $_POST['meal_description'];
            $dietaryRequirements = $_POST['dietary_requirements'];
            $requestedDate = $_POST['requested_date'];
            $preferredTime = $_POST['preferred_time'];

            $stmt = $conn->prepare("INSERT INTO meal_custom (user_id, meal_name, meal_description, dietary_requirements, requested_date, preferred_time, status) VALUES (:userId, :mealName, :mealDescription, :dietaryRequirements, :requestedDate, :preferredTime, 'Pending')");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':mealName', $mealName, PDO::PARAM_STR);
            $stmt->bindParam(':mealDescription', $mealDescription, PDO::PARAM_STR);
            $stmt->bindParam(':dietaryRequirements', $dietaryRequirements, PDO::PARAM_STR);
            $stmt->bindParam(':requestedDate', $requestedDate, PDO::PARAM_STR);
            $stmt->bindParam(':preferredTime', $preferredTime, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Custom meal request submitted successfully!';
            } else {
                $feedback = 'Error submitting custom meal request.';
            }
        } elseif ($action === 'edit') {
            $id = $_POST['meal_custom_id'];
            $mealName = $_POST['meal_name'];
            $mealDescription = $_POST['meal_description'];
            $dietaryRequirements = $_POST['dietary_requirements'];
            $requestedDate = $_POST['requested_date'];
            $preferredTime = $_POST['preferred_time'];

            $stmt = $conn->prepare("UPDATE meal_custom SET meal_name = :mealName, meal_description = :mealDescription, dietary_requirements = :dietaryRequirements, requested_date = :requestedDate, preferred_time = :preferredTime WHERE meal_custom_id = :id AND user_id = :userId AND status = 'Pending'");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':mealName', $mealName, PDO::PARAM_STR);
            $stmt->bindParam(':mealDescription', $mealDescription, PDO::PARAM_STR);
            $stmt->bindParam(':dietaryRequirements', $dietaryRequirements, PDO::PARAM_STR);
            $stmt->bindParam(':requestedDate', $requestedDate, PDO::PARAM_STR);
            $stmt->bindParam(':preferredTime', $preferredTime, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Custom meal request updated successfully!';
            } else {
                $feedback = 'Error updating custom meal request.';
            }
        } elseif ($action === 'delete') {
            $id = $_POST['meal_custom_id'];

            $stmt = $conn->prepare("DELETE FROM meal_custom WHERE meal_custom_id = :id AND user_id = :userId AND status = 'Pending'");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Custom meal request deleted successfully!';
            } else {
                $feedback = 'Error deleting custom meal request.';
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM meal_custom WHERE user_id = :userId ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mealCustomData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM meal_custom WHERE user_id = :userId");
$totalRecordsStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
$totalRecordsStmt->execute();
$totalRecordsQuery = $totalRecordsStmt;

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
    <title>Custom Meal Requests</title>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Custom Meal Requests</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Request Custom
                    Meal</button>
            </div>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Request Custom Meal</h2>
                <form method="POST" action="meal_custom.php">
                    <input type="hidden" name="action" value="add">
                    <label for="meal_name">Meal Name:</label>
                    <input type="text" id="meal_name" name="meal_name" required>
                    <label for="meal_description">Meal Description:</label>
                    <textarea id="meal_description" name="meal_description" required></textarea>
                    <label for="dietary_requirements">Dietary Requirements (optional):</label>
                    <textarea id="dietary_requirements" name="dietary_requirements"></textarea>
                    <label for="requested_date">Requested Date:</label>
                    <input type="date" id="requested_date" name="requested_date" required
                        min="<?php echo date('Y-m-d'); ?>">
                    <label for="preferred_time">Preferred Time:</label>
                    <select id="preferred_time" name="preferred_time" required>
                        <option value="Breakfast">Breakfast</option>
                        <option value="Lunch">Lunch</option>
                        <option value="Dinner">Dinner</option>
                    </select>
                    <button type="submit" class="update-button">Submit Request</button>
                </form>
            </div>

            <h2>Your Meal Requests</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Meal Name</th>
                        <th>Requested Date</th>
                        <th>Preferred Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mealCustomData as $mealCustom): ?>
                        <tr>
                            <td><?php echo $mealCustom['meal_custom_id']; ?></td>
                            <td><?php echo $mealCustom['meal_name']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($mealCustom['requested_date'])); ?></td>
                            <td><?php echo $mealCustom['preferred_time']; ?></td>
                            <td><?php echo $mealCustom['status']; ?></td>
                            <td>
                                <button
                                    onclick="LuckyNest.toggleForm('view-form-<?php echo $mealCustom['meal_custom_id']; ?>')"
                                    class="update-button">View</button>

                                <?php if ($mealCustom['status'] === 'Pending'): ?>
                                    <button
                                        onclick="LuckyNest.toggleForm('edit-form-<?php echo $mealCustom['meal_custom_id']; ?>')"
                                        class="update-button">Edit</button>
                                <?php endif; ?>

                                <div id='view-form-<?php echo $mealCustom['meal_custom_id']; ?>' class="edit-form">
                                    <button type="button" class="close-button"
                                        onclick="LuckyNest.toggleForm('view-form-<?php echo $mealCustom['meal_custom_id']; ?>')">✕</button>
                                    <h2>View Custom Meal Request</h2>
                                    <div class="form-group">
                                        <strong>Meal Name:</strong>
                                        <p><?php echo $mealCustom['meal_name']; ?></p>
                                    </div>
                                    <div class="form-group">
                                        <strong>Description:</strong>
                                        <p><?php echo $mealCustom['meal_description']; ?></p>
                                    </div>
                                    <div class="form-group">
                                        <strong>Dietary Requirements:</strong>
                                        <p><?php echo $mealCustom['dietary_requirements'] ? $mealCustom['dietary_requirements'] : 'None specified'; ?>
                                        </p>
                                    </div>
                                    <div class="form-group">
                                        <strong>Requested Date:</strong>
                                        <p><?php echo date('Y-m-d', strtotime($mealCustom['requested_date'])); ?></p>
                                    </div>
                                    <div class="form-group">
                                        <strong>Preferred Time:</strong>
                                        <p><?php echo $mealCustom['preferred_time']; ?></p>
                                    </div>
                                    <div class="form-group">
                                        <strong>Status:</strong>
                                        <p><?php echo $mealCustom['status']; ?></p>
                                    </div>
                                    <?php if (!empty($mealCustom['admin_notes'])): ?>
                                        <div class="form-group">
                                            <strong>Admin Notes:</strong>
                                            <p><?php echo $mealCustom['admin_notes']; ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="form-group">
                                        <strong>Submitted:</strong>
                                        <p><?php echo date('Y-m-d H:i', strtotime($mealCustom['created_at'])); ?></p>
                                    </div>
                                    <?php if ($mealCustom['status'] === 'Pending'): ?>
                                        <form id="delete-form-<?php echo $mealCustom['meal_custom_id']; ?>" method="POST"
                                            action="meal_custom.php">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="meal_custom_id"
                                                value="<?php echo $mealCustom['meal_custom_id']; ?>">
                                            <button type="submit" class="update-button">Cancel Request</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <?php if ($mealCustom['status'] === 'Pending'): ?>
                                    <div id='edit-form-<?php echo $mealCustom['meal_custom_id']; ?>' class="edit-form">
                                        <button type="button" class="close-button"
                                            onclick="LuckyNest.toggleForm('edit-form-<?php echo $mealCustom['meal_custom_id']; ?>')">✕</button>
                                        <h2>Edit Custom Meal Request</h2>
                                        <form method="POST" action="meal_custom.php">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="meal_custom_id"
                                                value="<?php echo $mealCustom['meal_custom_id']; ?>">
                                            <label for="meal_name_<?php echo $mealCustom['meal_custom_id']; ?>">Meal
                                                Name:</label>
                                            <input type="text" id="meal_name_<?php echo $mealCustom['meal_custom_id']; ?>"
                                                name="meal_name" value="<?php echo $mealCustom['meal_name']; ?>" required>
                                            <label for="meal_description_<?php echo $mealCustom['meal_custom_id']; ?>">Meal
                                                Description:</label>
                                            <textarea id="meal_description_<?php echo $mealCustom['meal_custom_id']; ?>"
                                                name="meal_description"
                                                required><?php echo $mealCustom['meal_description']; ?></textarea>
                                            <label
                                                for="dietary_requirements_<?php echo $mealCustom['meal_custom_id']; ?>">Dietary
                                                Requirements:</label>
                                            <textarea id="dietary_requirements_<?php echo $mealCustom['meal_custom_id']; ?>"
                                                name="dietary_requirements"><?php echo $mealCustom['dietary_requirements']; ?></textarea>
                                            <label for="requested_date_<?php echo $mealCustom['meal_custom_id']; ?>">Requested
                                                Date:</label>
                                            <input type="date" id="requested_date_<?php echo $mealCustom['meal_custom_id']; ?>"
                                                name="requested_date"
                                                value="<?php echo date('Y-m-d', strtotime($mealCustom['requested_date'])); ?>"
                                                min="<?php echo date('Y-m-d'); ?>" required>
                                            <label for="preferred_time_<?php echo $mealCustom['meal_custom_id']; ?>">Preferred
                                                Time:</label>
                                            <select id="preferred_time_<?php echo $mealCustom['meal_custom_id']; ?>"
                                                name="preferred_time" required>
                                                <option value="Breakfast" <?php echo $mealCustom['preferred_time'] === 'Breakfast' ? 'selected' : ''; ?>>Breakfast</option>
                                                <option value="Lunch" <?php echo $mealCustom['preferred_time'] === 'Lunch' ? 'selected' : ''; ?>>Lunch</option>
                                                <option value="Dinner" <?php echo $mealCustom['preferred_time'] === 'Dinner' ? 'selected' : ''; ?>>Dinner</option>
                                            </select>
                                            <div class="button-group">
                                                <button type="submit" class="update-button">Update</button>
                                                <button type="button" class="update-button"
                                                    onclick="document.getElementById('delete-form-<?php echo $mealCustom['meal_custom_id']; ?>').submit(); return false;">Delete</button>
                                            </div>
                                        </form>

                                        <form id="delete-form-<?php echo $mealCustom['meal_custom_id']; ?>" method="POST"
                                            action="meal_custom.php" style="display:none;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="meal_custom_id"
                                                value="<?php echo $mealCustom['meal_custom_id']; ?>">
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mealCustomData)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No custom meal requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            $url = 'meal_custom.php';
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>
        </div>
    </div>
    <div id="form-overlay"></div>
</body>

</html>