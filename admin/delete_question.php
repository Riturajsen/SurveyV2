<?php
// --- admin/delete_question.php ---
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

$question_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$survey_id = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT); // Needed for redirect back

if (!$question_id || !$survey_id) {
    header('Location: manage_surveys.php?error=' . urlencode("Invalid request parameters."));
    exit;
}

// --- Delete from Database ---
try {
    // Optional: Check if question actually exists and belongs to the survey_id first for security
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE question_id = ? AND survey_id = ?");
    $stmt_check->execute([$question_id, $survey_id]);
    if ($stmt_check->fetchColumn() == 0) {
         header('Location: manage_questions.php?survey_id=' . $survey_id . '&error=' . urlencode("Question not found or does not belong to this survey."));
         exit;
    }

    // Proceed with deletion
    $stmt_delete = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
    $stmt_delete->execute([$question_id]);

    // Redirect back to the questions list for the same survey
    header('Location: manage_questions.php?survey_id=' . $survey_id . '&success=' . urlencode('Question and associated responses deleted successfully.'));
    exit;

} catch (PDOException $e) {
    error_log("Error deleting question ID $question_id: " . $e->getMessage());
    header('Location: manage_questions.php?survey_id=' . $survey_id . '&error=' . urlencode('Database error occurred while deleting the question.'));
    exit;
}
?>