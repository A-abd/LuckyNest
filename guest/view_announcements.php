<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$announcementData = [];

$recordsPerPage = 10;
$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$offset = ($page - 1) * $recordsPerPage;

$stmt = $conn->prepare("SELECT a.*, u.forename, u.surname
                       FROM announcements a
                       LEFT JOIN users u ON a.created_by = u.user_id
                       ORDER BY a.important DESC, a.created_at DESC 
                       LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$announcementData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalStmt = $conn->query("SELECT COUNT(*) As total FROM announcements");
$totalRecords = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
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
    <title>Announcements</title>
    <style>
        .announcements-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .announcement {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .announcement.important {
            border-left: 5px solid #ff4444;
            background-color: #fff8f8;
        }

        .announcement-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .announcement-meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .announcement-message {
            line-height: 1.5;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px;
        }

        .badge-important {
            background-color: #ff4444;
            color: white;
        }

        .no-announcements {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>

<body>
    <?php include "../include/" . ($_SESSION['role'] == 'guest' ? 'guest_navbar.php' : 'admin_navbar.php'); ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../index.php">LuckyNest</a></h1>
        <div class="announcements-container">
            <h1>Announcements</h1>

            <?php if (count($announcementData) > 0): ?>
                <?php foreach ($announcementData as $announcement): ?>
                    <div class="announcement <?php echo $announcement['important'] ? 'important' : ''; ?>">
                        <div class="announcement-title">
                            <?php echo htmlspecialchars($announcement['title']); ?>
                            <?php if ($announcement['important']): ?>
                                <span class="badge badge-important">Important</span>
                            <?php endif; ?>
                        </div>
                        <div class="announcement-meta">
                            By <?php echo htmlspecialchars($announcement['forename'] . ' ' . $announcement['surname']); ?>
                            on <?php echo date('Y-m-d H:i:s', strtotime($announcement['created_at'])); ?>
                        </div>
                        <div class="announcement-message">
                            <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                $url = 'view_announcements.php';
                echo generatePagination($page, $totalPages, $url);
                ?>
            <?php else: ?>
                <div class="no-announcements">
                    <h3>No announcements available at this time.</h3>
                </div>
            <?php endif; ?>

            <br>
            <div class="back-button-container">
                <a href="dashboard.php" class="button">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>

</html>