<?php
session_start();

if (!isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';

$feedback = '';
$mealPlans = [];
$selectedPlanId = isset($_GET["plan_id"]) ? (int) $_GET["plan_id"] : null;

$allPlansQuery = $conn->prepare("SELECT meal_plan_id, name FROM meal_plans WHERE is_active = 1");
$allPlansQuery->execute();
$allMealPlans = $allPlansQuery->fetchAll(PDO::FETCH_ASSOC);

if (!$selectedPlanId && !empty($allMealPlans)) {
    $selectedPlanId = $allMealPlans[0]['meal_plan_id'];
}

if ($selectedPlanId) {
    $mealPlanQuery = $conn->prepare("SELECT * FROM meal_plans WHERE meal_plan_id = :planId AND is_active = 1");
    $mealPlanQuery->bindParam(':planId', $selectedPlanId, PDO::PARAM_INT);
    $mealPlanQuery->execute();

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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
    $userId = $_SESSION['user_id'];
    $mealPlanId = isset($_POST['meal_plan_id']) ? $_POST['meal_plan_id'] : null;
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;

    if ($mealPlanId && $startDate) {
        $addQuery = $conn->prepare("
            INSERT INTO meal_plan_user_link (user_id, meal_plan_id, start_date) 
            VALUES (:userId, :mealPlanId, :startDate)
        ");
        $addQuery->bindParam(':userId', $userId, PDO::PARAM_INT);
        $addQuery->bindParam(':mealPlanId', $mealPlanId, PDO::PARAM_INT);
        $addQuery->bindParam(':startDate', $startDate, PDO::PARAM_STR);

        if ($addQuery->execute()) {
            $mealPlanUserLinkId = $conn->lastInsertId();
            header("Location: payments_page.php?type=meal_plan&id=$mealPlanUserLinkId");
            exit();
        } else {
            $feedback = 'Error booking meal plan.';
        }
    } else {
        $feedback = 'Please select a start date for the meal plan.';
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
    <script src="../assets/scripts.js"></script>
    <title>Meal Plans</title>

</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Available Meal Plans</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Meal Plan Dropdown -->
            <div class="center-only">
                <label for="meal_plan_selector"><strong>Select a Meal Plan:</strong></label>
                <select id="meal_plan_selector" class="dropdown">
                    <?php foreach ($allMealPlans as $plan): ?>
                        <option value="<?php echo $plan['meal_plan_id']; ?>" <?php echo ($selectedPlanId == $plan['meal_plan_id']) ? 'selected' : ''; ?>>
                            <?php echo $plan['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (empty($mealPlans)): ?>
                <p>No meal plans available at the moment.</p>
            <?php else: ?>
                <?php foreach ($mealPlans as $plan): ?>
                    <div class="center-only">
                        <h2><?php echo $plan['name']; ?></h2>
                        <p>Type: <?php echo $plan['meal_plan_type']; ?></p>
                        <p>Duration: <?php echo $plan['duration_days']; ?> days</p>
                        <p>Total Price: £<?php echo number_format($plan['total_price'], 2); ?></p>

                        <form method="POST" action="meals.php?plan_id=<?php echo $selectedPlanId; ?>">
                            <input type="hidden" name="action" value="book">
                            <input type="hidden" name="meal_plan_id" value="<?php echo $plan['meal_plan_id']; ?>">

                            <div>
                                <label for="start_date_<?php echo $plan['meal_plan_id']; ?>">Start Date:</label>
                                <input type="date" id="start_date_<?php echo $plan['meal_plan_id']; ?>"
                                    class="meal-plan-date-picker" data-plan-type="<?php echo $plan['meal_plan_type']; ?>"
                                    name="start_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div>
                                <button type="submit" class="select-plan-button">Select this plan</button>
                            </div>
                        </form>

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
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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