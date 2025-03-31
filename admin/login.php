<?php
// --- admin/login.php ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Start session if not already started to check login status
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to admin dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php'); // Redirect within admin folder
    exit;
}

// Include the header (which itself includes functions and handles session start)
// The path 'partials/header.php' is correct relative to login.php within the admin folder
require_once 'partials/header.php';
?>

<h2>Admin Login</h2>




<form action="auth.php" method="post" class="form-basic">
    <div class="form-group">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>
    </div>
    <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div class="form-group">
        <button type="submit">Login</button>
    </div>
</form>

<?php include 'partials/footer.php'; ?>