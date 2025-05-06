<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/unauthorized');
    exit();
}

require __DIR__ . "/../include/db.php";
include __DIR__ . '/../include/pagination.php';

$user_id = $_SESSION['user_id'];
$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

try {
    $stmt = $conn->prepare("
        SELECT i.invoice_id, i.invoice_number, i.amount, i.filename, i.created_at, p.payment_type, b.booking_id, r.room_number
        FROM invoices i
        JOIN payments p ON i.payment_id = p.payment_id
        LEFT JOIN bookings b ON (p.reference_id = b.booking_id AND (p.payment_type = 'rent' OR p.payment_type = 'deposit'))
        LEFT JOIN rooms r ON (b.room_id = r.room_id)
        WHERE i.user_id = :user_id
        ORDER BY i.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRecordsQuery = $conn->prepare("
        SELECT COUNT(*) As total 
        FROM invoices i 
        JOIN payments p ON i.payment_id = p.payment_id
        WHERE i.user_id = :user_id
    ");
    $totalRecordsQuery->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $totalRecordsQuery->execute();
    $totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$rent_invoices = array_filter($invoices, function ($invoice) {
    return $invoice['payment_type'] === 'rent';
});

$food_invoices = array_filter($invoices, function ($invoice) {
    return $invoice['payment_type'] === 'meal_plan';
});

$laundry_invoices = array_filter($invoices, function ($invoice) {
    return $invoice['payment_type'] === 'laundry';
});

$deposit_invoices = array_filter($invoices, function ($invoice) {
    return $invoice['payment_type'] === 'deposit';
});
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
    <title>My Invoices</title>
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Rent Payments</h1>
            <?php if (empty($rent_invoices)): ?>
                <p>No rent invoices found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Booking ID</th>
                            <th>Room Number</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rent_invoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['booking_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($invoice['room_number'] ?? 'N/A'); ?></td>
                                <td>£<?php echo number_format($invoice['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($invoice['created_at']); ?></td>
                                <td>
                                    <a href="../invoices/<?php echo htmlspecialchars($invoice['filename']); ?>"
                                        target="_blank">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h1>Security Deposit Payments</h1>
            <?php if (empty($deposit_invoices)): ?>
                <p>No security deposit invoices found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Booking ID</th>
                            <th>Room Number</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposit_invoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['booking_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($invoice['room_number'] ?? 'N/A'); ?></td>
                                <td>£<?php echo number_format($invoice['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($invoice['created_at']); ?></td>
                                <td>
                                    <a href="../invoices/<?php echo htmlspecialchars($invoice['filename']); ?>"
                                        target="_blank">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h1>Food Payments</h1>
            <?php if (empty($food_invoices)): ?>
                <p>No food invoices found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($food_invoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td>£<?php echo number_format($invoice['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($invoice['created_at']); ?></td>
                                <td>
                                    <a href="../invoices/<?php echo htmlspecialchars($invoice['filename']); ?>"
                                        target="_blank">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h1>Laundry Payments</h1>
            <?php if (empty($laundry_invoices)): ?>
                <p>No laundry invoices found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laundry_invoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td>£<?php echo number_format($invoice['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($invoice['created_at']); ?></td>
                                <td>
                                    <a href="../invoices/<?php echo htmlspecialchars($invoice['filename']); ?>"
                                        target="_blank">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php
            $url = 'invoices';
            echo generatePagination($page, $totalPages, $url);
            ?>

            <br>
        </div>
    </div>
</body>

</html>