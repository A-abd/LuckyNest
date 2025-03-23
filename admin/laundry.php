<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';

$feedback = '';
$userData = [];

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

function getWeekDates()
{
    $dates = [];
    $today = new DateTime();
    $currentDayOfWeek = $today->format('N');

    $monday = clone $today;
    $monday->modify('-' . ($currentDayOfWeek - 1) . ' days');

    for ($i = 0; $i < 7; $i++) {
        $day = clone $monday;
        $day->modify('+' . $i . ' days');
        $dates[] = [
            'date' => $day->format('Y-m-d'),
            'day' => $day->format('D'),
            'dayNum' => $day->format('N'),
            'formatted' => $day->format('d/m/Y')
        ];
    }

    return $dates;
}

$weekDates = getWeekDates();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create_timeslot') {
            $dayOfWeek = $_POST['day_of_week'];
            $startTime = $_POST['start_time'];
            $recurring = isset($_POST['recurring']) ? 1 : 0;
            $recurringType = $_POST['recurring_type'] ?? 'weekly';
            $recurringEndDate = !empty($_POST['recurring_end_date']) ? $_POST['recurring_end_date'] : null;
            $price = 5.0;

            $date = null;
            foreach ($weekDates as $dayData) {
                if ($dayData['dayNum'] == $dayOfWeek) {
                    $date = $dayData['date'];
                    break;
                }
            }

            if (!$date) {
                $feedback = 'Invalid day of the week selected.';
            } else {
                $startDateTime = new DateTime("$date $startTime");
                $endDateTime = (new DateTime("$date $startTime"))->modify('+1 hour');

                if ($startDateTime->format('H') < 8 || $endDateTime->format('H') > 22) {
                    $feedback = 'Timeslot must be between 8 AM and 10 PM.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO laundry_slots (date, start_time, recurring, recurring_type, price) VALUES (:date, :start_time, :recurring, :recurring_type, :price)");
                    $stmt->bindValue(':date', $date, PDO::PARAM_STR);
                    $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                    $stmt->bindValue(':recurring', $recurring, PDO::PARAM_INT);
                    $stmt->bindValue(':recurring_type', $recurringType, PDO::PARAM_STR);
                    $stmt->bindValue(':price', $price, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        if ($recurring) {
                            $currentDate = new DateTime($date);
                            $endDate = $recurringEndDate ? new DateTime($recurringEndDate) : new DateTime($date);
                            $endDate->modify('+6 months');
                            
                            while ($currentDate < $endDate) {
                                if ($recurringType === 'daily') {
                                    $currentDate->modify('+1 day');
                                } elseif ($recurringType === 'weekly') {
                                    $currentDate->modify('+1 week');
                                } elseif ($recurringType === 'monthly') {
                                    $currentDate->modify('+1 month');
                                }
                                
                                if ($currentDate > $endDate) {
                                    break;
                                }
                                
                                $futureDateStr = $currentDate->format('Y-m-d');
                                $futureStmt = $conn->prepare("INSERT INTO laundry_slots (date, start_time, recurring, recurring_type, price) VALUES (:date, :start_time, :recurring, :recurring_type, :price)");
                                $futureStmt->bindValue(':date', $futureDateStr, PDO::PARAM_STR);
                                $futureStmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                                $futureStmt->bindValue(':recurring', $recurring, PDO::PARAM_INT);
                                $futureStmt->bindValue(':recurring_type', $recurringType, PDO::PARAM_STR);
                                $futureStmt->bindValue(':price', $price, PDO::PARAM_STR);
                                $futureStmt->execute();
                            }
                        }
                        
                        $feedback = 'Timeslot(s) created successfully!';
                    } else {
                        $feedback = 'Error creating timeslot.';
                    }
                }
            }
        } elseif ($action === 'update_timeslot') {
            $slotId = $_POST['slot_id'];
            $startTime = $_POST['start_time'];
            $recurring = isset($_POST['recurring']) ? 1 : 0;
            $recurringType = $_POST['recurring_type'] ?? 'weekly';
            $slotDate = $_POST['slot_date'];
            $recurringEndDate = !empty($_POST['recurring_end_date']) ? $_POST['recurring_end_date'] : null;

            $stmt = $conn->prepare("UPDATE laundry_slots SET start_time = :start_time, recurring = :recurring, recurring_type = :recurring_type WHERE laundry_slot_id = :slot_id");
            $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
            $stmt->bindValue(':recurring', $recurring, PDO::PARAM_INT);
            $stmt->bindValue(':recurring_type', $recurringType, PDO::PARAM_STR);
            $stmt->bindValue(':slot_id', $slotId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($recurring && isset($_POST['update_future']) && $_POST['update_future'] == 1) {
                    $deleteStmt = $conn->prepare("DELETE FROM laundry_slots WHERE date > :date AND start_time = :start_time");
                    $deleteStmt->bindValue(':date', $slotDate, PDO::PARAM_STR);
                    $deleteStmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                    $deleteStmt->execute();
                    
                    $currentDate = new DateTime($slotDate);
                    $endDate = $recurringEndDate ? new DateTime($recurringEndDate) : new DateTime($slotDate);
                    $endDate->modify('+6 months');
                    
                    while ($currentDate < $endDate) {
                        if ($recurringType === 'daily') {
                            $currentDate->modify('+1 day');
                        } elseif ($recurringType === 'weekly') {
                            $currentDate->modify('+1 week');
                        } elseif ($recurringType === 'monthly') {
                            $currentDate->modify('+1 month');
                        }
                        
                        if ($currentDate > $endDate) {
                            break;
                        }
                        
                        $futureDateStr = $currentDate->format('Y-m-d');
                        $futureStmt = $conn->prepare("INSERT INTO laundry_slots (date, start_time, recurring, recurring_type, price) VALUES (:date, :start_time, :recurring, :recurring_type, :price)");
                        $futureStmt->bindValue(':date', $futureDateStr, PDO::PARAM_STR);
                        $futureStmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                        $futureStmt->bindValue(':recurring', $recurring, PDO::PARAM_INT);
                        $futureStmt->bindValue(':recurring_type', $recurringType, PDO::PARAM_STR);
                        $futureStmt->bindValue(':price', 5.0, PDO::PARAM_STR);
                        $futureStmt->execute();
                    }
                }
                
                $feedback = 'Timeslot updated successfully!';
            } else {
                $feedback = 'Error updating timeslot.';
            }
        } elseif ($action === 'delete_timeslot') {
            $slotId = $_POST['slot_id'];
            $deleteOption = $_POST['delete_option'];
            $slotDate = $_POST['slot_date'];
            $slotTime = $_POST['slot_time'];
            
            if ($deleteOption === 'single') {
                $stmt = $conn->prepare("DELETE FROM laundry_slots WHERE laundry_slot_id = :slot_id");
                $stmt->bindValue(':slot_id', $slotId, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $feedback = 'Timeslot deleted successfully!';
                } else {
                    $feedback = 'Error deleting timeslot.';
                }
            } elseif ($deleteOption === 'future') {
                $stmt = $conn->prepare("DELETE FROM laundry_slots WHERE (date > :date OR (date = :date AND laundry_slot_id >= :slot_id)) AND start_time = :start_time");
                $stmt->bindValue(':date', $slotDate, PDO::PARAM_STR);
                $stmt->bindValue(':slot_id', $slotId, PDO::PARAM_INT);
                $stmt->bindValue(':start_time', $slotTime, PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    $feedback = 'Current and future timeslots deleted successfully!';
                } else {
                    $feedback = 'Error deleting timeslots.';
                }
            }
        }
    }
}

$selectedDay = isset($_GET['day']) ? intval($_GET['day']) : intval(date('N'));
if ($selectedDay < 1 || $selectedDay > 7) {
    $selectedDay = intval(date('N'));
}

$selectedDate = null;
foreach ($weekDates as $dayData) {
    if ($dayData['dayNum'] == $selectedDay) {
        $selectedDate = $dayData['date'];
        break;
    }
}

$stmt = $conn->prepare("SELECT * FROM laundry_slots WHERE date = :date ORDER BY start_time");
$stmt->bindValue(':date', $selectedDate, PDO::PARAM_STR);
$stmt->execute();
$timeslots = $stmt->fetchAll(PDO::FETCH_ASSOC);

$conn = null;
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laundry Management</title>
    <script src="../assets/scripts.js"></script>
    <script>
        //TODO move these to scripts
        function toggleDeleteOptions(id) {
            const deleteOptions = document.getElementById('delete-options-' + id);
            if (deleteOptions) {
                deleteOptions.style.display = deleteOptions.style.display === 'none' ? 'block' : 'none';
            }
        }
        
        function toggleUpdateFutureOption(checkbox, id) {
            const updateFutureOption = document.getElementById('update-future-option-' + id);
            if (updateFutureOption) {
                updateFutureOption.style.display = checkbox.checked ? 'block' : 'none';
            }
        }
    </script>
</head>

<body>
    <div>
        <h1>Laundry Management</h1>
        <?php if ($feedback): ?>
            <p><?php echo $feedback; ?></p>
        <?php endif; ?>

        <h2>Create Timeslot</h2>
        <form method="POST" action="laundry.php">
            <input type="hidden" name="action" value="create_timeslot">

            <label for="day_of_week">Day of Week:</label>
            <select id="day_of_week" name="day_of_week" required>
                <?php foreach ($weekDates as $dayData): ?>
                    <option value="<?php echo $dayData['dayNum']; ?>">
                        <?php echo $dayData['day'] . ' (' . $dayData['formatted'] . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="start_time">Start Time:</label>
            <input type="time" id="start_time" name="start_time" min="08:00" max="22:00" step="3600" required>

            <label for="recurring">Recurring:</label>
            <input type="checkbox" id="recurring" name="recurring" checked>

            <div id="recurring_options">
                <label for="recurring_type">Recurring Type:</label>
                <select id="recurring_type" name="recurring_type">
                    <option value="daily">Daily</option>
                    <option value="weekly" selected>Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>

                <label for="recurring_end_date">End Date (leave empty for 6 months):</label>
                <input type="date" id="recurring_end_date" name="recurring_end_date">
            </div>

            <input type="hidden" name="price" value="5.0">
            <button type="submit">Create Timeslot</button>
        </form>

        <h2>Weekly Timeslots</h2>
        <form method="GET" action="laundry.php">
            <label for="day_select">View day:</label>
            <select id="day_select" name="day" onchange="this.form.submit()">
                <?php foreach ($weekDates as $dayData): ?>
                    <option value="<?php echo $dayData['dayNum']; ?>" <?php echo ($dayData['dayNum'] == $selectedDay) ? 'selected' : ''; ?>>
                        <?php echo $dayData['day'] . ' (' . $dayData['formatted'] . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <h3>Timeslots for <?php
        foreach ($weekDates as $dayData) {
            if ($dayData['dayNum'] == $selectedDay) {
                echo $dayData['day'] . ' (' . $dayData['formatted'] . ')';
                break;
            }
        }
        ?></h3>

        <table>
            <thead>
                <tr>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Recurring</th>
                    <th>Recurring Typ</th>
                    <th>Price (£)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($timeslots) > 0): ?>
                    <?php foreach ($timeslots as $timeslot):
                        $startTime = $timeslot['start_time'];
                        $endTime = (new DateTime($startTime))->modify('+1 hour')->format('H:00');
                        $recurringType = isset($timeslot['recurring_type']) ? $timeslot['recurring_type'] : 'weekly';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($startTime); ?></td>
                            <td><?php echo htmlspecialchars($endTime); ?></td>
                            <td><?php echo $timeslot['recurring'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $timeslot['recurring'] ? ucfirst($recurringType) : 'N/A'; ?></td>
                            <td>£<?php echo htmlspecialchars($timeslot['price']); ?></td>
                            <td>
                                <button onclick="toggleEditLaundryForm(<?php echo $timeslot['laundry_slot_id']; ?>)">Edit</button>
                                <button onclick="toggleDeleteOptions(<?php echo $timeslot['laundry_slot_id']; ?>)">Delete</button>
                                
                                <div id="delete-options-<?php echo $timeslot['laundry_slot_id']; ?>" style="display: none;">
                                    <form method="POST" action="laundry.php">
                                        <input type="hidden" name="action" value="delete_timeslot">
                                        <input type="hidden" name="slot_id" value="<?php echo $timeslot['laundry_slot_id']; ?>">
                                        <input type="hidden" name="slot_date" value="<?php echo $timeslot['date']; ?>">
                                        <input type="hidden" name="slot_time" value="<?php echo $timeslot['start_time']; ?>">
                                        
                                        <div>
                                            <input type="radio" id="delete-single-<?php echo $timeslot['laundry_slot_id']; ?>" 
                                                name="delete_option" value="single" checked>
                                            <label for="delete-single-<?php echo $timeslot['laundry_slot_id']; ?>">
                                                Delete only this timeslot
                                            </label>
                                        </div>
                                        
                                        <?php if ($timeslot['recurring']): ?>
                                        <div>
                                            <input type="radio" id="delete-future-<?php echo $timeslot['laundry_slot_id']; ?>" 
                                                name="delete_option" value="future">
                                            <label for="delete-future-<?php echo $timeslot['laundry_slot_id']; ?>">
                                                Delete this and all future timeslots
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <button type="submit" onclick="return confirm('Are you sure you want to delete this timeslot?');">
                                            Confirm Delete
                                        </button>
                                    </form>
                                </div>
                                
                                <div id="edit-laundry-form-<?php echo $timeslot['laundry_slot_id']; ?>" style="display: none;">
                                    <form method="POST" action="laundry.php">
                                        <input type="hidden" name="action" value="update_timeslot">
                                        <input type="hidden" name="slot_id" value="<?php echo $timeslot['laundry_slot_id']; ?>">
                                        <input type="hidden" name="slot_date" value="<?php echo $timeslot['date']; ?>">

                                        <label for="edit-start-time-<?php echo $timeslot['laundry_slot_id']; ?>">Start Time:</label>
                                        <input type="time" id="edit-start-time-<?php echo $timeslot['laundry_slot_id']; ?>"
                                            name="start_time" min="08:00" max="22:00" step="3600"
                                            value="<?php echo $timeslot['start_time']; ?>" required>

                                        <label for="edit-recurring-<?php echo $timeslot['laundry_slot_id']; ?>">Recurring:</label>
                                        <input type="checkbox" id="edit-recurring-<?php echo $timeslot['laundry_slot_id']; ?>"
                                            name="recurring" <?php echo $timeslot['recurring'] ? 'checked' : ''; ?> 
                                            onchange="toggleUpdateFutureOption(this, <?php echo $timeslot['laundry_slot_id']; ?>)">

                                        <div id="edit-recurring-options-<?php echo $timeslot['laundry_slot_id']; ?>">
                                            <label for="edit-recurring-type-<?php echo $timeslot['laundry_slot_id']; ?>">Recurring Type:</label>
                                            <select id="edit-recurring-type-<?php echo $timeslot['laundry_slot_id']; ?>" name="recurring_type">
                                                <option value="daily" <?php echo ($recurringType == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo ($recurringType == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo ($recurringType == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                            </select>
                                            
                                            <label for="edit-recurring-end-date-<?php echo $timeslot['laundry_slot_id']; ?>">
                                                End Date (leave empty for 6 months):
                                            </label>
                                            <input type="date"
                                                id="edit-recurring-end-date-<?php echo $timeslot['laundry_slot_id']; ?>"
                                                name="recurring_end_date">
                                                
                                            <div id="update-future-option-<?php echo $timeslot['laundry_slot_id']; ?>" 
                                                style="display: <?php echo $timeslot['recurring'] ? 'block' : 'none'; ?>">
                                                <input type="checkbox" id="update-future-<?php echo $timeslot['laundry_slot_id']; ?>" 
                                                    name="update_future" value="1">
                                                <label for="update-future-<?php echo $timeslot['laundry_slot_id']; ?>">
                                                    Regenerate future occurrence
                                                </label>
                                            </div>
                                        </div>

                                        <button type="submit">Update</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No timeslots available for this day.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <br>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>
</body>

</html>