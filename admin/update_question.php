<?php
// --- admin/update_question.php ---
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_surveys.php'); // Redirect if not POST
    exit;
}
// --- CSRF Validation ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) {
    die('CSRF token validation failed. Request aborted.'); // Or redirect with generic error
}
// --- End CSRF Validation ---

// --- Get Data ---
$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
$survey_id = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT); // For redirect
$text = trim($_POST['question_text'] ?? '');
$type = trim($_POST['question_type'] ?? '');
$options_str = trim($_POST['options'] ?? '');
$is_required_input = filter_input(INPUT_POST, 'is_required', FILTER_VALIDATE_INT);
$is_required_db_val = ($is_required_input === 1) ? 1 : 0;

// --- Basic Validation ---
$errors = [];
if (!$question_id) {
    $errors[] = 'Invalid Question ID.';
    // Cannot redirect without survey_id, send to manage surveys
     header('Location: manage_surveys.php?error=' . urlencode('Invalid Question ID submitted for update.')); exit;
}
if (!$survey_id) { // Should be submitted from hidden field
    $errors[] = 'Missing Survey ID.';
    header('Location: manage_surveys.php?error=' . urlencode('Associated Survey ID missing.')); exit;
}
// (Add same validation as save_question.php for text, type, options)
 if (empty($text)) $errors[] = 'Question text cannot be empty.';
 $allowed_types = ['text', 'textarea', 'radio', 'checkbox', 'select'];
 if (!in_array($type, $allowed_types)) $errors[] = 'Invalid question type selected.';
 $types_requiring_options = ['radio', 'checkbox', 'select'];
 if (in_array($type, $types_requiring_options) && empty($options_str)) $errors[] = 'Options are required for radio, checkbox, or select types.';


// If validation fails, redirect back to edit form
if (!empty($errors)) {
    // Storing errors/data in session is better UX
    header('Location: edit_question.php?id=' . $question_id . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

$db_options = in_array($type, $types_requiring_options) ? $options_str : null;

// --- Update Database ---
try {
    $sql = "UPDATE questions
            SET question_text = ?, question_type = ?, options = ?, is_required = ?
            WHERE question_id = ? AND survey_id = ?"; // Extra check for survey_id might be good
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $text,
        $type,
        $db_options,
        $is_required_db_val,
        $question_id,
        $survey_id // Bind survey_id for WHERE clause
    ]);

    header('Location: manage_questions.php?survey_id=' . $survey_id . '&success=' . urlencode('Question updated successfully!'));
    exit;

} catch (PDOException $e) {
    error_log("Error updating question ID $question_id: " . $e->getMessage());
    header('Location: edit_question.php?id=' . $question_id . '&error=' . urlencode('Database error occurred while updating question.'));
    exit;
}
?>