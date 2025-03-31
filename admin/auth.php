<?php
// Start session explicitly here before any output or includes that might start it
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connect.php'; // Needs $pdo
require_once '../includes/functions.php'; // Needs escape_html if used, etc.

// Redirect if not a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Basic validation
if (empty($username) || empty($password)) {
    header('Location: login.php?error=' . urlencode('Username and password are required.'));
    exit;
}

try {
    // Prepare statement to prevent SQL injection
    $stmt = $pdo->prepare("SELECT admin_id, username, password_hash FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(); // Fetch associative array




    // Verify user exists and password is correct
    if ($admin && password_verify($password, $admin['password_hash'])) {
        // Password matches! Log the user in.
        // Regenerate session ID for security
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];

        // Redirect to the admin dashboard or intended page
        // $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
        // unset($_SESSION['redirect_url']); // Clear the stored URL
        header('Location: index.php');
        exit;

    } else {
        // Invalid credentials
        header('Location: login.php?error=' . urlencode('Invalid username or password.'));
        exit;
    }

} catch (PDOException $e) {
    // Log the error securely
    error_log("Admin Login Error: " . $e->getMessage());
    // Show generic error to user
    header('Location: login.php?error=' . urlencode('An error occurred during login. Please try again.'));
    exit;
}
?>