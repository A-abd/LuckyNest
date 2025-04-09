<?php
session_start();

if (!isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'book_laundry_slot') {
            $laundrySlotId = $_POST['laundry_slot_id'];
            $userId = $_SESSION['user_id'];

            $conn->beginTransaction();

            try {
                $slotStmt = $conn->prepare("UPDATE laundry_slots SET is_available = 0 WHERE laundry_slot_id = :id");
                $slotStmt->bindParam(':id', $laundrySlotId, PDO::PARAM_INT);
                $slotStmt->execute();

                $linkStmt = $conn->prepare("INSERT INTO laundry_slot_user_link (user_id, laundry_slot_id, is_cancelled, is_paid) VALUES (:user_id, :laundry_slot_id, 0, 0)");
                $linkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $linkStmt->bindParam(':laundry_slot_id', $laundrySlotId, PDO::PARAM_INT);
                $linkStmt->execute();

                $conn->commit();

                $_SESSION['pending_payment'] = [
                    'type' => 'laundry',
                    'id' => $laundrySlotId,
                    'price' => $_POST['price']
                ];
                header('Location: payments_page.php');
                exit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $feedback = 'Error booking laundry slot: ' . $e->getMessage();
            }
        }
    }
}

$laundryStmt = $conn->prepare("SELECT * FROM laundry_slots WHERE is_available = 1 ORDER BY date, start_time LIMIT :limit OFFSET :offset");
$laundryStmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$laundryStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$laundryStmt->execute();
$laundrySlots = $laundryStmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM laundry_slots WHERE is_available = 1");
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
    <title>Laundry Slots</title>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Laundry Slots</h1>
            <?php if ($feedback): ?>
                <div class="rooms-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <h2>Available Slots</h2>
            <?php if (count($laundrySlots) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Start Time</th>
                            <th>Recurring</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laundrySlots as $slot): ?>
                            <tr>
                                <td><?php echo $slot['date']; ?></td>
                                <td><?php echo $slot['start_time']; ?></td>
                                <td><?php echo $slot['recurring'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo number_format($slot['price'], 2); ?></td>
                                <td>
                                    <form method="POST" action="laundry.php" style="display:inline;">
                                        <input type="hidden" name="action" value="book_laundry_slot">
                                        <input type="hidden" name="laundry_slot_id"
                                            value="<?php echo $slot['laundry_slot_id']; ?>">
                                        <input type="hidden" name="price" value="<?php echo $slot['price']; ?>">
                                        <button type="submit" class="update-button">Book Slot</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $url = 'laundry.php';
                echo generatePagination($page, $totalPages, $url);
                ?>

            <?php else: ?>
                <div class="rooms-feedback">No laundry slots currently available.</div>
            <?php endif; ?>

            <br>
            <div class="back-button-container">
                <a href="dashboard.php" class="button">Back to Dashboard</a>
            </div>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>