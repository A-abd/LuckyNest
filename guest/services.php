<?php
session_start();

if (!isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'purchase_meal_plan') {
            $mealPlanId = $_POST['meal_plan_id'];
            $userId = $_SESSION['user_id'];

            $stmt = $conn->prepare("INSERT INTO meal_plan_user_link (user_id, meal_plan_id) VALUES (:user_id, :meal_plan_id)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':meal_plan_id', $mealPlanId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Meal plan purchased successfully!';
            } else {
                $feedback = 'Error purchasing meal plan.';
            }
        } elseif ($action === 'book_laundry_slot') {
            $laundrySlotId = $_POST['laundry_slot_id'];

            $stmt = $conn->prepare("UPDATE laundry_slots SET is_available = 0 WHERE laundry_slot_id = :id");
            $stmt->bindParam(':id', $laundrySlotId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $feedback = 'Laundry slot booked successfully!';
            } else {
                $feedback = 'Error booking laundry slot.';
            }
        }
    }
    elseif ($action === 'book_laundry_slot') {
        $laundrySlotId = $_POST['laundry_slot_id'];
        $userId = $_SESSION['user_id'];
        
        $conn->beginTransaction();
        
        try {
            $slotStmt = $conn->prepare("UPDATE laundry_slots SET is_available = 0 WHERE laundry_slot_id = :id");
            $slotStmt->bindParam(':id', $laundrySlotId, PDO::PARAM_INT);
            $slotStmt->execute();
            
            $linkStmt = $conn->prepare("INSERT INTO laundry_slot_user_link (user_id, laundry_slot_id, is_cancelled, is_paid) VALUES (:user_id, :laundry_slot_id, 0, 0)");
            $linkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $linkStmt->bindParam(':laundry_slot_id', $laundrySlotId, PDO::PARAM_INT);
            $linkStmt->execute();
            
            $conn->commit();
            
            $feedback = 'Laundry slot booked successfully!';
        } catch (PDOException $e) {
            $conn->rollBack();
            $feedback = 'Error booking laundry slot: ' . $e->getMessage();
        }
    }
}

$mealPlanQuery = $conn->query("SELECT meal_plan_id, name, meal_plan_type, is_active FROM meal_plans WHERE is_active = 1");
$mealPlans = $mealPlanQuery->fetchAll(PDO::FETCH_ASSOC);

$laundryQuery = $conn->query("SELECT * FROM laundry_slots WHERE is_available = 1 ORDER BY date, start_time");
$laundrySlots = $laundryQuery->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Services</title>
</head>

<body>
    <div class="manage-default">
        <h1>Services</h1>
        <?php if ($feedback): ?>
            <p style="color: green;"><?php echo $feedback; ?></p>
        <?php endif; ?>

        <div class="services-container">
            <div class="meal-plans-section">
                <h2>Meal Plans</h2>

                <?php if (count($mealPlans) > 0): ?>
                    <select id="meal-plan-selector" onchange="showMealPlanDetails(this.value)">
                        <option value="">Select a meal plan</option>
                        <?php foreach ($mealPlans as $plan): ?>
                            <option value="<?php echo $plan['meal_plan_id']; ?>"><?php echo $plan['name']; ?>
                                (<?php echo $plan['meal_plan_type']; ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <?php foreach ($mealPlans as $plan): ?>
                        <div id="meal-plan-<?php echo $plan['meal_plan_id']; ?>" class="meal-plan-details"
                            style="display: none;">
                            <h3><?php echo $plan['name']; ?></h3>
                            <p><strong>Type:</strong> <?php echo $plan['meal_plan_type']; ?></p>

                            <?php
                            $mealItemsQuery = $conn->prepare("
                                SELECT mpil.day_number, m.name as meal, m.price
                                FROM meal_plan_items_link mpil
                                JOIN meals m ON mpil.meal_id = m.meal_id
                                WHERE mpil.meal_plan_id = :planId
                                ORDER BY mpil.day_number
                            ");
                            $mealItemsQuery->bindParam(':planId', $plan['meal_plan_id'], PDO::PARAM_INT);
                            $mealItemsQuery->execute();
                            $mealItems = $mealItemsQuery->fetchAll(PDO::FETCH_ASSOC);

                            $mealsByDay = [];
                            foreach ($mealItems as $item) {
                                $dayNum = $item['day_number'];
                                if (!isset($mealsByDay[$dayNum])) {
                                    $mealsByDay[$dayNum] = [];
                                }
                                $mealsByDay[$dayNum][] = $item;
                            }
                            ?>

                            <h4>Menu</h4>
                            <?php if (count($mealItems) > 0): ?>
                                <?php foreach ($mealsByDay as $day => $dayMeals): ?>
                                    <div class="day-menu">
                                        <h5>Day <?php echo $day; ?></h5>
                                        <ul>
                                            <?php foreach ($dayMeals as $meal): ?>
                                                <li>
                                                    <strong><?php echo $meal['meal']; ?></strong>
                                                    (£<?php echo number_format($meal['price'], 2); ?>)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>

                                <?php
                                $totalPrice = 0;
                                foreach ($mealItems as $item) {
                                    $totalPrice += $item['price'];
                                }
                                ?>
                                <p><strong>Total Price: £<?php echo number_format($totalPrice, 2); ?></strong></p>

                                <form method="POST" action="services.php">
                                    <input type="hidden" name="action" value="purchase_meal_plan">
                                    <input type="hidden" name="meal_plan_id" value="<?php echo $plan['meal_plan_id']; ?>">
                                    <button type="submit" class="update-button">Purchase This Meal Plan</button>
                                </form>
                            <?php else: ?>
                                <p>No meals available for this plan.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No meal plans currently available.</p>
                <?php endif; ?>
            </div>

            <div class="laundry-slots-section">
                <h2>Laundry Slots</h2>

                <?php if (count($laundrySlots) > 0): ?>
                    <table border="1">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Start Time</th>
                                <th>Recurring</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($laundrySlots as $slot): ?>
                                <tr>
                                    <td><?php echo $slot['date']; ?></td>
                                    <td><?php echo $slot['start_time']; ?></td>
                                    <td><?php echo $slot['recurring'] ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo number_format($slot['price'], 2); ?></td>
                                    <td>
                                        <form method="POST" action="services.php">
                                            <input type="hidden" name="action" value="book_laundry_slot">
                                            <input type="hidden" name="laundry_slot_id"
                                                value="<?php echo $slot['laundry_slot_id']; ?>">
                                            <button type="submit" class="update-button">Book Slot</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No laundry slots currently available.</p>
                <?php endif; ?>
            </div>
        </div>

        <br>
        <a href="dashboard.php" class="button">Back to Dashboard</a>
    </div>

    <script>
        // TODO move this to scripst
        function showMealPlanDetails(planId) {
            const planDetails = document.querySelectorAll('.meal-plan-details');
            planDetails.forEach(plan => {
                plan.style.display = 'none';
            });

            if (planId) {
                const selectedPlan = document.getElementById('meal-plan-' + planId);
                if (selectedPlan) {
                    selectedPlan.style.display = 'block';
                }
            }
        }
    </script>
</body>

</html>