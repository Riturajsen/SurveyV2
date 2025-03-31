<?php
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

// Fetch all surveys
try {
    $stmt = $pdo->query("SELECT survey_id, title, unique_slug, is_active, created_at FROM surveys ORDER BY created_at DESC");
    $surveys = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching surveys: " . $e->getMessage());
    $surveys = [];
    $fetch_error = "Could not retrieve surveys.";
}

include 'partials/header.php';
?>

<h2>Manage Surveys</h2>

<a href="add_edit_survey.php" class="button add-new">Add New Survey</a>

<?php if (isset($fetch_error)): ?>
    <p class="message error"><?php echo $fetch_error; ?></p>
<?php endif; ?>

<?php if (!empty($surveys)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Unique Link Slug</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($surveys as $survey):
                $public_link = '../survey.php?s=' . urlencode($survey['unique_slug']);
            ?>
                <tr>
                    <td><?php echo escape_html($survey['title']); ?></td>
                    <td><a href="<?php echo $public_link; ?>" target="_blank"><?php echo escape_html($survey['unique_slug']); ?></a></td>
                    <td><?php echo $survey['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td><?php echo date("Y-m-d H:i", strtotime($survey['created_at'])); ?></td>
                    <td>                
                        <a href="manage_questions.php?survey_id=<?php echo $survey['survey_id']; ?>" class="action-link">Questions</a> |
                        <a href="view_responses.php?survey_id=<?php echo $survey['survey_id']; ?>" class="action-link">Responses</a> |
                         <a href="add_question.php?survey_id=<?php echo $survey['survey_id']; ?>" class="action-link">Add Qstn</a> |
                         <a href="add_edit_survey.php?id=<?php echo $survey['survey_id']; ?>" class="action-link">Edit</a> |
                        <form action="toggle_survey_status.php" method="POST" style="display:inline; margin: 0 4px;">
                        <input type="hidden" name="survey_id" value="<?php echo $survey['survey_id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
                        <button type="submit" class="action-link button-link-style" onclick="return confirm('Are you sure you want to toggle the status?');">
                            <?php echo $survey['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </button>
                        </form> 
                        </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif (!isset($fetch_error)): ?>
    <p>No surveys found. <a href="add_edit_survey.php">Add the first one!</a></p>
<?php endif; ?>


<?php include 'partials/footer.php'; ?>