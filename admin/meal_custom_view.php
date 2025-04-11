<?php
session_start();

if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'owner') {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$mealCustomData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'update_status') {
            $id = $_POST['meal_custom_id'];
            $status = $_POST['status'];
            $adminNotes = $_POST['admin_notes'];

            $stmt = $conn->prepare("UPDATE meal_custom SET status = :status, admin_notes = :adminNotes WHERE meal_custom_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':adminNotes', $adminNotes, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Custom meal request updated successfully!';
            } else {
                $feedback = 'Error updating custom meal request.';
            }
        } elseif ($action === 'delete') {
            $id = $_POST['meal_custom_id'];

            $stmt = $conn->prepare("DELETE FROM meal_custom WHERE meal_custom_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Custom meal request deleted successfully!';
            } else {
                $feedback = 'Error deleting custom meal request.';
            }
        }
    }
}

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$queryParams = [];
$queryConditions = [];

if (!empty($statusFilter)) {
    $queryConditions[] = "mc.status = :status";
    $queryParams[':status'] = $statusFilter;
}

if (!empty($search)) {
    $queryConditions[] = "(mc.meal_name LIKE :search OR u.forename LIKE :search OR u.surname LIKE :search)";
    $queryParams[':search'] = "%$search%";
}

$whereClause = !empty($queryConditions) ? "WHERE " . implode(" AND ", $queryConditions) : "";

$query = "SELECT mc.*, u.forename, u.surname 
          FROM meal_custom mc 
          JOIN users u ON mc.user_id = u.user_id 
          $whereClause
          ORDER BY mc.created_at DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);

foreach ($queryParams as $param => $value) {
    $stmt->bindValue($param, $value);
}

$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mealCustomData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "SELECT COUNT(*) AS total 
               FROM meal_custom mc 
               JOIN users u ON mc.user_id = u.user_id 
               $whereClause";

$countStmt = $conn->prepare($countQuery);
foreach ($queryParams as $param => $value) {
    $countStmt->bindValue($param, $value);
}
$countStmt->execute();
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$conn = null;
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
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Manage Custom Meal Requests</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Manage Custom Meal Requests</h1>
            <?php if ($feedback): ?>
                <div class="rooms-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="search-filter-container">
                <form method="GET" action="meal_custom_view.php" class="search-filter-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search by meal name or guest name"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-box">
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending
                            </option>
                            <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>
                                Approved</option>
                            <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>
                                Rejected</option>
                            <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>
                                Completed</option>
                        </select>
                    </div>
                    <button type="submit" class="update-button">Filter</button>
                    <a href="meal_custom_view.php" class="update-button">Reset</a>
                </form>
            </div>

            <h2>Custom Meal Requests</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Guest</th>
                        <th>Meal Name</th>
                        <th>Requested Date</th>
                        <th>Preferred Time</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mealCustomData as $mealCustom): ?>
                        <tr>
                            <td><?php echo $mealCustom['meal_custom_id']; ?></td>
                            <td><?php echo $mealCustom['forename'] . ' ' . $mealCustom['surname']; ?></td>
                            <td><?php echo $mealCustom['meal_name']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($mealCustom['requested_date'])); ?></td>
                            <td><?php echo $mealCustom['preferred_time']; ?></td>
                            <td><?php echo $mealCustom['status']; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($mealCustom['created_at'])); ?></td>
                            <td>
                                <button
                                    onclick="LuckyNest.toggleForm('view-form-<?php echo $mealCustom['meal_custom_id']; ?>')"
                                    class="update-button">View</button>

                                <button
                                    onclick="LuckyNest.toggleForm('status-form-<?php echo $mealCustom['meal_custom_id']; ?>')"
                                    class="update-button">Update</button>

                                <div id='view-form-<?php echo $mealCustom['meal_custom_id']; ?>'
                                    class="rooms-type-edit-form">
                                    <button type="button" class="close-button"
                                        onclick="LuckyNest.toggleForm('view-form-<?php echo $mealCustom['meal_custom_id']; ?>')">✕</button>
                                    <h2>View Custom Meal Request</h2>
                                    <div class="form-group">
                                        <strong>Guest:</strong>
                                        <p><?php echo $mealCustom['forename'] . ' ' . $mealCustom['surname']; ?></p>
                                    </div>
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
                                    <div class="form-group">
                                        <strong>Admin Notes:</strong>
                                        <p><?php echo $mealCustom['admin_notes'] ? $mealCustom['admin_notes'] : 'No notes'; ?>
                                        </p>
                                    </div>
                                    <div class="form-group">
                                        <strong>Submitted:</strong>
                                        <p><?php echo date('Y-m-d H:i', strtotime($mealCustom['created_at'])); ?></p>
                                    </div>
                                    <div class="rooms-button-group">
                                        <button type="button" class="update-button"
                                            onclick="LuckyNest.toggleForm('status-form-<?php echo $mealCustom['meal_custom_id']; ?>'); LuckyNest.toggleForm('view-form-<?php echo $mealCustom['meal_custom_id']; ?>')">Update
                                            Status</button>
                                        <button type="button" class="update-button"
                                            onclick="if(confirm('Are you sure you want to delete this request?')) document.getElementById('delete-form-<?php echo $mealCustom['meal_custom_id']; ?>').submit(); return false;">Delete</button>
                                    </div>

                                    <form id="delete-form-<?php echo $mealCustom['meal_custom_id']; ?>" method="POST"
                                        action="meal_custom_view.php" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="meal_custom_id"
                                            value="<?php echo $mealCustom['meal_custom_id']; ?>">
                                    </form>
                                </div>

                                <div id='status-form-<?php echo $mealCustom['meal_custom_id']; ?>'
                                    class="rooms-type-edit-form">
                                    <button type="button" class="close-button"
                                        onclick="LuckyNest.toggleForm('status-form-<?php echo $mealCustom['meal_custom_id']; ?>')">✕</button>
                                    <h2>Update Request Status</h2>
                                    <form method="POST" action="meal_custom_view.php">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="meal_custom_id"
                                            value="<?php echo $mealCustom['meal_custom_id']; ?>">
                                        <label for="status_<?php echo $mealCustom['meal_custom_id']; ?>">Status:</label>
                                        <select id="status_<?php echo $mealCustom['meal_custom_id']; ?>" name="status"
                                            required>
                                            <option value="Pending" <?php echo $mealCustom['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Approved" <?php echo $mealCustom['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="Rejected" <?php echo $mealCustom['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="Completed" <?php echo $mealCustom['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                        <label for="admin_notes_<?php echo $mealCustom['meal_custom_id']; ?>">Admin
                                            Notes:</label>
                                        <textarea id="admin_notes_<?php echo $mealCustom['meal_custom_id']; ?>"
                                            name="admin_notes"><?php echo $mealCustom['admin_notes']; ?></textarea>
                                        <div class="rooms-button-group">
                                            <button type="submit" class="update-button">Update Status</button>
                                            <button type="button" class="update-button"
                                                onclick="if(confirm('Are you sure you want to delete this request?')) document.getElementById('delete-form-status-<?php echo $mealCustom['meal_custom_id']; ?>').submit(); return false;">Delete</button>
                                        </div>
                                    </form>

                                    <form id="delete-form-status-<?php echo $mealCustom['meal_custom_id']; ?>" method="POST"
                                        action="meal_custom_view.php" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="meal_custom_id"
                                            value="<?php echo $mealCustom['meal_custom_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mealCustomData)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No custom meal requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            $url = 'meal_custom_view.php';
            if (!empty($statusFilter) || !empty($search)) {
                $url .= '?' . http_build_query(array_filter([
                    'status' => $statusFilter,
                    'search' => $search
                ]));
            }
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>
            <div class="back-button-container">
                <a href="dashboard.php" class="button">Back to Dashboard</a>
            </div>
        </div>
    </div>
    <div id="form-overlay"></div>
</body>

</html>