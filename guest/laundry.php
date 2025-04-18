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

// Get dates with available slots for the calendar
$availableDatesStmt = $conn->query("SELECT DISTINCT date FROM laundry_slots WHERE is_available = 1 ORDER BY date");
$availableDates = $availableDatesStmt->fetchAll(PDO::FETCH_COLUMN);
$availableDatesJson = json_encode($availableDates);

// Get slots for the selected date
$laundryStmt = $conn->prepare("SELECT * FROM laundry_slots WHERE is_available = 1 AND date = :selected_date ORDER BY start_time");
$laundryStmt->bindParam(':selected_date', $selectedDate, PDO::PARAM_STR);
$laundryStmt->execute();
$laundrySlots = $laundryStmt->fetchAll(PDO::FETCH_ASSOC);

// Count total available slots for the selected date
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
        .date-picker-container {
            margin-bottom: 20px;
        }

        #date-picker {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            width: 200px;
        }

        .no-slots-date {
            background-color: #ffcccc !important;
            color: #ff0000 !important;
        }

        .has-slots-date {
            background-color: #ccffcc !important;
            color: #006600 !important;
        }
    </style>
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

            <div class="date-picker-container">
                <h2>Select Date</h2>
                <input type="text" id="date-picker" value="<?php echo $selectedDate; ?>" placeholder="Select a date">
            </div>

            <h2>Available Slots for <?php echo date('j F, Y', strtotime($selectedDate)); ?></h2>
            <?php if (count($laundrySlots) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Start Time</th>
                            <th>Recurring</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laundrySlots as $slot): ?>
                            <tr>
                                <td><?php echo substr($slot['start_time'], 0, 5); ?></td>
                                <td><?php echo $slot['recurring'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo number_format($slot['price'], 2); ?></td>
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
                <div class="rooms-feedback">No laundry slots available for this date.</div>
            <?php endif; ?>

            <br>
            <div class="back-button-container">
                <a href="dashboard.php" class="button">Back to Dashboard</a>
            </div>
        </div>
        <div id="form-overlay"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const availableDates = <?php echo $availableDatesJson; ?>;

            const flatpickrInstance = flatpickr("#date-picker", {
                dateFormat: "d/m/Y",
                inline: false,
                minDate: "today",
                defaultDate: new Date(),
                onChange: function (selectedDates, dateStr) {
                    const dateParts = dateStr.split('/');
                    const formattedDate = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
                    window.location.href = 'laundry.php?date=' + formattedDate;
                },
                onDayCreate: function (dObj, dStr, fp, dayElem) {
                    const year = dayElem.dateObj.getFullYear();
                    const month = String(dayElem.dateObj.getMonth() + 1).padStart(2, '0');
                    const day = String(dayElem.dateObj.getDate()).padStart(2, '0');
                    const formattedDate = `${year}-${month}-${day}`;

                    if (!availableDates.includes(formattedDate) && dayElem.dateObj >= new Date()) {
                        dayElem.classList.add('no-slots-date');
                    }

                    if (availableDates.includes(formattedDate)) {
                        dayElem.classList.add('has-slots-date');
                    }
                }
            });
        });
    </script>
</body>

</html>