<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/unauthorized.php");
    exit();
}

$guest_id = $_SESSION['user_id'];

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$depositData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

try {
    // Get deposits for the current user
    $stmt = $conn->prepare("SELECT d.*, b.check_in_date, b.check_out_date, r.room_number, rt.room_type_name, rt.deposit_amount 
                           FROM deposits d 
                           JOIN bookings b ON d.booking_id = b.booking_id 
                           JOIN rooms r ON b.room_id = r.room_id 
                           JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                           WHERE b.guest_id = :guest_id 
                           ORDER BY d.deposit_id DESC 
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $depositData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM deposits d 
                           JOIN bookings b ON d.booking_id = b.booking_id 
                           WHERE b.guest_id = :guest_id");
    $stmt->bindValue(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Get unpaid deposits (for bookings that require deposits but don't have them yet)
    $stmt = $conn->prepare("SELECT b.booking_id, b.check_in_date, b.check_out_date, r.room_number, rt.room_type_name, rt.deposit_amount
                           FROM bookings b
                           JOIN rooms r ON b.room_id = r.room_id
                           JOIN room_types rt ON r.room_type_id = rt.room_type_id
                           LEFT JOIN deposits d ON b.booking_id = d.booking_id
                           WHERE b.guest_id = :guest_id
                           AND rt.deposit_amount > 0
                           AND d.deposit_id IS NULL
                           AND b.booking_is_cancelled = 0");
    $stmt->bindValue(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    $unpaidDeposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = "Database error: " . $e->getMessage();
}

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
    <title>My Security Deposits</title>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>My Security Deposits</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <?php if (count($unpaidDeposits) > 0): ?>
                <h2>Required Deposits</h2>
                <p>The following bookings require security deposits:</p>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Room Number</th>
                            <th>Room Type</th>
                            <th>Check-in Date</th>
                            <th>Check-out Date</th>
                            <th>Deposit Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unpaidDeposits as $deposit): ?>
                            <tr>
                                <td><?php echo $deposit['booking_id']; ?></td>
                                <td><?php echo $deposit['room_number']; ?></td>
                                <td><?php echo $deposit['room_type_name']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($deposit['check_in_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($deposit['check_out_date'])); ?></td>
                                <td>£<?php echo number_format($deposit['deposit_amount'], 2); ?></td>
                                <td>
                                    <a href="payments_page.php?deposit=1&booking_id=<?php echo $deposit['booking_id']; ?>"
                                        class="update-button">Pay Deposit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Deposit History</h2>
            <?php if (count($depositData) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Deposit ID</th>
                            <th>Booking ID</th>
                            <th>Room Number</th>
                            <th>Check-in Date</th>
                            <th>Check-out Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date Paid</th>
                            <th>Date Refunded</th>
                            <th>Refunded Amount</th>
                            <th>Reason (if withheld)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($depositData as $deposit): ?>
                            <tr>
                                <td><?php echo $deposit['deposit_id']; ?></td>
                                <td><?php echo $deposit['booking_id']; ?></td>
                                <td><?php echo $deposit['room_number']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($deposit['check_in_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($deposit['check_out_date'])); ?></td>
                                <td>£<?php echo number_format($deposit['amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch ($deposit['status']) {
                                        case 'paid':
                                            $statusClass = 'status-paid';
                                            break;
                                        case 'fully_refunded':
                                            $statusClass = 'status-refunded';
                                            break;
                                        case 'partially_refunded':
                                            $statusClass = 'status-partial';
                                            break;
                                        case 'withheld':
                                            $statusClass = 'status-withheld';
                                            break;
                                        default:
                                            $statusClass = 'status-pending';
                                    }
                                    echo '<span class="' . $statusClass . '">' . ucfirst(str_replace('_', ' ', $deposit['status'])) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo $deposit['date_paid'] ? date('d/m/Y', strtotime($deposit['date_paid'])) : 'N/A'; ?>
                                </td>
                                <td><?php echo $deposit['date_refunded'] ? date('d/m/Y', strtotime($deposit['date_refunded'])) : 'N/A'; ?>
                                </td>
                                <td><?php echo $deposit['refunded_amount'] ? '£' . number_format($deposit['refunded_amount'], 2) : 'N/A'; ?>
                                </td>
                                <td><?php echo $deposit['withholding_reason'] ? $deposit['withholding_reason'] : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $url = 'deposits.php';
                echo generatePagination($page, $totalPages, $url);
                ?>
            <?php else: ?>
                <p>You don't have any deposit history yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>