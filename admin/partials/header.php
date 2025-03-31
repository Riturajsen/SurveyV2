<?php
// Path Change: ../../includes -> ../includes
require_once __DIR__ . '/../../includes/functions.php';
$is_logged_in = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="admin-header">
        <h1>Survey Admin Panel</h1>
        <nav>
            <?php if ($is_logged_in): ?>
                <a href="index.php">Dashboard</a> |
                <a href="manage_surveys.php">Manage Surveys</a> |
                <a href="logout.php">Logout (<?php echo escape_html($_SESSION['admin_username'] ?? ''); ?>)</a>
            <?php else: ?>
                <span>Please login</span>
            <?php endif; ?>
        </nav>
    </header>
    <main class="admin-container">
        <?php if (isset($_GET['success'])): ?>
            <p class="message success"><?php echo escape_html(urldecode($_GET['success'])); ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <p class="message error"><?php echo escape_html(urldecode($_GET['error'])); ?></p>
        <?php endif; ?>