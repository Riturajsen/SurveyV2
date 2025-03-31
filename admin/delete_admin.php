<?php
// --- admin/delete_admin.php ---
require_once '../includes/functions.php';
require_admin_login(); // Must be logged in
require_once '../includes/db_connect.php'; // Needs $pdo

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_admins.php?error=' . urlencode('Invalid request method.'));
    exit;
}

// --- CSRF Validation ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) {
    header('Location: manage_admins.php?error=' . urlencode('Invalid security token. Please try again.'));
    exit;
}

// --- Get Admin ID to Delete ---
$admin_id_to_delete = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);

// --- Safety and Validation Checks ---
if (!$admin_id_to_delete) {
    header('Location: manage_admins.php?error=' . urlencode('Invalid Admin ID provided for deletion.'));
    exit;
}

// CRUCIAL: Prevent deleting the primary admin (ID 1)
if ($admin_id_to_delete === 1) {
    header('Location: manage_admins.php?error=' . urlencode('Cannot delete the primary administrator account.'));
    exit;
}

// CRUCIAL: Prevent self-deletion
if ($admin_id_to_delete === ($_SESSION['admin_id'] ?? null)) {
     header('Location: manage_admins.php?error=' . urlencode('You cannot delete your own account.'));
     exit;
}

// --- Perform Deletion ---
try {
    // Optional: Verify the admin ID actually exists before deleting
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE admin_id = ?");
    $stmt_check->execute([$admin_id_to_delete]);
    if ($stmt_check->fetchColumn() == 0) {
        header('Location: manage_admins.php?error=' . urlencode('Admin account not found.'));
        exit;
    }

    // Proceed with deletion
    $stmt_delete = $pdo->prepare("DELETE FROM admins WHERE admin_id = ?");
    $stmt_delete->execute([$admin_id_to_delete]);

    // Check if deletion was successful (rowCount might not be reliable on all drivers/configs)
    if ($stmt_delete->rowCount() > 0) {
        header('Location: manage_admins.php?success=' . urlencode('Administrator account deleted successfully.'));
    } else {
        // This might happen if the check above was skipped and ID didn't exist
        header('Location: manage_admins.php?error=' . urlencode('Failed to delete admin account (might not exist?).'));
    }
    exit;

} catch (PDOException $e) {
    error_log("Error deleting admin ID $admin_id_to_delete: " . $e->getMessage());
    header('Location: manage_admins.php?error=' . urlencode('Database error occurred while deleting the admin account.'));
    exit;
}
?>