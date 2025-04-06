<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';

$feedback = '';
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

function getWeekDays()
{
    $days = [
        1 => ['name' => 'Monday', 'short' => 'Mon'],
        2 => ['name' => 'Tuesday', 'short' => 'Tue'],
        3 => ['name' => 'Wednesday', 'short' => 'Wed'],
        4 => ['name' => 'Thursday', 'short' => 'Thu'],
        5 => ['name' => 'Friday', 'short' => 'Fri'],
        6 => ['name' => 'Saturday', 'short' => 'Sat'],
        7 => ['name' => 'Sunday', 'short' => 'Sun']
    ];

    $today = new DateTime();
    $currentDayOfWeek = $today->format('N');
    $monday = clone $today;
    $monday->modify('-' . ($currentDayOfWeek - 1) . ' days');

    for ($i = 1; $i <= 7; $i++) {
        $day = clone $monday;
        $day->modify('+' . ($i - 1) . ' days');
        $days[$i]['date'] = $day->format('Y-m-d');
        $days[$i]['formatted'] = $day->format('d/m/Y');
    }

    return $days;
}

$weekDays = getWeekDays();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create_timeslot') {
            $dayOfWeek = $_POST['day_of_week'];
            $startTime = $_POST['start_time'];
            $price = 5.0;

            $date = $weekDays[$dayOfWeek]['date'];

            $startDateTime = new DateTime("$date $startTime");
            $endDateTime = (new DateTime("$date $startTime"))->modify('+1 hour');

            if ($startDateTime->format('H') < 8 || $endDateTime->format('H') > 22) {
                $feedback = 'Timeslot must be between 8 AM and 10 PM.';
            } else {
                // Check if this timeslot already exists for this day of week
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM laundry_slots 
                              WHERE date LIKE :day_pattern AND start_time = :start_time");
                $dayPattern = '____-__-' . sprintf('%02d', $dayOfWeek);
                $checkStmt->bindValue(':day_pattern', $dayPattern, PDO::PARAM_STR);
                $checkStmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    $feedback = 'This timeslot already exists for this day of the week.';
                } else {
                    // Create slots for next 6 months for this day of week
                    $currentDate = new DateTime($date);
                    $endDate = (clone $currentDate)->modify('+6 months');

                    while ($currentDate <= $endDate) {
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

                    $feedback = 'Timeslot created successfully for all ' . $weekDays[$dayOfWeek]['name'] . 's!';
                }
            }
        } elseif ($action === 'update_timeslot') {
            $dayOfWeek = $_POST['day_of_week'];
            $oldStartTime = $_POST['old_start_time'];
            $newStartTime = $_POST['start_time'];

            $date = $weekDays[$dayOfWeek]['date'];
            $startDateTime = new DateTime("$date $newStartTime");
            $endDateTime = (new DateTime("$date $newStartTime"))->modify('+1 hour');

            if ($startDateTime->format('H') < 8 || $endDateTime->format('H') > 22) {
                $feedback = 'Timeslot must be between 8 AM and 10 PM.';
            } else {
                // Check if new time already exists (if changing time)
                if ($oldStartTime !== $newStartTime) {
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM laundry_slots 
                                  WHERE date LIKE :day_pattern AND start_time = :start_time");
                    $dayPattern = '____-__-' . sprintf('%02d', $dayOfWeek);
                    $checkStmt->bindValue(':day_pattern', $dayPattern, PDO::PARAM_STR);
                    $checkStmt->bindValue(':start_time', $newStartTime, PDO::PARAM_STR);
                    $checkStmt->execute();

                    if ($checkStmt->fetchColumn() > 0) {
                        $feedback = 'This timeslot already exists for this day of the week.';
                        goto skipUpdate;
                    }
                }

                // Update all the future slots for this day of week
                $updateStmt = $conn->prepare("UPDATE laundry_slots 
                              SET start_time = :new_start_time 
                              WHERE date >= :today 
                              AND date LIKE :day_pattern 
                              AND start_time = :old_start_time");

                $today = date('Y-m-d');
                $dayPattern = '____-__-' . sprintf('%02d', $dayOfWeek);

                $updateStmt->bindValue(':new_start_time', $newStartTime, PDO::PARAM_STR);
                $updateStmt->bindValue(':today', $today, PDO::PARAM_STR);
                $updateStmt->bindValue(':day_pattern', $dayPattern, PDO::PARAM_STR);
                $updateStmt->bindValue(':old_start_time', $oldStartTime, PDO::PARAM_STR);

                if ($updateStmt->execute()) {
                    $feedback = 'All future ' . $weekDays[$dayOfWeek]['name'] . ' timeslots updated successfully!';
                } else {
                    $feedback = 'Error updating timeslots.';
                }
            }

            skipUpdate:
        } elseif ($action === 'delete_timeslot') {
            $dayOfWeek = $_POST['day_of_week'];
            $startTime = $_POST['start_time'];

            // Delete all the future slots for this day of the week and time
            $deleteStmt = $conn->prepare("DELETE FROM laundry_slots 
                          WHERE date >= :today 
                          AND date LIKE :day_pattern 
                          AND start_time = :start_time");

            $today = date('Y-m-d');
            $dayPattern = '____-__-' . sprintf('%02d', $dayOfWeek);

            $deleteStmt->bindValue(':today', $today, PDO::PARAM_STR);
            $deleteStmt->bindValue(':day_pattern', $dayPattern, PDO::PARAM_STR);
            $deleteStmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);

            if ($deleteStmt->execute()) {
                $feedback = 'All future ' . $weekDays[$dayOfWeek]['name'] . ' timeslots at ' . $startTime . ' deleted successfully!';
            } else {
                $feedback = 'Error deleting timeslots.';
            }
        }
    }
}

// Get selected day
$selectedDay = isset($_GET['day']) ? intval($_GET['day']) : intval(date('N'));
if ($selectedDay < 1 || $selectedDay > 7) {
    $selectedDay = intval(date('N'));
}

// Get all the time slots for this day of week
$dayPattern = '____-__-' . sprintf('%02d', $selectedDay);
$today = date('Y-m-d');

$stmt = $conn->prepare("SELECT date, start_time, price 
                      FROM laundry_slots 
                      WHERE date >= :today 
                      AND date LIKE :day_pattern 
                      GROUP BY start_time 
                      ORDER BY start_time");
$stmt->bindValue(':today', $today, PDO::PARAM_STR);
$stmt->bindValue(':day_pattern', $dayPattern, PDO::PARAM_STR);
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
    <script src="../assets/scripts.js"></script>
    <title>Laundry Management</title>
</head>

<body>
    <?php include '../include/admin_navbar.php'; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Laundry Management</h1>
            <?php if ($feedback): ?>
                <div class="rooms-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Timeslot</button>
            </div>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Create Weekly Timeslot</h2>
                <form method="POST" action="laundry.php">
                    <input type="hidden" name="action" value="create_timeslot">

                    <label for="day_of_week">Day of Week:</label>
                    <select id="day_of_week" name="day_of_week" required>
                        <?php foreach ($weekDays as $num => $day): ?>
                            <option value="<?php echo $num; ?>">
                                <?php echo $day['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="start_time">Start Time:</label>
                    <input type="time" id="start_time" name="start_time" min="08:00" max="22:00" step="3600" required>

                    <input type="hidden" name="price" value="5.0">
                    <button type="submit" class="update-button">Create Timeslot</button>
                </form>
            </div>

            <h2>Weekly Timeslots</h2>
            <form method="GET" action="laundry.php" class="button-center">
                <label for="day_select">View day:</label>
                <select id="day_select" name="day" onchange="this.form.submit()">
                    <?php foreach ($weekDays as $num => $day): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($num == $selectedDay) ? 'selected' : ''; ?>>
                            <?php echo $day['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <h3>Timeslots for <?php echo $weekDays[$selectedDay]['name']; ?></h3>

            <table border="1">
                <thead>
                    <tr>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Price (£)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($timeslots) > 0): ?>
                        <?php foreach ($timeslots as $timeslot):
                            $startTime = $timeslot['start_time'];
                            $endTime = (new DateTime($startTime))->modify('+1 hour')->format('H:00');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($startTime); ?></td>
                                <td><?php echo htmlspecialchars($endTime); ?></td>
                                <td>£<?php echo htmlspecialchars($timeslot['price']); ?></td>
                                <td>
                                    <button
                                        onclick="LuckyNest.toggleForm('edit-form-<?php echo $selectedDay; ?>-<?php echo str_replace(':', '', $startTime); ?>')"
                                        class="update-button">Edit</button>

                                    <!-- Edit Form -->
                                    <div id="edit-form-<?php echo $selectedDay; ?>-<?php echo str_replace(':', '', $startTime); ?>"
                                        class="rooms-type-edit-form">
                                        <button type="button" class="close-button"
                                            onclick="LuckyNest.toggleForm('edit-form-<?php echo $selectedDay; ?>-<?php echo str_replace(':', '', $startTime); ?>')">✕</button>
                                        <form method="POST" action="laundry.php">
                                            <h2>Edit Timeslot</h2>
                                            <input type="hidden" name="action" value="update_timeslot">
                                            <input type="hidden" name="day_of_week" value="<?php echo $selectedDay; ?>">
                                            <input type="hidden" name="old_start_time" value="<?php echo $startTime; ?>">

                                            <label
                                                for="new_time-<?php echo $selectedDay; ?>-<?php echo str_replace(':', '', $startTime); ?>">New
                                                Start Time:</label>
                                            <input type="time"
                                                id="new_time-<?php echo $selectedDay; ?>-<?php echo str_replace(':', '', $startTime); ?>"
                                                name="start_time" min="08:00" max="22:00" step="3600"
                                                value="<?php echo $startTime; ?>" required>

                                            <div class="rooms-button-group">
                                                <button type="submit" class="update-button">Update</button>
                                                <button type="button" class="update-button"
                                                    onclick="document.getElementById('delete-form-<?php echo $selectedDay; ?>-<?php echo str_replace(':', '', $startTime); ?>').submit(); return false;">Delete</button>
                                            </div>
                                        </form>

                                        <form
                                            id="delete-form-<?php echo $selectedDay; ?>-<?php echo str_replace(':', '', $startTime); ?>"
                                            method="POST" action="laundry.php" style="display:none;">
                                            <input type="hidden" name="action" value="delete_timeslot">
                                            <input type="hidden" name="day_of_week" value="<?php echo $selectedDay; ?>">
                                            <input type="hidden" name="start_time" value="<?php echo $startTime; ?>">
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No timeslots available for this day.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <br>
            <div class="back-button-container">
                <a href="dashboard.php" class="button">Back to Dashboard</a>
            </div>
        </div>
    </div>
    <div id="form-overlay"></div>
</body>

</html>