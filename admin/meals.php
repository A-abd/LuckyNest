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

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$activeSection = isset($_GET['section']) ? $_GET['section'] : 'meals';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $activeSection = isset($_POST['section']) ? $_POST['section'] : 'meals';

        if ($action === 'add_meal') {
            $name = $_POST['name'];
            $mealType = $_POST['meal_type'];
            $price = $_POST['price'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $dietaryTags = isset($_POST['dietary_tags']) ? $_POST['dietary_tags'] : [];

            $stmt = $conn->prepare("INSERT INTO meals (name, meal_type, price, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $mealType, $price, $isActive]);

            if ($stmt) {
                $mealId = $conn->lastInsertId();

                if (!empty($dietaryTags)) {
                    foreach ($dietaryTags as $tagId) {
                        $tagStmt = $conn->prepare("INSERT INTO meal_dietary_tags_link (meal_id, meal_dietary_tag_id) VALUES (?, ?)");
                        $tagStmt->execute([$mealId, $tagId]);
                    }
                }

                $feedback = 'Meal added successfully!';
                header("Location: meals.php?section=meals&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error adding meal.';
            }
        } elseif ($action === 'edit_meal') {
            $mealId = $_POST['meal_id'];
            $name = $_POST['name'];
            $mealType = $_POST['meal_type'];
            $price = $_POST['price'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $dietaryTags = isset($_POST['dietary_tags']) ? $_POST['dietary_tags'] : [];

            $stmt = $conn->prepare("UPDATE meals SET name = ?, meal_type = ?, price = ?, is_active = ? WHERE meal_id = ?");
            $stmt->execute([$name, $mealType, $price, $isActive, $mealId]);

            if ($stmt) {
                $deleteTagsStmt = $conn->prepare("DELETE FROM meal_dietary_tags_link WHERE meal_id = ?");
                $deleteTagsStmt->execute([$mealId]);

                if (!empty($dietaryTags)) {
                    foreach ($dietaryTags as $tagId) {
                        $tagStmt = $conn->prepare("INSERT INTO meal_dietary_tags_link (meal_id, meal_dietary_tag_id) VALUES (?, ?)");
                        $tagStmt->execute([$mealId, $tagId]);
                    }
                }

                $feedback = 'Meal updated successfully!';
                header("Location: meals.php?section=meals&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error updating the meal.';
            }
        } elseif ($action === 'delete_meal') {
            $mealId = $_POST['meal_id'];

            $stmt = $conn->prepare("DELETE FROM meals WHERE meal_id = ?");
            $stmt->execute([$mealId]);

            if ($stmt) {
                $feedback = 'Meal deleted successfully!';
                header("Location: meals.php?section=meals&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error deleting the meal.';
            }
        } elseif ($action === 'add_meal_plan') {
            $planType = $_POST['meal_plan_type'];
            $name = $_POST['name'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $stmt = $conn->prepare("INSERT INTO meal_plans (meal_plan_type, name, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$planType, $name, $isActive]);

            if ($stmt) {
                $feedback = 'Meal plan added successfully!';
                header("Location: meals.php?section=meal-plans&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error adding meal plan.';
            }
        } elseif ($action === 'edit_meal_plan') {
            $planId = $_POST['plan_id'];
            $planType = $_POST['meal_plan_type'];
            $name = $_POST['name'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $stmt = $conn->prepare("UPDATE meal_plans SET meal_plan_type = ?, name = ?, is_active = ? WHERE meal_plan_id = ?");
            $stmt->execute([$planType, $name, $isActive, $planId]);

            if ($stmt) {
                $feedback = 'Meal plan updated successfully!';
                header("Location: meals.php?section=meal-plans&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error updating the meal plan.';
            }
        } elseif ($action === 'delete_meal_plan') {
            $planId = $_POST['plan_id'];

            $stmt = $conn->prepare("DELETE FROM meal_plans WHERE meal_plan_id = ?");
            $stmt->execute([$planId]);

            if ($stmt) {
                $feedback = 'Meal plan deleted successfully!';
                header("Location: meals.php?section=meal-plans&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error deleting the meal plan.';
            }
        } elseif ($action === 'assign_meal_to_plan') {
            $planId = $_POST['plan_id'];
            $mealId = $_POST['meal_id'];
            $dayNumber = $_POST['day_number'];

            $stmt = $conn->prepare("INSERT INTO meal_plan_items_link (meal_plan_id, meal_id, day_number) VALUES (?, ?, ?)");
            $stmt->execute([$planId, $mealId, $dayNumber]);

            if ($stmt) {
                $feedback = 'Meal assigned to plan successfully!';
                header("Location: meals.php?section=assign-meals&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error assigning meal to plan.';
            }
        } elseif ($action === 'delete_assignment') {
            $assignmentId = $_POST['assignment_id'];

            $stmt = $conn->prepare("DELETE FROM meal_plan_items_link WHERE meal_plan_item_link_id = ?");
            $stmt->execute([$assignmentId]);

            if ($stmt) {
                $feedback = 'Assignment deleted successfully!';
                header("Location: meals.php?section=assign-meals&feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error deleting the assignment.';
            }
        }
    }
}

if (isset($_GET['feedback'])) {
    $feedback = $_GET['feedback'];
}

$query = "SELECT * FROM meals LIMIT $recordsPerPage OFFSET $offset";
$result = $conn->query($query);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $mealId = $row['meal_id'];
    $tagQuery = $conn->prepare("
        SELECT mdt.meal_dietary_tag_id, mdt.name 
        FROM meal_dietary_tags mdt 
        JOIN meal_dietary_tags_link mdtl ON mdt.meal_dietary_tag_id = mdtl.meal_dietary_tag_id 
        WHERE mdtl.meal_id = ?
    ");
    $tagQuery->execute([$mealId]);
    $row['dietary_tags'] = $tagQuery->fetchAll(PDO::FETCH_ASSOC);
    $mealData[] = $row;
}

$totalRecordsQuery = $conn->query("SELECT COUNT(*) AS total FROM meals");
$totalRecords = $totalRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

$mealPlanQuery = "SELECT * FROM meal_plans LIMIT $recordsPerPage OFFSET $offset";
$mealPlanResult = $conn->query($mealPlanQuery);
while ($row = $mealPlanResult->fetch(PDO::FETCH_ASSOC)) {
    $mealPlanData[] = $row;
}

$totalMealPlanRecordsQuery = $conn->query("SELECT COUNT(*) AS total FROM meal_plans");
$totalMealPlanRecords = $totalMealPlanRecordsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalMealPlanPages = ceil($totalMealPlanRecords / $recordsPerPage);

$assignmentsQuery = "
    SELECT mpl.meal_plan_item_link_id, mpl.meal_plan_id, mpl.meal_id, mpl.day_number,
           m.name AS meal_name, m.meal_type, mp.name AS plan_name
    FROM meal_plan_items_link mpl
    JOIN meals m ON mpl.meal_id = m.meal_id
    JOIN meal_plans mp ON mpl.meal_plan_id = mp.meal_plan_id
    LIMIT $recordsPerPage OFFSET $offset
";
$assignmentsResult = $conn->query($assignmentsQuery);
$assignmentsData = [];
while ($row = $assignmentsResult->fetch(PDO::FETCH_ASSOC)) {
    $assignmentsData[] = $row;
}

$totalAssignmentsQuery = $conn->query("SELECT COUNT(*) AS total FROM meal_plan_items_link");
$totalAssignments = $totalAssignmentsQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalAssignmentsPages = ceil($totalAssignments / $recordsPerPage);

$dietaryTagsQuery = $conn->query("SELECT * FROM meal_dietary_tags");
$dietaryTags = $dietaryTagsQuery->fetchAll(PDO::FETCH_ASSOC);

$mealTypes = ['Any', 'Breakfast', 'Lunch', 'Dinner'];

$planTypes = ['Daily', 'Weekly', 'Monthly'];

$conn = null;
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="..//assets/scripts.js"></script>
    <title>Manage Meals and Meal Plans</title>
</head>

<body>
    <div class="manage-default">
        <button onclick="location.href='meals.php?section=meals'">Manage Meals</button>
        <button onclick="location.href='meals.php?section=meal-plans'">Manage Meal Plans</button>
        <button onclick="location.href='meals.php?section=assign-meals'">Assign Meals to Plans</button>

        <section id="meals" style="display: <?php echo $activeSection === 'meals' ? 'block' : 'none'; ?>;">
            <h1>Manage Meals</h1>
            <?php if ($feedback): ?>
                <p style="color: green;"><?php echo $feedback; ?></p>
            <?php endif; ?>

            <button onclick="toggleAddMealForm()">Add Meal</button>

            <div id="add-meal-form" style="display: none;">
                <h2>Add New Meal</h2>
                <form method="POST" action="meals.php">
                    <input type="hidden" name="action" value="add_meal">
                    <input type="hidden" name="section" value="meals">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                    
                    <label for="meal_type">Meal Type:</label>
                    <select id="meal_type" name="meal_type" required>
                        <?php foreach ($mealTypes as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="price">Price:</label>
                    <input type="text" id="price" name="price" required>
                    
                    <label for="is_active">Active:</label>
                    <input type="checkbox" id="is_active" name="is_active" checked>
                    
                    <label>Dietary Tags:</label>
                    <?php foreach ($dietaryTags as $tag): ?>
                        <div>
                            <input type="checkbox" id="tag_<?php echo $tag['meal_dietary_tag_id']; ?>" name="dietary_tags[]"
                                value="<?php echo $tag['meal_dietary_tag_id']; ?>">
                            <label for="tag_<?php echo $tag['meal_dietary_tag_id']; ?>"><?php echo $tag['name']; ?></label>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit">Add Meal</button>
                </form>
            </div>

            <h2>Meal List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Dietary Tags</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mealData as $meal): ?>
                        <tr>
                            <td><?php echo $meal['meal_id']; ?></td>
                            <td><?php echo $meal['name']; ?></td>
                            <td><?php echo $meal['meal_type']; ?></td>
                            <td><?php echo '£' . $meal['price']; ?></td>
                            <td><?php echo $meal['is_active'] ? 'Active' : 'Inactive'; ?></td>
                            <td>
                                <?php
                                $tagNames = array_map(function ($tag) {
                                    return $tag['name'];
                                }, $meal['dietary_tags']);
                                echo implode(', ', $tagNames);
                                ?>
                            </td>
                            <td>
                                <button onclick="toggleEditMealForm(<?php echo $meal['meal_id']; ?>)">Edit</button>
                                <div id="edit-meal-form-<?php echo $meal['meal_id']; ?>" style="display: none;">
                                    <form method="POST" action="meals.php" style="display:inline;">
                                        <input type="hidden" name="action" value="edit_meal">
                                        <input type="hidden" name="section" value="meals">
                                        <input type="hidden" name="meal_id" value="<?php echo $meal['meal_id']; ?>">
                                        
                                        <label for="name_<?php echo $meal['meal_id']; ?>">Name:</label>
                                        <input type="text" id="name_<?php echo $meal['meal_id']; ?>" name="name"
                                            value="<?php echo $meal['name']; ?>" required>
                                        
                                        <label for="meal_type_<?php echo $meal['meal_id']; ?>">Meal Type:</label>
                                        <select id="meal_type_<?php echo $meal['meal_id']; ?>" name="meal_type" required>
                                            <?php foreach ($mealTypes as $type): ?>
                                                <option value="<?php echo $type; ?>" <?php echo $meal['meal_type'] == $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <label for="price_<?php echo $meal['meal_id']; ?>">Price:</label>
                                        <input type="text" id="price_<?php echo $meal['meal_id']; ?>" name="price"
                                            value="<?php echo $meal['price']; ?>" required>
                                        
                                        <label for="is_active_<?php echo $meal['meal_id']; ?>">Active:</label>
                                        <input type="checkbox" id="is_active_<?php echo $meal['meal_id']; ?>" name="is_active"
                                            <?php echo $meal['is_active'] ? 'checked' : ''; ?>>
                                        
                                        <label>Dietary Tags:</label>
                                        <?php
                                        $mealTagIds = array_map(function ($tag) {
                                            return $tag['meal_dietary_tag_id'];
                                        }, $meal['dietary_tags']);

                                        foreach ($dietaryTags as $tag):
                                            ?>
                                            <div>
                                                <input type="checkbox"
                                                    id="tag_<?php echo $meal['meal_id']; ?>_<?php echo $tag['meal_dietary_tag_id']; ?>"
                                                    name="dietary_tags[]" value="<?php echo $tag['meal_dietary_tag_id']; ?>"
                                                    <?php echo in_array($tag['meal_dietary_tag_id'], $mealTagIds) ? 'checked' : ''; ?>>
                                                <label
                                                    for="tag_<?php echo $meal['meal_id']; ?>_<?php echo $tag['meal_dietary_tag_id']; ?>"><?php echo $tag['name']; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                        <button type="submit">Update</button>
                                    </form>

                                    <form method="POST" action="meals.php" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_meal">
                                        <input type="hidden" name="section" value="meals">
                                        <input type="hidden" name="meal_id" value="<?php echo $meal['meal_id']; ?>">
                                        <button type="submit" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $url = 'meals.php?section=meals';
            echo generatePagination($page, $totalPages, $url);
            ?>
        </section>

        <section id="meal-plans" style="display: <?php echo $activeSection === 'meal-plans' ? 'block' : 'none'; ?>;">
            <h1>Manage Meal Plans</h1>
            <?php if ($feedback): ?>
                <p style="color: green;"><?php echo $feedback; ?></p>
            <?php endif; ?>

            <button onclick="toggleAddMealPlanForm()">Add Meal Plan</button>

            <div id="add-meal-plan-form" style="display: none;">
                <h2>Add New Meal Plan</h2>
                <form method="POST" action="meals.php">
                    <input type="hidden" name="action" value="add_meal_plan">
                    <input type="hidden" name="section" value="meal-plans">
                    
                    <label for="meal_plan_type">Plan Type:</label>
                    <select id="meal_plan_type" name="meal_plan_type" required>
                        <?php foreach ($planTypes as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="name">Plan Name:</label>
                    <input type="text" id="name" name="name" required>
                    
                    <label for="is_active">Active:</label>
                    <input type="checkbox" id="is_active" name="is_active" checked>
                    
                    <button type="submit">Add Meal Plan</button>
                </form>
            </div>

            <h2>Meal Plan List</h2>
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
                                <button onclick="toggleEditMealPlanForm(<?php echo $mealPlan['meal_plan_id']; ?>)">Edit</button>
                                <div id="edit-meal-plan-form-<?php echo $mealPlan['meal_plan_id']; ?>" style="display: none;">
                                    <form method="POST" action="meals.php" style="display:inline;">
                                        <input type="hidden" name="action" value="edit_meal_plan">
                                        <input type="hidden" name="section" value="meal-plans">
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
                                        
                                        <label for="is_active_<?php echo $mealPlan['meal_plan_id']; ?>">Active:</label>
                                        <input type="checkbox" id="is_active_<?php echo $mealPlan['meal_plan_id']; ?>"
                                            name="is_active" <?php echo $mealPlan['is_active'] ? 'checked' : ''; ?>>
                                        
                                        <button type="submit">Update</button>
                                    </form>

                                    <form method="POST" action="meals.php" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_meal_plan">
                                        <input type="hidden" name="section" value="meal-plans">
                                        <input type="hidden" name="plan_id" value="<?php echo $mealPlan['meal_plan_id']; ?>">
                                        <button type="submit" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $url = 'meals.php?section=meal-plans';
            echo generatePagination($page, $totalMealPlanPages, $url);
            ?>
        </section>

        <section id="assign-meals" style="display: <?php echo $activeSection === 'assign-meals' ? 'block' : 'none'; ?>;">
            <h1>Assign Meals to Meal Plans</h1>
            <?php if ($feedback): ?>
                <p style="color: green;"><?php echo $feedback; ?></p>
            <?php endif; ?>

            <form method="POST" action="meals.php">
                <input type="hidden" name="action" value="assign_meal_to_plan">
                <input type="hidden" name="section" value="assign-meals">

                <label for="plan_id">Plan Name:</label>
                <select id="plan_id" name="plan_id" required>
                    <?php foreach ($mealPlanData as $plan): ?>
                        <option value="<?php echo $plan['meal_plan_id']; ?>"><?php echo $plan['name']; ?> (<?php echo $plan['meal_plan_type']; ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label for="meal_id">Meal Name:</label>
                <select id="meal_id" name="meal_id" required>
                    <?php foreach ($mealData as $meal): ?>
                        <option value="<?php echo $meal['meal_id']; ?>"><?php echo $meal['name']; ?> (<?php echo $meal['meal_type']; ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label for="day_number">Day Number:</label>
                <select id="day_number" name="day_number" required>
                    <?php
                    $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($dayNames as $index => $dayName): ?>
                        <option value="<?php echo $index + 1; ?>"><?php echo $dayName; ?> (<?php echo $index + 1; ?>)</option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Assign Meal to Plan</button>
            </form>

            <h1>View Meal Plan Assignments</h1>
            
            <h2>Meal Plan Assignments</h2>
            <?php
            $groupedAssignments = [];
            foreach ($assignmentsData as $assignment) {
                $planId = $assignment['meal_plan_id'];
                if (!isset($groupedAssignments[$planId])) {
                    $groupedAssignments[$planId] = [];
                }
                $groupedAssignments[$planId][] = $assignment;
            }

            foreach ($groupedAssignments as $planId => $assignments):
                $planName = $assignments[0]['plan_name'];
                ?>
                <h3><?php echo $planName; ?></h3>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Meal Name</th>
                            <th>Meal Type</th>
                            <th>Day</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td><?php echo $assignment['meal_name']; ?></td>
                                <td><?php echo $assignment['meal_type']; ?></td>
                                <td>
                                    <?php
                                    $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    $dayNumber = $assignment['day_number'];
                                    echo ($dayNumber <= 7) ? $dayNames[$dayNumber - 1] : 'Day ' . $dayNumber;
                                    ?>
                                </td>
                                <td>
                                    <form method="POST" action="meals.php" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_assignment">
                                        <input type="hidden" name="section" value="assign-meals">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['meal_plan_item_link_id']; ?>">
                                        <button type="submit" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
            
            <?php
            $url = 'meals.php?section=assign-meals';
            echo generatePagination($page, $totalAssignmentsPages, $url);
            ?>
        </section>

        <br>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>

</html>