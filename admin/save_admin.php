<?php
// --- admin/save_admin.php ---
require_once '../includes/functions.php';
require_admin_login(); // Must be logged in to perform this action
require_once '../includes/db_connect.php';

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_admins.php');
    exit;
}

// --- CSRF Validation ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) {
    // Redirecting with a generic error is safer than die()
    header('Location: add_admin.php?error=' . urlencode('Invalid security token. Please try again.'));
    exit;
}

// --- Get Data ---
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? ''; // Don't trim password initially
$confirm_password = $_POST['confirm_password'] ?? '';

// --- Validation ---
$errors = [];
if (empty($username)) {
    $errors[] = "Username cannot be empty.";
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) { // Allow letters, numbers, underscore
    $errors[] = "Username can only contain letters, numbers, and underscores.";
} elseif (strlen($username) < 3) {
     $errors[] = "Username must be at least 3 characters long.";
}

if (empty($password)) {
    $errors[] = "Password cannot be empty.";
} elseif (strlen($password) < 8) { // Enforce minimum length
    $errors[] = "Password must be at least 8 characters long.";
}

if ($password !== $confirm_password) {
    $errors[] = "Passwords do not match.";
}

// Check if username already exists (only if other validation passes)
if (empty($errors)) {
    try {
        $stmt_check = $pdo->prepare("SELECT 1 FROM admins WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetch()) {
            $errors[] = "Username '$username' is already taken. Please choose another.";
        }
    } catch (PDOException $e) {
        error_log("Error checking username existence: " . $e->getMessage());
        $errors[] = "Database error checking username. Please try again.";
    }
}

// --- Redirect if Errors ---
if (!empty($errors)) {
    // Using session flash messages for errors/data is recommended
    // $_SESSION['form_errors'] = $errors;
    // $_SESSION['form_data'] = $_POST;
    header('Location: add_admin.php?error=' . urlencode($errors[0])); // Simple redirect with first error
    exit;
}

// --- Hash Password ---
$password_hash = password_hash($password, PASSWORD_DEFAULT);
if ($password_hash === false) {
     error_log("Password hashing failed for user $username.");
     header('Location: add_admin.php?error=' . urlencode('Failed to process password. Please try again.'));
     exit;
}


// --- Save to Database ---
try {
    $sql = "INSERT INTO admins (username, password_hash) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $password_hash]);

    header("Location: manage_admins.php?success=" . urlencode("Administrator '$username' added successfully!"));
    exit;

} catch (PDOException $e) {
    error_log("Error saving new admin '$username': " . $e->getMessage());
     // Check for unique constraint violation specifically (error code 1062) - redundant if checked above but good fallback
     if ($e->getCode() == '23000') {
         header('Location: add_admin.php?error=' . urlencode("Username '$username' is already taken."));
     } else {
         header('Location: add_admin.php?error=' . urlencode('Database error occurred while saving the admin.'));
     }
     exit;
}
?>