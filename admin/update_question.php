<?php
// --- admin/update_question.php ---
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: manage_surveys.php'); exit; }

// --- CSRF Check ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) { die('CSRF token validation failed.'); }

// --- Get Data ---
$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
$survey_id = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT);
$text = trim($_POST['question_text'] ?? '');
$type = trim($_POST['question_type'] ?? '');
$options_str = trim($_POST['options'] ?? '');
$is_required_input = filter_input(INPUT_POST, 'is_required', FILTER_VALIDATE_INT);
$is_required_db_val = ($is_required_input === 1) ? 1 : 0;

// --- Get Conditional Data ---
$is_conditional = isset($_POST['is_conditional']) && $_POST['is_conditional'] == '1' ? 1 : 0;
$parent_question_id = filter_input(INPUT_POST, 'parent_question_id', FILTER_VALIDATE_INT);
$parent_trigger_value = trim($_POST['parent_trigger_value'] ?? '');

// --- Validation ---
$errors = [];
if (!$question_id) { $errors[] = 'Invalid Question ID.'; header('Location: manage_surveys.php?error=Invalid+Question+ID'); exit; }
if (!$survey_id) { $errors[] = 'Missing Survey ID.'; header('Location: manage_surveys.php?error=Missing+Survey+ID'); exit; }
// Basic validation (add more as needed)
if (empty($text)) { $errors[] = 'Question text cannot be empty.'; }
$allowed_types = ['text', 'textarea', 'radio', 'checkbox', 'select'];
if (!in_array($type, $allowed_types)) { $errors[] = 'Invalid question type.'; }
$types_requiring_options = ['radio', 'checkbox', 'select'];
if (in_array($type, $types_requiring_options) && empty($options_str)) { $errors[] = 'Options required.'; }

// --- Conditional Field Validation ---
if ($is_conditional) {
    if (empty($parent_question_id)) { $errors[] = "Parent question must be selected."; }
    if ($parent_trigger_value === '') { $errors[] = "Parent trigger value must be provided."; }
    if ($parent_question_id == $question_id) { $errors[] = "A question cannot depend on itself."; } // Prevent self-dependency
    // Add check: ensure parent_question_id exists *within the same survey* and has an ID less than the current ID (prevent loops) - more complex check omitted
} else {
    // If not conditional, ensure parent fields are NULL
    $parent_question_id = null;
    $parent_trigger_value = null;
}

// Redirect if Errors
if (!empty($errors)) {
    header('Location: edit_question.php?id=' . $question_id . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

$db_options = in_array($type, $types_requiring_options) ? $options_str : null;

// --- Update Database ---
try {
    // UPDATED SQL to include conditional fields
    $sql = "UPDATE questions
            SET question_text = ?, question_type = ?, options = ?, is_required = ?,
                is_conditional = ?, parent_question_id = ?, parent_trigger_value = ?
            WHERE question_id = ? AND survey_id = ?";
    $stmt = $pdo->prepare($sql);
    // UPDATED execute params
    $stmt->execute([
        $text, $type, $db_options, $is_required_db_val,
        $is_conditional, $parent_question_id, $parent_trigger_value,
        $question_id, $survey_id
    ]);

    header('Location: manage_questions.php?survey_id=' . $survey_id . '&success=' . urlencode('Question updated successfully!'));
    exit;

} catch (PDOException $e) {
    error_log("Error updating question ID $question_id: " . $e->getMessage());
     if ($e->getCode() == '23000' && strpos($e->getMessage(), 'questions_ibfk_2') !== false) {
         $error_msg = 'Invalid parent question selected.';
     } else {
         $error_msg = 'Database error occurred while updating question.';
     }
    header('Location: edit_question.php?id=' . $question_id . '&error=' . urlencode($error_msg));
    exit;
}
?>