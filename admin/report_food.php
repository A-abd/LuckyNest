<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$foodReportData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+1 month'));

$mealPlanFilter = isset($_GET['meal_plan']) ? $_GET['meal_plan'] : '';
$mealTypeFilter = isset($_GET['meal_type']) ? $_GET['meal_type'] : '';

$query = "
    SELECT 
        u.user_id,
        CONCAT(u.forename, ' ', u.surname) AS guest_name,
        mp.name AS meal_plan_name,
        mp.meal_plan_type,
        m.name AS meal_name,
        m.meal_type,
        m.price AS meal_price,
        GROUP_CONCAT(DISTINCT mdt.name) AS dietary_tags,
        mpul.is_cancelled,
        mpul.is_paid
    FROM 
        meal_plan_user_link mpul
    JOIN 
        users u ON mpul.user_id = u.user_id
        JOIN 
        meal_plans mp ON mpul.meal_plan_id = mp.meal_plan_id
    JOIN 
        meal_plan_items_link mpil ON mp.meal_plan_id = mpil.meal_plan_id
    JOIN 
        meals m ON mpil.meal_id = m.meal_id
    LEFT JOIN 
        meal_dietary_tags_link mdtl ON m.meal_id = mdtl.meal_id
    LEFT JOIN 
        meal_dietary_tags mdt ON mdtl.meal_dietary_tag_id = mdt.meal_dietary_tag_id
    WHERE 
        1=1
";

$params = [];
if (!empty($mealPlanFilter)) {
    $query .= " AND mp.name = :meal_plan";
    $params[':meal_plan'] = $mealPlanFilter;
}
if (!empty($mealTypeFilter)) {
    $query .= " AND m.meal_type = :meal_type";
    $params[':meal_type'] = $mealTypeFilter;
}

$query .= "
    GROUP BY 
        u.user_id, 
        u.forename, 
        u.surname, 
        mp.name, 
        mp.meal_plan_type, 
        m.name, 
        m.meal_type, 
        m.price,
        mpul.is_cancelled,
        mpul.is_paid
    ORDER BY 
        u.surname, 
        u.forename, 
        mp.name, 
        m.name
    LIMIT :records_per_page OFFSET :offset
";

$stmt = $conn->prepare($query);

$stmt->bindValue(':records_per_page', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$foodReportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "
    SELECT 
        COUNT(DISTINCT u.user_id) AS total 
    FROM 
        meal_plan_user_link mpul
    JOIN 
        users u ON mpul.user_id = u.user_id
    JOIN 
        meal_plans mp ON mpul.meal_plan_id = mp.meal_plan_id
    JOIN 
        meal_plan_items_link mpil ON mp.meal_plan_id = mpil.meal_plan_id
    JOIN 
        meals m ON mpil.meal_id = m.meal_id
    WHERE 
        1=1
";

$countStmt = $conn->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$summaryQuery = $conn->prepare("
    SELECT 
        COUNT(DISTINCT mpul.user_id) AS total_meal_plan_users,
        ROUND(AVG(m.price), 2) AS average_meal_price,
        SUM(m.price) AS total_meal_value,
        COUNT(DISTINCT m.meal_id) AS unique_meals_consumed
    FROM 
        meal_plan_user_link mpul
    JOIN 
        meal_plans mp ON mpul.meal_plan_id = mp.meal_plan_id
    JOIN 
        meal_plan_items_link mpil ON mp.meal_plan_id = mpil.meal_plan_id
    JOIN 
        meals m ON mpil.meal_id = m.meal_id
");
$summaryQuery->execute();
$summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

$mealPlansStmt = $conn->query("SELECT DISTINCT name FROM meal_plans");
$mealPlans = $mealPlansStmt->fetchAll(PDO::FETCH_COLUMN);

$mealTypesStmt = $conn->query("SELECT DISTINCT meal_type FROM meals");
$mealTypes = $mealTypesStmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Food Consumption Report</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Food Consumption Report</h1>

            <!-- Filters -->
            <form method="GET" action="report_food.php">
                <div class="center-only">
                    <!-- Meal Plan Filter -->
                    <div class="filter-group">
                        <label for="meal_plan">Meal Plan:</label>
                        <select name="meal_plan" id="meal_plan">
                            <option value="">All Meal Plans</option>
                            <?php foreach ($mealPlans as $plan): ?>
                                <option value="<?php echo htmlspecialchars($plan); ?>" <?php echo ($mealPlanFilter === $plan) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($plan); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Meal Type Filter -->
                    <div class="filter-group">
                        <label for="meal_type">Meal Type:</label>
                        <select name="meal_type" id="meal_type">
                            <option value="">All Meal Types</option>
                            <?php foreach ($mealTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($mealTypeFilter === $type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date"
                            value="<?php echo htmlspecialchars($startDate); ?>">

                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date"
                            value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>

                    <button type="submit" class="update-button">Apply Filters</button>
                </div>
            </form>

            <!-- Summary Statistics -->
            <div class="center-only">
                <h2>Summary</h2>
                <p>Total Meal Plan Users: <?php echo $summary['total_meal_plan_users']; ?></p>
                <p>Average Meal Price: $<?php echo number_format($summary['average_meal_price'], 2); ?></p>
                <p>Total Meal Value: $<?php echo number_format($summary['total_meal_value'], 2); ?></p>
                <p>Unique Meals Consumed: <?php echo $summary['unique_meals_consumed']; ?></p>
            </div>

            <!-- Food Consumption Report Table -->
            <h2>Detailed Food Consumption</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>Guest Name</th>
                        <th>Meal Plan</th>
                        <th>Meal Plan Type</th>
                        <th>Meal Name</th>
                        <th>Meal Type</th>
                        <th>Meal Price</th>
                        <th>Dietary Tags</th>
                        <th>Plan Status</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($foodReportData as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['guest_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['meal_plan_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['meal_plan_type']); ?></td>
                            <td><?php echo htmlspecialchars($record['meal_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['meal_type']); ?></td>
                            <td>$<?php echo number_format($record['meal_price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($record['dietary_tags'] ?? 'N/A'); ?></td>
                            <td><?php echo $record['is_cancelled'] ? 'Cancelled' : 'Active'; ?></td>
                            <td><?php echo $record['is_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $url = 'report_food.php';
            $urlWithParams = $url .
                "?start_date=" . urlencode($startDate) .
                "&end_date=" . urlencode($endDate) .
                ($mealPlanFilter ? "&meal_plan=" . urlencode($mealPlanFilter) : '') .
                ($mealTypeFilter ? "&meal_type=" . urlencode($mealTypeFilter) : '');
            echo generatePagination($page, $totalPages, $urlWithParams);
            ?>

            <br>

        </div>
    </div>
</body>

</html>