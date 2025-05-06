<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest') {
    header('Location: ../authentication/unauthorized');
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
</head>

<body>
    <?php include "../include/" . ($_SESSION['role'] == 'guest' ? 'guest_navbar.php' : 'admin_navbar.php'); ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../guest/dashboard">LuckyNest</a></h1>
        <div class="content-container">
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
                            at <?php echo date('H:i', strtotime($announcement['created_at'])); ?>
                            on <?php echo date('d/m/Y', strtotime($announcement['created_at'])); ?>
                        </div>
                        <div class="announcement-message">
                            <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                $url = 'view_announcements';
                echo generatePagination($page, $totalPages, $url);
                ?>
            <?php else: ?>
                <div class="no-announcements">
                    <h3>No announcements available at this time.</h3>
                </div>
            <?php endif; ?>

            <br>
        </div>
    </div>
</body>

</html>