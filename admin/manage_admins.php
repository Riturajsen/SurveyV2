<?php
// --- admin/manage_admins.php ---
require_once '../includes/functions.php';
require_admin_login(); // Must be logged in
require_once '../includes/db_connect.php';

$fetch_error = null;
$admins = [];
try {
    // Fetch usernames (never fetch password hashes!)
    $stmt = $pdo->query("SELECT admin_id, username FROM admins ORDER BY username ASC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching admin list: " . $e->getMessage());
    $fetch_error = "Could not retrieve admin list.";
}

include 'partials/header.php';
?>

<h2>Manage Administrators</h2>

<a href="add_admin.php" class="button add-new">Add New Admin</a>

<?php if ($fetch_error): ?>
    <p class="message error"><?php echo $fetch_error; ?></p>
<?php endif; ?>

<?php if (!empty($admins)): ?>
    <table class="admin-table" style="margin-top: 20px; max-width: 600px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($admins as $admin_user): ?>
                <tr>
                    <td><?php echo $admin_user['admin_id']; ?></td>
                    <td><?php echo escape_html($admin_user['username']); ?></td>
                    <td>
                    
                        <?php
                        // Safety Check: Prevent deleting Admin ID 1 or the currently logged-in admin
                        if ($admin_user['admin_id'] != 1 && $admin_user['admin_id'] != $_SESSION['admin_id']):
                        ?>
                            <form action="delete_admin.php" method="POST" style="display:inline; margin: 0 4px;">
                                <input type="hidden" name="admin_id" value="<?php echo $admin_user['admin_id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo escape_html(generate_csrf_token()); ?>">
                                <button type="submit" class="action-link delete-link button-link-style"
                                        onclick="return confirm('Are you absolutely sure you want to delete the admin user \'<?php echo escape_html(addslashes($admin_user['username'])); ?>\'? This cannot be undone.');">
                                    Delete
                                </button>
                            </form>
                            <?php else: ?>
                             <span style="color:#999; font-size:0.9em;">(Cannot Delete)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif (!$fetch_error): ?>
    <p>No administrators found (this shouldn't happen if you are logged in).</p>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>