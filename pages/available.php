<?php include('../includes/header.php'); ?>

<?php
// Get all items that are NOT currently borrowed
$result = $conn->query("
    SELECT i.* FROM items i 
    WHERE i.id NOT IN (
        SELECT item_id FROM borrow_records WHERE status='BORROWED'
    ) 
    ORDER BY i.id DESC
");
$has_records = $result && $result->num_rows > 0;
$total_available = $result ? $result->num_rows : 0;
?>

<div class="equipment-page">
    <h2 class="page-title">Available Equipment</h2>
    <p class="page-subtitle">Ready for release</p>

    <div class="card dashboard-table-card">
        <div class="table-card-head">
            <div>
                <h3>Available Items (<?php echo $total_available; ?>)</h3>
                <p class="table-card-subtext">Equipment ready to be borrowed</p>
            </div>
        </div>

        <div class="table-shell">
            <?php if ($has_records): ?>
                <input type="text" class="records-search-input" placeholder="Search available items..." onkeyup="applyRecordFilters()">
                <table class="equipment-table js-next-pagination" id="availableTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Serial Number</th>
                            <th>Brand</th>
                            <th>Accessories</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="availableTableBody">
                        <?php
                        $counter = 1;
                        while ($item = $result->fetch_assoc()) {
                            echo "
                            <tr>
                                <td>{$counter}</td>
                                <td>" . htmlspecialchars($item['item_name']) . "</td>
                                <td>" . htmlspecialchars($item['asset_code'] ?? '-') . "</td>
                                <td>" . htmlspecialchars($item['brand'] ?? '-') . "</td>
                                <td>" . htmlspecialchars($item['accessories'] ?? '-') . "</td>
                                <td><span class='status-badge status-available'>AVAILABLE</span></td>
                            </tr>
                            ";
                            $counter++;
                        }
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No available equipment at the moment. All items are currently borrowed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function applyRecordFilters() {
    const searchInput = document.querySelector(".records-search-input");
    const query = searchInput ? searchInput.value.trim().toLowerCase() : "";
    const table = document.getElementById("availableTable");
    const rows = document.querySelectorAll("#availableTableBody tr");

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
    const table = document.getElementById("availableTable");
    if (table && typeof window.setupNextOnlyTablePagination === "function") {
        window.setupNextOnlyTablePagination(table, { pageSize: 10 });
    }
    applyRecordFilters();
});
</script>

<?php include('../includes/footer.php'); ?>
