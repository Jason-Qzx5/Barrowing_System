<?php include('../includes/header.php'); ?>

<?php
$result = $conn->query("
    SELECT br.*, i.item_name, i.asset_code, i.brand 
    FROM borrow_records br 
    JOIN items i ON br.item_id = i.id 
    WHERE br.status = 'BORROWED' 
    ORDER BY br.release_date DESC
");
$has_records = $result && $result->num_rows > 0;
$total_borrowed = $result ? $result->num_rows : 0;
?>

<div class="equipment-page">
    <h2 class="page-title">Borrowed Equipment</h2>
    <p class="page-subtitle">Currently borrowed equipment</p>

    <div class="card dashboard-table-card">
        <div class="table-card-head">
            <div>
                <h3>Active Borrowings (<?php echo $total_borrowed; ?>)</h3>
                <p class="table-card-subtext">Items currently out on loan</p>
            </div>
        </div>

        <div class="table-shell">
            <?php if ($has_records): ?>
                <input type="text" class="records-search-input" placeholder="Search borrowed items..." onkeyup="applyRecordFilters()">
                <table class="equipment-table js-next-pagination" id="borrowedTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Serial Number</th>
                            <th>Borrower</th>
                            <th>Office</th>
                            <th>Release Date</th>
                            <th>Expected Return</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="borrowedTableBody">
                        <?php
                        $counter = 1;
                        while ($record = $result->fetch_assoc()) {
                            $expected_return = new DateTime($record['expected_return']);
                            $today = new DateTime();
                            $is_overdue = $expected_return < $today;
                            $status_class = $is_overdue ? 'status-overdue' : 'status-pending';
                            $status_text = $is_overdue ? 'OVERDUE' : 'PENDING';
                            
                            echo "
                            <tr>
                                <td>{$counter}</td>
                                <td>" . htmlspecialchars($record['item_name']) . "</td>
                                <td>" . htmlspecialchars($record['asset_code'] ?? '-') . "</td>
                                <td>" . htmlspecialchars($record['borrower']) . "</td>
                                <td>" . htmlspecialchars($record['office']) . "</td>
                                <td>" . htmlspecialchars($record['release_date']) . "</td>
                                <td>" . htmlspecialchars($record['expected_return']) . "</td>
                                <td><span class='status-badge {$status_class}'>{$status_text}</span></td>
                            </tr>
                            ";
                            $counter++;
                        }
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No borrowed equipment at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function applyRecordFilters() {
    const searchInput = document.querySelector(".records-search-input");
    const query = searchInput ? searchInput.value.trim().toLowerCase() : "";
    const table = document.getElementById("borrowedTable");
    const rows = document.querySelectorAll("#borrowedTableBody tr");

    rows.forEach((row) => {
        const rowText = row.innerText.toLowerCase();
        const shouldShow = rowText.includes(query);
        row.dataset.filterMatch = shouldShow ? "1" : "0";
    });

    if (table && typeof window.refreshNextOnlyTablePagination === "function") {
        window.refreshNextOnlyTablePagination(table, true);
        return;
    }

    rows.forEach((row) => {
        row.style.display = row.dataset.filterMatch === "1" ? "" : "none";
    });
}

document.addEventListener("DOMContentLoaded", () => {
    const table = document.getElementById("borrowedTable");
    if (table && typeof window.setupNextOnlyTablePagination === "function") {
        window.setupNextOnlyTablePagination(table, { pageSize: 10 });
    }
    applyRecordFilters();
});
</script>

<?php include('../includes/footer.php'); ?>
