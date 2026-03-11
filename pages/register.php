<?php include('../includes/header.php'); ?>

<div class="card register-card">
<h3 class="register-title">User Registration (Disabled)</h3>

<?php
// There is no in‑system registration. Accounts are added manually via
// phpMyAdmin or another database client. Passwords stored in the
// `users` table must be SHA1 hashes.
//
// The form below is provided purely as a helper for administrators to
// generate SHA1 hashes for a plaintext password. It does NOT insert any
// user into the database.

$hash = '';
if (isset($_POST['calculate'])) {
    $hash = sha1($_POST['plain_password']);
}
?>

<p class="info-message">Accounts must be created manually. Use the
utility below to compute a SHA1 hash for the password you intend to
store.</p>

<form method="POST" class="register-form">
    <div class="form-group">
        <label>Plain Password</label>
        <input type="password" name="plain_password" required>
    </div>
    <button type="submit" name="calculate" class="btn-primary">
        Calculate SHA1</button>
</form>

<?php if ($hash !== ''): ?>
    <div class="form-group">
        <label>SHA1 Hash</label>
        <input type="text" readonly value="<?php echo $hash; ?>" class="full-width">
    </div>
    <p class="note">Copy this hash into the <code>password</code> column when
    inserting a new <code>users</code> record.</p>
<?php endif; ?>

</div>

<?php include('../includes/footer.php'); ?>