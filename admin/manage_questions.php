<?php
// --- admin/manage_questions.php ---
require_once '../includes/functions.php';
require_admin_login();
require_once '../includes/db_connect.php';

$survey_id = filter_input(INPUT_GET, 'survey_id', FILTER_VALIDATE_INT);

if (!$survey_id) {
    header("Location: manage_surveys.php?error=" . urlencode("No survey selected."));
    exit;
}

// Fetch survey details and questions
$survey_title = '';
$questions = [];
$fetch_error = null;

try {
    $stmt_s = $pdo->prepare("SELECT title FROM surveys WHERE survey_id = ?");
    $stmt_s->execute([$survey_id]);
    $survey_title = $stmt_s->fetchColumn();

    if (!$survey_title) {
        header("Location: manage_surveys.php?error=" . urlencode("Survey not found."));
        exit;
    }

    $stmt_q = $pdo->prepare("SELECT question_id, question_text, question_type, is_required FROM questions WHERE survey_id = ? ORDER BY question_id ASC");
    $stmt_q->execute([$survey_id]);
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching questions for survey $survey_id: " . $e->getMessage());
    $fetch_error = "Could not retrieve questions for this survey.";
}

include 'partials/header.php';
?>

<h2>Manage Questions for Survey: "<?php echo escape_html($survey_title); ?>"</h2>

<a href="add_question.php?survey_id=<?php echo $survey_id; ?>" class="button add-new">Add New Question</a>
<a href="manage_surveys.php" class="button cancel" style="margin-left: 10px;">Back to Surveys</a>


<?php if ($fetch_error): ?>
    <p class="message error"><?php echo $fetch_error; ?></p>
<?php endif; ?>

<?php if (!empty($questions)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Question Text</th>
                <th>Type</th>
                <th>Required</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($questions as $question): ?>
                <tr>
                    <td><?php echo $question['question_id']; ?></td>
                    <td><?php echo escape_html(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?></td>
                    <td><?php echo escape_html(ucfirst($question['question_type'])); ?></td>
                    <td><?php echo $question['is_required'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="edit_question.php?id=<?php echo $question['question_id']; ?>" class="action-link">Edit</a> |
                        <form action="delete_question.php" method="POST" style="display:inline; margin: 0 4px;">
                        <input type="hidden" name="question_id" value="<?php echo $question['question_id']; ?>">
                        <input type="hidden" name="survey_id" value="<?php echo $survey_id; // Already available in manage_questions.php ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
                        <button type="submit" class="action-link delete-link button-link-style" onclick="return confirm('Are you sure you want to delete this question AND all associated responses? This cannot be undone.');">Delete</button>
                       </form>   
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif (!$fetch_error): ?>
    <p style="margin-top:20px;">No questions found for this survey yet. <a href="add_question.php?survey_id=<?php echo $survey_id; ?>">Add the first one!</a></p>
<?php endif; ?>


<?php include 'partials/footer.php'; ?>