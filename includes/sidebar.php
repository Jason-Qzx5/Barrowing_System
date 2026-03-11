<div class="sidebar">
    <div class="logo-container">
        <img src="../assets/images/da_logo.jpg" alt="DA Logo" class="da-logo">
        <div class="logo-text">Department of Agriculture</div>
    </div>

    <a href="dashboard.php">Dashboard</a>
    <a href="borrow_item.php#addEquipmentCard">Add/Borrow Equipment</a>
    <a href="return_item.php">Return Item</a>
    <a href="logbook.php">Record Logbook</a>

    <?php if(isset($_SESSION['full_name'])): ?>
    <div class="sidebar-footer">
        <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
        <small>Logged in</small>
    </div>
    <?php endif; ?>
</div>
