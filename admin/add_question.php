<?php
// --- admin/add_question.php ---
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

$survey_id = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT);
$is_editing = false; // This is the ADD page

if (!$survey_id) {
    header("Location: manage_surveys.php?error=" . urlencode("No survey selected."));
    exit;
}

// Fetch survey title and potential parent questions
$survey_title = '';
$potential_parents = [];
try {
    $stmt_s = $pdo->prepare("SELECT title FROM surveys WHERE survey_id = ?");
    $stmt_s->execute([$survey_id]);
    $survey_title = $stmt_s->fetchColumn();
    if (!$survey_title) {
        header("Location: manage_surveys.php?error=" . urlencode("Selected survey not found."));
        exit;
    }

    // Fetch existing questions in this survey to potentially be parents
    // (Order by ID, assuming lower IDs come first)
    $stmt_parents = $pdo->prepare("SELECT question_id, question_text FROM questions WHERE survey_id = ? ORDER BY question_id ASC");
    $stmt_parents->execute([$survey_id]);
    $potential_parents = $stmt_parents->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
     error_log("Error fetching survey details/parents for add question: " . $e->getMessage());
     header("Location: manage_surveys.php?error=" . urlencode("Error fetching survey details."));
     exit;
}

include 'partials/header.php';

// --- Retrieve potential form data/errors from session flash if implemented ---
// $form_data = $_SESSION['form_data'] ?? [];
// $form_errors = $_SESSION['form_errors'] ?? [];
// unset($_SESSION['form_data'], $_SESSION['form_errors']); // Clear after use
?>

<h2>Add New Question to Survey: "<?php echo escape_html($survey_title); ?>"</h2>

<form action="save_question.php" method="post" id="add-question-form" class="form-basic">
    <input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
    <input type="hidden" name="survey_id" value="<?php echo $survey_id; ?>">

    <div class="form-group">
        <label for="question_text">Question Text:</label>
        <textarea id="question_text" name="question_text" rows="3" required><?php /* echo escape_html($form_data['question_text'] ?? ''); */ ?></textarea>
    </div>

    <div class="form-group">
        <label for="question_type">Question Type:</label>
        <select id="question_type" name="question_type" required>
            <option value="">-- Select Type --</option>
            <?php $types = ['text', 'textarea', 'radio', 'checkbox', 'select']; ?>
            <?php foreach ($types as $type): ?>
                <option value="<?php echo $type; ?>" <?php /* echo (($form_data['question_type'] ?? '') == $type) ? 'selected' : ''; */ ?> >
                    <?php echo ucfirst($type); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" id="options-group" style="display: none;">
        <label for="options">Options (comma-separated):</label>
        <input type="text" id="options" name="options" value="<?php /* echo escape_html($form_data['options'] ?? ''); */ ?>">
        <small>Enter choices separated by commas (e.g., Yes, No, Maybe)</small>
    </div>

    <div class="form-group">
         <label for="is_required">
             <input type="checkbox" id="is_required" name="is_required" value="1" checked> Is this question required?
         </label>
     </div>
     <hr style="margin: 20px 0;">

    <div class="form-group">
         <label>
             <input type="checkbox" name="is_conditional" value="1" id="is_conditional_cb"
                 <?php /* echo !empty($form_data['is_conditional']) ? 'checked' : ''; */ ?>
                 onchange="toggleConditionalFields()">
             Is this question conditional (shown based on a previous answer)?
         </label>
     </div>

     <div id="conditional-fields" style="display: none; border: 1px dashed #ccc; padding: 15px; margin-top: -15px; margin-bottom: 20px; background: #fafafa;">
          <p><em>Configure condition (only if checkbox above is checked):</em></p>
         <div class="form-group">
             <label for="parent_question_id">Show this question IF the answer to:</label>
             <select name="parent_question_id" id="parent_question_id">
                 <option value="">-- Select Parent Question --</option>
                 <?php foreach ($potential_parents as $parent): ?>
                     <option value="<?php echo $parent['question_id']; ?>"
                          <?php /* echo (($form_data['parent_question_id'] ?? '') == $parent['question_id']) ? 'selected' : ''; */ ?>>
                         <?php echo '(ID: ' . $parent['question_id'] . ') ' . escape_html(substr($parent['question_text'], 0, 80)) . (strlen($parent['question_text']) > 80 ? '...' : ''); ?>
                     </option>
                 <?php endforeach; ?>
             </select>
         </div>
         <div class="form-group">
             <label for="parent_trigger_value">IS EXACTLY:</label>
             <input type="text" name="parent_trigger_value" id="parent_trigger_value"
                  value="<?php /* echo escape_html($form_data['parent_trigger_value'] ?? ''); */ ?>"
                  placeholder="e.g., Yes, Option A, specific value">
             <small>Enter the exact answer value from the parent question that should display this question. Case-sensitive.</small>
         </div>
     </div>
    <div class="form-group" style="margin-top: 30px;">
        <button type="submit">Save Question</button>
         <a href="manage_questions.php?survey_id=<?php echo $survey_id; ?>" class="button cancel">Cancel</a>
    </div>
</form>

<script>
// Basic JS to toggle conditional fields
function toggleConditionalFields() {
    const isChecked = document.getElementById('is_conditional_cb').checked;
    const fieldsDiv = document.getElementById('conditional-fields');
    const parentSelect = document.getElementById('parent_question_id');
    const triggerInput = document.getElementById('parent_trigger_value');

    if (isChecked) {
        fieldsDiv.style.display = 'block';
        // Make conditional fields required if the checkbox is checked
        parentSelect.required = true;
        triggerInput.required = true;
    } else {
        fieldsDiv.style.display = 'none';
        // Make fields not required when hidden
        parentSelect.required = false;
        triggerInput.required = false;
        // Optional: Clear values when hiding
        // parentSelect.value = '';
        // triggerInput.value = '';
    }
}
// Run on page load in case of validation errors re-populating form
document.addEventListener('DOMContentLoaded', toggleConditionalFields);
</script>


<?php include 'partials/footer.php'; ?>