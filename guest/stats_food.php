<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest') {
    header('Location: ../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$user_id = $_SESSION['user_id'];
$feedback = '';
$mealPlanData = [];
$mealConsumptionData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$stmt = $conn->prepare("
    SELECT mp.meal_plan_id, mp.name, mp.meal_plan_type, mp.price, 
           mpul.start_date, mpul.end_date, mpul.is_paid 
    FROM meal_plans mp
    JOIN meal_plan_user_link mpul ON mp.meal_plan_id = mpul.meal_plan_id
    WHERE mpul.user_id = :user_id AND mpul.is_cancelled = 0
    ORDER BY mpul.start_date DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mealPlanData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->prepare("
    SELECT COUNT(*) As total 
    FROM meal_plan_user_link 
    WHERE user_id = :user_id AND is_cancelled = 0
");
$totalRecordsQuery->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$totalRecordsQuery->execute();
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$consumptionStmt = $conn->prepare("
    SELECT 
        m.name, 
        m.meal_type, 
        m.price,
        COUNT(*) as times_consumed,
        SUM(m.price) as total_cost
    FROM meals m
    JOIN meal_plan_items_link mpil ON m.meal_id = mpil.meal_id
    JOIN meal_plan_user_link mpul ON mpil.meal_plan_id = mpul.meal_plan_id
    WHERE mpul.user_id = :user_id AND mpul.is_cancelled = 0
    GROUP BY m.meal_id, m.name, m.meal_type, m.price
    ORDER BY times_consumed DESC
    LIMIT 10
");

$consumptionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$consumptionStmt->execute();
$mealConsumptionData = $consumptionStmt->fetchAll(PDO::FETCH_ASSOC);

$totalConsumptionStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_meals,
        SUM(m.price) as total_cost
    FROM meals m
    JOIN meal_plan_items_link mpil ON m.meal_id = mpil.meal_id
    JOIN meal_plan_user_link mpul ON mpil.meal_plan_id = mpul.meal_plan_id
    WHERE mpul.user_id = :user_id AND mpul.is_cancelled = 0
");
$totalConsumptionStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$totalConsumptionStmt->execute();
$totalConsumption = $totalConsumptionStmt->fetch(PDO::FETCH_ASSOC);


$currentDate = date('Y-m-d');
$upcomingMealsStmt = $conn->prepare("
    SELECT 
        m.name,
        m.meal_type,
        mpil.day_number,
        CURDATE() as base_date
    FROM meals m
    JOIN meal_plan_items_link mpil ON m.meal_id = mpil.meal_id
    JOIN meal_plan_user_link mpul ON mpil.meal_plan_id = mpul.meal_plan_id
    WHERE mpul.user_id = :user_id 
      AND mpul.is_cancelled = 0
      AND mpil.day_number BETWEEN 0 AND 7
    ORDER BY mpil.day_number, 
        CASE m.meal_type 
            WHEN 'Breakfast' THEN 1 
            WHEN 'Lunch' THEN 2 
            WHEN 'Dinner' THEN 3 
            ELSE 4 
        END
    LIMIT 20
");
$upcomingMealsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$upcomingMealsStmt->execute();
$upcomingMeals = $upcomingMealsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($upcomingMeals as &$meal) {
    $meal['meal_date'] = date('Y-m-d', strtotime($meal['base_date'] . ' + ' . $meal['day_number'] . ' days'));
}
unset($meal); 

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
    <title>My Meal Plans</title>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>My Meal Plans & Consumption</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Summary Stats -->
            <div class="stats-summary">
                <div class="stat-card">
                    <h3>Total Meals</h3>
                    <p class="stat-number">
                        <?php echo isset($totalConsumption['total_meals']) ? $totalConsumption['total_meals'] : 0; ?>
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Total Cost</h3>
                    <p class="stat-number">
                        £<?php echo isset($totalConsumption['total_cost']) ? number_format($totalConsumption['total_cost'], 2) : '0.00'; ?>
                    </p>
                </div>
                <div class="stat-card">
                    <h3>Active Plans</h3>
                    <p class="stat-number"><?php echo count($mealPlanData); ?></p>
                </div>
            </div>



            <!-- Upcoming Meals -->
            <h2>Upcoming Meals (Next 7 Days)</h2>
            <?php if (count($upcomingMeals) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Meal Type</th>
                            <th>Meal Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingMeals as $meal): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($meal['meal_date'])); ?></td>
                                <td><?php echo $meal['meal_type']; ?></td>
                                <td><?php echo $meal['name']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No upcoming meals scheduled.</p>
            <?php endif; ?>

            <!-- Most Consumed Meals -->
            <h2>Most Consumed Meals</h2>
            <?php if (count($mealConsumptionData) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Meal Name</th>
                            <th>Meal Type</th>
                            <th>Times Consumed</th>
                            <th>Unit Price</th>
                            <th>Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mealConsumptionData as $meal): ?>
                            <tr>
                                <td><?php echo $meal['name']; ?></td>
                                <td><?php echo $meal['meal_type']; ?></td>
                                <td><?php echo $meal['times_consumed']; ?></td>
                                <td>£<?php echo number_format($meal['price'], 2); ?></td>
                                <td>£<?php echo number_format($meal['total_cost'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No meal consumption data available.</p>
            <?php endif; ?>

            <!-- Active Meal Plans -->
            <h2>My Active Meal Plans</h2>
            <?php if (count($mealPlanData) > 0): ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Price</th>
                            <th>Payment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mealPlanData as $plan): ?>
                            <tr>
                                <td><?php echo $plan['name']; ?></td>
                                <td><?php echo $plan['meal_plan_type']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($plan['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($plan['end_date'])); ?></td>
                                <td>£<?php echo number_format($plan['price'], 2); ?></td>
                                <td><?php echo $plan['is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $url = 'stats_food';
                echo generatePagination($page, $totalPages, $url);
                ?>
            <?php else: ?>
                <p>You don't have any active meal plans.</p>
                <div class="button-center">
                    <a href="meal_plans" class="update-button">View Available Meal Plans</a>
                </div>
            <?php endif; ?>


        </div>
    </div>
</body>

</html>