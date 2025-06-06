<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized');
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

$uploadDir = __DIR__ . '/../assets/meal_images/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_meal') {
        $name = $_POST['name'];
        $mealType = $_POST['meal_type'];
        $price = $_POST['price'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $dietaryTags = isset($_POST['dietary_tags']) ? $_POST['dietary_tags'] : [];
        $imagePath = null;

        if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($_FILES['meal_image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                if (move_uploaded_file($_FILES['meal_image']['tmp_name'], $targetPath)) {
                    $imagePath = 'assets/meal_images/' . $fileName;
                } else {
                    $feedback = 'Error uploading image.';
                }
            } else {
                $feedback = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
            }
        }

        if (empty($feedback)) {
            $stmt = $conn->prepare("INSERT INTO meals (name, meal_type, price, is_active, image_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $mealType, $price, $isActive, $imagePath]);

            if ($stmt) {
                $mealId = $conn->lastInsertId();

                if (!empty($dietaryTags)) {
                    foreach ($dietaryTags as $tagId) {
                        $tagStmt = $conn->prepare("INSERT INTO meal_dietary_tags_link (meal_id, meal_dietary_tag_id) VALUES (?, ?)");
                        $tagStmt->execute([$mealId, $tagId]);
                    }
                }

                $feedback = 'Meal added successfully!';
                header("Location: meals?feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error adding meal.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_meal') {
        $mealId = $_POST['meal_id'];
        $name = $_POST['name'];
        $mealType = $_POST['meal_type'];
        $price = $_POST['price'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $dietaryTags = isset($_POST['dietary_tags']) ? $_POST['dietary_tags'] : [];
        $imagePath = null;
        $deleteImage = isset($_POST['delete_image']);

        $currentImageStmt = $conn->prepare("SELECT image_path FROM meals WHERE meal_id = ?");
        $currentImageStmt->execute([$mealId]);
        $currentImage = $currentImageStmt->fetchColumn();

        if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($_FILES['meal_image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                if (move_uploaded_file($_FILES['meal_image']['tmp_name'], $targetPath)) {
                    if ($currentImage && file_exists(__DIR__ . '/../' . $currentImage)) {
                        unlink(__DIR__ . '/../' . $currentImage);
                    }
                    $imagePath = 'assets/meal_images/' . $fileName;
                } else {
                    $feedback = 'Error uploading image.';
                }
            } else {
                $feedback = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
            }
        } elseif ($deleteImage && $currentImage) {
            if (file_exists(__DIR__ . '/../' . $currentImage)) {
                unlink(__DIR__ . '/../' . $currentImage);
            }
            $imagePath = null;
        } else {
            $imagePath = $currentImage;
        }

        if (empty($feedback)) {
            $stmt = $conn->prepare("UPDATE meals SET name = ?, meal_type = ?, price = ?, is_active = ?, image_path = ? WHERE meal_id = ?");
            $stmt->execute([$name, $mealType, $price, $isActive, $imagePath, $mealId]);

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
                header("Location: meals?feedback=" . urlencode($feedback));
                exit();
            } else {
                $feedback = 'Error updating the meal.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_meal') {
        $mealId = $_POST['meal_id'];

        $imageStmt = $conn->prepare("SELECT image_path FROM meals WHERE meal_id = ?");
        $imageStmt->execute([$mealId]);
        $imagePath = $imageStmt->fetchColumn();

        $stmt = $conn->prepare("DELETE FROM meals WHERE meal_id = ?");
        $stmt->execute([$mealId]);

        if ($stmt) {
            if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
                unlink(__DIR__ . '/../' . $imagePath);
            }
            $feedback = 'Meal deleted successfully!';
            header("Location: meals?feedback=" . urlencode($feedback));
            exit();
        } else {
            $feedback = 'Error deleting the meal.';
        }
    }
}

if (isset($_GET['feedback'])) {
    $feedback = $_GET['feedback'];
}

$stmt = $conn->prepare("SELECT * FROM meals ORDER BY meal_id LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mealData = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($mealData as $key => $meal) {
    $mealId = $meal['meal_id'];
    $tagQuery = $conn->prepare("
        SELECT mdt.meal_dietary_tag_id, mdt.name 
        FROM meal_dietary_tags mdt 
        JOIN meal_dietary_tags_link mdtl ON mdt.meal_dietary_tag_id = mdtl.meal_dietary_tag_id 
        WHERE mdtl.meal_id = ?
    ");
    $tagQuery->execute([$mealId]);
    $mealData[$key]['dietary_tags'] = $tagQuery->fetchAll(PDO::FETCH_ASSOC);
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
        <h1><a class="title" href="../admin/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Manage Meals</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Meal</button>
            </div>

            <br>

            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Add New Meal</h2>
                <form method="POST" action="meals" enctype="multipart/form-data">
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

                    <label for="meal_image">Meal Image (optional):</label>
                    <input type="file" id="meal_image" name="meal_image" accept="image/*">

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
                        <th>Image</th>
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
                            <td>
                                <?php if ($meal['image_path']): ?>
                                    <img src="../<?php echo $meal['image_path']; ?>" alt="<?php echo $meal['name']; ?>"
                                        style="max-width: 100px; max-height: 100px;">
                                <?php else: ?>
                                    No image
                                <?php endif; ?>
                            </td>
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
                                <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $meal['meal_id']; ?>')"
                                    class="update-button">Edit</button>
                                <div id="edit-form-<?php echo $meal['meal_id']; ?>" class="edit-form">
                                    <form method="POST" action="meals" enctype="multipart/form-data"
                                        style="display:inline;">
                                        <button type="button" class="close-button"
                                            onclick="LuckyNest.toggleForm('edit-form-<?php echo $meal['meal_id']; ?>')">✕</button>
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
                                            <input type="checkbox" id="is_active_<?php echo $meal['meal_id']; ?>"
                                                name="is_active" <?php echo $meal['is_active'] ? 'checked' : ''; ?>>
                                        </div>

                                        <label for="meal_image_<?php echo $meal['meal_id']; ?>">Meal Image:</label>
                                        <?php if ($meal['image_path']): ?>
                                            <div>
                                                <img src="../<?php echo $meal['image_path']; ?>" alt="Current image"
                                                    style="max-width: 100px; max-height: 100px;">
                                                <div>
                                                    <input type="checkbox" id="delete_image_<?php echo $meal['meal_id']; ?>"
                                                        name="delete_image">
                                                    <label for="delete_image_<?php echo $meal['meal_id']; ?>">Delete current
                                                        image</label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" id="meal_image_<?php echo $meal['meal_id']; ?>" name="meal_image"
                                            accept="image/*">

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

                                        <div class="button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button"
                                                onclick="document.getElementById('delete-meal-form-<?php echo $meal['meal_id']; ?>').submit(); return false;">
                                                Delete
                                            </button>
                                        </div>
                                    </form>

                                    <form id="delete-meal-form-<?php echo $meal['meal_id']; ?>" method="POST"
                                        action="meals" style="display:none;">
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
            $url = 'meals';
            echo generatePagination($page, $totalPages, $url);
            ?>
        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>