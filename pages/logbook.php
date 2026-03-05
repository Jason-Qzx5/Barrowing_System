<?php
include('../includes/header.php');
?>

<div class="card">
<h2>Equipment Borrowing Logbook</h2>
<p class="section-subtitle">Department of Agriculture official record</p>

<form method="GET" action="download_logbook_pdf.php" class="logbook-filter">
    <label><b>Download Duration:</b></label>
    <select name="filter_type" onchange="toggleDateRange(this.value)">
        <option value="today">Today</option>
        <option value="week">Last 7 Days</option>
        <option value="custom">Custom Date Range</option>
    </select>

    <div id="customDates" class="custom-dates">
        <div>
            <label>From</label>
            <input type="date" name="start_date">
        </div>
        <div>
            <label>To</label>
            <input type="date" name="end_date">
        </div>
    </div>

    <button type="submit">Download PDF Logbook</button>
</form>

<table class="js-next-pagination">
<thead>
<tr>
<th>ID</th>
<th>Item</th>
<th>Asset Code</th>
<th>Borrower</th>
<th>Office</th>
<th>Purpose</th>
<th>Release Date</th>
<th>Expected Return</th>
<th>Status</th>
</tr>
</thead>
<tbody>

<?php
$sql = "SELECT * FROM borrow_records ORDER BY id DESC";
$result = $conn->query($sql);

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $statusClass = ($row['status'] == "BORROWED") ? "status-borrowed" : "status-returned";
        echo "<tr>
            <td>".$row['id']."</td>
            <td>".$row['manual_item']."</td>
            <td>".$row['asset_code']."</td>
            <td>".$row['borrower']."</td>
            <td>".$row['office']."</td>
            <td>".$row['purpose']."</td>
            <td>".$row['release_date']."</td>
            <td>".$row['expected_return']."</td>
            <td><span class='status ".$statusClass."'>".$row['status']."</span></td>
        </tr>";
    }
}else{
    echo "<tr><td colspan='9' class='text-center'>No records found.</td></tr>";
}
?>
</tbody>
</table>

<h3 style="margin-top: 18px;">Returned Records</h3>
<p class="section-subtitle">Shows when equipment was returned and who received it.</p>

<form method="GET" action="download_logbook_pdf.php" class="logbook-filter">
    <label><b>Download Duration:</b></label>
    <select name="filter_type" onchange="toggleDateRange(this.value, 'customDatesReturned')">
        <option value="today">Today</option>
        <option value="week">Last 7 Days</option>
        <option value="custom">Custom Date Range</option>
    </select>

    <div id="customDatesReturned" class="custom-dates">
        <div>
            <label>From</label>
            <input type="date" name="start_date">
        </div>
        <div>
            <label>To</label>
            <input type="date" name="end_date">
        </div>
    </div>

    <button type="submit">Download PDF Logbook</button>
</form>

<table class="js-next-pagination">
<thead>
<tr>
<th>ID</th>
<th>Borrow Code</th>
<th>Item</th>
<th>Asset Code</th>
<th>Borrower</th>
<th>Office</th>
<th>Return Date</th>
<th>Received By</th>
</tr>
</thead>
<tbody>

<?php
$returned_sql = "SELECT * FROM borrow_records
                 WHERE status='RETURNED'
                 ORDER BY return_date DESC, id DESC";
$returned_result = $conn->query($returned_sql);

if($returned_result && $returned_result->num_rows > 0){
    while($returned_row = $returned_result->fetch_assoc()){
        $returned_date = trim((string) ($returned_row['return_date'] ?? ''));
        if ($returned_date === '' || $returned_date === '0000-00-00') {
            $returned_date = '-';
        }

        $received_by = trim((string) ($returned_row['received_by'] ?? ''));
        if ($received_by === '') {
            $received_by = '-';
        }

        echo "<tr>
            <td>".htmlspecialchars((string) $returned_row['id'])."</td>
            <td>".htmlspecialchars((string) ($returned_row['borrow_code'] ?? '-'))."</td>
            <td>".htmlspecialchars((string) ($returned_row['manual_item'] ?? '-'))."</td>
            <td>".htmlspecialchars((string) ($returned_row['asset_code'] ?? '-'))."</td>
            <td>".htmlspecialchars((string) ($returned_row['borrower'] ?? '-'))."</td>
            <td>".htmlspecialchars((string) ($returned_row['office'] ?? '-'))."</td>
            <td>".htmlspecialchars($returned_date)."</td>
            <td>".htmlspecialchars($received_by)."</td>
        </tr>";
    }
}else{
    echo "<tr><td colspan='8' class='text-center'>No returned records found.</td></tr>";
}
?>
</tbody>
</table>
</div>

<script>
function toggleDateRange(value, targetId = "customDates"){
    const target = document.getElementById(targetId);
    if (!target) {
        return;
    }
    target.style.display =
        (value === "custom") ? "grid" : "none";
}
</script>

<?php include('../includes/footer.php'); ?>
