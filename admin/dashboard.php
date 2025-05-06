<?php
session_start();

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'owner') {
    header('Location: ../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../include/db.php';

try {
    $userQuery = $conn->query("SELECT COUNT(*) as total_users FROM users");
    $totalUsers = $userQuery->fetch(PDO::FETCH_ASSOC)['total_users'];

    $visitorQuery = $conn->query("SELECT COUNT(*) as total_visitors FROM visitors");
    $totalVisitors = $visitorQuery->fetch(PDO::FETCH_ASSOC)['total_visitors'];

    $roomQuery = $conn->query("SELECT COUNT(*) as total_rooms FROM rooms");
    $totalRooms = $roomQuery->fetch(PDO::FETCH_ASSOC)['total_rooms'];

    $availableRoomsQuery = $conn->query("SELECT COUNT(*) as available_rooms FROM rooms WHERE status = 'Available'");
    $availableRooms = $availableRoomsQuery->fetch(PDO::FETCH_ASSOC)['available_rooms'];

    $bookingQuery = $conn->query("SELECT COUNT(*) as total_bookings FROM bookings WHERE booking_is_cancelled = 0");
    $totalBookings = $bookingQuery->fetch(PDO::FETCH_ASSOC)['total_bookings'];

    $recentBookingsQuery = $conn->query("SELECT b.booking_id, u.forename, u.surname, r.room_number, b.check_in_date, b.check_out_date 
        FROM bookings b 
        JOIN users u ON b.guest_id = u.user_id 
        JOIN rooms r ON b.room_id = r.room_id 
        WHERE b.booking_is_cancelled = 0 
        ORDER BY b.check_in_date DESC 
        LIMIT 5");
    $recentBookings = $recentBookingsQuery->fetchAll(PDO::FETCH_ASSOC);

    $mealPlansQuery = $conn->query("SELECT COUNT(*) as total_meal_plans FROM meal_plans WHERE is_active = 1");
    $totalMealPlans = $mealPlansQuery->fetch(PDO::FETCH_ASSOC)['total_meal_plans'];

    $laundryQuery = $conn->query("SELECT COUNT(*) as total_laundry_slots FROM laundry_slots WHERE is_available = 1");
    $totalLaundrySlots = $laundryQuery->fetch(PDO::FETCH_ASSOC)['total_laundry_slots'];

    function formatDate($dateString)
    {
        $date = new DateTime($dateString);
        return $date->format('d/m/Y');
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
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
    <title>Admin Dashboard</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Admin Dashboard</h1>

            <div class="dashboard-stats" style="display: flex; justify-content: space-between;">
                <div class="stat-card button-center" style="flex: 1; margin: 0 5px;">
                    <h3>Total Users</h3>
                    <p><?php echo $totalUsers; ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; margin: 0 5px;">
                    <h3>Total Visitors</h3>
                    <p><?php echo $totalVisitors; ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; margin: 0 5px;">
                    <h3>Total Rooms</h3>
                    <p><?php echo $totalRooms; ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; margin: 0 5px;">
                    <h3>Available Rooms</h3>
                    <p><?php echo $availableRooms; ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; margin: 0 5px;">
                    <h3>Total Bookings</h3>
                    <p><?php echo $totalBookings; ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; margin: 0 5px;">
                    <h3>Meal Plans</h3>
                    <p><?php echo $totalMealPlans; ?></p>
                </div>
                <div class="stat-card button-center" style="flex: 1; margin: 0 5px;">
                    <h3>Laundry Slots</h3>
                    <p><?php echo $totalLaundrySlots; ?></p>
                </div>
            </div>

            <h2>Recent Bookings</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Guest Name</th>
                        <th>Room Number</th>
                        <th>Check-in Date</th>
                        <th>Check-out Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentBookings as $booking): ?>
                        <tr>
                            <td><?php echo $booking['booking_id']; ?></td>
                            <td><?php echo $booking['forename'] . ' ' . $booking['surname']; ?></td>
                            <td><?php echo $booking['room_number']; ?></td>
                            <td><?php echo formatDate($booking['check_in_date']); ?></td>
                            <td><?php echo formatDate($booking['check_out_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="form-overlay"></div>
        </div>
</body>

</html>