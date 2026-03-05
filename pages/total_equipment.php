<?php include('../includes/header.php'); ?>

<?php
$result = $conn->query("SELECT * FROM items ORDER BY id DESC");
$has_records = $result && $result->num_rows > 0;
$total_items = $result ? $result->num_rows : 0;
?>

<div class="equipment-page">
    <h2 class="page-title">Total Equipment</h2>
    <p class="page-subtitle">All registered inventory items</p>

    <div class="card dashboard-table-card">
        <div class="table-card-head">
            <div>
                <h3>Equipment List (<?php echo $total_items; ?>)</h3>
                <p class="table-card-subtext">Complete list of all registered equipment in inventory</p>
            </div>
        </div>

        <div class="table-shell">
            <?php if ($has_records): ?>
                <input type="text" class="records-search-input" placeholder="Search items..." onkeyup="applyRecordFilters()">
                <table class="equipment-table js-next-pagination" id="equipmentTable">
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
                    <tbody id="equipmentTableBody">
                        <?php
                        $counter = 1;
                        while ($item = $result->fetch_assoc()) {
                            // Check if item is borrowed
                            $borrow_check = $conn->query("SELECT id FROM borrow_records WHERE item_id='{$item['id']}' AND status='BORROWED'");
                            $is_borrowed = $borrow_check && $borrow_check->num_rows > 0;
                            
                            $status = $is_borrowed ? 'BORROWED' : 'AVAILABLE';
                            $status_class = $is_borrowed ? 'status-borrowed' : 'status-available';
                            
                            echo "
                            <tr data-status='" . strtolower($status) . "'>
                                <td>{$counter}</td>
                                <td>" . htmlspecialchars($item['item_name']) . "</td>
                                <td>" . htmlspecialchars($item['asset_code'] ?? '-') . "</td>
                                <td>" . htmlspecialchars($item['brand'] ?? '-') . "</td>
                                <td>" . htmlspecialchars($item['accessories'] ?? '-') . "</td>
                                <td><span class='status-badge {$status_class}'>{$status}</span></td>
                            </tr>
                            ";
                            $counter++;
                        }
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No equipment found in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function applyRecordFilters() {
    const searchInput = document.querySelector(".records-search-input");
    const query = searchInput ? searchInput.value.trim().toLowerCase() : "";
    const table = document.getElementById("equipmentTable");
    const rows = document.querySelectorAll("#equipmentTableBody tr");

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
    const table = document.getElementById("equipmentTable");
    if (table && typeof window.setupNextOnlyTablePagination === "function") {
        window.setupNextOnlyTablePagination(table, { pageSize: 10 });
    }
    applyRecordFilters();
});
</script>

<?php include('../includes/footer.php'); ?>
