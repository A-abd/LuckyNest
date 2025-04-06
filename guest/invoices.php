<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: unauthorized.php');
    exit();
}

require __DIR__ . "/../include/db.php";

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("
        SELECT i.invoice_id, i.invoice_number, i.amount, i.filename, i.created_at, p.payment_type, b.booking_id, r.room_number
        FROM invoices i
        JOIN payments p ON i.payment_id = p.payment_id
        LEFT JOIN bookings b ON (p.reference_id = b.booking_id AND p.payment_type = 'rent')
        LEFT JOIN rooms r ON (b.room_id = r.room_id)
        WHERE i.user_id = :user_id
        ORDER BY i.created_at DESC
    ");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>My Invoices</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
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
        </div>
    </div>
</body>

</html>