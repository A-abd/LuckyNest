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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'meal_assignment') {
        $planId = $_POST['plan_id'];
        $mealId = $_POST['meal_id'];
        $dayNumber = $_POST['day_number'];

        $stmt = $conn->prepare("INSERT INTO meal_plan_items_link (meal_plan_id, meal_id, day_number) VALUES (?, ?, ?)");
        $stmt->execute([$planId, $mealId, $dayNumber]);

        if ($stmt) {
            $feedback = 'Meal assigned to plan successfully!';
            header("Location: meal_assignment.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error assigning meal to plan.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_assignment') {
        $assignmentId = $_POST['assignment_id'];

        $stmt = $conn->prepare("DELETE FROM meal_plan_items_link WHERE meal_plan_item_link_id = ?");
        $stmt->execute([$assignmentId]);

        if ($stmt) {
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
           m.name AS meal_name, m.meal_type, mp.name AS plan_name
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
            
            <!-- Meal Plan Type Filter -->
            <div class="button-center">
                <form method="GET" action="meal_assignment.php">
                    <select name="meal_plan_type" onchange="this.form.submit()">
                        <option value="">All Meal Plan Types</option>
                        <option value="Daily" <?php echo ($selectedPlanType == 'Daily' ? 'selected' : ''); ?>>Daily Plans</option>
                        <option value="Weekly" <?php echo ($selectedPlanType == 'Weekly' ? 'selected' : ''); ?>>Weekly Plans</option>
                        <option value="Monthly" <?php echo ($selectedPlanType == 'Monthly' ? 'selected' : ''); ?>>Monthly Plans</option>
                    </select>
                </form>
            </div>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Assign Meal to Plan</button>
            </div>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Assign New Meal to Plan</h2>
                <form method="POST" action="meal_assignment.php">
                    <input type="hidden" name="action" value="meal_assignment">
                    
                    <label for="plan_id">Meal Plan:</label>
                    <select id="plan_id" name="plan_id" required>
                        <?php foreach ($mealPlanData as $plan): ?>
                            <option value="<?php echo $plan['meal_plan_id']; ?>"><?php echo $plan['name'] . ' (' . $plan['meal_plan_type'] . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Breakfast:</label>
                    <select name="breakfast_meal_id">
                        <option value="">Select Breakfast</option>
                        <?php foreach ($mealData as $meal): ?>
                            <?php if ($meal['meal_type'] == 'Breakfast' || $meal['meal_type'] == 'Any'): ?>
                                <option value="<?php echo $meal['meal_id']; ?>"><?php echo $meal['name']; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Lunch:</label>
                    <select name="lunch_meal_id">
                        <option value="">Select Lunch</option>
                        <?php foreach ($mealData as $meal): ?>
                            <?php if ($meal['meal_type'] == 'Lunch' || $meal['meal_type'] == 'Any'): ?>
                                <option value="<?php echo $meal['meal_id']; ?>"><?php echo $meal['name']; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Dinner:</label>
                    <select name="dinner_meal_id">
                        <option value="">Select Dinner</option>
                        <?php foreach ($mealData as $meal): ?>
                            <?php if ($meal['meal_type'] == 'Dinner' || $meal['meal_type'] == 'Any'): ?>
                                <option value="<?php echo $meal['meal_id']; ?>"><?php echo $meal['name']; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="day_number">Day Number:</label>
                    <input type="number" id="day_number" name="day_number" min="1" required>
                    
                    <button type="submit" class="update-button">Assign Meal</button>
                </form>
            </div>

            <!-- Edit Assignment Form -->
            <div id="edit-assignment-form" class="add-form" style="display:none;">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('edit-assignment-form')">✕</button>
                <h2>Edit Meal Assignment</h2>
                <form method="POST" action="meal_assignment.php">
                    <input type="hidden" name="action" value="edit_assignment">
                    <input type="hidden" name="assignment_id" id="edit-assignment-id">
                    
                    <label>Meal Type:</label>
                    <select name="meal_type" id="edit-meal-type">
                        <option value="Breakfast">Breakfast</option>
                        <option value="Lunch">Lunch</option>
                        <option value="Dinner">Dinner</option>
                    </select>
                    
                    <label>New Meal:</label>
                    <select name="new_meal_id" id="edit-meal-select">
                        <?php foreach ($mealData as $meal): ?>
                            <option value="<?php echo $meal['meal_id']; ?>"><?php echo $meal['name'] . ' (' . $meal['meal_type'] . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="update-button">Update Assignment</button>
                </form>
            </div>

            <!-- Meal Plan Tables -->
            <?php foreach ($mealPlanData as $plan): ?>
                <?php 
                $planAssignments = array_filter($assignmentsData, function($assignment) use ($plan) {
                    return $assignment['meal_plan_id'] == $plan['meal_plan_id'];
                });
                ?>
                <div class="meal-plan-section">
                    <h2><?php echo $plan['name'] . ' (' . $plan['meal_plan_type'] . ')'; ?></h2>
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
                                            <button onclick="LuckyNest.editAssignment(
                                                '<?php echo $assignment['meal_plan_item_link_id']; ?>',
                                                '<?php echo $assignment['meal_type']; ?>'
                                            )" class="update-button">Edit</button>
                                            <form method="POST" action="meal_assignment.php" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_assignment">
                                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['meal_plan_item_link_id']; ?>">
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

    <script>
    if (!window.LuckyNest) {
        window.LuckyNest = {};
    }

    LuckyNest.editAssignment = function(assignmentId, mealType) {
        document.getElementById('edit-assignment-id').value = assignmentId;
        
        var mealTypeSelect = document.getElementById('edit-meal-type');
        for (var i = 0; i < mealTypeSelect.options.length; i++) {
            if (mealTypeSelect.options[i].value === mealType) {
                mealTypeSelect.selectedIndex = i;
                break;
            }
        }

        var mealSelect = document.getElementById('edit-meal-select');
        for (var i = 0; i < mealSelect.options.length; i++) {
            var optionText = mealSelect.options[i].text.toLowerCase();
            if (optionText.includes(mealType.toLowerCase()) || optionText.includes('any')) {
                mealSelect.options[i].style.display = '';
            } else {
                mealSelect.options[i].style.display = 'none';
            }
        }

        LuckyNest.toggleForm('edit-assignment-form');
    };
    </script>
</body>
</html>