<?php
session_start();

if (!isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$mealPlans = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$mealPlanQuery = $conn->query("SELECT * FROM meal_plans WHERE is_active = 1 LIMIT $recordsPerPage OFFSET $offset");
while ($row = $mealPlanQuery->fetch(PDO::FETCH_ASSOC)) {
    $mealPlanId = $row['meal_plan_id'];

    $mealsQuery = $conn->prepare("
        SELECT m.*, mpl.day_number 
        FROM meals m
        JOIN meal_plan_items_link mpl ON m.meal_id = mpl.meal_id
        WHERE mpl.meal_plan_id = :mealPlanId
        ORDER BY mpl.day_number ASC
    ");
    $mealsQuery->bindParam(':mealPlanId', $mealPlanId, PDO::PARAM_INT);
    $mealsQuery->execute();

    $meals = [];
    while ($mealRow = $mealsQuery->fetch(PDO::FETCH_ASSOC)) {
        $tagsQuery = $conn->prepare("
            SELECT mdt.name 
            FROM meal_dietary_tags mdt
            JOIN meal_dietary_tags_link mdtl ON mdt.meal_dietary_tag_id = mdtl.meal_dietary_tag_id
            WHERE mdtl.meal_id = :mealId
        ");
        $tagsQuery->bindParam(':mealId', $mealRow['meal_id'], PDO::PARAM_INT);
        $tagsQuery->execute();

        $tags = [];
        while ($tagRow = $tagsQuery->fetch(PDO::FETCH_ASSOC)) {
            $tags[] = $tagRow['name'];
        }

        $mealRow['tags'] = $tags;
        $meals[] = $mealRow;
    }

    $row['meals'] = $meals;

    $totalPrice = 0;
    foreach ($meals as $meal) {
        $totalPrice += $meal['price'];
    }
    $row['total_price'] = $totalPrice;

    $mealPlans[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
    $mealPlanId = $_POST['meal_plan_id'];
    $userId = $_SESSION['user_id'];

    $checkQuery = $conn->prepare("
        SELECT * FROM meal_plan_user_link 
        WHERE user_id = :userId AND meal_plan_id = :mealPlanId AND is_cancelled = 0
    ");
    $checkQuery->bindParam(':userId', $userId, PDO::PARAM_INT);
    $checkQuery->bindParam(':mealPlanId', $mealPlanId, PDO::PARAM_INT);
    $checkQuery->execute();

    if ($checkQuery->rowCount() > 0) {
        $feedback = 'You already have this meal plan booked!';
    } else {
        $addQuery = $conn->prepare("
            INSERT INTO meal_plan_user_link (user_id, meal_plan_id) 
            VALUES (:userId, :mealPlanId)
        ");
        $addQuery->bindParam(':userId', $userId, PDO::PARAM_INT);
        $addQuery->bindParam(':mealPlanId', $mealPlanId, PDO::PARAM_INT);

        if ($addQuery->execute()) {
            $mealPlanUserLinkId = $conn->lastInsertId();
            header("Location: payments_page.php?type=meal_plan&id=$mealPlanUserLinkId");
            exit();
        } else {
            $feedback = 'Error booking meal plan.';
        }
    }
}

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM meal_plans WHERE is_active = 1");
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
    <script src="../assets/scripts.js"></script>
    <title>Meal Plans</title>
    <style>
        .meal-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .meal-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: 8px;
            position: relative;
        }

        .meal-image {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .meal-details {
            margin-top: 15px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        .meal-name-link {
            cursor: pointer;
            color: #0066cc;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Available Meal Plans</h1>
            <?php if ($feedback): ?>
                <div class="rooms-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <?php if (empty($mealPlans)): ?>
                <p>No meal plans available at the moment.</p>
            <?php else: ?>
                <?php foreach ($mealPlans as $plan): ?>
                    <div class="meal-plan-card">
                        <h2><?php echo $plan['name']; ?></h2>
                        <p>Type: <?php echo $plan['meal_plan_type']; ?></p>
                        <p>Duration: <?php echo $plan['duration_days']; ?> days</p>
                        <p>Total Price: £<?php echo number_format($plan['total_price'], 2); ?></p>

                        <h3>Included Meals:</h3>
                        <table border="1">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Dietary Tags</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plan['meals'] as $meal): ?>
                                    <tr>
                                        <td>Day <?php echo $meal['day_number']; ?></td>
                                        <td>
                                            <span class="meal-name-link" data-meal-id="<?php echo $meal['meal_id']; ?>"
                                                data-meal-name="<?php echo htmlspecialchars($meal['name']); ?>"
                                                data-meal-type="<?php echo htmlspecialchars($meal['meal_type']); ?>"
                                                data-meal-price="<?php echo number_format($meal['price'], 2); ?>"
                                                data-meal-tags="<?php echo htmlspecialchars(implode(', ', $meal['tags'])); ?>"
                                                data-meal-image="<?php echo htmlspecialchars($meal['image_path'] ?? ''); ?>">
                                                <?php echo $meal['name']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $meal['meal_type']; ?></td>
                                        <td>£<?php echo number_format($meal['price'], 2); ?></td>
                                        <td><?php echo implode(', ', $meal['tags']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <form method="POST" action="meals.php">
                            <input type="hidden" name="action" value="book">
                            <input type="hidden" name="meal_plan_id" value="<?php echo $plan['meal_plan_id']; ?>">
                            <button type="submit" class="update-button">Book Plan</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php
            $url = 'meals.php';
            echo generatePagination($page, $totalPages, $url);
            ?>

            <br>
            <div class="back-button-container">
                <a href="dashboard.php" class="button">Back to Dashboard</a>
            </div>
        </div>
        <div id="form-overlay"></div>
    </div>

    <div id="mealModal" class="meal-modal">
        <div class="meal-modal-content">
            <span class="close">&times;</span>
            <h2 id="modalMealName"></h2>
            <div id="modalImageContainer">
                <img id="modalMealImage" class="meal-image" src="" alt="Meal Image">
            </div>
            <div class="meal-details">
                <p><strong>Type:</strong> <span id="modalMealType"></span></p>
                <p><strong>Price:</strong> £<span id="modalMealPrice"></span></p>
                <p><strong>Dietary Tags:</strong> <span id="modalMealTags"></span></p>
            </div>
        </div>
    </div>
</body>

</html>