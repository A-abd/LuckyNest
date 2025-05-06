<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$ratingsData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$ratingType = isset($_GET['rating_type']) ? $_GET['rating_type'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$roomRatingsQuery = "SELECT r.room_number, AVG(rr.rating) as avg_rating, 
                     COUNT(rr.rating_id) as rating_count, 'room' as type,
                     MIN(rr.created_at) as first_rating, MAX(rr.created_at) as last_rating 
                     FROM room_ratings rr 
                     JOIN bookings b ON rr.booking_id = b.booking_id 
                     JOIN rooms r ON b.room_id = r.room_id";

$mealPlanRatingsQuery = "SELECT mp.name, AVG(mpr.rating) as avg_rating,
                         COUNT(mpr.rating_id) as rating_count, 'meal_plan' as type,
                         MIN(mpr.created_at) as first_rating, MAX(mpr.created_at) as last_rating 
                         FROM meal_plan_ratings mpr 
                         JOIN meal_plans mp ON mpr.meal_plan_id = mp.meal_plan_id";

$dateFilter = '';
if (!empty($startDate) && !empty($endDate)) {
    $dateFilter = " WHERE created_at BETWEEN :startDate AND :endDate";
}

if (!empty($dateFilter)) {
    $roomRatingsQuery .= $dateFilter;
    $mealPlanRatingsQuery .= $dateFilter;
}

$roomRatingsQuery .= " GROUP BY r.room_id";
$mealPlanRatingsQuery .= " GROUP BY mp.meal_plan_id";

$sqlQuery = '';
if ($ratingType == 'room' || $ratingType == 'all') {
    $sqlQuery = $roomRatingsQuery;

    if ($ratingType == 'all' && ($mealPlanRatingsQuery != '')) {
        $sqlQuery .= " UNION " . $mealPlanRatingsQuery;
    }
} elseif ($ratingType == 'meal_plan') {
    $sqlQuery = $mealPlanRatingsQuery;
}

$sqlQuery .= " ORDER BY avg_rating DESC LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sqlQuery);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

if (!empty($startDate) && !empty($endDate)) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bindParam(':startDate', $startDateTime, PDO::PARAM_STR);
    $stmt->bindParam(':endDate', $endDateTime, PDO::PARAM_STR);
}

$stmt->execute();
$ratingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countSql = "SELECT COUNT(*) as total FROM (";
$countRoomSql = "SELECT r.room_id FROM room_ratings rr 
                 JOIN bookings b ON rr.booking_id = b.booking_id 
                 JOIN rooms r ON b.room_id = r.room_id";
$countMealPlanSql = "SELECT mp.meal_plan_id FROM meal_plan_ratings mpr 
                     JOIN meal_plans mp ON mpr.meal_plan_id = mp.meal_plan_id";

if (!empty($dateFilter)) {
    $countRoomSql .= $dateFilter;
    $countMealPlanSql .= $dateFilter;
}

$countRoomSql .= " GROUP BY r.room_id";
$countMealPlanSql .= " GROUP BY mp.meal_plan_id";

if ($ratingType == 'room') {
    $countSql .= $countRoomSql;
} elseif ($ratingType == 'meal_plan') {
    $countSql .= $countMealPlanSql;
} else {
    $countSql .= $countRoomSql . " UNION " . $countMealPlanSql;
}

$countSql .= ") as total_count";

$totalRecordsStmt = $conn->prepare($countSql);
if (!empty($startDate) && !empty($endDate)) {
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    $totalRecordsStmt->bindParam(':startDate', $startDateTime, PDO::PARAM_STR);
    $totalRecordsStmt->bindParam(':endDate', $endDateTime, PDO::PARAM_STR);
}
$totalRecordsStmt->execute();
$totalRecords = $totalRecordsStmt->fetch(PDO::FETCH_ASSOC)['total'];
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
    <script src="../assets/scripts.js"></script>
    <title>Overall Ratings</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Overall Ratings</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Filter Form -->
            <form method="GET" action="ratings_overall" id="filter-form">
                <div class="filter-container">
                    <label for="rating_type">Rating Type:</label>
                    <select id="rating_type" name="rating_type" onchange="this.form.submit()">
                        <option value="all" <?php echo ($ratingType == 'all') ? 'selected' : ''; ?>>All Ratings</option>
                        <option value="room" <?php echo ($ratingType == 'room') ? 'selected' : ''; ?>>Room Ratings
                        </option>
                        <option value="meal_plan" <?php echo ($ratingType == 'meal_plan') ? 'selected' : ''; ?>>Meal Plan
                            Ratings</option>
                    </select>

                    <label for="start_date">From:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">

                    <label for="end_date">To:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">

                    <button type="submit" class="filter-button">Apply Filters</button>
                    <button type="button" onclick="window.location.href='ratings_overall'"
                        class="reset-button">Reset</button>
                </div>
            </form>

            <!-- Ratings List -->
            <h2>Ratings Summary</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Average Rating</th>
                        <th>Number of Ratings</th>
                        <th>First Rating</th>
                        <th>Last Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ratingsData as $rating): ?>
                        <tr>
                            <td><?php echo isset($rating['room_number']) ? $rating['room_number'] : $rating['name']; ?></td>
                            <td><?php echo $rating['type'] == 'room' ? 'Room' : 'Meal Plan'; ?></td>
                            <td>
                                <?php
                                $avgRating = round($rating['avg_rating'], 1);
                                echo $avgRating . ' / 5.0 ';

                                $fullStars = floor($avgRating);
                                $halfStar = ($avgRating - $fullStars) >= 0.5;

                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $fullStars) {
                                        echo '★';
                                    } elseif ($i == $fullStars + 1 && $halfStar) {
                                        echo '☆';
                                    } else {
                                        echo '☆';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo $rating['rating_count']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($rating['first_rating'])); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($rating['last_rating'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ratingsData)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No ratings found with the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $url = 'ratings_overall?rating_type=' . $ratingType;
            if (!empty($startDate)) {
                $url .= '&start_date=' . $startDate;
            }
            if (!empty($endDate)) {
                $url .= '&end_date=' . $endDate;
            }

            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>
        </div>
    </div>
</body>

</html>