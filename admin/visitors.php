<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: ../authentication/unauthorized');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$visitorData = [];
$errors = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            $forename = $_POST['forename'];
            $surname = $_POST['surname'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $visit_time = $_POST['visit_time'];
            $leave_time = $_POST['leave_time'];

            if (strtotime($leave_time) <= strtotime($visit_time)) {
                $errors['leave_time'] = 'Leave time must be after visit time';
                $isValid = false;
            }

            if (strtotime($leave_time) <= strtotime($visit_time)) {
                $errors['leave_time'] = 'Leave time must be after visit time';
                $isValid = false;
            }

            $isValid = true;

            if (empty($phone)) {
                $errors['phone'] = 'Phone number is required';
                $isValid = false;
            } elseif (!preg_match('/^\+[0-9]{1,4}[0-9]{9,11}$/', $phone)) {
                $errors['phone'] = 'Phone number must include country code (+) followed by 9-11 digits';
                $isValid = false;
            }

            if ($isValid) {
                $stmt = $conn->prepare("INSERT INTO visitors (forename, surname, email, phone, visit_time, leave_time) VALUES (:forename, :surname, :email, :phone, :visit_time, :leave_time)");
                $stmt->bindParam(':forename', $forename, PDO::PARAM_STR);
                $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindParam(':visit_time', $visit_time, PDO::PARAM_STR);
                $stmt->bindParam(':leave_time', $leave_time, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $_SESSION['feedback'] = 'Visitor added successfully!';
                    header("Location: visitors");
                    exit();
                } else {
                    $feedback = 'Error adding visitor.';
                }
            } else {
                $feedback = 'Please correct the errors.';
            }
        } elseif ($action === 'edit') {
            $visitor_id = $_POST['visitor_id'];
            $forename = $_POST['forename'];
            $surname = $_POST['surname'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $visit_time = $_POST['visit_time'];
            $leave_time = $_POST['leave_time'];

            $isValid = true;

            if (empty($phone)) {
                $errors['phone'] = 'Phone number is required';
                $isValid = false;
            } elseif (!preg_match('/^\+[0-9]{1,4}[0-9]{9,11}$/', $phone)) {
                $errors['phone'] = 'Phone number must include country code (+) followed by 9-11 digits';
                $isValid = false;
            }

            if ($isValid) {
                $stmt = $conn->prepare("UPDATE visitors SET forename = :forename, surname = :surname, email = :email, phone = :phone, visit_time = :visit_time, leave_time = :leave_time WHERE visitor_id = :visitor_id");
                $stmt->bindParam(':visitor_id', $visitor_id, PDO::PARAM_INT);
                $stmt->bindParam(':forename', $forename, PDO::PARAM_STR);
                $stmt->bindParam(':surname', $surname, PDO::PARAM_STR);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindParam(':visit_time', $visit_time, PDO::PARAM_STR);
                $stmt->bindParam(':leave_time', $leave_time, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $_SESSION['feedback'] = 'Visitor updated successfully!';
                    header("Location: visitors");
                    exit();
                } else {
                    $feedback = 'Error updating the visitor.';
                }
            } else {
                $feedback = 'Please correct the errors.';
            }
        } elseif ($action === 'delete') {
            $visitor_id = $_POST['visitor_id'];

            $stmt = $conn->prepare("DELETE FROM visitors WHERE visitor_id = :visitor_id");
            $stmt->bindParam(':visitor_id', $visitor_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['feedback'] = 'Visitor deleted successfully!';
                header("Location: visitors");
                exit();
            } else {
                $feedback = 'Error deleting the visitor.';
            }
        }
    }
}

if (isset($_SESSION['feedback'])) {
    $feedback = $_SESSION['feedback'];
    unset($_SESSION['feedback']);
}

$stmt = $conn->prepare("SELECT * FROM visitors LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$visitorData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRecordsQuery = $conn->query("SELECT COUNT(*) As total FROM visitors");
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
    <title>Manage Visitors</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard">LuckyNest</a></h1>
        <div class="content-container">
            <h1>Manage Visitors</h1>
            <?php if ($feedback): ?>
                <div class="feedback-message" id="feedback_message"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <!-- Add Visitor Button -->
            <div class="button-center">
                <button onclick="LuckyNest.toggleForm('add-form')" class="update-add-button">Add Visitor</button>
            </div>

            <!-- Add Visitor Form -->
            <div id="add-form" class="add-form">
                <button type="button" class="close-button" onclick="LuckyNest.toggleForm('add-form')">✕</button>
                <h2>Add New Visitor</h2>
                <form method="POST" action="visitors">
                    <input type="hidden" name="action" value="add">
                    <label for="forename">Forename:</label>
                    <input type="text" id="forename" name="forename" required>
                    <label for="surname">Surname:</label>
                    <input type="text" id="surname" name="surname" required>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                    <label for="phone">Phone (with country code):</label>
                    <input type="text" id="phone" name="phone" required pattern="^\+[0-9]{1,4}[0-9]{9,11}$"
                        placeholder="+441234567890">
                    <small>Phone number must include country code (e.g., +44 for UK, +1 for US)</small>
                    <?php if (isset($errors['phone'])): ?>
                        <small class="error-text"><?php echo $errors['phone']; ?></small>
                    <?php endif; ?>
                    <label for="visit_time">Visit Time:</label>
                    <input type="datetime-local" id="visit_time" name="visit_time" required>
                    <label for="leave_time">Leave Time:</label>
                    <input type="datetime-local" id="leave_time" name="leave_time" required>
                    <?php if (isset($errors['leave_time'])): ?>
                        <small class="error-text"><?php echo $errors['leave_time']; ?></small>
                    <?php endif; ?>
                    <button type="submit" class="update-button">Add Visitor</button>
                </form>
            </div>

            <!-- Visitor List -->
            <h2>Visitor List</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Forename</th>
                        <th>Surname</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Visit Time</th>
                        <th>Leave Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitorData as $visitor): ?>
                        <tr>
                            <td><?php echo $visitor['visitor_id']; ?></td>
                            <td><?php echo $visitor['forename']; ?></td>
                            <td><?php echo $visitor['surname']; ?></td>
                            <td><?php echo $visitor['email']; ?></td>
                            <td><?php echo $visitor['phone']; ?></td>
                            <td><?php echo date('H:i d/m/Y', strtotime($visitor['visit_time'])); ?></td>
                            <td><?php echo date('H:i d/m/Y', strtotime($visitor['leave_time'])); ?></td>
                            <td>
                                <button onclick="LuckyNest.toggleForm('edit-form-<?php echo $visitor['visitor_id']; ?>')"
                                    class="update-button">Edit</button>
                                <div id="edit-form-<?php echo $visitor['visitor_id']; ?>" class="edit-form">
                                    <form method="POST" action="visitors" style="display:inline;">
                                        <button type="button" class="close-button"
                                            onclick="LuckyNest.toggleForm('edit-form-<?php echo $visitor['visitor_id']; ?>')">✕</button>
                                        <h2>Edit Visitor</h2>
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="visitor_id"
                                            value="<?php echo $visitor['visitor_id']; ?>">
                                        <label for="forename_<?php echo $visitor['visitor_id']; ?>">Forename:</label>
                                        <input type="text" id="forename_<?php echo $visitor['visitor_id']; ?>"
                                            name="forename" value="<?php echo $visitor['forename']; ?>" required>
                                        <label for="surname_<?php echo $visitor['visitor_id']; ?>">Surname:</label>
                                        <input type="text" id="surname_<?php echo $visitor['visitor_id']; ?>" name="surname"
                                            value="<?php echo $visitor['surname']; ?>" required>
                                        <label for="email_<?php echo $visitor['visitor_id']; ?>">Email:</label>
                                        <input type="email" id="email_<?php echo $visitor['visitor_id']; ?>" name="email"
                                            value="<?php echo $visitor['email']; ?>" required>
                                        <label for="phone_<?php echo $visitor['visitor_id']; ?>">Phone (with country
                                            code):</label>
                                        <input type="text" id="phone_<?php echo $visitor['visitor_id']; ?>" name="phone"
                                            value="<?php echo $visitor['phone']; ?>" required
                                            pattern="^\+[0-9]{1,4}[0-9]{9,11}$" placeholder="+441234567890">
                                        <small>Phone number must include country code (e.g., +44 for UK, +1 for US)</small>
                                        <?php if (isset($errors['phone'])): ?>
                                            <small class="error-text"><?php echo $errors['phone']; ?></small>
                                        <?php endif; ?>
                                        <label for="visit_time_<?php echo $visitor['visitor_id']; ?>">Visit Time:</label>
                                        <input type="datetime-local" id="visit_time_<?php echo $visitor['visitor_id']; ?>"
                                            name="visit_time"
                                            value="<?php echo date('Y-m-d\TH:i', strtotime($visitor['visit_time'])); ?>"
                                            required>
                                        <label for="leave_time_<?php echo $visitor['visitor_id']; ?>">Leave Time:</label>
                                        <input type="datetime-local" id="leave_time_<?php echo $visitor['visitor_id']; ?>"
                                            name="leave_time"
                                            value="<?php echo date('Y-m-d\TH:i', strtotime($visitor['leave_time'])); ?>"
                                            required>
                                        <?php if (isset($errors['leave_time'])): ?>
                                            <small class="error-text"><?php echo $errors['leave_time']; ?></small>
                                        <?php endif; ?>
                                        <div class="button-group">
                                            <button type="submit" class="update-button">Update</button>
                                            <button type="button" class="update-button"
                                                onclick="document.getElementById('delete-form-<?php echo $visitor['visitor_id']; ?>').submit(); return false;">Delete</button>
                                        </div>
                                    </form>

                                    <form id="delete-form-<?php echo $visitor['visitor_id']; ?>" method="POST"
                                        action="visitors" style="display:none;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="visitor_id"
                                            value="<?php echo $visitor['visitor_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $url = 'visitors';
            echo generatePagination($page, $totalPages, $url);
            ?>
            <br>

        </div>
        <div id="form-overlay"></div>
    </div>
</body>

</html>