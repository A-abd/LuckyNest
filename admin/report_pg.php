<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$selectedGuestId = isset($_GET['guest_id']) ? (int) $_GET['guest_id'] : null;
$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$guestsQuery = $conn->prepare("SELECT user_id, forename, surname FROM users WHERE role = 'guest' ORDER BY surname, forename");
$guestsQuery->execute();
$guests = $guestsQuery->fetchAll(PDO::FETCH_ASSOC);

$query = "
    SELECT 
        b.booking_id,
        r.room_number,
        rt.room_type_name,
        b.check_in_date,
        b.check_out_date,
        b.total_price,
        b.booking_is_cancelled,
        b.booking_is_paid,
        CONCAT(u.forename, ' ', u.surname) AS guest_name
        FRoM bookings b
        JOIN  rooms r ON b.room_id = r.room_id
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        JOIN users u ON b.guest_id = u.user_id
        WHERE 1=1
";

$params = [];

if ($selectedGuestId) {
    $query .= " AND u.user_id = :guest_id";
    $params[':guest_id'] = $selectedGuestId;
}

$query .= " ORDER BY b.check_in_date DESC LIMIT :records_per_page OFFSET :offset";

$stmt = $conn->prepare($query);

if ($selectedGuestId) {
    $stmt->bindParam(':guest_id', $selectedGuestId, PDO::PARAM_INT);
}
$stmt->bindParam(':records_per_page', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$bookingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM bookings b
    JOIN users u ON b.guest_id = u.user_id
    WHERE 1=1 " . ($selectedGuestId ? " AND u.user_id = :guest_id" : "")
);

if ($selectedGuestId) {
    $totalRecordsQuery->bindParam(':guest_id', $selectedGuestId, PDO::PARAM_INT);
}
$totalRecordsQuery->execute();
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$summaryQuery = $conn->prepare("
    SELECT 
        COUNT(*) AS total_bookings,
        SUM(CASE WHEN booking_is_cancelled = 1 THEN 1 ELSE 0 END) AS cancelled_bookings,
        SUM(CASE WHEN booking_is_paid = 1 THEN 1 ELSE 0 END) AS paid_bookings,
        ROUND(SUM(total_price), 2) AS total_spent
    FROM 
        bookings b
    JOIN 
        users u ON b.guest_id = u.user_id
    WHERE 
        1=1 " . ($selectedGuestId ? " AND u.user_id = :guest_id" : "")
);

if ($selectedGuestId) {
    $summaryQuery->bindParam(':guest_id', $selectedGuestId, PDO::PARAM_INT);
}
$summaryQuery->execute();
$summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

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
    <title>Guest Booking Report</title>
</head>

<div>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">

        <div class="content-container">
            <h1>Guest Booking Report</h1>


            <form method="GET" action="report_pg.php" class="center-only">
                <label for="guest_id">Select Guest:</label>
                <select name="guest_id" id="guest_id" onchange="this.form.submit()">
                    <option value="">All Guests</option>
                    <?php foreach ($guests as $guest): ?>
                        <option value="<?php echo $guest['user_id']; ?>" <?php echo ($selectedGuestId == $guest['user_id'] ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($guest['surname'] . ', ' . $guest['forename']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="center-only">
                <h2>Summary</h2>
                <p>Total Bookings: <?php echo $summary['total_bookings']; ?></p>
                <p>Cancelled Bookings: <?php echo $summary['cancelled_bookings']; ?></p>
                <p>Paid Bookings: <?php echo $summary['paid_bookings']; ?></p>
                <p>Total Amount Spent: £<?php echo $summary['total_spent']; ?></p>
            </div>

            <table border="1">
                <thead>
                    <tr>
                        <th>Guest Name</th>
                        <th>Room Number</th>
                        <th>Room Type</th>
                        <th>Check-In Date</th>
                        <th>Check-Out Date</th>
                        <th>Total Price</th>
                        <th>Cancelled</th>
                        <th>Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookingData as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($booking['room_type_name']); ?></td>
                            <td><?php echo $booking['check_in_date']; ?></td>
                            <td><?php echo $booking['check_out_date']; ?></td>
                            <td>£<?php echo $booking['total_price']; ?></td>
                            <td><?php echo $booking['booking_is_cancelled'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $booking['booking_is_paid'] ? 'Yes' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $url = 'report_pg.php';
            $urlWithParams = $selectedGuestId ? $url . "?guest_id=" . $selectedGuestId : $url;
            echo generatePagination($page, $totalPages, $urlWithParams);
            ?>
        </div>
    </div>
    </body>

</html>