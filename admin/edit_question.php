<?php
// --- admin/edit_question.php ---
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

$question_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$question = null;
$survey_title = '';

if (!$question_id) {
    header("Location: manage_surveys.php?error=" . urlencode("Invalid question ID."));
    exit;
}

// Fetch question details and its survey title
try {
    $stmt = $pdo->prepare("SELECT q.*, s.title as survey_title FROM questions q JOIN surveys s ON q.survey_id = s.survey_id WHERE q.question_id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        header("Location: manage_surveys.php?error=" . urlencode("Question not found."));
        exit;
    }
    $survey_title = $question['survey_title'];
    $survey_id = $question['survey_id']; // Needed for cancel link

} catch (PDOException $e) {
     error_log("Error fetching question for edit: " . $e->getMessage());
     // Redirect back to the questions list for its survey if possible
     if(isset($survey_id)) {
        header("Location: manage_questions.php?survey_id=$survey_id&error=" . urlencode("Error fetching question details."));
     } else {
        header("Location: manage_surveys.php?error=" . urlencode("Error fetching question details."));
     }
     exit;
}

include 'partials/header.php';
?>

<h2>Edit Question for Survey: "<?php echo escape_html($survey_title); ?>"</h2>

<form action="update_question.php" method="post" id="edit-question-form" class="form-basic">
<input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
    <input type="hidden" name="question_id" value="<?php echo $question['question_id']; ?>">
    <input type="hidden" name="survey_id" value="<?php echo $question['survey_id']; ?>"> <div class="form-group">
        <label for="question_text">Question Text:</label>
        <textarea id="question_text" name="question_text" rows="3" required><?php echo escape_html($question['question_text']); ?></textarea>
    </div>

    <div class="form-group">
        <label for="question_type">Question Type:</label>
        <select id="question_type" name="question_type" required>
            <option value="">-- Select Type --</option>
            <?php $types = ['text', 'textarea', 'radio', 'checkbox', 'select']; ?>
            <?php foreach ($types as $type): ?>
                <option value="<?php echo $type; ?>" <?php echo ($question['question_type'] == $type) ? 'selected' : ''; ?>>
                    <?php echo ucfirst($type); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" id="options-group" style="<?php echo in_array($question['question_type'], ['radio', 'checkbox', 'select']) ? 'display: block;' : 'display: none;'; ?>">
        <label for="options">Options (comma-separated):</label>
        <input type="text" id="options" name="options" value="<?php echo escape_html($question['options'] ?? ''); ?>">
        <small>Enter choices separated by commas (e.g., Yes, No, Maybe)</small>
    </div>

    <div class="form-group">
         <label for="is_required">
             <input type="checkbox" id="is_required" name="is_required" value="1" <?php echo ($question['is_required'] == 1) ? 'checked' : ''; ?>>
             Is this question required?
         </label>
     </div>

    <div class="form-group">
        <button type="submit">Update Question</button>
        <a href="manage_questions.php?survey_id=<?php echo $question['survey_id']; ?>" class="button cancel">Cancel</a>
    </div>
</form>

<?php include 'partials/footer.php'; ?>