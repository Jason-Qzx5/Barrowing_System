<?php
include('../includes/header.php');
?>

<style>
.receipt-view-btn {
    min-width: 76px;
    padding: 6px 12px;
}

.receipt-preview-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.62);
    display: none;
    align-items: flex-start;
    justify-content: center;
    padding: 10px 12px;
    z-index: 10000;
    backdrop-filter: blur(1px);
    -webkit-backdrop-filter: blur(1px);
}

.receipt-preview-modal.is-open {
    display: flex;
}

.receipt-preview-dialog {
    width: min(1140px, calc(100vw - 24px));
    height: calc(100vh - 20px);
    max-width: 1140px;
    max-height: calc(100vh - 20px);
    background: #eef2f7;
    border-radius: 16px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 24px 56px rgba(15, 23, 42, 0.34);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    margin-top: 0;
}

.receipt-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid #dbe3ee;
    background: #ffffff;
}

.receipt-preview-header h3 {
    margin: 0;
    font-size: 20px;
    color: #0f172a;
}

.receipt-preview-close {
    width: 44px;
    height: 44px;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    background: #ffffff;
    color: #1f2937;
    font-size: 34px;
    line-height: 1;
    cursor: pointer;
    padding: 0;
}

.receipt-preview-close:hover {
    background: #f1f5f9;
}

.receipt-preview-frame {
    width: 100%;
    flex: 1 1 auto;
    border: 0;
    background: #eef2f7;
}
</style>

<div class="card">
<h2>Equipment Borrowing Logbook</h2>
<p class="section-subtitle">Department of Agriculture official record</p>

<form method="GET" action="download_logbook_pdf.php" class="logbook-filter">
    <input type="hidden" name="report_type" value="borrowed">
    <label><b>Download Duration:</b></label>
    <select name="filter_type" onchange="toggleDateRange(this.value)">
        <option value="all">All Records</option>
        <option value="today">Today</option>
        <option value="week">Last 7 Days</option>
        <option value="month">This Month</option>
        <option value="year">This Year</option>
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
<th>Item</th>
<th>Asset Code</th>
<th>Accessories</th>
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
        $status = strtoupper(trim((string) ($row['status'] ?? 'BORROWED')));
        switch($status) {
            case 'BORROWED':
                $statusClass = 'status-borrowed';
                break;
            case 'RETURNED':
                $statusClass = 'status-returned';
                break;
            case 'LOST':
                $statusClass = 'status-lost';
                break;
            case 'DAMAGED':
                $statusClass = 'status-damaged';
                break;
            default:
                $statusClass = 'status-other';
        }
        echo "<tr>
            <td>".$row['manual_item']."</td>
            <td>".$row['asset_code']."</td>
            <td>".(isset($row['accessories']) && trim($row['accessories']) !== '' ? htmlspecialchars($row['accessories']) : '-')."</td>
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
    <input type="hidden" name="report_type" value="returned">
    <label><b>Download Duration:</b></label>
    <select name="filter_type" onchange="toggleDateRange(this.value, 'customDatesReturned')">
        <option value="all">All Records</option>
        <option value="today">Today</option>
        <option value="week">Last 7 Days</option>
        <option value="month">This Month</option>
        <option value="year">This Year</option>
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
<th>Borrow Code</th>
<th>Item</th>
<th>Asset Code</th>
<th>Accessories</th>
<th>Accessory Status</th>
<th>Borrower</th>
<th>Lender</th>
<th>Office</th>
<th>Return Date</th>
<th>Returned By</th>
<th>Received By</th>
<th>Receipt</th>
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

        $lender_name = trim((string) ($returned_row['lender'] ?? ''));
        if ($lender_name === '') {
            $lender_name = '-';
        }

        $receipt_link = 'borrow_slip_qr.php?code=' . rawurlencode((string) ($returned_row['borrow_code'] ?? ''));
        $receipt_link = htmlspecialchars($receipt_link, ENT_QUOTES, 'UTF-8');

        $accessory_status = strtoupper(trim((string) ($returned_row['accessory_status'] ?? 'GOOD')));
        $accessory_status_display = ucfirst(strtolower(str_replace('_', ' ', $accessory_status)));
        $accessory_status_color = $accessory_status === 'GOOD' ? '#166534' : ($accessory_status === 'LOST' ? '#dc2626' : ($accessory_status === 'DAMAGED' ? '#d97706' : '#6b7280'));

        echo "<tr>
            <td>".htmlspecialchars((string) ($returned_row['borrow_code'] ?? '-'))."</td>
            <td>".htmlspecialchars((string) ($returned_row['manual_item'] ?? '-'))."</td>
            <td>".htmlspecialchars((string) ($returned_row['asset_code'] ?? '-'))."</td>
            <td>".htmlspecialchars((string) ($returned_row['accessories'] ?? '-'))."</td>
            <td><span style='color: {$accessory_status_color}; font-weight: bold;'>".htmlspecialchars($accessory_status_display)."</span></td>
            <td>".htmlspecialchars((string) ($returned_row['borrower'] ?? '-'))."</td>
            <td>".htmlspecialchars($lender_name)."</td>
            <td>".htmlspecialchars((string) ($returned_row['office'] ?? '-'))."</td>
            <td>".htmlspecialchars($returned_date)."</td>
            <td>".htmlspecialchars(trim((string) ($returned_row['returned_by'] ?? '')) !== '' ? (string) $returned_row['returned_by'] : '-')."</td>
            <td>".htmlspecialchars($received_by)."</td>
            <td><button type='button' class='btn-secondary receipt-view-btn' data-receipt-url='".$receipt_link."'>View</button></td>
        </tr>";
    }
}else{
    echo "<tr><td colspan='12' class='text-center'>No returned records found.</td></tr>";
}
?>
</tbody>
</table>

<div id="receiptPreviewModal" class="receipt-preview-modal" aria-hidden="true">
    <div class="receipt-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="receiptPreviewTitle">
        <div class="receipt-preview-header">
            <h3 id="receiptPreviewTitle">Borrow Slip Preview</h3>
            <button type="button" class="receipt-preview-close" id="receiptPreviewCloseBtn" aria-label="Close receipt preview">&times;</button>
        </div>
        <iframe
            id="receiptPreviewFrame"
            class="receipt-preview-frame"
            title="Borrow Slip Preview"
            loading="lazy"
            src="about:blank"
        ></iframe>
    </div>
</div>
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

function openReceiptPreview(url){
    const modal = document.getElementById("receiptPreviewModal");
    const frame = document.getElementById("receiptPreviewFrame");
    if (!modal || !frame || !url) {
        return;
    }

    frame.src = url;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
}

function closeReceiptPreview(){
    const modal = document.getElementById("receiptPreviewModal");
    const frame = document.getElementById("receiptPreviewFrame");
    if (!modal || !frame) {
        return;
    }

    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    frame.src = "about:blank";
    document.body.style.overflow = "";
}

document.addEventListener("DOMContentLoaded", function(){
    const modal = document.getElementById("receiptPreviewModal");
    const closeBtn = document.getElementById("receiptPreviewCloseBtn");
    const viewButtons = document.querySelectorAll(".receipt-view-btn");

    viewButtons.forEach((button) => {
        button.addEventListener("click", function(){
            const url = button.getAttribute("data-receipt-url") || "";
            openReceiptPreview(url);
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", closeReceiptPreview);
    }

    if (modal) {
        modal.addEventListener("click", function(event){
            if (event.target === modal) {
                closeReceiptPreview();
            }
        });
    }

    document.addEventListener("keydown", function(event){
        if (event.key === "Escape") {
            closeReceiptPreview();
        }
    });
});
</script>

<?php include('../includes/footer.php'); ?>
