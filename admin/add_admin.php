<?php
// --- admin/add_admin.php ---
require_once '../includes/functions.php';
require_admin_login(); // Must be logged in

// Include header needs to generate CSRF token
include 'partials/header.php';

// Retrieve potential form data/errors from session flash messages if implemented
// $form_data = $_SESSION['form_data'] ?? [];
// $form_errors = $_SESSION['form_errors'] ?? [];
// unset($_SESSION['form_data'], $_SESSION['form_errors']); // Clear after use
?>

<h2>Add New Administrator</h2>


<?php /* if (!empty($form_errors)): ?>
    <div class="message error">
        <strong>Please correct the following errors:</strong><br>
        <?php foreach ($form_errors as $error): ?>
            - <?php echo escape_html($error); ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; */ ?>


<form action="save_admin.php" method="post" class="form-basic" style="max-width: 500px;">
    <input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">

    <div class="form-group">
        <label for="username">New Admin Username:</label>
        <input type="text" id="username" name="username" required autocomplete="off" value="<?php /* echo escape_html($form_data['username'] ?? ''); */ ?>">
        <small>Must be unique.</small>
    </div>
    <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        <small>Minimum 8 characters recommended.</small>
    </div>
     <div class="form-group">
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
    </div>
    <div class="form-group">
        <button type="submit">Add Admin</button>
        <a href="manage_admins.php" class="button cancel">Cancel</a>
    </div>
</form>

<?php include 'partials/footer.php'; ?>