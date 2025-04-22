<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_month_days') {
    $planId = isset($_GET['plan_id']) ? (int) $_GET['plan_id'] : 0;
    $result = ['days' => 31];

    if ($planId > 0) {
        try {
            $metadataQuery = "SELECT meta_value FROM meal_plan_metadata WHERE meal_plan_id = ? AND meta_key = 'month'";
            $metadataStmt = $conn->prepare($metadataQuery);
            $metadataStmt->execute([$planId]);
            $metadata = $metadataStmt->fetch(PDO::FETCH_ASSOC);

            $currentYear = date('Y');

            if ($metadata) {
                $month = (int) $metadata['meta_value'];
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $currentYear);
                $result['days'] = $daysInMonth;
                $result['month'] = $month;
            } else {
                $planQuery = "SELECT meal_plan_type FROM meal_plans WHERE meal_plan_id = ?";
                $planStmt = $conn->prepare($planQuery);
                $planStmt->execute([$planId]);
                $planData = $planStmt->fetch(PDO::FETCH_ASSOC);

                if ($planData && $planData['meal_plan_type'] == 'Monthly') {
                    $currentMonth = date('n');
                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
                    $result['days'] = $daysInMonth;
                    $result['month'] = $currentMonth;

                    $saveMetadataStmt = $conn->prepare(
                        "INSERT INTO meal_plan_metadata (meal_plan_id, meta_key, meta_value) 
                         VALUES (?, 'month', ?) 
                         ON DUPLICATE KEY UPDATE meta_value = ?"
                    );
                    $saveMetadataStmt->execute([$planId, $currentMonth, $currentMonth]);
                } elseif ($planData && $planData['meal_plan_type'] == 'Weekly') {
                    $result['days'] = 7;
                } elseif ($planData && $planData['meal_plan_type'] == 'Daily') {
                    $result['days'] = 1;
                }
            }
        } catch (PDOException $e) {
            $result['error'] = 'Database error occurred';
        }
    }

    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

$feedback = '';
$mealData = [];
$mealPlanData = [];
$assignmentsData = [];

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

    $updateQuery = "UPDATE meal_plans SET price = ? WHERE meal_plan_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute([$finalPrice, $planId]);

    return $finalPrice;
}

function getDaysInMonth($year, $month)
{
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

function generateDayOptions($planType, $month = null)
{
    $options = '';
    $currentYear = date('Y');
    $currentMonth = $month ?? date('n');

    if ($planType == 'Daily') {
        $options = '<option value="1">Day 1</option>';
    } elseif ($planType == 'Weekly') {
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        for ($i = 0; $i < 7; $i++) {
            $options .= '<option value="' . ($i + 1) . '">' . $daysOfWeek[$i] . '</option>';
        }
    } elseif ($planType == 'Monthly') {
        $daysInMonth = getDaysInMonth($currentYear, $currentMonth);
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $options .= '<option value="' . $i . '">Day ' . $i . '</option>';
        }
    }

    return $options;
}

function getMonthOptions()
{
    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];

    $options = '';
    foreach ($months as $num => $name) {
        $selected = ($num == date('n')) ? 'selected' : '';
        $options .= "<option value=\"$num\" $selected>$name</option>";
    }

    return $options;
}

$createMetadataTableQuery = "
CREATE TABLE IF NOT EXISTS meal_plan_metadata (
    meta_id INT AUTO_INCREMENT PRIMARY KEY,
    meal_plan_id INT NOT NULL,
    meta_key VARCHAR(255) NOT NULL,
    meta_value TEXT NOT NULL,
    FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(meal_plan_id) ON DELETE CASCADE,
    UNIQUE KEY unique_meta (meal_plan_id, meta_key)
) ENGINE=InnoDB;
";
$conn->exec($createMetadataTableQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'meal_assignment') {
        $planId = $_POST['plan_id'];
        $dayNumber = $_POST['day_number'];

        $planStmt = $conn->prepare("SELECT meal_plan_type FROM meal_plans WHERE meal_plan_id = ?");
        $planStmt->execute([$planId]);
        $planData = $planStmt->fetch(PDO::FETCH_ASSOC);
        $planType = $planData['meal_plan_type'];

        $isValidDay = true;
        if ($planType == 'Daily' && $dayNumber != 1) {
            $isValidDay = false;
        } elseif ($planType == 'Weekly' && ($dayNumber < 1 || $dayNumber > 7)) {
            $isValidDay = false;
        } elseif ($planType == 'Monthly') {
            $monthStmt = $conn->prepare("SELECT meta_value FROM meal_plan_metadata WHERE meal_plan_id = ? AND meta_key = 'month'");
            $monthStmt->execute([$planId]);
            $monthData = $monthStmt->fetch(PDO::FETCH_ASSOC);
            $month = $monthData['meta_value'] ?? date('n');
            $year = date('Y');
            $daysInMonth = getDaysInMonth($year, $month);

            if ($dayNumber < 1 || $dayNumber > $daysInMonth) {
                $isValidDay = false;
            }
        }

        if (!$isValidDay) {
            $feedback = 'Invalid day number for this plan type.';
        } else {
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
                    $checkStmt = $conn->prepare("
                        SELECT mpl.meal_plan_item_link_id 
                        FROM meal_plan_items_link mpl
                        JOIN meals m ON mpl.meal_id = m.meal_id
                        WHERE mpl.meal_plan_id = ? AND mpl.day_number = ? AND m.meal_type = ?
                    ");
                    $checkStmt->execute([$planId, $dayNumber, $meal['meal_type']]);
                    $existingMeal = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingMeal) {
                        $updateStmt = $conn->prepare("UPDATE meal_plan_items_link SET meal_id = ? WHERE meal_plan_item_link_id = ?");
                        $updateStmt->execute([$meal['meal_id'], $existingMeal['meal_plan_item_link_id']]);
                        if ($updateStmt) {
                            $successCount++;
                        }
                    } else {
                        $insertStmt = $conn->prepare("INSERT INTO meal_plan_items_link (meal_plan_id, meal_id, day_number) VALUES (?, ?, ?)");
                        $insertStmt->execute([$planId, $meal['meal_id'], $dayNumber]);
                        if ($insertStmt) {
                            $successCount++;
                        }
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

$selectedPlanType = isset($_GET['meal_plan_type']) ? $_GET['meal_plan_type'] : '';
$selectedPlanId = isset($_GET['selected_plan']) ? (int) $_GET['selected_plan'] : 0;
$mealPlanQuery = "SELECT * FROM meal_plans";
$conditions = [];

if (!empty($selectedPlanType)) {
    $conditions[] = "meal_plan_type = " . $conn->quote($selectedPlanType);
}

if ($selectedPlanId > 0) {
    $conditions[] = "meal_plan_id = " . $selectedPlanId;
}

if (!empty($conditions)) {
    $mealPlanQuery .= " WHERE " . implode(" AND ", $conditions);
}

$mealPlanResult = $conn->query($mealPlanQuery);
while ($row = $mealPlanResult->fetch(PDO::FETCH_ASSOC)) {
    $mealPlanData[] = $row;
}

$assignmentsQuery = "
    SELECT mpl.meal_plan_item_link_id, mpl.meal_plan_id, mpl.meal_id, mpl.day_number,
    m.name AS meal_name, m.meal_type, mp.name AS plan_name, mp.price AS plan_price,
    mp.meal_plan_type
    FROM meal_plan_items_link mpl
    JOIN meals m ON mpl.meal_id = m.meal_id
    JOIN meal_plans mp ON mpl.meal_plan_id = mp.meal_plan_id
    WHERE 1=1";

if (!empty($selectedPlanType)) {
    $assignmentsQuery .= " AND mp.meal_plan_type = " . $conn->quote($selectedPlanType);
}

if ($selectedPlanId > 0) {
    $assignmentsQuery .= " AND mp.meal_plan_id = " . $selectedPlanId;
}

$assignmentsStmt = $conn->prepare($assignmentsQuery);
$assignmentsStmt->execute();
$assignmentsData = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);

$planMonths = [];
if (!empty($mealPlanData)) {
    $monthlyPlans = array_filter($mealPlanData, function ($plan) {
        return $plan['meal_plan_type'] == 'Monthly';
    });

    if (!empty($monthlyPlans)) {
        $monthlyPlanIds = array_column($monthlyPlans, 'meal_plan_id');
        $placeholders = str_repeat('?,', count($monthlyPlanIds) - 1) . '?';

        $metadataQuery = "SELECT meal_plan_id, meta_value FROM meal_plan_metadata 
                          WHERE meal_plan_id IN ($placeholders) AND meta_key = 'month'";
        $metadataStmt = $conn->prepare($metadataQuery);
        $metadataStmt->execute($monthlyPlanIds);

        while ($row = $metadataStmt->fetch(PDO::FETCH_ASSOC)) {
            $planMonths[$row['meal_plan_id']] = $row['meta_value'];
        }
    }
}

$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

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
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Assign Meals to Meal Plans</h1>

            <?php if (!empty($feedback)): ?>
                <div class="feedback-message"><?php echo htmlspecialchars($feedback); ?></div>
            <?php endif; ?>

            <div class="button-center">
                <form method="GET" action="meal_assignment.php" style="display: inline-block; margin-right: 10px;">
                    <select name="meal_plan_type" onchange="this.form.submit()">
                        <option value="">All Meal Plan Types</option>
                        <option value="Daily" <?php echo (isset($_GET['meal_plan_type']) && $_GET['meal_plan_type'] == 'Daily' ? 'selected' : ''); ?>>Daily Plans</option>
                        <option value="Weekly" <?php echo (isset($_GET['meal_plan_type']) && $_GET['meal_plan_type'] == 'Weekly' ? 'selected' : ''); ?>>Weekly Plans</option>
                        <option value="Monthly" <?php echo (isset($_GET['meal_plan_type']) && $_GET['meal_plan_type'] == 'Monthly' ? 'selected' : ''); ?>>Monthly Plans</option>
                    </select>
                </form>

                <form method="GET" action="meal_assignment.php" style="display: inline-block; margin-right: 10px;">
                    <select name="selected_plan" onchange="this.form.submit()">
                        <option value="">Select Specific Plan</option>
                        <?php foreach ($mealPlanData as $plan): ?>
                            <?php
                            $planDisplay = $plan['name'] . ' (' . $plan['meal_plan_type'];
                            if ($plan['meal_plan_type'] == 'Monthly' && isset($planMonths[$plan['meal_plan_id']])) {
                                $planDisplay .= ' - ' . $months[$planMonths[$plan['meal_plan_id']]];
                            }
                            $planDisplay .= ')';
                            $selected = (isset($_GET['selected_plan']) && $_GET['selected_plan'] == $plan['meal_plan_id']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $plan['meal_plan_id']; ?>" <?php echo $selected; ?>>
                                <?php echo $planDisplay; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <a href="meal_assignment.php" class="update-button"
                    style="display: inline-block; padding: 5px 10px; text-decoration: none;">Reset Filters</a>
            </div>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Assign Meal to
                    Plan</button>
            </div>

            <div id="add-form" class="add-form" style="display:none;">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Assign New Meal to Plan</h2>
                <form method="POST" action="meal_assignment.php">
                    <input type="hidden" name="action" value="meal_assignment">

                    <label for="plan_id">Meal Plan:</label>
                    <select id="plan_id" name="plan_id" onchange="LuckyNest.updateDayOptions(this.value)" required>
                        <?php foreach ($mealPlanData as $plan): ?>
                            <option value="<?php echo $plan['meal_plan_id']; ?>"
                                data-plan-type="<?php echo $plan['meal_plan_type']; ?>">
                                <?php echo $plan['name'] . ' (' . $plan['meal_plan_type'];
                                if ($plan['meal_plan_type'] == 'Monthly' && isset($planMonths[$plan['meal_plan_id']])) {
                                    echo ' - ' . $months[$planMonths[$plan['meal_plan_id']]];
                                }
                                echo ') - £' . number_format($plan['price'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="day_selection_container">
                        <label for="day_number">Day:</label>
                        <select id="day_number" name="day_number" required>
                            <?php
                            $firstPlanType = !empty($mealPlanData) ? $mealPlanData[0]['meal_plan_type'] : 'Daily';
                            $firstPlanMonth = null;
                            if ($firstPlanType == 'Monthly' && !empty($mealPlanData)) {
                                $firstPlanId = $mealPlanData[0]['meal_plan_id'];
                                $firstPlanMonth = $planMonths[$firstPlanId] ?? null;
                            }
                            echo generateDayOptions($firstPlanType, $firstPlanMonth);
                            ?>
                        </select>
                    </div>
                    <input type="hidden" id="default_day_number" name="day_number" value="1" disabled>

                    <label>Breakfast:</label>
                    <select name="breakfast_meal_id">
                        <option value="">Select Breakfast</option>
                        <?php foreach ($mealData as $meal): ?>
                            <?php if ($meal['meal_type'] == 'Breakfast' || $meal['meal_type'] == 'Any'): ?>
                                <option value="<?php echo $meal['meal_id']; ?>">
                                    <?php echo $meal['name'] . ' (£' . number_format($meal['price'], 2) . ')'; ?>
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
                                    <?php echo $meal['name'] . ' (£' . number_format($meal['price'], 2) . ')'; ?>
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
                                    <?php echo $meal['name'] . ' (£' . number_format($meal['price'], 2) . ')'; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="update-button">Assign Meal(s)</button>
                </form>
            </div>

            <?php foreach ($mealPlanData as $plan): ?>
                <?php
                $planAssignments = array_filter($assignmentsData, function ($assignment) use ($plan) {
                    return $assignment['meal_plan_id'] == $plan['meal_plan_id'];
                });

                usort($planAssignments, function ($a, $b) {
                    if ($a['day_number'] != $b['day_number']) {
                        return $a['day_number'] - $b['day_number'];
                    }

                    $mealTypeOrder = ['Breakfast' => 1, 'Lunch' => 2, 'Dinner' => 3, 'Any' => 4];
                    $aOrder = $mealTypeOrder[$a['meal_type']] ?? 999;
                    $bOrder = $mealTypeOrder[$b['meal_type']] ?? 999;

                    return $aOrder - $bOrder;
                });

                $month = null;
                if ($plan['meal_plan_type'] == 'Monthly' && isset($planMonths[$plan['meal_plan_id']])) {
                    $monthNum = $planMonths[$plan['meal_plan_id']];
                    $month = $months[$monthNum] ?? '';
                }
                ?>
                <div class="meal-plan-section">
                    <h2>
                        <?php echo $plan['name']; ?>
                        (<?php echo $plan['meal_plan_type'];
                        echo ($month ? ' - ' . $month : ''); ?>)
                        - £<?php echo number_format($plan['price'], 2); ?>
                    </h2>
                    <?php if (!empty($planAssignments)): ?>
                        <table border="1">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Day</th>
                                    <th>Meal Name</th>
                                    <th>Meal Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planAssignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo $assignment['meal_plan_item_link_id']; ?></td>
                                        <td>
                                            <?php
                                            if ($plan['meal_plan_type'] == 'Weekly') {
                                                $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                                echo $daysOfWeek[$assignment['day_number'] - 1];
                                            } else {
                                                echo 'Day ' . $assignment['day_number'];
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $assignment['meal_name']; ?></td>
                                        <td><?php echo $assignment['meal_type']; ?></td>
                                        <td>
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
            if (empty($mealPlanData)): ?>
                <p>No meal plans found. Please create a meal plan first.</p>
            <?php endif; ?>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>