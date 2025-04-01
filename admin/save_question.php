<?php
// --- admin/save_question.php ---
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: manage_surveys.php'); exit; }

// --- CSRF Check ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) { die('CSRF token validation failed.'); }

// --- Get Data ---
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
if (!$survey_id) { $errors[] = 'Invalid survey ID.'; header('Location: manage_surveys.php?error=Invalid+Survey+ID'); exit; } // Basic check
if (empty($text)) { $errors[] = 'Question text cannot be empty.'; }
$allowed_types = ['text', 'textarea', 'radio', 'checkbox', 'select'];
if (!in_array($type, $allowed_types)) { $errors[] = 'Invalid question type selected.'; }
$types_requiring_options = ['radio', 'checkbox', 'select'];
if (in_array($type, $types_requiring_options) && empty($options_str)) { $errors[] = 'Options are required for radio, checkbox, or select types.'; }

// --- Conditional Field Validation ---
if ($is_conditional) {
    if (empty($parent_question_id)) { $errors[] = "Parent question must be selected."; }
    if ($parent_trigger_value === '') { $errors[] = "Parent trigger value must be provided."; }
    // Add check: ensure parent_question_id exists *within the same survey* and has an ID less than the current potential ID (prevent loops) - more complex check omitted for brevity
} else {
    // If not conditional, ensure parent fields are NULL
    $parent_question_id = null;
    $parent_trigger_value = null;
}


// Redirect if Errors
if (!empty($errors)) {
    header('Location: add_question.php?survey_id=' . $survey_id . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

$db_options = in_array($type, $types_requiring_options) ? $options_str : null;

// --- Save to Database ---
try {
    // UPDATED SQL to include conditional fields
    $sql = "INSERT INTO questions (survey_id, question_text, question_type, options, is_required, is_conditional, parent_question_id, parent_trigger_value)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    // UPDATED execute params
    $stmt->execute([
        $survey_id,
        $text,
        $type,
        $db_options,
        $is_required_db_val,
        $is_conditional,
        $parent_question_id,
        $parent_trigger_value
    ]);

    header('Location: add_question.php?survey_id=' . $survey_id . '&success=' . urlencode('Question added successfully!'));
    exit;

} catch (PDOException $e) {
    error_log("Error saving question for survey $survey_id: " . $e->getMessage());
    // Check for foreign key constraint failure on parent_question_id
    if ($e->getCode() == '23000' && strpos($e->getMessage(), 'questions_ibfk_2') !== false) {
         $error_msg = 'Invalid parent question selected.';
    } else {
         $error_msg = 'Database error occurred while saving the question.';
    }
    header('Location: add_question.php?survey_id=' . $survey_id . '&error=' . urlencode($error_msg));
    exit;
}
?>