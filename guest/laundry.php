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

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$formattedSelectedDate = date('d/m/Y', strtotime($selectedDate));
$displayDate = date('j F, Y', strtotime($selectedDate));

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

$stmt = $conn->prepare("SELECT DISTINCT date FROM laundry_slots WHERE date >= :today ORDER BY date");
$stmt->bindValue(':today', date('Y-m-d'), PDO::PARAM_STR);
$stmt->execute();
$datesWithSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT 
        ls.date
    FROM 
        laundry_slots ls
    WHERE 
        ls.date >= :today
        AND ls.is_available = 1
    GROUP BY 
        ls.date
");
$stmt->bindValue(':today', date('Y-m-d'), PDO::PARAM_STR);
$stmt->execute();
$datesWithAvailableSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT 
        ls.date
    FROM 
        laundry_slots ls
    WHERE 
        ls.date >= :today
        AND ls.date NOT IN (
            SELECT date FROM laundry_slots WHERE date >= :today2 AND is_available = 1
        )
    GROUP BY 
        ls.date
");
$stmt->bindValue(':today', date('Y-m-d'), PDO::PARAM_STR);
$stmt->bindValue(':today2', date('Y-m-d'), PDO::PARAM_STR);
$stmt->execute();
$datesWithNoAvailableSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

$laundryStmt = $conn->prepare("SELECT * FROM laundry_slots WHERE is_available = 1 AND date = :selected_date ORDER BY start_time");
$laundryStmt->bindParam(':selected_date', $selectedDate, PDO::PARAM_STR);
$laundryStmt->execute();
$laundrySlots = $laundryStmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->prepare("SELECT COUNT(*) As total FROM laundry_slots WHERE is_available = 1 AND date = :selected_date");
$totalRecordsQuery->bindParam(':selected_date', $selectedDate, PDO::PARAM_STR);
$totalRecordsQuery->execute();
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
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../assets/scripts.js"></script>
    <title>Laundry Slots</title>
    <style>
        .calendar-container {
            margin-bottom: 20px;
            text-align: center;
        }

        .flatpickr-calendar {
            margin: 0 auto;
        }

        .flatpickr-day.available-slots {
            background-color: #c8e6c9 !important;
            border-color: #c8e6c9 !important;
            color: #000 !important;
        }

        .flatpickr-day.no-available-slots {
            background-color: #ffcdd2 !important;
            border-color: #ffcdd2 !important;
            color: #000 !important;
        }

        .flatpickr-day.flatpickr-disabled {
            color: rgba(64, 64, 64, 0.3) !important;
            background-color: rgba(240, 240, 240, 0.5) !important;
            cursor: not-allowed;
            border-color: transparent !important;
        }

        .selected-date-display {
            text-align: center;
            font-size: 1.2em;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Laundry Slots</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="calendar-container">
                <div id="date-picker" class="selected-date-display">Selected date is:
                    <?php echo $formattedSelectedDate; ?></div>
            </div>

            <h2>Available Slots for <?php echo date('j F, Y', strtotime($selectedDate)); ?></h2>
            <?php if (count($laundrySlots) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Recurring</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laundrySlots as $slot):
                            $startTime = $slot['start_time'];
                            $formattedStartTime = date("H:i", strtotime($startTime));
                            $endTime = (new DateTime($startTime))->modify('+1 hour')->format('H:00');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($formattedStartTime); ?></td>
                                <td><?php echo htmlspecialchars($endTime); ?></td>
                                <td><?php echo $slot['recurring'] ? 'Yes' : 'No'; ?></td>
                                <td>£<?php echo number_format($slot['price'], 2); ?></td>
                                <td>
                                    <form method="POST" action="laundry.php?date=<?php echo $selectedDate; ?>"
                                        style="display:inline;">
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

            <?php else: ?>
                <div class="feedback-message">No laundry slots available for this date.</div>
            <?php endif; ?>

            <br>
        </div>
        <div id="form-overlay"></div>
    </div>

    <script>
        window.datesWithSlots = <?php echo json_encode($datesWithSlots); ?>;
        window.datesWithAvailableSlots = <?php echo json_encode($datesWithAvailableSlots); ?>;
        window.datesWithNoAvailableSlots = <?php echo json_encode($datesWithNoAvailableSlots); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            LuckyNest.initLaundryCalendar("<?php echo $selectedDate; ?>");
        });
    </script>
</body>

</html>