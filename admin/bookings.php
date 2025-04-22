<?php

session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$bookingData = [];
$guestOptions = [];
$roomOptions = [];
$currentDate = date("Y-m-d");

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$guestResult = $conn->query("SELECT user_id, forename, surname FROM users WHERE role = 'guest'");
while ($row = $guestResult->fetch(PDO::FETCH_ASSOC)) {
    $guestOptions[] = $row;
}

$roomResult = $conn->query("
    SELECT r.room_id, r.room_number, rt.room_type_name, rt.rate_monthly, rt.deposit_amount,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM bookings b
                WHERE b.room_id = r.room_id
                AND b.booking_is_cancelled = 0
                AND '$currentDate' BETWEEN b.check_in_date AND b.check_out_date
            ) THEN 'occupied'
            ELSE 'unoccupied'
        END AS room_status
    FROM rooms r
    JOIN room_types rt ON r.room_type_id = rt.room_type_id
");

while ($row = $roomResult->fetch(PDO::FETCH_ASSOC)) {
    $roomOptions[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            $roomId = $_POST['room_id'];
            $guestId = $_POST['guest_id'];
            $checkInDate = $_POST['check_in_date'];
            $checkOutDate = $_POST['check_out_date'];

            $checkIn = new DateTime($checkInDate);
            $checkOut = new DateTime($checkOutDate);

            $formattedCheckInDate = $checkIn->format('Y-m-d');
            $formattedCheckOutDate = $checkOut->format('Y-m-d');

            $interval = $checkIn->diff($checkOut);
            $totalDays = $interval->days;

            $totalMonths = $interval->y * 12 + $interval->m;

            $isWholeMonth = ($interval->d === 0);

            if ($totalMonths < 6 || !$isWholeMonth) {
                $feedback = 'Error: Bookings must be a minimum of 6 whole months (no weeks or partial months allowed, booking must be whole months).';
            } else {
                $roomDetailsQuery = $conn->prepare("SELECT r.room_id, rt.rate_monthly, rt.deposit_amount
                    FROM rooms r
                    JOIN room_types rt ON r.room_type_id = rt.room_type_id
                    WHERE r.room_id = :roomId");
                $roomDetailsQuery->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                $roomDetailsQuery->execute();
                $roomDetails = $roomDetailsQuery->fetch(PDO::FETCH_ASSOC);
                $monthlyRate = $roomDetails['rate_monthly'];
                $depositAmount = $roomDetails['deposit_amount'];

                $totalPrice = $totalMonths * $monthlyRate;

                $isCancelled = isset($_POST['booking_is_cancelled']) ? 1 : 0;
                $isPaid = isset($_POST['booking_is_paid']) ? 1 : 0;

                $overlapQuery = $conn->prepare("SELECT COUNT(*) as count
                    FROM bookings
                    WHERE guest_id = :guestId
                    AND booking_is_cancelled = 0
                    AND ((check_in_date <= :checkOutDate AND check_out_date >= :checkInDate))");
                $overlapQuery->bindParam(':guestId', $guestId, PDO::PARAM_INT);
                $overlapQuery->bindParam(':checkInDate', $formattedCheckInDate, PDO::PARAM_STR);
                $overlapQuery->bindParam(':checkOutDate', $formattedCheckOutDate, PDO::PARAM_STR);
                $overlapQuery->execute();
                $overlapResult = $overlapQuery->fetch(PDO::FETCH_ASSOC);

                if ($overlapResult['count'] > 0) {
                    $feedback = 'Error: You already have a booking during the selected dates.';
                } else {
                    $roomOverlapQuery = $conn->prepare("SELECT COUNT(*) as count
                        FROM bookings
                        WHERE room_id = :roomId
                        AND booking_is_cancelled = 0
                        AND ((check_in_date <= :checkOutDate AND check_out_date >= :checkInDate))");
                    $roomOverlapQuery->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                    $roomOverlapQuery->bindParam(':checkInDate', $formattedCheckInDate, PDO::PARAM_STR);
                    $roomOverlapQuery->bindParam(':checkOutDate', $formattedCheckOutDate, PDO::PARAM_STR);
                    $roomOverlapQuery->execute();
                    $roomOverlapResult = $roomOverlapQuery->fetch(PDO::FETCH_ASSOC);

                    if ($roomOverlapResult['count'] > 0) {
                        $feedback = 'Error: Room is already booked for the selected dates.';
                    } else {
                        try {
                            $conn->beginTransaction();

                            $stmt = $conn->prepare("INSERT INTO bookings (room_id, guest_id, check_in_date, check_out_date, total_price, booking_is_cancelled, booking_is_paid)
                                VALUES (:roomId, :guestId, :checkInDate, :checkOutDate, :totalPrice, :isCancelled, :isPaid)");
                            $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                            $stmt->bindParam(':guestId', $guestId, PDO::PARAM_INT);
                            $stmt->bindParam(':checkInDate', $formattedCheckInDate, PDO::PARAM_STR);
                            $stmt->bindParam(':checkOutDate', $formattedCheckOutDate, PDO::PARAM_STR);
                            $stmt->bindParam(':totalPrice', $totalPrice, PDO::PARAM_STR);
                            $stmt->bindParam(':isCancelled', $isCancelled, PDO::PARAM_INT);
                            $stmt->bindParam(':isPaid', $isPaid, PDO::PARAM_INT);
                            $stmt->execute();

                            $bookingId = $conn->lastInsertId();

                            $depositStatus = 'pending';
                            $depositStmt = $conn->prepare("INSERT INTO deposits (booking_id, amount, status)
                                VALUES (:bookingId, :amount, :status)");
                            $depositStmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
                            $depositStmt->bindParam(':amount', $depositAmount, PDO::PARAM_STR);
                            $depositStmt->bindParam(':status', $depositStatus, PDO::PARAM_STR);
                            $depositStmt->execute();

                            $conn->commit();

                            $feedback = 'Booking added successfully! A security deposit of ' . $depositAmount . ' has been created.';
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $feedback = 'Error adding booking: ' . $e->getMessage();
                        }
                    }
                }
            }
        } elseif ($action === 'edit') {
            if (isset($_POST['booking_id']) && isset($_POST['guest_id'])) {
                $bookingId = $_POST['booking_id'];
                $roomId = $_POST['room_id'];
                $guestId = $_POST['guest_id'];
                $checkInDate = $_POST['check_in_date'];
                $checkOutDate = $_POST['check_out_date'];

                $checkIn = new DateTime($checkInDate);
                $checkOut = new DateTime($checkOutDate);

                $formattedCheckInDate = $checkIn->format('Y-m-d');
                $formattedCheckOutDate = $checkOut->format('Y-m-d');

                $interval = $checkIn->diff($checkOut);
                $totalDays = $interval->days;

                $totalMonths = $interval->y * 12 + $interval->m;

                $isWholeMonth = ($interval->d === 0);

                if ($totalMonths < 6 || !$isWholeMonth) {
                    $feedback = 'Error: Bookings must be a minimum of 6 whole months (no weeks or partial months allowed).';
                } else {
                    $roomPriceQuery = $conn->prepare("SELECT rt.rate_monthly
                        FROM rooms r
                        JOIN room_types rt ON r.room_type_id = rt.room_type_id
                        WHERE r.room_id = :roomId");
                    $roomPriceQuery->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                    $roomPriceQuery->execute();
                    $roomPriceResult = $roomPriceQuery->fetch(PDO::FETCH_ASSOC);
                    $monthlyRate = $roomPriceResult['rate_monthly'];

                    $totalPrice = $totalMonths * $monthlyRate;

                    $isCancelled = isset($_POST['booking_is_cancelled']) ? 1 : 0;
                    $isPaid = isset($_POST['booking_is_paid']) ? 1 : 0;

                    $overlapQuery = $conn->prepare("SELECT COUNT(*) as count
                        FROM bookings
                        WHERE guest_id = :guestId
                        AND booking_id != :bookingId
                        AND booking_is_cancelled = 0
                        AND ((check_in_date <= :checkOutDate AND check_out_date >= :checkInDate))");
                    $overlapQuery->bindParam(':guestId', $guestId, PDO::PARAM_INT);
                    $overlapQuery->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
                    $overlapQuery->bindParam(':checkInDate', $formattedCheckInDate, PDO::PARAM_STR);
                    $overlapQuery->bindParam(':checkOutDate', $formattedCheckOutDate, PDO::PARAM_STR);
                    $overlapQuery->execute();
                    $overlapResult = $overlapQuery->fetch(PDO::FETCH_ASSOC);

                    if ($overlapResult['count'] > 0) {
                        $feedback = 'Error: You already have a booking during the selected dates.';
                    } else {
                        $roomOverlapQuery = $conn->prepare("SELECT COUNT(*) as count
                            FROM bookings
                            WHERE room_id = :roomId
                            AND booking_id != :bookingId
                            AND booking_is_cancelled = 0
                            AND ((check_in_date <= :checkOutDate AND check_out_date >= :checkInDate))");
                        $roomOverlapQuery->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                        $roomOverlapQuery->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
                        $roomOverlapQuery->bindParam(':checkInDate', $formattedCheckInDate, PDO::PARAM_STR);
                        $roomOverlapQuery->bindParam(':checkOutDate', $formattedCheckOutDate, PDO::PARAM_STR);
                        $roomOverlapQuery->execute();
                        $roomOverlapResult = $roomOverlapQuery->fetch(PDO::FETCH_ASSOC);

                        if ($roomOverlapResult['count'] > 0) {
                            $feedback = 'Error: Room is already booked for the selected dates.';
                        } else {
                            $stmt = $conn->prepare("UPDATE bookings
                                SET room_id = :roomId,
                                guest_id = :guestId,
                                check_in_date = :checkInDate,
                                check_out_date = :checkOutDate,
                                total_price = :totalPrice,
                                booking_is_cancelled = :isCancelled,
                                booking_is_paid = :isPaid
                                WHERE booking_id = :bookingId");
                            $stmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
                            $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                            $stmt->bindParam(':guestId', $guestId, PDO::PARAM_INT);
                            $stmt->bindParam(':checkInDate', $formattedCheckInDate, PDO::PARAM_STR);
                            $stmt->bindParam(':checkOutDate', $formattedCheckOutDate, PDO::PARAM_STR);
                            $stmt->bindParam(':totalPrice', $totalPrice, PDO::PARAM_STR);
                            $stmt->bindParam(':isCancelled', $isCancelled, PDO::PARAM_INT);
                            $stmt->bindParam(':isPaid', $isPaid, PDO::PARAM_INT);

                            if ($stmt->execute()) {
                                $feedback = 'Booking updated successfully!';
                            } else {
                                $feedback = 'Error updating booking.';
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $bookingId = $_POST['booking_id'];

            $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = :bookingId");
            $stmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Booking deleted successfully!';
            } else {
                $feedback = 'Error deleting booking.';
            }
        }
    }
}

$stmt = $conn->prepare("SELECT b.*, r.room_number, rt.room_type_name, u.forename, u.surname,
        COALESCE(d.status, 'none') AS deposit_status, COALESCE(d.amount, 0) AS deposit_amount
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN room_types rt ON r.room_type_id = rt.room_type_id
    JOIN users u ON b.guest_id = u.user_id
    LEFT JOIN deposits d ON b.booking_id = d.booking_id
    LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$bookingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM bookings");
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

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
    <title>Manage Bookings</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Manage Bookings</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Add Booking Button -->
            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Booking</button>
            </div>

            <!-- Add Booking Form -->
            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Add New Booking</h2>
                <form method="POST" action="bookings.php">
                    <input type="hidden" name="action" value="add">
                    <label for="room_id">Room:</label>
                    <select id="room_id" name="room_id" required>
                        <?php foreach ($roomOptions as $room): ?>
                            <option value="<?php echo $room['room_id']; ?>">
                                Room <?php echo $room['room_number']; ?> (<?php echo $room['room_type_name']; ?>) - Deposit:
                                <?php echo $room['deposit_amount']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="guest_id">Guest:</label>
                    <select id="guest_id" name="guest_id" required>
                        <?php foreach ($guestOptions as $guest): ?>
                            <option value="<?php echo $guest['user_id']; ?>">
                                <?php echo $guest['forename'] . ' ' . $guest['surname']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="check_in_date">Check-in Date:</label>
                    <input type="date" id="check_in_date" name="check_in_date" required>
                    <label for="check_out_date">Check-out Date:</label>
                    <input type="date" id="check_out_date" name="check_out_date" required>
                    <label for="booking_is_cancelled">
                        <input type="checkbox" name="booking_is_cancelled"> Cancelled
                    </label>
                    <label for="booking_is_paid">
                        <input type="checkbox" name="booking_is_paid"> Paid
                    </label>
                    <button type="submit" class="update-button">Add Booking</button>
                </form>
            </div>

            <!-- Booking List -->
            <h2>Booking List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Room</th>
                        <th>Room Type</th>
                        <th>Guest</th>
                        <th>Check-in Date</th>
                        <th>Check-out Date</th>
                        <th>Total Price</th>
                        <th>Cancelled</th>
                        <th>Paid</th>
                        <th>Deposit Status</th>
                        <th>Room Available Today</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookingData as $booking): ?>
                        <tr>
                            <td><?php echo $booking['booking_id']; ?></td>
                            <td><?php echo $booking['room_number']; ?></td>
                            <td><?php echo $booking['room_type_name']; ?></td>
                            <td><?php echo $booking['forename'] . ' ' . $booking['surname']; ?></td>
                            <td><?php echo (new DateTime($booking['check_in_date']))->format("d/m/Y"); ?></td>
                            <td><?php echo (new DateTime($booking['check_out_date']))->format("d/m/Y"); ?></td>
                            <td><?php echo $booking['total_price']; ?></td>
                            <td><?php echo $booking['booking_is_cancelled'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $booking['booking_is_paid'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo ucfirst($booking['deposit_status']); ?>
                                (<?php echo $booking['deposit_amount']; ?>)</td>
                            <td><?php $isAvailableToday = true;
                            foreach ($roomOptions as $room) {
                                if ($room['room_id'] == $booking['room_id'] && $room['room_status'] === 'occupied') {
                                    $isAvailableToday = false;
                                    break;
                                }
                            }
                            echo $isAvailableToday ? 'Yes' : 'No';
                            ?></td>
                            <td>
                                <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $booking['booking_id']; ?>')"
                                    class="update-button">Edit</button>
                                <div id="edit-form-<?php echo $booking['booking_id']; ?>" class="edit-form">
                                    <button type="button" class="close-button"
                                        onclick="LuckyNest.toggleForm('edit-form-<?php echo $booking['booking_id']; ?>')">✕</button>
                                    <form method="POST" action="bookings.php" style="display:inline;">
                                        <h2>Edit Booking</h2>
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="booking_id"
                                            value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="guest_id" value="<?php echo $booking['guest_id']; ?>">

                                        <label for="room_id_<?php echo $booking['booking_id']; ?>">Room ID:</label>
                                        <input type="text" id="room_id_<?php echo $booking['booking_id']; ?>" name="room_id"
                                            value="<?php echo $booking['room_id']; ?>" required>

                                        <label for="check_in_date_<?php echo $booking['booking_id']; ?>">Check-In
                                            Date:</label>
                                        <input type="date" id="check_in_date_<?php echo $booking['booking_id']; ?>"
                                            name="check_in_date" value="<?php echo $booking['check_in_date']; ?>" required>

                                        <label for="check_out_date_<?php echo $booking['booking_id']; ?>">Check-Out
                                            Date:</label>
                                        <input type="date" id="check_out_date_<?php echo $booking['booking_id']; ?>"
                                            name="check_out_date" value="<?php echo $booking['check_out_date']; ?>"
                                            required>

                                        <label for="booking_is_cancelled_<?php echo $booking['booking_id']; ?>">
                                            <input type="checkbox"
                                                id="booking_is_cancelled_<?php echo $booking['booking_id']; ?>"
                                                name="booking_is_cancelled" <?php echo $booking['booking_is_cancelled'] ? 'checked' : ''; ?>> Cancelled
                                        </label>

                                        <label for="booking_is_paid_<?php echo $booking['booking_id']; ?>">
                                            <input type="checkbox"
                                                id="booking_is_paid_<?php echo $booking['booking_id']; ?>"
                                                name="booking_is_paid" <?php echo $booking['booking_is_paid'] ? 'checked' : ''; ?>>
                                            Paid
                                        </label>

                                        <div class="button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button"
                                                onclick="document.getElementById('delete-form-<?php echo $booking['booking_id']; ?>').submit(); return false;">Delete</button>
                                        </div>
                                    </form>

                                    <form id="delete-form-<?php echo $booking['booking_id']; ?>" method="POST"
                                        action="bookings.php" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="booking_id"
                                            value="<?php echo $booking['booking_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $url = 'bookings.php';
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>
        </div>
        <div id="form-overlay"></div>
    </div>

    <!--pass bookedDates data to JS here as it is generated in the PHP-->
    <script id="booked-dates" type="application/json">
    <?php echo json_encode(array_map(function ($booking) {
            return [
            'booking_id' => $booking['booking_id'],
            'room_id' => $booking['room_id'],
            'guest_id' => $booking['guest_id'],
            'start' => $booking['check_in_date'],
            'end' => $booking['check_out_date']
        ];
    }, $bookingData)); ?>
</script>
</body>

</html>