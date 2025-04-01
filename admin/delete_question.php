<?php
// --- admin/delete_question.php ---

// ADD FOR DEBUGGING: Force error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

// DEBUG: Show what's received
echo "<pre>POST Data:\n"; var_dump($_POST); echo "</pre><hr>";

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Error: Invalid request method. Must be POST.<br>"; // DEBUG
    // header('Location: manage_surveys.php?error=' . urlencode('Invalid request method.'));
    exit;
}
echo "Request method OK (POST).<br>"; // DEBUG

// --- CSRF Validation ---
$submitted_token = $_POST['csrf_token'] ?? '';
echo "Submitted CSRF Token: " . escape_html($submitted_token) . "<br>"; // DEBUG
echo "Session CSRF Token: " . escape_html($_SESSION['csrf_token'] ?? 'Not Set') . "<br>"; // DEBUG
if (!validate_csrf_token($submitted_token)) {
    echo "Error: CSRF token validation failed.<br>"; // DEBUG
    // header('Location: manage_admins.php?error=' . urlencode('Invalid security token. Please try again.'));
    exit; // Stop execution
}
echo "CSRF token OK.<br>"; // DEBUG

// --- Get Admin ID to Delete ---
$question_id_to_delete = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
$survey_id = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT); // For redirect

echo "Attempting to delete Question ID: "; var_dump($question_id_to_delete); echo "<br>"; // DEBUG
echo "Survey ID for redirect: "; var_dump($survey_id); echo "<br>"; // DEBUG

// --- Safety and Validation Checks ---
if (!$question_id_to_delete || !$survey_id) { // Check both IDs needed
    echo "Error: Invalid Question ID or Survey ID provided.<br>"; // DEBUG
    // header('Location: manage_surveys.php?error=' . urlencode('Invalid request parameters.'));
    exit;
}
echo "IDs seem valid.<br>"; // DEBUG

// --- Perform Deletion ---
try {
    // Optional: Verify existence and ownership (good practice)
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE question_id = ? AND survey_id = ?");
    $stmt_check->execute([$question_id_to_delete, $survey_id]);
    $count = $stmt_check->fetchColumn();
    echo "Check existence count: "; var_dump($count); echo "<br>"; // DEBUG
    if ($count == 0) {
        echo "Error: Question not found or doesn't belong to this survey.<br>"; // DEBUG
        // header('Location: manage_questions.php?survey_id=' . $survey_id . '&error=' . urlencode("Question not found or does not belong to this survey."));
        exit;
    }
    echo "Existence check passed.<br>"; // DEBUG

    // Proceed with deletion
    echo "Preparing DELETE statement...<br>"; // DEBUG
    $stmt_delete = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
    echo "Executing DELETE for question ID: " . $question_id_to_delete . "<br>"; // DEBUG
    $delete_success = $stmt_delete->execute([$question_id_to_delete]);
    $rows_affected = $stmt_delete->rowCount(); // Get affected rows count

    echo "Delete execute result: "; var_dump($delete_success); echo "<br>"; // DEBUG
    echo "Rows affected: " . $rows_affected . "<br>"; // DEBUG

    // Check if deletion was successful
    if ($delete_success && $rows_affected > 0) {
        echo "Deletion successful! Redirecting...<br>"; // DEBUG
         header('Location: manage_questions.php?survey_id=' . $survey_id . '&success=' . urlencode('Question and associated responses deleted successfully.'));
    } else {
         echo "Error: Delete operation failed or affected 0 rows.<br>"; // DEBUG
         // header('Location: manage_questions.php?survey_id=' . $survey_id . '&error=' . urlencode('Failed to delete question (might not exist?).'));
    }
    exit; // Stop script after debug output

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "<br>"; // DEBUG
    error_log("Error deleting question ID $question_id_to_delete: " . $e->getMessage());
    // header('Location: manage_questions.php?survey_id=' . $survey_id . '&error=' . urlencode('Database error occurred while deleting the question.'));
    exit;
}
?>