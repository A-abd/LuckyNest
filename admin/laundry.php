<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../include/db.php';

$feedback = '';
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create_timeslot') {
            $selectedDate = $_POST['selected_date'];
            $startTime = $_POST['start_time'];
            $price = 5.0;
            $recurring = isset($_POST['recurring']) ? 1 : 0;
            $endDate = null;

            if ($recurring && isset($_POST['end_date'])) {
                $endDate = $_POST['end_date'];
            }

            $startDateTime = new DateTime("$selectedDate $startTime");
            $endDateTime = (new DateTime("$selectedDate $startTime"))->modify('+1 hour');

            if ($startDateTime->format('H') < 8 || $endDateTime->format('H') > 22) {
                $feedback = 'Timeslot must be between 8 AM and 10 PM.';
            } else {
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM laundry_slots 
                              WHERE date = :date AND start_time = :start_time");
                $checkStmt->bindValue(':date', $selectedDate, PDO::PARAM_STR);
                $checkStmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    $feedback = 'This timeslot already exists for this date.';
                } else {
                    if ($recurring && $endDate) {
                        $currentDate = new DateTime($selectedDate);
                        $endDateObj = new DateTime($endDate);
                        $dayOfWeek = $currentDate->format('N');

                        while ($currentDate <= $endDateObj) {
                            if ($currentDate->format('N') == $dayOfWeek) {
                                $slotDate = $currentDate->format('Y-m-d');

                                $stmt = $conn->prepare("INSERT INTO laundry_slots 
                                        (date, start_time, recurring, recurring_type, price) 
                                        VALUES (:date, :start_time, 1, 'weekly', :price)");
                                $stmt->bindValue(':date', $slotDate, PDO::PARAM_STR);
                                $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                                $stmt->bindValue(':price', $price, PDO::PARAM_STR);
                                $stmt->execute();
                            }

                            $currentDate->modify('+1 day');
                        }

                        $feedback = 'Recurring timeslots created successfully until ' . date('d/m/Y', strtotime($endDate)) . '!';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO laundry_slots 
                                (date, start_time, recurring, recurring_type, price) 
                                VALUES (:date, :start_time, 0, 'weekly', :price)");
                        $stmt->bindValue(':date', $selectedDate, PDO::PARAM_STR);
                        $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                        $stmt->bindValue(':price', $price, PDO::PARAM_STR);
                        $stmt->execute();

                        $feedback = 'Timeslot created successfully for ' . date('d/m/Y', strtotime($selectedDate)) . '!';
                    }
                }
            }
        } elseif ($action === 'update_timeslot') {
            $slotId = $_POST['slot_id'];
            $selectedDate = $_POST['selected_date'];
            $newStartTime = $_POST['start_time'];

            $startDateTime = new DateTime("$selectedDate $newStartTime");
            $endDateTime = (new DateTime("$selectedDate $newStartTime"))->modify('+1 hour');

            if ($startDateTime->format('H') < 8 || $endDateTime->format('H') > 22) {
                $feedback = 'Timeslot must be between 8 AM and 10 PM.';
            } else {
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM laundry_slots 
                              WHERE date = :date AND start_time = :start_time AND laundry_slot_id != :slot_id");
                $checkStmt->bindValue(':date', $selectedDate, PDO::PARAM_STR);
                $checkStmt->bindValue(':start_time', $newStartTime, PDO::PARAM_STR);
                $checkStmt->bindValue(':slot_id', $slotId, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    $feedback = 'This timeslot already exists for this date.';
                } else {
                    $updateStmt = $conn->prepare("UPDATE laundry_slots 
                                  SET start_time = :new_start_time 
                                  WHERE laundry_slot_id = :slot_id");

                    $updateStmt->bindValue(':new_start_time', $newStartTime, PDO::PARAM_STR);
                    $updateStmt->bindValue(':slot_id', $slotId, PDO::PARAM_INT);

                    if ($updateStmt->execute()) {
                        $feedback = 'Timeslot updated successfully!';
                    } else {
                        $feedback = 'Error updating timeslot.';
                    }
                }
            }
        } elseif ($action === 'delete_timeslot') {
            $slotId = $_POST['slot_id'];

            $deleteStmt = $conn->prepare("DELETE FROM laundry_slots 
                          WHERE laundry_slot_id = :slot_id");
            $deleteStmt->bindValue(':slot_id', $slotId, PDO::PARAM_INT);

            if ($deleteStmt->execute()) {
                $feedback = 'Timeslot deleted successfully!';
            } else {
                $feedback = 'Error deleting timeslot.';
            }
        }
    }
}

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$formattedSelectedDate = date('d/m/Y', strtotime($selectedDate));

$stmt = $conn->prepare("SELECT DISTINCT date FROM laundry_slots WHERE date >= :today ORDER BY date");
$stmt->bindValue(':today', date('Y-m-d'), PDO::PARAM_STR);
$stmt->execute();
$datesWithSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT 
        ls.date,
        COUNT(ls.laundry_slot_id) AS total_slots,
        COUNT(lsul.laundry_slot_user_link_id) AS booked_slots
    FROM 
        laundry_slots ls
    LEFT JOIN 
        laundry_slot_user_link lsul ON ls.laundry_slot_id = lsul.laundry_slot_id AND lsul.is_cancelled = 0
    WHERE 
        ls.date >= :today
    GROUP BY 
        ls.date
    HAVING 
        COUNT(ls.laundry_slot_id) > COUNT(lsul.laundry_slot_user_link_id)
");
$stmt->bindValue(':today', date('Y-m-d'), PDO::PARAM_STR);
$stmt->execute();
$datesWithAvailableSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT 
        ls.date
    FROM 
        laundry_slots ls
    LEFT JOIN 
        laundry_slot_user_link lsul ON ls.laundry_slot_id = lsul.laundry_slot_id AND lsul.is_cancelled = 0
    WHERE 
        ls.date >= :today
    GROUP BY 
        ls.date
    HAVING 
        COUNT(ls.laundry_slot_id) = COUNT(lsul.laundry_slot_user_link_id) AND COUNT(ls.laundry_slot_id) > 0
");
$stmt->bindValue(':today', date('Y-m-d'), PDO::PARAM_STR);
$stmt->execute();
$datesWithOnlyBookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("
    SELECT 
        ls.laundry_slot_id, 
        ls.date, 
        ls.start_time, 
        ls.price,
        ls.recurring,
        lsul.laundry_slot_user_link_id,
        u.forename,
        u.surname
    FROM 
        laundry_slots ls
    LEFT JOIN 
        laundry_slot_user_link lsul ON ls.laundry_slot_id = lsul.laundry_slot_id AND lsul.is_cancelled = 0
    LEFT JOIN 
        users u ON lsul.user_id = u.user_id
    WHERE 
        ls.date = :selected_date
    ORDER BY 
        ls.start_time
");
$stmt->bindValue(':selected_date', $selectedDate, PDO::PARAM_STR);
$stmt->execute();
$timeslots = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../assets/scripts.js"></script>
    <title>Laundry Management</title>
</head>

<body>
    <?php include '../include/admin_navbar.php'; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Laundry Management</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="calendar-container">
                <div id="date-picker" class="selected-date-display">Selected date is: <?php echo $formattedSelectedDate; ?></div>
            </div>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Timeslot</button>
            </div>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Create Timeslot</h2>
                <form method="POST" action="laundry">
                    <input type="hidden" name="action" value="create_timeslot">
                    <input type="hidden" id="selected_date" name="selected_date" value="<?php echo $selectedDate; ?>">

                    <label for="start_time">Start Time:</label>
                    <input type="time" id="start_time" name="start_time" min="08:00" max="22:00" step="3600" required>

                    <div>
                        <input type="checkbox" id="recurring" name="recurring"
                            onchange="LuckyNest.toggleEndDateField()">
                        <label for="recurring">Make this a recurring weekly slot</label>
                    </div>

                    <div id="end_date_container" style="display: none;">
                        <label for="end_date">End Date (for recurring):</label>
                        <input type="date" id="end_date" name="end_date" min="<?php echo $selectedDate; ?>">
                    </div>

                    <input type="hidden" name="price" value="5.0">
                    <button type="submit" class="update-button">Create Timeslot</button>
                </form>
            </div>

            <h2>Timeslots for <?php echo date('l, d/m/Y', strtotime($selectedDate)); ?></h2>

            <table border="1">
                <thead>
                    <tr>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Price (£)</th>
                        <th>Status</th>
                        <th>Recurring</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($timeslots) > 0): ?>
                        <?php foreach ($timeslots as $timeslot):
                            $startTime = $timeslot['start_time'];
                            $formattedStartTime = date("H:i", strtotime($startTime));
                            $endTime = (new DateTime($startTime))->modify('+1 hour')->format('H:00');
                            $isBooked = !empty($timeslot['laundry_slot_user_link_id']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($formattedStartTime); ?></td>
                                <td><?php echo htmlspecialchars($endTime); ?></td>
                                <td>£<?php echo htmlspecialchars($timeslot['price']); ?></td>
                                <td>
                                    <?php if ($isBooked): ?>
                                        Booked by
                                        <?php echo htmlspecialchars($timeslot['forename'] . ' ' . $timeslot['surname']); ?>
                                    <?php else: ?>
                                        Available
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($timeslot['recurring']): ?>
                                        <span class="recurring-badge">Yes</span>
                                    <?php else: ?>
                                        <span class="recurring-badge">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button
                                        onclick="LuckyNest.toggleForm('edit-form-<?php echo $timeslot['laundry_slot_id']; ?>')"
                                        class="update-button">Edit</button>

                                    <div id="edit-form-<?php echo $timeslot['laundry_slot_id']; ?>" class="edit-form">
                                        <button type="button" class="close-button"
                                            onclick="LuckyNest.toggleForm('edit-form-<?php echo $timeslot['laundry_slot_id']; ?>')">✕</button>
                                        <form method="POST" action="laundry">
                                            <h2>Edit Timeslot</h2>
                                            <input type="hidden" name="action" value="update_timeslot">
                                            <input type="hidden" name="slot_id"
                                                value="<?php echo $timeslot['laundry_slot_id']; ?>">
                                            <input type="hidden" name="selected_date" value="<?php echo $selectedDate; ?>">

                                            <label for="new_time-<?php echo $timeslot['laundry_slot_id']; ?>">New Start
                                                Time:</label>
                                            <input type="time" id="new_time-<?php echo $timeslot['laundry_slot_id']; ?>"
                                                name="start_time" min="08:00" max="22:00" step="3600"
                                                value="<?php echo $startTime; ?>" required>

                                            <div class="button-group">
                                                <button type="submit" class="update-button">Update</button>
                                                <button type="button" class="update-button"
                                                    onclick="document.getElementById('delete-form-<?php echo $timeslot['laundry_slot_id']; ?>').submit(); return false;">Delete</button>
                                            </div>
                                        </form>

                                        <form id="delete-form-<?php echo $timeslot['laundry_slot_id']; ?>" method="POST"
                                            action="laundry" style="display:none;">
                                            <input type="hidden" name="action" value="delete_timeslot">
                                            <input type="hidden" name="slot_id"
                                                value="<?php echo $timeslot['laundry_slot_id']; ?>">
                                        </form>
                                    </div>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No timeslots available for this date.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <br>

        </div>
    </div>
    <div id="form-overlay"></div>

    <script>
        window.datesWithSlots = <?php echo json_encode($datesWithSlots); ?>;
        window.datesWithAvailableSlots = <?php echo json_encode($datesWithAvailableSlots); ?>;
        window.datesWithOnlyBookedSlots = <?php echo json_encode($datesWithOnlyBookedSlots); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            LuckyNest.initLaundryCalendar("<?php echo $selectedDate; ?>");
        });
    </script>
</body>

</html>