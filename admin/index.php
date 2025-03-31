<?php
require_once '../includes/functions.php';
require_admin_login();

include 'partials/header.php';
?>

<h2>Admin Dashboard</h2>
<p>Welcome, <?php echo escape_html($_SESSION['admin_username']); ?>!</p>

<p>From here you can manage the surveys:</p>
<ul>
    <li><a href="manage_surveys.php">Manage Surveys</a></li>
    </ul>

<?php include 'partials/footer.php'; ?>