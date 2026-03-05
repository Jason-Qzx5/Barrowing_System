<?php include('../includes/header.php'); ?>

<?php
$totalItems = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM items");
if($row = $res->fetch_assoc()){
    $totalItems = $row['total'];
}

$borrowedItems = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM borrow_records WHERE status='BORROWED'");
if($row = $res->fetch_assoc()){
    $borrowedItems = $row['total'];
}

$availableItems = $totalItems - $borrowedItems;

$overdue_sql = "
SELECT *,
DATEDIFF(CURDATE(), expected_return) AS days_late
FROM borrow_records
WHERE status='BORROWED'
AND expected_return < CURDATE()
ORDER BY expected_return ASC
";
$overdue_result = $conn->query($overdue_sql);
?>

<div class="grid">
    <div class="card card-clickable stat-card stat-total" onclick="window.location.href='available.php?filter=all#equipmentRecords'">
        <div class="stat-card-head">
            <h4>Total Equipment</h4>
            <span class="stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                    <path d="M3 10h18"></path>
                </svg>
            </span>
        </div>
        <h1 class="stat-value"><?php echo $totalItems; ?></h1>
        <p class="card-subtext">All registered inventory items</p>
    </div>

    <div class="card card-clickable stat-card stat-borrowed" onclick="window.location.href='borrowed_item.php?filter=borrowed#equipmentRecords'">
        <div class="stat-card-head">
            <h4>Borrowed</h4>
            <span class="stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M12 4v9"></path>
                    <path d="m8 8 4-4 4 4"></path>
                    <rect x="4" y="14" width="16" height="6" rx="2"></rect>
                </svg>
            </span>
        </div>
        <h1 class="stat-value"><?php echo $borrowedItems; ?></h1>
        <p class="card-subtext">Currently borrowed equipment</p>
    </div>

    <div class="card card-clickable stat-card stat-available" onclick="window.location.href='available.php?filter=available#equipmentRecords'">
        <div class="stat-card-head">
            <h4>Available</h4>
            <span class="stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="m8 12 2.5 2.5L16 9"></path>
                </svg>
            </span>
        </div>
        <h1 class="stat-value"><?php echo $availableItems; ?></h1>
        <p class="card-subtext">Ready for release</p>
    </div>
</div>

<div class="card dashboard-table-card overdue-card">
    <div class="table-card-head">
        <div>
            <h3>Overdue Borrowed Equipment</h3>
            <p class="table-card-subtext">Items that are past their expected return date.</p>
        </div>
        <span class="table-chip chip-danger">Urgent</span>
    </div>
    <div class="table-shell">
        <table class="dashboard-table dashboard-table-overdue js-next-pagination">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Borrower</th>
                    <th>Office</th>
                    <th>Expected Return</th>
                    <th>Days Late</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($overdue_result && $overdue_result->num_rows > 0){
                    while($row = $overdue_result->fetch_assoc()){
                        $days_late = (int) $row['days_late'];
                        echo "<tr class='row-overdue'>
                            <td>{$row['manual_item']}</td>
                            <td>{$row['borrower']}</td>
                            <td>{$row['office']}</td>
                            <td>{$row['expected_return']}</td>
                            <td><span class='dash-days-pill'>{$days_late} day(s)</span></td>
                        </tr>";
                    }
                }else{
                    echo "<tr><td colspan='5' class='text-center table-empty'>No overdue equipment.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card dashboard-table-card activity-card">
    <div class="table-card-head">
        <div>
            <h3>Recent Borrowing Activity</h3>
            <p class="table-card-subtext">Latest 5 borrowing transactions.</p>
        </div>
        <span class="table-chip chip-info">Latest</span>
    </div>
    <div class="table-shell">
        <table class="dashboard-table dashboard-table-activity js-next-pagination">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Borrower</th>
                    <th>Office</th>
                    <th>Release Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT * FROM borrow_records ORDER BY id DESC LIMIT 5";
                $result = $conn->query($sql);

                if($result && $result->num_rows > 0){
                    while($row = $result->fetch_assoc()){
                        $statusClass = ($row['status'] === "BORROWED") ? "dash-status-borrowed" : "dash-status-returned";
                        echo "<tr>
                            <td>{$row['manual_item']}</td>
                            <td>{$row['borrower']}</td>
                            <td>{$row['office']}</td>
                            <td>{$row['release_date']}</td>
                            <td><span class='dash-status-pill {$statusClass}'>{$row['status']}</span></td>
                        </tr>";
                    }
                }else{
                    echo "<tr><td colspan='5' class='text-center table-empty'>No activity yet.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
