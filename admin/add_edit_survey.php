<?php
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

$survey_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$survey = null;
$is_editing = false;

if ($survey_id) {
    // Editing existing survey
    $is_editing = true;
    try {
        $stmt = $pdo->prepare("SELECT * FROM surveys WHERE survey_id = ?");
        $stmt->execute([$survey_id]);
        $survey = $stmt->fetch();
        if (!$survey) {
             header("Location: manage_surveys.php?error=" . urlencode("Survey not found."));
             exit;
        }
    } catch (PDOException $e) {
         error_log("Error fetching survey for edit: " . $e->getMessage());
         header("Location: manage_surveys.php?error=" . urlencode("Error fetching survey details."));
         exit;
    }
}

include 'partials/header.php';
?>

<h2><?php echo $is_editing ? 'Edit Survey' : 'Add New Survey'; ?></h2>

<form action="save_survey.php" method="post" class="form-basic">
<input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
    <?php if ($is_editing): ?>
        <input type="hidden" name="survey_id" value="<?php echo $survey['survey_id']; ?>">
    <?php endif; ?>

    <div class="form-group">
        <label for="title">Survey Title:</label>
        <input type="text" id="title" name="title" value="<?php echo escape_html($survey['title'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Description (Optional):</label>
        <textarea id="description" name="description" rows="4"><?php echo escape_html($survey['description'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
        <label for="unique_slug">Unique URL Slug:</label>
        <input type="text" id="unique_slug" name="unique_slug" value="<?php echo escape_html($survey['unique_slug'] ?? ''); ?>" required pattern="[a-z0-9\-]+" title="Use only lowercase letters, numbers, and hyphens.">
        <small>Used for the survey link (e.g., your-survey-name). Only lowercase letters, numbers, and hyphens allowed. Leave blank to auto-generate from title (on add).</small>
    </div>

     <div class="form-group">
        <label for="is_active">Status:</label>
        <select name="is_active" id="is_active">
            <option value="1" <?php echo ($is_editing && $survey['is_active'] == 1) ? 'selected' : ''; ?>>Active</option>
            <option value="0" <?php echo ($is_editing && $survey['is_active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
        </select>
     </div>

    <div class="form-group">
        <button type="submit"><?php echo $is_editing ? 'Update Survey' : 'Save Survey'; ?></button>
        <a href="manage_surveys.php" class="button cancel">Cancel</a>
    </div>
</form>

<?php include 'partials/footer.php'; ?>