<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$ratingsData = [];
$ratingType = isset($_GET['type']) ? $_GET['type'] : 'all';
$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$mealPlanId = isset($_GET['meal_plan_id']) ? (int) $_GET['meal_plan_id'] : null;
$roomId = isset($_GET['room_id']) ? (int) $_GET['room_id'] : null;

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($ratingType === 'meal_plan') {
    $query = "
        SELECT mpr.rating_id, mpr.rating, mpr.review, 
               DATE_FORMAT(mpr.created_at, '%H:%i') AS formatted_time,
               DATE_FORMAT(mpr.created_at, '%d/%m/%Y') AS formatted_date,
               u.user_id, u.forename, u.surname, u.email,
               mp.meal_plan_id, mp.name AS plan_name,
               'meal_plan' AS rating_type,
               NULL AS room_id, NULL AS room_number
        FROM meal_plan_ratings mpr
        JOIN users u ON mpr.user_id = u.user_id
        JOIN meal_plans mp ON mpr.meal_plan_id = mp.meal_plan_id
        WHERE 1=1 ";

    if ($userId) {
        $query .= "AND mpr.user_id = :userId ";
    }
    if ($mealPlanId) {
        $query .= "AND mpr.meal_plan_id = :mealPlanId ";
    }

    $query .= "ORDER BY mpr.created_at DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
} elseif ($ratingType === 'room') {
    $query = "
        SELECT rr.rating_id, rr.rating, rr.review, 
               DATE_FORMAT(rr.created_at, '%H:%i') AS formatted_time,
               DATE_FORMAT(rr.created_at, '%d/%m/%Y') AS formatted_date,
               u.user_id, u.forename, u.surname, u.email,
               r.room_id, r.room_number,
               'room' AS rating_type,
               NULL AS meal_plan_id, NULL AS plan_name
        FROM room_ratings rr
        JOIN users u ON rr.user_id = u.user_id
        JOIN bookings b ON rr.booking_id = b.booking_id
        JOIN rooms r ON b.room_id = r.room_id
        WHERE 1=1 ";

    if ($userId) {
        $query .= "AND rr.user_id = :userId ";
    }
    if ($roomId) {
        $query .= "AND r.room_id = :roomId ";
    }

    $query .= "ORDER BY rr.created_at DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
} else {
    $query = "
        (SELECT mpr.rating_id, mpr.rating, mpr.review, 
                DATE_FORMAT(mpr.created_at, '%H:%i') AS formatted_time,
                DATE_FORMAT(mpr.created_at, '%d/%m/%Y') AS formatted_date,
                u.user_id, u.forename, u.surname, u.email,
                mp.meal_plan_id, mp.name AS plan_name,
                'meal_plan' AS rating_type,
                NULL AS room_id, NULL AS room_number
         FROM meal_plan_ratings mpr
         JOIN users u ON mpr.user_id = u.user_id
         JOIN meal_plans mp ON mpr.meal_plan_id = mp.meal_plan_id
         WHERE 1=1 ";

    if ($userId) {
        $query .= "AND mpr.user_id = :userId ";
    }
    if ($mealPlanId) {
        $query .= "AND mpr.meal_plan_id = :mealPlanId ";
    }

    $query .= ")
        UNION ALL
        (SELECT rr.rating_id, rr.rating, rr.review, 
                DATE_FORMAT(rr.created_at, '%H:%i') AS formatted_time,
                DATE_FORMAT(rr.created_at, '%d/%m/%Y') AS formatted_date,
                u.user_id, u.forename, u.surname, u.email,
                NULL AS meal_plan_id, NULL AS plan_name,
                'room' AS rating_type,
                r.room_id, r.room_number
         FROM room_ratings rr
         JOIN users u ON rr.user_id = u.user_id
         JOIN bookings b ON rr.booking_id = b.booking_id
         JOIN rooms r ON b.room_id = r.room_id
         WHERE 1=1 ";

    if ($userId) {
        $query .= "AND rr.user_id = :userId ";
    }
    if ($roomId) {
        $query .= "AND r.room_id = :roomId ";
    }

    $query .= ")
        ORDER BY formatted_date DESC, formatted_time DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
}

if ($userId) {
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
}
if ($mealPlanId && ($ratingType === 'meal_plan' || $ratingType === 'all')) {
    $stmt->bindParam(':mealPlanId', $mealPlanId, PDO::PARAM_INT);
}
if ($roomId && ($ratingType === 'room' || $ratingType === 'all')) {
    $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
}

$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$ratingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($ratingType === 'meal_plan') {
    $countQuery = "SELECT COUNT(*) As total FROM meal_plan_ratings mpr 
                  JOIN meal_plans mp ON mpr.meal_plan_id = mp.meal_plan_id
                  WHERE 1=1 ";

    if ($userId) {
        $countQuery .= "AND mpr.user_id = :userId ";
    }
    if ($mealPlanId) {
        $countQuery .= "AND mpr.meal_plan_id = :mealPlanId ";
    }

    $countStmt = $conn->prepare($countQuery);
} elseif ($ratingType === 'room') {
    $countQuery = "SELECT COUNT(*) As total FROM room_ratings rr 
                  JOIN bookings b ON rr.booking_id = b.booking_id
                  JOIN rooms r ON b.room_id = r.room_id
                  WHERE 1=1 ";

    if ($userId) {
        $countQuery .= "AND rr.user_id = :userId ";
    }
    if ($roomId) {
        $countQuery .= "AND r.room_id = :roomId ";
    }

    $countStmt = $conn->prepare($countQuery);
} else {
    $countQuery = "
        SELECT (
            (SELECT COUNT(*) FROM meal_plan_ratings mpr 
             JOIN meal_plans mp ON mpr.meal_plan_id = mp.meal_plan_id
             WHERE 1=1 ";

    if ($userId) {
        $countQuery .= "AND mpr.user_id = :userId1 ";
    }
    if ($mealPlanId) {
        $countQuery .= "AND mpr.meal_plan_id = :mealPlanId ";
    }

    $countQuery .= ") + 
            (SELECT COUNT(*) FROM room_ratings rr 
             JOIN bookings b ON rr.booking_id = b.booking_id
             JOIN rooms r ON b.room_id = r.room_id
             WHERE 1=1 ";

    if ($userId) {
        $countQuery .= "AND rr.user_id = :userId2 ";
    }
    if ($roomId) {
        $countQuery .= "AND r.room_id = :roomId ";
    }

    $countQuery .= ")
        ) AS total";

    $countStmt = $conn->prepare($countQuery);
}

if ($userId) {
    if ($ratingType === 'all') {
        $countStmt->bindParam(':userId1', $userId, PDO::PARAM_INT);
        $countStmt->bindParam(':userId2', $userId, PDO::PARAM_INT);
    } else {
        $countStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    }
}
if ($mealPlanId && ($ratingType === 'meal_plan' || $ratingType === 'all')) {
    $countStmt->bindParam(':mealPlanId', $mealPlanId, PDO::PARAM_INT);
}
if ($roomId && ($ratingType === 'room' || $ratingType === 'all')) {
    $countStmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
}

$countStmt->execute();
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$userStmt = $conn->query("SELECT user_id, CONCAT(forename, ' ', surname, ' (', email, ')') AS user_name 
                         FROM users 
                         WHERE role = 'guest' 
                         ORDER BY surname, forename");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$mealPlanStmt = $conn->query("SELECT meal_plan_id, name FROM meal_plans ORDER BY name");
$mealPlans = $mealPlanStmt->fetchAll(PDO::FETCH_ASSOC);

$roomStmt = $conn->query("SELECT r.room_id, r.room_number, rt.room_type_name 
                         FROM rooms r 
                         JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                         ORDER BY r.room_number");
$rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Ratings</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Manage Ratings</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Filter Form -->
            <h3>Filter Ratings</h3>
            <form method="GET" action="ratings.php">
                <table class="filter-table">
                    <tr>
                        <td>
                            <label for="type">Rating Type:</label>
                            <select name="type" id="type">
                                <option value="all" <?php echo ($ratingType == 'all') ? 'selected' : ''; ?>>All Ratings
                                </option>
                                <option value="meal_plan" <?php echo ($ratingType == 'meal_plan') ? 'selected' : ''; ?>>
                                    Meal Plan Ratings</option>
                                <option value="room" <?php echo ($ratingType == 'room') ? 'selected' : ''; ?>>Room Ratings
                                </option>
                            </select>
                        </td>

                        <td>
                            <label for="user_id">Filter by User:</label>
                            <select name="user_id" id="user_id">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>" <?php echo ($userId == $user['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo $user['user_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="meal_plan_id">Filter by Meal Plan:</label>
                            <select name="meal_plan_id" id="meal_plan_id">
                                <option value="">All Meal Plans</option>
                                <?php foreach ($mealPlans as $plan): ?>
                                    <option value="<?php echo $plan['meal_plan_id']; ?>" <?php echo ($mealPlanId == $plan['meal_plan_id']) ? 'selected' : ''; ?>>
                                        <?php echo $plan['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <label for="room_id">Filter by Room:</label>
                            <select name="room_id" id="room_id">
                                <option value="">All Rooms</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['room_id']; ?>" <?php echo ($roomId == $room['room_id']) ? 'selected' : ''; ?>>
                                        <?php echo $room['room_number'] . ' (' . $room['room_type_name'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: center;">
                            <button type="submit" class="update-button">Apply Filters</button>
                            <a href="ratings.php" class="update-button">Reset</a>
                        </td>
                    </tr>
                </table>
            </form>

            <!-- Ratings List -->
            <h2><?php echo $ratingType === 'all' ? 'All' : ($ratingType === 'meal_plan' ? 'Meal Plan' : 'Room'); ?>
                Ratings</h2>
            <?php if ($totalRecords == 0): ?>
                <p><em>No ratings found matching your filter criteria</em></p>
            <?php endif; ?>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Time</th>
                        <th>Date</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Rating</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ratingsData as $rating): ?>
                        <tr>
                            <td><?php echo $rating['rating_id']; ?></td>
                            <td><?php echo $rating['formatted_time']; ?></td>
                            <td><?php echo $rating['formatted_date']; ?></td>
                            <td>
                                <a href="ratings.php?user_id=<?php echo $rating['user_id']; ?>">
                                    <?php echo $rating['forename'] . ' ' . $rating['surname']; ?>
                                </a>
                            </td>
                            <td><?php echo $rating['email']; ?></td>
                            <td><?php echo $rating['rating_type'] === 'meal_plan' ? 'Meal Plan' : 'Room'; ?></td>
                            <td>
                                <?php if ($rating['rating_type'] === 'meal_plan'): ?>
                                    <a href="ratings.php?type=meal_plan&meal_plan_id=<?php echo $rating['meal_plan_id']; ?>">
                                        <?php echo $rating['plan_name']; ?>
                                    </a>
                                <?php else: ?>
                                    <a href="ratings.php?type=room&room_id=<?php echo $rating['room_id']; ?>">
                                        <?php echo $rating['room_number']; ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating['rating'] ? '★' : '☆';
                                }
                                echo ' (' . $rating['rating'] . '/5)';
                                ?>
                            </td>
                            <td><?php echo $rating['review'] ? $rating['review'] : 'No review provided'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ratingsData)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">No ratings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            $urlParams = [];
            if ($ratingType !== 'all')
                $urlParams[] = "type=$ratingType";
            if ($userId)
                $urlParams[] = "user_id=$userId";
            if ($mealPlanId)
                $urlParams[] = "meal_plan_id=$mealPlanId";
            if ($roomId)
                $urlParams[] = "room_id=$roomId";

            $url = 'ratings.php' . (!empty($urlParams) ? "?" . implode("&", $urlParams) : "");
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>

        </div>
        <div id="form-overlay"></div>
</body>

</html>