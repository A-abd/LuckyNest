<?php
session_start();

include __DIR__ . '/../include/db.php';
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
    <title>Unauthorized Access</title>
</head>

<body>
    <?php
    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'admin':
                include "../include/admin_navbar.php";
                break;
            case 'owner':
                include "../include/admin_navbar.php";
                break;
            default:
                include "../include/guest_navbar.php";
        }
    }
    ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="content-container">
            <div class="unauthorized-message">
                <h1>Unauthorized Access</h1>
                <p>You do not have permission to view this page.</p>
                <p>Please contact the administrator if you believe this is an error.</p>

                <?php if (!isset($_SESSION['role'])): ?>
                    <p>You are not logged in, please login to access this page.</p>
                    <div class="button-center">
                        <a href="../index.php" class="update-button">Log In</a>
                    </div>
                <?php else: ?>
                    <div class="button-center">
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <a href="../admin/dashboard.php" class="update-button">Go to Dashboard</a>
                        <?php elseif ($_SESSION['role'] == 'staff'): ?>
                            <a href="../guest/dashboard.php" class="update-button">Go to Dashboard</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>