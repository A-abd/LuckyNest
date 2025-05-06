<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$depositData = [];

if (isset($_GET['feedback'])) {
    $feedback = $_GET['feedback'];
}

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'edit') {
            $deposit_id = $_POST['deposit_id'];
            $status = $_POST['status'];
            
            if (in_array($status, ['partially_refunded', 'fully_refunded'])) {
                $refund_amount = $_POST['refunded_amount'];
                $withholding_reason = $_POST['withholding_reason'] ?? '';
                
                header('Location: ../include/refund');
                exit();
            } else {
                $refunded_amount = $_POST['refunded_amount'] ?? 0;
                $withholding_reason = $_POST['withholding_reason'] ?? '';
                
                $stmt = $conn->prepare("UPDATE deposits SET status = :status, refunded_amount = :refunded_amount, withholding_reason = :withholding_reason, date_refunded = CASE WHEN status IN ('partially_refunded', 'fully_refunded') THEN NOW() ELSE date_refunded END WHERE deposit_id = :deposit_id");
                $stmt->bindParam(':deposit_id', $deposit_id, PDO::PARAM_INT);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':refunded_amount', $refunded_amount, PDO::PARAM_STR);
                $stmt->bindParam(':withholding_reason', $withholding_reason, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $feedback = 'Deposit status updated successfully!';
                } else {
                    $feedback = 'Error updating deposit status.';
                }
            }
        }
    }
}

$query = "
    SELECT d.deposit_id, d.booking_id, d.amount, d.status, d.date_paid, d.date_refunded,
           d.refunded_amount, d.withholding_reason, b.check_in_date, b.check_out_date,
           u.forename, u.surname, u.email, r.room_number
    FROM deposits d
    JOIN bookings b ON d.booking_id = b.booking_id
    JOIN users u ON b.guest_id = u.user_id
    JOIN rooms r ON b.room_id = r.room_id
    ORDER BY d.deposit_id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$depositData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->query("SELECT COUNT(*) AS total FROM deposits");
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
    <title>Manage Deposits</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-4"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Manage Deposits</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Deposits List -->
            <h2>Deposits List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Guest</th>
                        <th>Room</th>
                        <th>Booking Dates</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date Paid</th>
                        <th>Date Refunded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($depositData as $deposit): ?>
                        <tr>
                            <td><?php echo $deposit['deposit_id']; ?></td>
                            <td><?php echo $deposit['forename'] . ' ' . $deposit['surname'] . '<br>(' . $deposit['email'] . ')'; ?></td>
                            <td><?php echo $deposit['room_number']; ?></td>
                            <td><?php echo $deposit['check_in_date'] . ' to ' . $deposit['check_out_date']; ?></td>
                            <td>£<?php echo number_format($deposit['amount'], 2); ?></td>
                            <td><span class="deposit-status status-<?php echo $deposit['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $deposit['status'])); ?></span></td>
                            <td><?php echo $deposit['date_paid'] ? date('Y-m-d', strtotime($deposit['date_paid'])) : '-'; ?></td>
                            <td><?php echo $deposit['date_refunded'] ? date('Y-m-d', strtotime($deposit['date_refunded'])) : '-'; ?></td>
                            <td>
                                <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $deposit['deposit_id']; ?>')"
                                    class="update-button">Manage</button>
                                <!-- Edit Form -->
                                <div id="edit-form-<?php echo $deposit['deposit_id']; ?>" class="edit-form">
                                    <button type="button" class="close-button"
                                        onclick="LuckyNest.toggleForm('edit-form-<?php echo $deposit['deposit_id']; ?>')">✕</button>
                                    
                                    <h2>Manage Deposit</h2>
                                    <p><strong>Guest:</strong> <?php echo $deposit['forename'] . ' ' . $deposit['surname']; ?></p>
                                    <p><strong>Room:</strong> <?php echo $deposit['room_number']; ?></p>
                                    <p><strong>Original Amount:</strong> £<?php echo number_format($deposit['amount'], 2); ?></p>
                                    
                                    <!-- Modified form action to use refund.php for refund actions -->
                                    <form method="POST" action="<?php echo in_array($deposit['status'], ['paid']) ? '../include/refund' : 'deposits'; ?>" id="form-<?php echo $deposit['deposit_id']; ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="deposit_id" value="<?php echo $deposit['deposit_id']; ?>">
                                        
                                        <label for="status_<?php echo $deposit['deposit_id']; ?>">Status:</label>
                                        <select id="status_<?php echo $deposit['deposit_id']; ?>" name="status" onchange="LuckyNest.updateDepositForm(this.value, <?php echo $deposit['deposit_id']; ?>, <?php echo $deposit['amount']; ?>)">
                                            <option value="pending" <?php echo $deposit['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="paid" <?php echo $deposit['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="partially_refunded" <?php echo $deposit['status'] == 'partially_refunded' ? 'selected' : ''; ?>>Partially Refunded</option>
                                            <option value="fully_refunded" <?php echo $deposit['status'] == 'fully_refunded' ? 'selected' : ''; ?>>Fully Refunded</option>
                                            <option value="withheld" <?php echo $deposit['status'] == 'withheld' ? 'selected' : ''; ?>>Withheld</option>
                                        </select>
                                        
                                        <div id="refund-fields-<?php echo $deposit['deposit_id']; ?>" <?php echo (!in_array($deposit['status'], ['partially_refunded', 'fully_refunded'])) ? 'style="display:none;"' : ''; ?>>
                                            <label for="refunded_amount_<?php echo $deposit['deposit_id']; ?>">Refunded Amount:</label>
                                            <input type="number" step="0.01" id="refunded_amount_<?php echo $deposit['deposit_id']; ?>" name="refund_amount" value="<?php echo $deposit['refunded_amount'] ?? 0; ?>" min="0" max="<?php echo $deposit['amount']; ?>">
                                        </div>
                                        
                                        <div id="withholding-fields-<?php echo $deposit['deposit_id']; ?>" <?php echo ($deposit['status'] != 'withheld' && $deposit['status'] != 'partially_refunded') ? 'style="display:none;"' : ''; ?>>
                                            <label for="withholding_reason_<?php echo $deposit['deposit_id']; ?>">Reason for Withholding:</label>
                                            <textarea id="withholding_reason_<?php echo $deposit['deposit_id']; ?>" name="withholding_reason" rows="3"><?php echo $deposit['withholding_reason']; ?></textarea>
                                        </div>
                                        
                                        <button type="submit" class="update-button">Update Deposit</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $url = 'deposits';
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>
        </div>
        <div id="form-overlay"></div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            LuckyNest.initDepositFormHandlers();
        });
    </script>
</body>

</html>