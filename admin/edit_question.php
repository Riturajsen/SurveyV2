<?php
// --- admin/edit_question.php ---
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

$question_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$question = null;
$survey_title = '';
$survey_id = null; // Initialize survey_id
$potential_parents = [];
$is_editing = true; // This is the EDIT page

if (!$question_id) {
    header("Location: manage_surveys.php?error=" . urlencode("Invalid question ID."));
    exit;
}

// Fetch question details and its survey title
try {
    // Fetch the question including conditional fields
    $stmt = $pdo->prepare("SELECT q.*, s.title as survey_title
                          FROM questions q
                          JOIN surveys s ON q.survey_id = s.survey_id
                          WHERE q.question_id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        header("Location: manage_surveys.php?error=" . urlencode("Question not found."));
        exit;
    }
    $survey_title = $question['survey_title'];
    $survey_id = $question['survey_id']; // Get survey_id from fetched question

    // Fetch potential parent questions (questions defined earlier in the same survey)
    $sql_parents = "SELECT question_id, question_text FROM questions WHERE survey_id = ? AND question_id < ? ORDER BY question_id ASC";
    $stmt_parents = $pdo->prepare($sql_parents);
    $stmt_parents->execute([$survey_id, $question['question_id']]);
    $potential_parents = $stmt_parents->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
     error_log("Error fetching question for edit: " . $e->getMessage());
     $redirect_url = $survey_id ? "manage_questions.php?survey_id=$survey_id" : "manage_surveys.php";
     header("Location: $redirect_url&error=" . urlencode("Error fetching question details."));
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
      <hr style="margin: 20px 0;">

    <div class="form-group">
         <label>
             <input type="checkbox" name="is_conditional" value="1" id="is_conditional_cb"
                 <?php echo ($question['is_conditional'] == 1) ? 'checked' : ''; ?>
                 onchange="toggleConditionalFields()">
             Is this question conditional (shown based on a previous answer)?
         </label>
     </div>

     <div id="conditional-fields" style="<?php echo ($question['is_conditional'] == 1) ? 'display: block; border: 1px dashed #ccc; padding: 15px; margin-top: -15px; margin-bottom: 20px; background: #fafafa;' : 'display: none;'; ?>">
           <p><em>Configure condition (only if checkbox above is checked):</em></p>
         <div class="form-group">
             <label for="parent_question_id">Show this question IF the answer to:</label>
             <select name="parent_question_id" id="parent_question_id">
                 <option value="">-- Select Parent Question --</option>
                 <?php foreach ($potential_parents as $parent): ?>
                     <option value="<?php echo $parent['question_id']; ?>"
                         <?php echo ($question['parent_question_id'] == $parent['question_id']) ? 'selected' : ''; ?>>
                         <?php echo '(ID: ' . $parent['question_id'] . ') ' . escape_html(substr($parent['question_text'], 0, 80)) . (strlen($parent['question_text']) > 80 ? '...' : ''); ?>
                     </option>
                 <?php endforeach; ?>
             </select>
         </div>
         <div class="form-group">
             <label for="parent_trigger_value">IS EXACTLY:</label>
             <input type="text" name="parent_trigger_value" id="parent_trigger_value"
                  value="<?php echo escape_html($question['parent_trigger_value'] ?? ''); ?>"
                  placeholder="e.g., Yes, Option A, specific value">
             <small>Enter the exact answer value from the parent question that should display this question. Case-sensitive.</small>
         </div>
     </div>
    <div class="form-group" style="margin-top: 30px;">
        <button type="submit">Update Question</button>
        <a href="manage_questions.php?survey_id=<?php echo $question['survey_id']; ?>" class="button cancel">Cancel</a>
    </div>
</form>

<script>
// Include the SAME toggleConditionalFields() function as in add_question.php
function toggleConditionalFields() {
    const isChecked = document.getElementById('is_conditional_cb').checked;
    const fieldsDiv = document.getElementById('conditional-fields');
    const parentSelect = document.getElementById('parent_question_id');
    const triggerInput = document.getElementById('parent_trigger_value');
    if (isChecked) {
        fieldsDiv.style.display = 'block'; fieldsDiv.style.border = '1px dashed #ccc'; fieldsDiv.style.padding = '15px'; fieldsDiv.style.marginTop = '-15px'; fieldsDiv.style.marginBottom = '20px'; fieldsDiv.style.background = '#fafafa';
        parentSelect.required = true; triggerInput.required = true;
    } else {
        fieldsDiv.style.display = 'none';
        parentSelect.required = false; triggerInput.required = false;
    }
}
// Run on page load to set initial state
document.addEventListener('DOMContentLoaded', toggleConditionalFields);
</script>


<?php include 'partials/footer.php'; ?>