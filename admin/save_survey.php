<?php
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_surveys.php');
    exit;
}



// --- CSRF Validation ---
$submitted_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($submitted_token)) {
    die('CSRF token validation failed. Request aborted.'); // Or redirect with generic error
}
// --- End CSRF Validation ---

// Get data from POST
$survey_id = filter_input(INPUT_POST, 'survey_id', FILTER_VALIDATE_INT);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$unique_slug_raw = trim($_POST['unique_slug'] ?? '');
$is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
$admin_id = $_SESSION['admin_id'] ?? null; // Assuming admin_id is stored in session

$is_editing = !empty($survey_id);

// --- Validation ---
$errors = [];
if (empty($title)) {
    $errors[] = "Survey title is required.";
}
// Validate slug format
if (!empty($unique_slug_raw) && !preg_match('/^[a-z0-9\-]+$/', $unique_slug_raw)) {
    $errors[] = "Slug can only contain lowercase letters, numbers, and hyphens.";
}
if ($is_active === null || $is_active === false) { // Check if filter_input failed or returned 0
    $is_active = 0; // Default to inactive if invalid value provided
}


// --- Handle Slug ---
if (empty($unique_slug_raw) && !$is_editing) {
    $unique_slug = generate_slug($title);
} else {
    $unique_slug = $unique_slug_raw;
}
if (empty($unique_slug)) { // Final check if title was also empty resulting in empty slug
     $errors[] = "Could not generate a valid slug. Please provide a title or enter a slug manually.";
}

// Check if slug exists (excluding current survey if editing)
if (empty($errors) && slug_exists($pdo, $unique_slug, $is_editing ? $survey_id : null)) {
    $errors[] = "The slug '$unique_slug' is already in use. Please choose another.";
}

// --- Redirect if Errors ---
if (!empty($errors)) {
    $redirect_url = $is_editing ? "add_edit_survey.php?id=$survey_id" : "add_edit_survey.php";
    // Storing errors in session flash messages is better UX than URL params for multiple errors
    $_SESSION['form_errors'] = $errors; // Example, implement proper flash messages if needed
     $_SESSION['form_data'] = $_POST;    // Preserve user input
    header("Location: $redirect_url&error=" . urlencode("Validation failed: ". $errors[0])); // Simple error message
    exit;
}

// --- Save to Database ---
try {
    if ($is_editing) {
        // Update existing survey
        $sql = "UPDATE surveys SET title = ?, description = ?, unique_slug = ?, is_active = ? WHERE survey_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $unique_slug, $is_active, $survey_id]);
        $success_msg = "Survey updated successfully!";
    } else {
        // Insert new survey
        $sql = "INSERT INTO surveys (title, description, unique_slug, is_active, admin_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $unique_slug, $is_active, $admin_id]);
        $success_msg = "Survey created successfully!";
    }

    header("Location: manage_surveys.php?success=" . urlencode($success_msg));
    exit;

} catch (PDOException $e) {
    error_log("Error saving survey: " . $e->getMessage());
    $error_msg = "Database error occurred while saving the survey.";
     // Check for unique constraint violation specifically (error code 1062)
     if ($e->getCode() == '23000') { // Integrity constraint violation
          if (strpos($e->getMessage(), 'unique_slug') !== false) {
               $error_msg = "The slug '$unique_slug' is already in use. Please choose another.";
          }
     }
     $redirect_url = $is_editing ? "add_edit_survey.php?id=$survey_id" : "add_edit_survey.php";
     $_SESSION['form_data'] = $_POST; // Preserve user input on error
     header("Location: $redirect_url&error=" . urlencode($error_msg));
     exit;
}
?>