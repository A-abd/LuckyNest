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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create_timeslot') {
            $date = $_POST['date'];
            $startTime = $_POST['start_time'];
            $recurring = isset($_POST['recurring']) ? 1 : 0;
            $price = 5.0;

            $startDateTime = new DateTime("$date $startTime");
            $endDateTime = (new DateTime("$date $startTime"))->modify('+1 hour');

            if ($startDateTime->format('H') < 8 || $endDateTime->format('H') > 22) {
                $feedback = 'Timeslot must be between 8 AM and 10 PM.';
            } else {
                $stmt = $conn->prepare("INSERT INTO laundry_slots (date, start_time, recurring, price) VALUES (:date, :start_time, :recurring, :price)");
                $stmt->bindValue(':date', $date, PDO::PARAM_STR);
                $stmt->bindValue(':start_time', $startTime, PDO::PARAM_STR);
                $stmt->bindValue(':recurring', $recurring, PDO::PARAM_INT);
                $stmt->bindValue(':price', $price, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $feedback = 'Timeslot created successfully!';
                } else {
                    $feedback = 'Error creating timeslot.';
                }
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM laundry_slots ORDER BY date, start_time");
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
</head>

<body>
    <div class="manage-default">
        <h1>Laundry Management</h1>
        <?php if ($feedback): ?>
            <p style="color: green;"><?php echo $feedback; ?></p>
        <?php endif; ?>

        <h2>Create Timeslot</h2>
        <form method="POST" action="laundry.php">
            <input type="hidden" name="action" value="create_timeslot">
            <label for="date">Date:</label>
            <input type="date" id="date" name="date" required>
            <label for="start_time">Start Time:</label>
            <input type="time" id="start_time" name="start_time" min="08:00" max="22:00" step="3600" required>
            <label for="recurring">Recurring:</label>
            <input type="checkbox" id="recurring" name="recurring" checked>
            <input type="hidden" name="price" value="5.0">
            <button type="submit" class="update-button">Create Timeslot</button>
        </form>

        <h2>Timeslots</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Recurring</th>
                    <th>Price (£)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timeslots as $timeslot): 
                    $dateFormatted = DateTime::createFromFormat('Y-m-d', $timeslot['date'])->format('d/m/Y');
                    $startTime = $timeslot['start_time'];
                    $endTime = (new DateTime($startTime))->modify('+1 hour')->format('H:00');
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dateFormatted); ?></td>
                        <td><?php echo htmlspecialchars($startTime); ?></td>
                        <td><?php echo htmlspecialchars($endTime); ?></td>
                        <td><?php echo $timeslot['recurring'] ? 'Yes' : 'No'; ?></td>
                        <td>£<?php echo htmlspecialchars($timeslot['price']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <br>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>
</body>

</html>