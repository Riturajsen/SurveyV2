<?php
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

$survey_id = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT);

if (!$survey_id) {
    header("Location: manage_surveys.php?error=" . urlencode("No survey selected."));
    exit;
}

// Fetch survey title to display
try {
    $stmt = $pdo->prepare("SELECT title FROM surveys WHERE survey_id = ?");
    $stmt->execute([$survey_id]);
    $survey_title = $stmt->fetchColumn();
    if (!$survey_title) {
        header("Location: manage_surveys.php?error=" . urlencode("Selected survey not found."));
        exit;
    }
} catch (PDOException $e) {
     error_log("Error fetching survey title for add question: " . $e->getMessage());
     header("Location: manage_surveys.php?error=" . urlencode("Error fetching survey details."));
     exit;
}


include 'partials/header.php';
?>

<h2>Add New Question to Survey: "<?php echo escape_html($survey_title); ?>"</h2>

<form action="save_question.php" method="post" id="add-question-form" class="form-basic">
<input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
    <input type="hidden" name="survey_id" value="<?php echo $survey_id; ?>">

    <div class="form-group">
        <label for="question_text">Question Text:</label>
        <textarea id="question_text" name="question_text" rows="3" required></textarea>
    </div>

    <div class="form-group">
        <label for="question_type">Question Type:</label>
        <select id="question_type" name="question_type" required>
            <option value="">-- Select Type --</option>
            <option value="text">Short Text (Single Line)</option>
            <option value="textarea">Long Text (Multi Line)</option>
            <option value="radio">Multiple Choice (Single Answer - Radio)</option>
            <option value="checkbox">Multiple Choice (Multiple Answers - Checkbox)</option>
            <option value="select">Dropdown (Single Answer - Select)</option>
        </select>
    </div>

    <div class="form-group" id="options-group" style="display: none;">
        <label for="options">Options (comma-separated):</label>
        <input type="text" id="options" name="options">
        <small>Enter the choices separated by commas (e.g., Yes, No, Maybe)</small>
    </div>

    <div class="form-group">
         <label for="is_required">
             <input type="checkbox" id="is_required" name="is_required" value="1" checked> Is this question required?
         </label>
     </div>


    <div class="form-group">
        <button type="submit">Save Question</button>
         <a href="manage_surveys.php" class="button cancel">Cancel</a> </div>
</form>

<?php include 'partials/footer.php'; ?>