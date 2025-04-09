<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$occupancyReportData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+1 month'));

$query = "
    SELECT 
        r.room_id, 
        r.room_number, 
        rt.room_type_name,
        r.status,
        b.check_in_date,
        b.check_out_date,
        CONCAT(u.forename, ' ', u.surname) AS guest_name
    FROM 
        rooms r
    LEFT JOIN 
        room_types rt ON r.room_type_id = rt.room_type_id
    LEFT JOIN 
        bookings b ON r.room_id = b.room_id AND b.booking_is_cancelled = 0
        AND (
            (b.check_in_date BETWEEN :start_date AND :end_date) OR 
            (b.check_out_date BETWEEN :start_date AND :end_date) OR 
            (:start_date BETWEEN b.check_in_date AND b.check_out_date)
        )
    LEFT JOIN 
        users u ON b.guest_id = u.user_id
    ORDER BY 
        r.room_number
    LIMIT :records_per_page OFFSET :offset
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
$stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
$stmt->bindParam(':records_per_page', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$occupancyReportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->prepare("
    SELECT COUNT(DISTINCT r.room_id) AS total 
    FROM rooms r
    LEFT JOIN bookings b ON r.room_id = b.room_id AND b.booking_is_cancelled = 0
    AND (
        (b.check_in_date BETWEEN :start_date AND :end_date) OR 
        (b.check_out_date BETWEEN :start_date AND :end_date) OR 
        (:start_date BETWEEN b.check_in_date AND b.check_out_date)
    )
");
$totalRecordsQuery->bindParam(':start_date', $startDate, PDO::PARAM_STR);
$totalRecordsQuery->bindParam(':end_date', $endDate, PDO::PARAM_STR);
$totalRecordsQuery->execute();
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$summaryQuery = $conn->prepare("
    SELECT 
        COUNT(DISTINCT r.room_id) AS total_rooms,
        COUNT(DISTINCT CASE WHEN b.room_id IS NOT NULL THEN r.room_id END) AS occupied_rooms,
        ROUND(COUNT(DISTINCT CASE WHEN b.room_id IS NOT NULL THEN r.room_id END) * 100.0 / COUNT(DISTINCT r.room_id), 2) AS occupancy_rate
    FROM 
        rooms r
    LEFT JOIN 
        bookings b ON r.room_id = b.room_id AND b.booking_is_cancelled = 0
        AND (
            (b.check_in_date BETWEEN :start_date AND :end_date) OR 
            (b.check_out_date BETWEEN :start_date AND :end_date) OR 
            (:start_date BETWEEN b.check_in_date AND b.check_out_date)
        )
");
$summaryQuery->bindParam(':start_date', $startDate, PDO::PARAM_STR);
$summaryQuery->bindParam(':end_date', $endDate, PDO::PARAM_STR);
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
    <title>Guest Occupancy Report</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Guest Occupancy Report</h1>

            <div class="center-container">
                <!-- Date Range Filter -->
                <form method="GET" action="report_occupancy.php">
                    <div class="date-filter">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date"
                            value="<?php echo htmlspecialchars($startDate); ?>">

                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date"
                            value="<?php echo htmlspecialchars($endDate); ?>">

                        <button type="submit" class="update-button">Filter</button>
                    </div>
                </form>

                <!-- Summary Statistics -->
                <div class="summary-stats">
                    <h2>Summary</h2>
                    <p>Total Rooms: <?php echo $summary['total_rooms']; ?></p>
                    <p>Occupied Rooms: <?php echo $summary['occupied_rooms']; ?></p>
                    <p>Occupancy Rate: <?php echo $summary['occupancy_rate']; ?>%</p>
                </div>

                <!-- Occupancy Report Table -->
                <h2>Room Occupancy Details</h2>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Room Number</th>
                            <th>Room Type</th>
                            <th>Status</th>
                            <th>Check-In Date</th>
                            <th>Check-Out Date</th>
                            <th>Guest Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($occupancyReportData as $room): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($room['room_type_name']); ?></td>
                                <td><?php echo htmlspecialchars($room['status']); ?></td>
                                <td><?php echo $room['check_in_date'] ? htmlspecialchars($room['check_in_date']) : 'N/A'; ?>
                                </td>
                                <td><?php echo $room['check_out_date'] ? htmlspecialchars($room['check_out_date']) : 'N/A'; ?>
                                </td>
                                <td><?php echo $room['guest_name'] ? htmlspecialchars($room['guest_name']) : 'Vacant'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $url = 'report_occupancy.php';
                $urlWithParams = $url . "?start_date=" . urlencode($startDate) . "&end_date=" . urlencode($endDate);
                echo generatePagination($page, $totalPages, $urlWithParams);
                ?>

                <br>
                <div class="back-button-container">
                    <a href="dashboard.php" class="button">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>