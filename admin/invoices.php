<?php
session_start();

if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'owner') {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$invoices = [];
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$users = [];

try {
    $stmt = $conn->prepare("SELECT user_id, forename, surname, email FROM users WHERE role != 'admin' AND role != 'owner' ORDER BY surname, forename");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = "Database error: " . $e->getMessage();
}

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

if ($user_id) {
    try {
        $query = "
            SELECT i.invoice_id, i.invoice_number, i.amount, i.filename, i.created_at, 
                   p.payment_type, p.reference_id, u.forename, u.surname, u.email,
                   b.booking_id, r.room_number
            FROM invoices i
            JOIN payments p ON i.payment_id = p.payment_id
            JOIN users u ON i.user_id = u.user_id
            LEFT JOIN bookings b ON (p.reference_id = b.booking_id AND (p.payment_type = 'rent' OR p.payment_type = 'deposit'))
            LEFT JOIN rooms r ON (b.room_id = r.room_id)
            WHERE i.user_id = :user_id
        ";

        $countQuery = "
            SELECT COUNT(*) as total
            FROM invoices i
            JOIN payments p ON i.payment_id = p.payment_id
            WHERE i.user_id = :user_id
        ";

        $params = [':user_id' => $user_id];

        if (!empty($payment_type)) {
            $query .= " AND p.payment_type = :payment_type";
            $countQuery .= " AND p.payment_type = :payment_type";
            $params[':payment_type'] = $payment_type;
        }

        if (!empty($start_date)) {
            $query .= " AND i.created_at >= :start_date";
            $countQuery .= " AND i.created_at >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }

        if (!empty($end_date)) {
            $query .= " AND i.created_at <= :end_date";
            $countQuery .= " AND i.created_at <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }

        $query .= " ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $conn->prepare($countQuery);
        foreach ($params as $key => $value) {
            if ($key != ':limit' && $key != ':offset') {
                $countStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);
    } catch (PDOException $e) {
        $feedback = "Database error: " . $e->getMessage();
    }
}

function formatDate($dateString)
{
    $date = new DateTime($dateString);
    return $date->format('d/m/Y');
}

function getPaymentTypeLabel($type)
{
    switch ($type) {
        case 'rent':
            return 'Rent Payment';
        case 'deposit':
            return 'Security Deposit';
        case 'meal_plan':
            return 'Meal Plan';
        case 'laundry':
            return 'Laundry Service';
        case 'deposit_refund':
            return 'Deposit Refund';
        default:
            return ucfirst($type);
    }
}

function getPaymentTypes()
{
    return [
        'rent' => 'Rent Payment',
        'deposit' => 'Security Deposit',
        'meal_plan' => 'Meal Plan',
        'laundry' => 'Laundry Service',
        'deposit_refund' => 'Deposit Refund'
    ];
}
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
    <title>View Invoices</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>View User Invoices</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="user-selection-form">
                <form method="GET" action="invoices.php">
                    <label for="user_id">Select User:</label>
                    <select id="user_id" name="user_id" onchange="this.form.submit()">
                        <option value="">-- Select a User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo ($user_id == $user['user_id']) ? 'selected' : ''; ?>>
                                <?php echo $user['surname'] . ', ' . $user['forename'] . ' (' . $user['email'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($user_id): ?>
                <div class="filter-form">
                    <form method="GET" action="invoices.php">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                        <div class="filter-row">
                            <div class="filter-item">
                                <label for="payment_type">Payment Type:</label>
                                <select id="payment_type" name="payment_type">
                                    <option value="">All Types</option>
                                    <?php foreach (getPaymentTypes() as $type => $label): ?>
                                        <option value="<?php echo $type; ?>" <?php echo ($payment_type == $type) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label for="start_date">Start Date:</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>

                            <div class="filter-item">
                                <label for="end_date">End Date:</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>

                            <div class="filter-buttons">
                                <button type="submit" class="filter-button">Apply Filter</button>
                                <a href="invoices.php?user_id=<?php echo $user_id; ?>" class="filter-button">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>

                <h2>Invoices</h2>
                <?php if (empty($invoices)): ?>
                    <p>No invoices found for this user with the selected filters.</p>
                <?php else: ?>
                    <table border="1">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Invoice Number</th>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Room Number</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo $invoice['invoice_id']; ?></td>
                                    <td><?php echo $invoice['invoice_number']; ?></td>
                                    <td><?php echo getPaymentTypeLabel($invoice['payment_type']); ?></td>
                                    <td>
                                        <?php
                                        if ($invoice['payment_type'] == 'rent' || $invoice['payment_type'] == 'deposit') {
                                            echo $invoice['booking_id'] ?? 'N/A';
                                        } else {
                                            echo $invoice['reference_id'] ?? 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $invoice['room_number'] ?? 'N/A'; ?></td>
                                    <td>£<?php echo number_format($invoice['amount'], 2); ?></td>
                                    <td><?php echo formatDate($invoice['created_at']); ?></td>
                                    <td>
                                        <a href="../invoices/<?php echo $invoice['filename']; ?>" target="_blank"
                                            class="update-button">View Invoice</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php
                    $queryParams = http_build_query([
                        'user_id' => $user_id,
                        'payment_type' => $payment_type,
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ]);
                    echo generatePagination($page, $totalPages, "invoices.php?" . $queryParams);
                    ?>
                <?php endif; ?>
            <?php else: ?>
                <p>Please select a user to view their invoices.</p>
            <?php endif; ?>

            <br>

        </div>
    </div>
</body>

</html>