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

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$mealTypes = ['Breakfast', 'Lunch', 'Dinner'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_meal') {
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
            header("Location: meals.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error adding meal.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_meal') {
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
            header("Location: meals.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error updating the meal.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_meal') {
        $mealId = $_POST['meal_id'];

        $stmt = $conn->prepare("DELETE FROM meals WHERE meal_id = ?");
        $stmt->execute([$mealId]);

        if ($stmt) {
            $feedback = 'Meal deleted successfully!';
            header("Location: meals.php?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error deleting the meal.';
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

$dietaryTagsQuery = $conn->query("SELECT * FROM meal_dietary_tags");
$dietaryTags = $dietaryTagsQuery->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Meals</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="rooms-types-container">
            <h1>Manage Meals</h1>
            <?php if ($feedback): ?>
                <div class="rooms-feedback" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Meal</button>
            </div>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Add New Meal</h2>
                <form method="POST" action="meals.php">
                    <input type="hidden" name="action" value="add_meal">
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
                    
                    <div>
                        <label for="is_active">Active:</label>
                        <input type="checkbox" id="is_active" name="is_active" checked>
                    </div>
                    
                    <label>Dietary Tags:</label>
                    <?php foreach ($dietaryTags as $tag): ?>
                        <div>
                            <input type="checkbox" id="tag_<?php echo $tag['meal_dietary_tag_id']; ?>" name="dietary_tags[]"
                                value="<?php echo $tag['meal_dietary_tag_id']; ?>">
                            <label for="tag_<?php echo $tag['meal_dietary_tag_id']; ?>"><?php echo $tag['name']; ?></label>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="update-button">Add Meal</button>
                </form>
            </div>

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
                                <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $meal['meal_id']; ?>')" class="update-button">Edit</button>
                                <div id="edit-form-<?php echo $meal['meal_id']; ?>" class="rooms-type-edit-form">
                                    <form method="POST" action="meals.php" style="display:inline;">
                                        <button type="button" class="close-button" onclick="LuckyNest.toggleForm('edit-form-<?php echo $meal['meal_id']; ?>')">✕</button>
                                        <h2>Edit Meal</h2>
                                        <input type="hidden" name="action" value="edit_meal">
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
                                        
                                        <div>
                                            <label for="is_active_<?php echo $meal['meal_id']; ?>">Active:</label>
                                            <input type="checkbox" id="is_active_<?php echo $meal['meal_id']; ?>" name="is_active"
                                                <?php echo $meal['is_active'] ? 'checked' : ''; ?>>
                                        </div>
                                        
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
                                        
                                        <div class="rooms-button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button" 
                                                onclick="document.getElementById('delete-meal-form-<?php echo $meal['meal_id']; ?>').submit(); return false;">
                                                Delete
                                            </button>
                                        </div>
                                    </form>

                                    <form id="delete-meal-form-<?php echo $meal['meal_id']; ?>" method="POST" action="meals.php" style="display:none;">
                                        <input type="hidden" name="action" value="delete_meal">
                                        <input type="hidden" name="meal_id" value="<?php echo $meal['meal_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $url = 'meals.php';
            echo generatePagination($page, $totalPages, $url);
            ?>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>