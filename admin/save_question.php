<?php
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';



// Redirect if not a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_surveys.php'); // Redirect to survey list
    exit;
}
// --- CSRF Validation ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) {
    die('CSRF token validation failed. Request aborted.'); // Or redirect with generic error
}
// --- End CSRF Validation ---

// --- Get Data ---
$survey_id = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT);
$text = trim($_POST['question_text'] ?? '');
$type = trim($_POST['question_type'] ?? '');
$options_str = trim($_POST['options'] ?? '');
// Checkbox value: 1 if checked, null if not submitted
$is_required_input = filter_input(INPUT_POST, 'is_required', FILTER_VALIDATE_INT);
$is_required_db_val = ($is_required_input === 1) ? 1 : 0; // Convert to 1 or 0

// --- Validation ---
$errors = [];
if (!$survey_id) {
    $errors[] = 'Invalid or missing survey ID.';
    // Redirect immediately if survey ID is invalid as other checks depend on it
    header('Location: manage_surveys.php?error=' . urlencode(implode(' ', $errors)));
    exit;
}
if (empty($text)) {
    $errors[] = 'Question text cannot be empty.';
}
$allowed_types = ['text', 'textarea', 'radio', 'checkbox', 'select'];
if (!in_array($type, $allowed_types)) {
     $errors[] = 'Invalid question type selected.';
}
$types_requiring_options = ['radio', 'checkbox', 'select'];
if (in_array($type, $types_requiring_options) && empty($options_str)) {
    $errors[] = 'Options are required for radio, checkbox, or select types.';
}

// If validation fails, redirect back with errors
if (!empty($errors)) {
    // Storing errors/data in session is better for UX
    header('Location: add_question.php?survey_id=' . $survey_id . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

$db_options = in_array($type, $types_requiring_options) ? $options_str : null;

// --- Save to Database ---
try {
    $sql = "INSERT INTO questions (survey_id, question_text, question_type, options, is_required) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    // Ensure order matches VALUES(?, ?, ?, ?, ?)
    $stmt->execute([$survey_id, $text, $type, $db_options, $is_required_db_val]);

    // Redirect to add another question for the same survey, or back to survey list
    header('Location: add_question.php?survey_id=' . $survey_id . '&success=' . urlencode('Question added successfully!'));
    // Or: header('Location: manage_surveys.php?success=' . urlencode('Question added successfully!'));
    exit;

} catch (PDOException $e) {
    error_log("Error saving question for survey $survey_id: " . $e->getMessage());
    header('Location: add_question.php?survey_id=' . $survey_id . '&error=' . urlencode('Database error occurred while saving the question.'));
    exit;
}
?>