<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$mealPlanData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$planTypes = ['Daily', 'Weekly', 'Monthly'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_meal_plan') {
        $planType = $_POST['meal_plan_type'];
        $name = $_POST['name'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO meal_plans (meal_plan_type, name, is_active) VALUES (?, ?, ?)");
        $stmt->execute([$planType, $name, $isActive]);

        if ($stmt) {
            $feedback = 'Meal plan added successfully!';
            header("Location: meal_plans.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error adding meal plan.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_meal_plan') {
        $planId = $_POST['plan_id'];
        $planType = $_POST['meal_plan_type'];
        $name = $_POST['name'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE meal_plans SET meal_plan_type = ?, name = ?, is_active = ? WHERE meal_plan_id = ?");
        $stmt->execute([$planType, $name, $isActive, $planId]);

        if ($stmt) {
            $feedback = 'Meal plan updated successfully!';
            header("Location: meal_plans.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error updating the meal plan.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_meal_plan') {
        $planId = $_POST['plan_id'];

        $stmt = $conn->prepare("DELETE FROM meal_plans WHERE meal_plan_id = ?");
        $stmt->execute([$planId]);

        if ($stmt) {
            $feedback = 'Meal plan deleted successfully!';
            header("Location: meal_plans.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error deleting the meal plan.';
        }
    }
}

if (isset($_GET['feedback'])) {
    $feedback = $_GET['feedback'];
}

$mealPlanQuery = "SELECT * FROM meal_plans LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($mealPlanQuery);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mealPlanData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMealPlanRecordsQuery = $conn->query("SELECT COUNT(*) AS total FROM meal_plans");
$totalMealPlanRecords = $totalMealPlanRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalMealPlanPages = ceil($totalMealPlanRecords / $recordsPerPage);

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
    <title>Manage Meal Plans</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Manage Meal Plans</h1>
            <?php if ($feedback): ?>
                <div class="rooms-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Meal Plan</button>
            </div>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Add New Meal Plan</h2>
                <form method="POST" action="meal_plans.php">
                    <input type="hidden" name="action" value="add_meal_plan">
                    
                    <label for="meal_plan_type">Plan Type:</label>
                    <select id="meal_plan_type" name="meal_plan_type" required>
                        <?php foreach ($planTypes as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="name">Plan Name:</label>
                    <input type="text" id="name" name="name" required>
                    
                    <div>
                        <label for="is_active">Active:</label>
                        <input type="checkbox" id="is_active" name="is_active" checked>
                    </div>
                    
                    <button type="submit" class="update-button">Add Meal Plan</button>
                </form>
            </div>

            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Plan Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mealPlanData as $mealPlan): ?>
                        <tr>
                            <td><?php echo $mealPlan['meal_plan_id']; ?></td>
                            <td><?php echo $mealPlan['name']; ?></td>
                            <td><?php echo $mealPlan['meal_plan_type']; ?></td>
                            <td><?php echo $mealPlan['is_active'] ? 'Active' : 'Inactive'; ?></td>
                            <td>
                                <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $mealPlan['meal_plan_id']; ?>')" class="update-button">Edit</button>
                                <div id="edit-form-<?php echo $mealPlan['meal_plan_id']; ?>" class="rooms-type-edit-form">
                                    <form method="POST" action="meal_plans.php" style="display:inline;">
                                        <button type="button" class="close-button" onclick="LuckyNest.toggleForm('edit-form-<?php echo $mealPlan['meal_plan_id']; ?>')">✕</button>
                                        <h2>Edit Meal Plan</h2>
                                        <input type="hidden" name="action" value="edit_meal_plan">
                                        <input type="hidden" name="plan_id" value="<?php echo $mealPlan['meal_plan_id']; ?>">
                                        
                                        <label for="meal_plan_type_<?php echo $mealPlan['meal_plan_id']; ?>">Plan Type:</label>
                                        <select id="meal_plan_type_<?php echo $mealPlan['meal_plan_id']; ?>" name="meal_plan_type" required>
                                            <?php foreach ($planTypes as $type): ?>
                                                <option value="<?php echo $type; ?>" <?php echo $mealPlan['meal_plan_type'] == $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <label for="name_<?php echo $mealPlan['meal_plan_id']; ?>">Plan Name:</label>
                                        <input type="text" id="name_<?php echo $mealPlan['meal_plan_id']; ?>" name="name"
                                            value="<?php echo $mealPlan['name']; ?>" required>
                                        
                                        <div>
                                            <label for="is_active_<?php echo $mealPlan['meal_plan_id']; ?>">Active:</label>
                                            <input type="checkbox" id="is_active_<?php echo $mealPlan['meal_plan_id']; ?>"
                                                name="is_active" <?php echo $mealPlan['is_active'] ? 'checked' : ''; ?>>
                                        </div>
                                        
                                        <div class="rooms-button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button" 
                                                onclick="document.getElementById('delete-meal-plan-form-<?php echo $mealPlan['meal_plan_id']; ?>').submit(); return false;">
                                                Delete
                                            </button>
                                        </div>
                                    </form>

                                    <form id="delete-meal-plan-form-<?php echo $mealPlan['meal_plan_id']; ?>" method="POST" action="meal_plans.php" style="display:none;">
                                        <input type="hidden" name="action" value="delete_meal_plan">
                                        <input type="hidden" name="plan_id" value="<?php echo $mealPlan['meal_plan_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $url = 'meal_plans.php';
            echo generatePagination($page, $totalMealPlanPages, $url);
            ?>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>