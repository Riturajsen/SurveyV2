<?php
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* redirect */ exit; }

// --- CSRF Validation ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) {
    die('CSRF token validation failed. Request aborted.'); // Or redirect with generic error
}
// --- End CSRF Validation ---


$survey_id = $_POST['survey_id'];

if (!$survey_id) {
    header('Location: manage_surveys.php?error=' . urlencode("Invalid survey ID."). ' survey_id = '.$survey_id);
    exit;
}



try {
    // Fetch current status
    $stmt_check = $pdo->prepare("SELECT is_active FROM surveys WHERE survey_id = ?");
    $stmt_check->execute([$survey_id]);
    $current_status = $stmt_check->fetchColumn();

    if ($current_status === false) {
        header('Location: manage_surveys.php?error=' . urlencode("Survey not found."));
        exit;
    }

    // Toggle status
    $new_status = ($current_status == 1) ? 0 : 1;

    $stmt_update = $pdo->prepare("UPDATE surveys SET is_active = ? WHERE survey_id = ?");
    $stmt_update->execute([$new_status, $survey_id]);

    $status_text = $new_status ? 'activated' : 'deactivated';
    header('Location: manage_surveys.php?success=' . urlencode("Survey successfully $status_text."));
    exit;

} catch (PDOException $e) {
     error_log("Error toggling survey status for ID $survey_id: " . $e->getMessage());
     header('Location: manage_surveys.php?error=' . urlencode("Database error occurred."));
     exit;
}
?>