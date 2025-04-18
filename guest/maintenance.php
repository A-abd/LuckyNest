<?php
session_start();

include __DIR__ . '/../include/db.php';

$feedback = '';
$userRooms = [];
$userName = '';
$userEmail = '';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userStmt = $conn->prepare("SELECT forename, surname, email FROM users WHERE user_id = :userId");
    $userStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        $userName = $userData['forename'] . ' ' . $userData['surname'];
        $userEmail = $userData['email'];
    }

    $roomStmt = $conn->prepare("
        SELECT r.room_id, r.room_number 
        FROM rooms r 
        JOIN bookings b ON r.room_id = b.room_id 
        WHERE b.guest_id = :userId 
        AND b.booking_is_cancelled = 0 
        AND b.check_in_date <= CURRENT_DATE 
        AND b.check_out_date >= CURRENT_DATE
    ");
    $roomStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $roomStmt->execute();
    $userRooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $roomNumber = $_POST['room_number'];
        $description = $_POST['description'];
        $reportDate = date('Y-m-d H:i:s');
        $status = 'Pending';
        $guestName = $_POST['guest_name'];
        $guestEmail = $_POST['guest_email'];

        $stmt = $conn->prepare("INSERT INTO maintenance_requests (room_number, description, report_date, status, guest_name, guest_email) VALUES (:roomNumber, :description, :reportDate, :status, :guestName, :guestEmail)");
        $stmt->bindParam(':roomNumber', $roomNumber, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':reportDate', $reportDate, PDO::PARAM_STR);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':guestName', $guestName, PDO::PARAM_STR);
        $stmt->bindParam(':guestEmail', $guestEmail, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $feedback = 'Maintenance request submitted successfully! Our team will address your issue as soon as possible.';
        } else {
            $feedback = 'Error submitting maintenance request. Please try again.';
        }
    }
}

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
    <title>Maintenance Request</title>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Maintenance Request</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div id="maintenance-form" class="maintenance-form">
                <h2>Submit a Maintenance Request</h2>
                <form method="POST" action="maintenance.php">
                    <input type="hidden" name="action" value="add">

                    <label for="room_number">Room Number:</label>
                    <?php if (count($userRooms) === 1): ?>
                        <input type="text" id="room_number" name="room_number"
                            value="<?php echo htmlspecialchars($userRooms[0]['room_number']); ?>" readonly>
                    <?php elseif (count($userRooms) > 1): ?>
                        <select id="room_number" name="room_number" required>
                            <?php foreach ($userRooms as $room): ?>
                                <option value="<?php echo htmlspecialchars($room['room_number']); ?>">
                                    <?php echo htmlspecialchars($room['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="room_number" name="room_number" required>
                    <?php endif; ?>

                    <label for="description">Description of Issue:</label>
                    <textarea id="description" name="description" rows="4" required></textarea>

                    <label for="guest_name">Your Name:</label>
                    <input type="text" id="guest_name" name="guest_name"
                        value="<?php echo htmlspecialchars($userName); ?>" required>

                    <label for="guest_email">Your Email:</label>
                    <input type="email" id="guest_email" name="guest_email"
                        value="<?php echo htmlspecialchars($userEmail); ?>" required>

                    <button type="submit" class="submit-button">Submit Request</button>
                </form>
            </div>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] != 'guest'): ?>
                <div class="admin-link">
                    <a href="view_maintenance.php" class="button">View & Manage Maintenance Requests</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>