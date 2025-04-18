<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$maintenanceData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$whereClause = '';
$params = [];

if (!empty($statusFilter)) {
    $whereClause = " WHERE status = :status";
    $params[':status'] = $statusFilter;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'update_status') {
            $id = $_POST['request_id'];
            $status = $_POST['status'];
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

            $stmt = $conn->prepare("UPDATE maintenance_requests SET status = :status, admin_notes = :notes WHERE request_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $feedback = 'Maintenance request updated successfully!';
            } else {
                $feedback = 'Error updating maintenance request.';
            }
        } elseif ($action === 'delete') {
            $id = $_POST['request_id'];

            $stmt = $conn->prepare("DELETE FROM maintenance_requests WHERE request_id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Maintenance request deleted successfully!';
            } else {
                $feedback = 'Error deleting maintenance request.';
            }
        }
    }
}

$query = "SELECT * FROM maintenance_requests" . $whereClause . " ORDER BY report_date DESC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($query);

$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

if (!empty($statusFilter)) {
    $stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
}

$stmt->execute();
$maintenanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "SELECT COUNT(*) AS total FROM maintenance_requests" . $whereClause;
$countStmt = $conn->prepare($countQuery);

if (!empty($statusFilter)) {
    $countStmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
}

$countStmt->execute();
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
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
    <title>View Maintenance Requests</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>View Maintenance Requests</h1>
            <?php if ($feedback): ?>
                <div class="maintenance-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="filter-section">
                <form method="GET" action="view_maintenance.php">
                    <label for="status-filter">Filter by Status:</label>
                    <select id="status-filter" name="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo ($statusFilter == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Progress" <?php echo ($statusFilter == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Completed" <?php echo ($statusFilter == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo ($statusFilter == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </form>
            </div>

            <h2>Maintenance Request List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Room</th>
                        <th>Description</th>
                        <th>Date Reported</th>
                        <th>Status</th>
                        <th>Guest Name</th>
                        <th>Guest Email</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($maintenanceData as $request): ?>
                        <tr>
                            <td><?php echo $request['request_id']; ?></td>
                            <td><?php echo $request['room_number']; ?></td>
                            <td><?php echo $request['description']; ?></td>
                            <td><?php echo $request['report_date']; ?></td>
                            <td><?php echo $request['status']; ?></td>
                            <td><?php echo $request['guest_name']; ?></td>  
                            <td><?php echo $request['guest_email']; ?></td>
                            <td><?php echo isset($request['admin_notes']) ? $request['admin_notes'] : ''; ?></td>
                            <td>
                                <button onclick="LuckyNest.toggleForm('update-form-<?php echo $request['request_id']; ?>')"
                                    class="update-button">Update</button>
                                <div id='update-form-<?php echo $request['request_id']; ?>'
                                    class="maintenance-edit-form">
                                    <button type="button" class="close-button"
                                        onclick="LuckyNest.toggleForm('update-form-<?php echo $request['request_id']; ?>')">✕</button>
                                    <form method="POST" action="view_maintenance.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>" style="display:inline;">
                                        <h2>Update Maintenance Request</h2>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="request_id"
                                            value="<?php echo $request['request_id']; ?>">
                                        
                                        <label for="status_<?php echo $request['request_id']; ?>">Status:</label>
                                        <select id="status_<?php echo $request['request_id']; ?>" name="status" required>
                                            <option value="Pending" <?php echo ($request['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="In Progress" <?php echo ($request['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="Completed" <?php echo ($request['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?php echo ($request['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        
                                        <label for="notes_<?php echo $request['request_id']; ?>">Admin Notes:</label>
                                        <textarea id="notes_<?php echo $request['request_id']; ?>" name="notes" rows="3"><?php echo isset($request['admin_notes']) ? $request['admin_notes'] : ''; ?></textarea>
                                        
                                        <div class="maintenance-button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button"
                                                onclick="if(confirm('Are you sure you want to delete this request?')) document.getElementById('delete-form-<?php echo $request['request_id']; ?>').submit(); return false;">Delete</button>
                                        </div>
                                    </form>

                                    <form id="delete-form-<?php echo $request['request_id']; ?>" method="POST"
                                        action="view_maintenance.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="request_id"
                                            value="<?php echo $request['request_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($maintenanceData)): ?>
                <p>No maintenance requests found.</p>
            <?php endif; ?>
            
            <?php
            $url = 'view_maintenance.php';
            if (!empty($statusFilter)) {
                $url .= '?status=' . urlencode($statusFilter);
            }
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>
            <div class="back-button-container">
                <a href="dashboard.php" class="button">Back to Dashboard</a>
            </div>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>