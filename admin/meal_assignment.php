<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$mealData = [];
$mealPlanData = [];
$assignmentsData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

function updateMealPlanPrice($conn, $planId)
{
    $priceQuery = "
        SELECT SUM(m.price) as total_price
        FROM meal_plan_items_link mpl
        JOIN meals m ON mpl.meal_id = m.meal_id
        WHERE mpl.meal_plan_id = ?
    ";
    $stmt = $conn->prepare($priceQuery);
    $stmt->execute([$planId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalPrice = $result['total_price'] ?? 0;

    // Apply discount based on plan duration
    $planQuery = "SELECT meal_plan_type FROM meal_plans WHERE meal_plan_id = ?";
    $stmt = $conn->prepare($planQuery);
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    $discount = 0;
    if ($plan['meal_plan_type'] == 'Weekly') {
        $discount = 0.1;
    } elseif ($plan['meal_plan_type'] == 'Monthly') {
        $discount = 0.2;
    }

    $finalPrice = $totalPrice * (1 - $discount);

    // Update the meal plan price
    $updateQuery = "UPDATE meal_plans SET price = ? WHERE meal_plan_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute([$finalPrice, $planId]);

    return $finalPrice;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'meal_assignment') {
        $planId = $_POST['plan_id'];
        $dayNumber = $_POST['day_number'];

        // Process all selected meals
        $mealsToAssign = [];
        if (!empty($_POST['breakfast_meal_id'])) {
            $mealsToAssign[] = ['meal_id' => $_POST['breakfast_meal_id'], 'meal_type' => 'Breakfast'];
        }
        if (!empty($_POST['lunch_meal_id'])) {
            $mealsToAssign[] = ['meal_id' => $_POST['lunch_meal_id'], 'meal_type' => 'Lunch'];
        }
        if (!empty($_POST['dinner_meal_id'])) {
            $mealsToAssign[] = ['meal_id' => $_POST['dinner_meal_id'], 'meal_type' => 'Dinner'];
        }

        if (!empty($mealsToAssign)) {
            $successCount = 0;
            foreach ($mealsToAssign as $meal) {
                $stmt = $conn->prepare("INSERT INTO meal_plan_items_link (meal_plan_id, meal_id, day_number) VALUES (?, ?, ?)");
                $stmt->execute([$planId, $meal['meal_id'], $dayNumber]);
                if ($stmt) {
                    $successCount++;
                }
            }

            if ($successCount > 0) {
                updateMealPlanPrice($conn, $planId);

                $feedback = 'Successfully assigned ' . $successCount . ' meal(s) to plan!';
                header("Location: meal_assignment.php?feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error assigning meals to plan.';
            }
        } else {
            $feedback = 'Please select at least one meal to assign.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_assignment') {
        $assignmentId = $_POST['assignment_id'];

        $planQuery = "SELECT meal_plan_id FROM meal_plan_items_link WHERE meal_plan_item_link_id = ?";
        $stmt = $conn->prepare($planQuery);
        $stmt->execute([$assignmentId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        $planId = $plan['meal_plan_id'];

        $stmt = $conn->prepare("DELETE FROM meal_plan_items_link WHERE meal_plan_item_link_id = ?");
        $stmt->execute([$assignmentId]);

        if ($stmt) {
            updateMealPlanPrice($conn, $planId);

            $feedback = 'Assignment deleted successfully!';
            header("Location: meal_assignment.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error deleting the assignment.';
        }
    }
}

if (isset($_GET['feedback'])) {
    $feedback = $_GET['feedback'];
}

$mealQuery = "SELECT * FROM meals";
$mealResult = $conn->query($mealQuery);
while ($row = $mealResult->fetch(PDO::FETCH_ASSOC)) {
    $mealData[] = $row;
}

$mealPlanQuery = "SELECT * FROM meal_plans";
$mealPlanResult = $conn->query($mealPlanQuery);
while ($row = $mealPlanResult->fetch(PDO::FETCH_ASSOC)) {
    $mealPlanData[] = $row;
}

$assignmentsQuery = "
    SELECT mpl.meal_plan_item_link_id, mpl.meal_plan_id, mpl.meal_id, mpl.day_number,
           m.name AS meal_name, m.meal_type, mp.name AS plan_name, mp.price AS plan_price
    FROM meal_plan_items_link mpl
    JOIN meals m ON mpl.meal_id = m.meal_id
    JOIN meal_plans mp ON mpl.meal_plan_id = mp.meal_plan_id
    LIMIT $recordsPerPage OFFSET $offset
";
$assignmentsResult = $conn->query($assignmentsQuery);
while ($row = $assignmentsResult->fetch(PDO::FETCH_ASSOC)) {
    $assignmentsData[] = $row;
}

$totalAssignmentsQuery = $conn->query("SELECT COUNT(*) AS total FROM meal_plan_items_link");
$totalAssignments = $totalAssignmentsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalAssignmentsPages = ceil($totalAssignments / $recordsPerPage);

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
    <title>Assign Meals to Meal Plans</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Assign Meals to Meal Plans</h1>

            <?php if (!empty($feedback)): ?>
                <div class="feedback"><?php echo htmlspecialchars($feedback); ?></div>
            <?php endif; ?>

            <!-- Meal Plan Type Filter -->
            <div class="button-center">
                <form method="GET" action="meal_assignment.php">
                    <select name="meal_plan_type" onchange="this.form.submit()">
                        <option value="">All Meal Plan Types</option>
                        <option value="Daily" <?php echo (isset($_GET['meal_plan_type']) && $_GET['meal_plan_type'] == 'Daily' ? 'selected' : ''); ?>>Daily Plans</option>
                        <option value="Weekly" <?php echo (isset($_GET['meal_plan_type']) && $_GET['meal_plan_type'] == 'Weekly' ? 'selected' : ''); ?>>Weekly Plans</option>
                        <option value="Monthly" <?php echo (isset($_GET['meal_plan_type']) && $_GET['meal_plan_type'] == 'Monthly' ? 'selected' : ''); ?>>Monthly Plans</option>
                    </select>
                </form>
            </div>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Assign Meal to
                    Plan</button>
            </div>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Assign New Meal to Plan</h2>
                <form method="POST" action="meal_assignment.php">
                    <input type="hidden" name="action" value="meal_assignment">

                    <label for="plan_id">Meal Plan:</label>
                    <select id="plan_id" name="plan_id" required>
                        <?php foreach ($mealPlanData as $plan): ?>
                            <option value="<?php echo $plan['meal_plan_id']; ?>">
                                <?php echo $plan['name'] . ' (' . $plan['meal_plan_type'] . ') - $' . number_format($plan['price'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Breakfast:</label>
                    <select name="breakfast_meal_id">
                        <option value="">Select Breakfast</option>
                        <?php foreach ($mealData as $meal): ?>
                            <?php if ($meal['meal_type'] == 'Breakfast' || $meal['meal_type'] == 'Any'): ?>
                                <option value="<?php echo $meal['meal_id']; ?>">
                                    <?php echo $meal['name'] . ' ($' . number_format($meal['price'], 2) . ')'; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>

                    <label>Lunch:</label>
                    <select name="lunch_meal_id">
                        <option value="">Select Lunch</option>
                        <?php foreach ($mealData as $meal): ?>
                            <?php if ($meal['meal_type'] == 'Lunch' || $meal['meal_type'] == 'Any'): ?>
                                <option value="<?php echo $meal['meal_id']; ?>">
                                    <?php echo $meal['name'] . ' ($' . number_format($meal['price'], 2) . ')'; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>

                    <label>Dinner:</label>
                    <select name="dinner_meal_id">
                        <option value="">Select Dinner</option>
                        <?php foreach ($mealData as $meal): ?>
                            <?php if ($meal['meal_type'] == 'Dinner' || $meal['meal_type'] == 'Any'): ?>
                                <option value="<?php echo $meal['meal_id']; ?>">
                                    <?php echo $meal['name'] . ' ($' . number_format($meal['price'], 2) . ')'; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>

                    <label for="day_number">Day Number:</label>
                    <input type="number" id="day_number" name="day_number" min="1" required>

                    <button type="submit" class="update-button">Assign Meal(s)</button>
                </form>
            </div>

            <!-- Meal Plan Tables -->
            <?php foreach ($mealPlanData as $plan): ?>
                <?php
                $planAssignments = array_filter($assignmentsData, function ($assignment) use ($plan) {
                    return $assignment['meal_plan_id'] == $plan['meal_plan_id'];
                });
                ?>
                <div class="meal-plan-section">
                    <h2><?php echo $plan['name'] . ' (' . $plan['meal_plan_type'] . ') - $' . number_format($plan['price'], 2); ?>
                    </h2>
                    <?php if (!empty($planAssignments)): ?>
                        <table border="1">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Meal Name</th>
                                    <th>Meal Type</th>
                                    <th>Day Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planAssignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo $assignment['meal_plan_item_link_id']; ?></td>
                                        <td><?php echo $assignment['meal_name']; ?></td>
                                        <td><?php echo $assignment['meal_type']; ?></td>
                                        <td><?php echo $assignment['day_number']; ?></td>
                                        <td>
                                            <button
                                                onclick="LuckyNest.toggleForm('edit-form-<?php echo $assignment['meal_plan_item_link_id']; ?>')"
                                                class="update-button">Edit</button>

                                            <!-- Edit Form -->
                                            <div id='edit-form-<?php echo $assignment['meal_plan_item_link_id']; ?>'
                                                class="edit-form">
                                                <button type="button" class="close-button"
                                                    onclick="LuckyNest.toggleForm('edit-form-<?php echo $assignment['meal_plan_item_link_id']; ?>')">✕</button>
                                                <h2>Edit Meal Assignment</h2>
                                                <form method="POST" action="meal_assignment.php">
                                                    <input type="hidden" name="action" value="edit_assignment">
                                                    <input type="hidden" name="assignment_id"
                                                        value="<?php echo $assignment['meal_plan_item_link_id']; ?>">

                                                    <label>Meal Type:</label>
                                                    <select name="meal_type">
                                                        <option value="Breakfast" <?php echo ($assignment['meal_type'] == 'Breakfast' ? 'selected' : ''); ?>>Breakfast</option>
                                                        <option value="Lunch" <?php echo ($assignment['meal_type'] == 'Lunch' ? 'selected' : ''); ?>>Lunch</option>
                                                        <option value="Dinner" <?php echo ($assignment['meal_type'] == 'Dinner' ? 'selected' : ''); ?>>Dinner</option>
                                                    </select>

                                                    <label>New Meal:</label>
                                                    <select name="new_meal_id">
                                                        <?php foreach ($mealData as $meal): ?>
                                                            <?php if ($meal['meal_type'] == $assignment['meal_type'] || $meal['meal_type'] == 'Any'): ?>
                                                                <option value="<?php echo $meal['meal_id']; ?>" <?php echo ($meal['meal_id'] == $assignment['meal_id'] ? 'selected' : ''); ?>>
                                                                    <?php echo $meal['name'] . ' ($' . number_format($meal['price'], 2) . ')'; ?>
                                                                </option>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button type="submit" class="update-button">Update Assignment</button>
                                                </form>
                                            </div>

                                            <form method="POST" action="meal_assignment.php" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_assignment">
                                                <input type="hidden" name="assignment_id"
                                                    value="<?php echo $assignment['meal_plan_item_link_id']; ?>">
                                                <button type="submit" class="update-button">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No meals assigned to this plan yet.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php
            $url = 'meal_assignment.php';
            echo generatePagination($page, $totalAssignmentsPages, $url);
            ?>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>