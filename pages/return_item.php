<?php include('../includes/header.php'); ?>

<?php
$feedback_message = '';
$feedback_type = '';

function normalize_borrow_code_input($raw_value) {
    return strtoupper(trim((string) $raw_value));
}

if (isset($_GET['clear_last_return']) && $_GET['clear_last_return'] === '1') {
    unset($_SESSION['last_returned_code']);
}

if (isset($_POST['return_from_table'])) {
    $selected_code = normalize_borrow_code_input($_POST['selected_code'] ?? '');
    $entered_code = normalize_borrow_code_input($_POST['borrow_code_input'] ?? '');
    $returned_by = trim($_POST['returned_by'] ?? '');
    $received_by = trim($_POST['received_by'] ?? '');
    $return_status = $_POST['return_status'] ?? 'RETURNED';
    $accessory_status = $_POST['accessory_status'] ?? 'GOOD';
    $accessory_notes = trim($_POST['accessory_notes'] ?? '');

    if ($selected_code === '' && $entered_code !== '') {
        $selected_code = $entered_code;
    }

    if ($selected_code === '' || $entered_code === '') {
        $feedback_message = 'Borrowing code is required.';
        $feedback_type = 'alert-danger';
    } elseif ($selected_code !== $entered_code) {
        $feedback_message = 'Borrowing code does not match the selected record.';
        $feedback_type = 'alert-danger';
    } elseif ($return_status === 'RETURNED' && $returned_by === '') {
        $feedback_message = 'Returned by field is required for returned items.';
        $feedback_type = 'alert-danger';
    } elseif ($received_by === '') {
        $feedback_message = 'Received by field is required.';
        $feedback_type = 'alert-danger';
    } elseif (!in_array($return_status, ['RETURNED', 'LOST', 'DAMAGED'])) {
        $feedback_message = 'Invalid return status.';
        $feedback_type = 'alert-danger';
    } elseif (!in_array($accessory_status, ['GOOD', 'LOST', 'DAMAGED', 'NOT_INCLUDED'])) {
        $feedback_message = 'Invalid accessory status.';
        $feedback_type = 'alert-danger';
    } else {
        $safe_code = $conn->real_escape_string($selected_code);
        $check = $conn->query("SELECT id FROM borrow_records
                               WHERE borrow_code='{$safe_code}'
                               AND status='BORROWED'");

        if ($check && $check->num_rows > 0) {
            $safe_received_by = $conn->real_escape_string($received_by);
            $safe_returned_by = $conn->real_escape_string($returned_by);
            $safe_return_status = $conn->real_escape_string($return_status);
            $safe_accessory_status = $conn->real_escape_string($accessory_status);
            $safe_accessory_notes = $conn->real_escape_string($accessory_notes);

            $update_ok = $conn->query("UPDATE borrow_records
                                       SET status='{$safe_return_status}',
                                           returned_by='{$safe_returned_by}',
                                           received_by='{$safe_received_by}',
                                           accessory_status='{$safe_accessory_status}',
                                           accessory_notes='{$safe_accessory_notes}',
                                           return_date=CURDATE()
                                       WHERE borrow_code='{$safe_code}'
                                       AND status='BORROWED'");

            if (!$update_ok) {
                $feedback_message = 'Failed to update return record: ' . $conn->error;
                $feedback_type = 'alert-danger';
            } else {
                $returned_items = (int) $conn->affected_rows;
                if ($returned_items > 0) {
                    $status_text = strtolower($return_status);
                    $accessory_text = $accessory_status !== 'GOOD' ? " (Accessories: {$accessory_status})" : "";
                    $feedback_message = "{$returned_items} item(s) marked as {$status_text}{$accessory_text}. ";
                    if ($return_status === 'RETURNED') {
                        $feedback_message .= "Returned by {$returned_by}, received by {$received_by}.";
                    } else {
                        $feedback_message .= "Received by {$received_by}.";
                    }
                    $feedback_type = 'alert-success';
                    $_SESSION['last_returned_code'] = $selected_code;
                } else {
                    $feedback_message = 'No borrowed items were updated.';
                    $feedback_type = 'alert-danger';
                }
            }
        } else {
            $feedback_message = 'Invalid or already returned borrowing code.';
            $feedback_type = 'alert-danger';
        }
    }
}

$last_returned_code = strtoupper(trim((string) ($_SESSION['last_returned_code'] ?? '')));
$slip_records = array();
$slip_summary = null;

if ($last_returned_code !== '') {
    $safe_last_returned_code = $conn->real_escape_string($last_returned_code);
    $slip_result = $conn->query("SELECT *
                                 FROM borrow_records
                                 WHERE borrow_code='{$safe_last_returned_code}'
                                 ORDER BY id ASC");

    if ($slip_result) {
        while ($row = $slip_result->fetch_assoc()) {
            $slip_records[] = $row;
        }
        if (!empty($slip_records)) {
            $slip_summary = $slip_records[0];
        }
    }

    if ($slip_summary === null) {
        unset($_SESSION['last_returned_code']);
        $last_returned_code = '';
    }
}

$borrow_items_map = array();
$borrow_items_query = $conn->query("SELECT
                                        borrow_code,
                                        manual_item,
                                        asset_code,
                                        accessories
                                    FROM borrow_records
                                    WHERE status='BORROWED'
                                    ORDER BY id ASC");

if ($borrow_items_query) {
    while ($item_row = $borrow_items_query->fetch_assoc()) {
        $code_key = strtoupper(trim((string) ($item_row['borrow_code'] ?? '')));
        if ($code_key === '') {
            continue;
        }

        if (!isset($borrow_items_map[$code_key])) {
            $borrow_items_map[$code_key] = array();
        }

        $borrow_items_map[$code_key][] = array(
            'manual_item' => (string) ($item_row['manual_item'] ?? ''),
            'asset_code' => (string) ($item_row['asset_code'] ?? ''),
            'accessories' => (string) ($item_row['accessories'] ?? '')
        );
    }
}

$active_borrows = [];
$return_preview_data = array();
$active_query = $conn->query("SELECT
                                borrow_code,
                                MAX(borrower) AS borrower,
                                MAX(office) AS office,
                                MAX(purpose) AS purpose,
                                MIN(release_date) AS release_date,
                                MAX(expected_return) AS expected_return,
                                MAX(status) AS borrow_status,
                                GROUP_CONCAT(manual_item ORDER BY id SEPARATOR '||') AS item_names,
                                COUNT(*) AS total_items,
                                MAX(id) AS latest_id
                              FROM borrow_records
                              WHERE status='BORROWED'
                              GROUP BY borrow_code
                              ORDER BY latest_id DESC");

if ($active_query) {
    while ($row = $active_query->fetch_assoc()) {
        $items = array_values(array_filter(array_map('trim', explode('||', (string) ($row['item_names'] ?? '')))));
        $full_items = !empty($items) ? implode(', ', $items) : '-';

        if (count($items) > 3) {
            $summary_items = implode(', ', array_slice($items, 0, 3)) . ' +' . (count($items) - 3) . ' more';
        } else {
            $summary_items = $full_items;
        }

        $release_date = trim((string) ($row['release_date'] ?? ''));
        $release_date_text = '-';
        if ($release_date !== '' && $release_date !== '0000-00-00') {
            $timestamp = strtotime($release_date);
            if ($timestamp !== false) {
                $release_date_text = date('M d, Y', $timestamp);
            }
        }

        $row['item_names_full'] = $full_items;
        $row['item_names_summary'] = $summary_items;
        $row['release_date_text'] = $release_date_text;
        $code_key = strtoupper(trim((string) ($row['borrow_code'] ?? '')));
        $preview_items = $borrow_items_map[$code_key] ?? array();
        $active_borrows[] = $row;

        if ($code_key !== '') {
            $return_preview_data[$code_key] = array(
                'borrow_code' => (string) ($row['borrow_code'] ?? ''),
                'borrower' => (string) ($row['borrower'] ?? '-'),
                'office' => (string) ($row['office'] ?? '-'),
                'purpose' => (string) ($row['purpose'] ?? '-'),
                'release_date' => (string) ($row['release_date'] ?? '-'),
                'expected_return' => (string) ($row['expected_return'] ?? '-'),
                'received_by' => '',
                'total_items' => (int) ($row['total_items'] ?? 0),
                'items' => $preview_items
            );
        }
    }
}

$return_receipt_preview_url = '';
if ($slip_summary !== null) {
    $receipt_code = trim((string) ($slip_summary['borrow_code'] ?? $last_returned_code));
    if ($receipt_code !== '') {
        $return_receipt_preview_url = 'borrow_slip_qr.php?code=' . rawurlencode($receipt_code);
    }
}

$should_open_receipt_modal = (
    $slip_summary !== null &&
    isset($_POST['return_from_table']) &&
    $feedback_type === 'alert-success'
);
?>

<style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }

    .modal.is-open {
        display: flex;
    }

    .modal-content {
        background-color: #fff;
        padding: 25px;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        position: relative;
    }

    .return-receipt-modal-content {
        width: min(1140px, 95vw);
        height: min(88vh, 820px);
        max-width: 1140px;
        max-height: 88vh;
        overflow: hidden;
        padding: 0;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        display: flex;
        flex-direction: column;
    }

    .return-receipt-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 14px 16px;
        border-bottom: 1px solid #dbe3ee;
        background: #ffffff;
    }

    .return-receipt-head h2 {
        margin: 0;
        color: #0f172a;
        font-size: 20px;
    }

    .return-receipt-close-btn {
        width: 44px;
        height: 44px;
        min-width: 44px;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 34px;
        line-height: 1;
        padding: 0;
        cursor: pointer;
    }

    .return-receipt-close-btn:hover {
        background: #f3f4f6;
    }

    .return-receipt-body {
        flex: 1 1 auto;
        min-height: 0;
        padding: 18px;
        overflow: auto;
    }

    .return-receipt-frame {
        width: 100%;
        min-height: 100%;
        height: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        background: #fff;
    }

    #returnReceiptModal {
        background: rgba(15, 23, 42, 0.62);
        backdrop-filter: blur(1px);
        -webkit-backdrop-filter: blur(1px);
        z-index: 12000;
    }

    #returnReceiptModal.is-open {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 42px 16px 20px;
    }
</style>

<div class="return-page">
    <div class="return-hero">
        <div class="return-hero-head">
            <span class="return-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="4"></rect>
                    <path d="M12 6v8"></path>
                    <path d="m8.5 10.5 3.5 3.5 3.5-3.5"></path>
                    <path d="M7 18h10"></path>
                </svg>
            </span>
            <h2>Return Equipment</h2>
        </div>
        <p>Return borrowed equipment using Borrow Code</p>
    </div>

    <section class="card return-check-card">
        <h3>Return Item (Using Borrowing Code)</h3>

        <?php if ($feedback_message !== ''): ?>
            <div class="<?php echo $feedback_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>

        <?php if ($slip_summary !== null): ?>
            <div class="return-action-row" style="margin-top: 12px;">
                <button type="button" class="btn-secondary" onclick="openReturnReceiptModal()">
                    View Return Receipt
                </button>
                <button type="button" class="btn-secondary" onclick="clearLastReturnedReceipt()">
                    Clear Preview
                </button>
            </div>

            <div id="returnReceiptModal" class="modal">
                <div class="modal-content return-receipt-modal-content">
                    <div class="return-receipt-head">
                        <h2>Borrow Slip Preview</h2>
                        <button type="button" class="return-receipt-close-btn" onclick="closeReturnReceiptModal()" aria-label="Close">&times;</button>
                    </div>
                    <div class="return-receipt-body">
                        <iframe
                            id="returnReceiptFrame"
                            class="return-receipt-frame"
                            src="about:blank"
                            data-src="<?php echo htmlspecialchars($return_receipt_preview_url); ?>"
                            loading="lazy"
                            title="Borrow Slip Preview"
                        ></iframe>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="return-code-search">
            <label for="borrowCodeSearch">Search Borrowing Code</label>
            <div class="return-code-search-row">
                <input
                    type="text"
                    id="borrowCodeSearch"
                    placeholder="Type borrowing code"
                    oninput="highlightBorrowRows(false)"
                    autocomplete="off"
                >
                <button type="button" class="btn-secondary" onclick="highlightBorrowRows(true)">Find</button>
                <button type="button" class="btn-secondary" onclick="clearBorrowSearch()">Clear</button>
            </div>
            <p id="borrowSearchResult" class="return-search-result"></p>
        </div>

        <p class="section-subtitle">
            Showing <?php echo count($active_borrows); ?> active borrowed record(s).
        </p>

        <div class="equipment-table-wrap return-table-wrap">
            <table class="equipment-table return-records-table js-next-pagination" data-no-pagination="1">
                <thead>
                    <tr>
                        <th>Borrower Name</th>
                        <th>DA Office</th>
                        <th>Date</th>
                        <th>Item Name(s)</th>
                        <th>Status</th>
                        <th class="return-action-col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($active_borrows)): ?>
                        <tr>
                            <td colspan="6" class="table-empty text-center">No active borrowed items.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($active_borrows as $borrow_record): ?>
                            <tr class="return-record-row" data-borrow-code="<?php echo htmlspecialchars((string) ($borrow_record['borrow_code'] ?? '')); ?>">
                                <td><?php echo htmlspecialchars((string) ($borrow_record['borrower'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($borrow_record['office'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($borrow_record['release_date_text'] ?? '-')); ?></td>
                                <td title="<?php echo htmlspecialchars((string) ($borrow_record['item_names_full'] ?? '-')); ?>">
                                    <?php echo htmlspecialchars((string) ($borrow_record['item_names_summary'] ?? '-')); ?>
                                </td>
                                <td>
                                    <span class="status-inline borrowed">
                                        <?php
                                        $row_status = trim((string) ($borrow_record['borrow_status'] ?? 'BORROWED'));
                                        echo htmlspecialchars(ucfirst(strtolower($row_status)));
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn-return-record"
                                        data-borrow-code="<?php echo htmlspecialchars((string) ($borrow_record['borrow_code'] ?? ''), ENT_QUOTES); ?>"
                                        data-borrower-name="<?php echo htmlspecialchars((string) ($borrow_record['borrower'] ?? '-'), ENT_QUOTES); ?>"
                                    >
                                        Return
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div id="returnCodeModal" class="modal">
    <div class="modal-content return-code-modal-content">
        <h2>Return Item via Borrowing Code</h2>
        <p id="returnModalRecordLabel">Enter borrowing code to continue.</p>

        <form method="POST" id="returnCodeForm" onsubmit="return submitReturnModal(event);">
            <input type="hidden" name="return_from_table" value="1">
            <input type="hidden" name="selected_code" id="modalSelectedCode" value="">

            <label for="modalBorrowCodeInput">Borrowing Code</label>
            <input
                type="text"
                name="borrow_code_input"
                id="modalBorrowCodeInput"
                placeholder="Example: DA-20260224-ABC12"
                autocomplete="off"
                required
            >

            <label for="modalReturnedByInput">Returned By</label>
            <input
                type="text"
                name="returned_by"
                id="modalReturnedByInput"
                placeholder="Enter name of person returning items"
                autocomplete="off"
                required
            >

            <label for="modalReturnStatus">Return Status</label>
            <select name="return_status" id="modalReturnStatus" onchange="toggleReturnedByField(this.value)">
                <option value="RETURNED">Returned</option>
                <option value="LOST">Lost</option>
                <option value="DAMAGED">Damaged</option>
            </select>

            <label for="modalAccessoryStatus">Accessory Status</label>
            <select name="accessory_status" id="modalAccessoryStatus">
                <option value="GOOD">Good Condition</option>
                <option value="LOST">Lost</option>
                <option value="DAMAGED">Damaged</option>
                <option value="NOT_INCLUDED">Not Included</option>
            </select>

            <label for="modalAccessoryNotes">Accessory Notes (Optional)</label>
            <textarea name="accessory_notes" id="modalAccessoryNotes" placeholder="Any notes about accessory condition..." rows="2"></textarea>

            <label for="modalReceivedByInput">Received By</label>
            <input
                type="text"
                name="received_by"
                id="modalReceivedByInput"
                placeholder="Enter name of person who received items"
                autocomplete="off"
                required
            >

            <div id="returnSlipPreview" class="return-slip-preview hidden">
                <h3>Borrow Slip Preview</h3>
                <div id="returnSlipPreviewBody"></div>
            </div>

            <div class="modal-actions">
                
                <button type="submit" id="returnModalSubmitBtn">Verify Code</button>
            </div>
        </form>
    </div>
</div>

<script>
const returnPreviewData = <?php echo json_encode($return_preview_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let returnCodeVerified = false;
let currentReturnCode = "";
let verifiedReturnCode = "";

function normalizeCode(value){
    return (value || "").trim().toUpperCase();
}

function extractBorrowCode(value){
    return normalizeCode(value);
}

function toggleReturnedByField(statusValue){
    const returnedByInput = document.getElementById("modalReturnedByInput");
    if (returnedByInput) {
        if (statusValue === 'RETURNED') {
            returnedByInput.required = true;
            returnedByInput.style.display = '';
        } else {
            returnedByInput.required = false;
            returnedByInput.style.display = 'none';
        }
    }
}

function escapeHtml(value){
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function renderReturnSlipPreview(codeValue){
    const previewWrap = document.getElementById("returnSlipPreview");
    const previewBody = document.getElementById("returnSlipPreviewBody");
    if (!previewWrap || !previewBody) {
        return;
    }

    const codeKey = normalizeCode(codeValue);
    const data = codeKey !== "" ? (returnPreviewData[codeKey] || null) : null;
    if (!data) {
        previewBody.innerHTML = "";
        previewWrap.classList.add("hidden");
        return;
    }

    const items = Array.isArray(data.items) ? data.items : [];
    const itemRows = items.map((item, index) => {
        const itemName = escapeHtml(item && item.manual_item ? item.manual_item : "-");
        const assetCode = escapeHtml(item && item.asset_code ? item.asset_code : "-");
        const accessories = escapeHtml(item && item.accessories ? item.accessories : "-");

        return "<tr>"
            + "<td>" + (index + 1) + "</td>"
            + "<td>" + itemName + "</td>"
            + "<td>" + assetCode + "</td>"
            + "<td>" + accessories + "</td>"
            + "</tr>";
    }).join("");

    const borrowCode = escapeHtml(data.borrow_code || "-");
    const borrower = escapeHtml(data.borrower || "-");
    const office = escapeHtml(data.office || "-");
    const purpose = escapeHtml(data.purpose || "-");
    const releaseDate = escapeHtml(data.release_date || "-");
    const expectedReturn = escapeHtml(data.expected_return || "-");
    const returnedByInput = document.getElementById("modalReturnedByInput");
    const returnStatusSelect = document.getElementById("modalReturnStatus");
    const accessoryStatusSelect = document.getElementById("modalAccessoryStatus");
    const accessoryNotesTextarea = document.getElementById("modalAccessoryNotes");
    const returnStatus = returnStatusSelect ? escapeHtml(returnStatusSelect.value || "RETURNED") : "RETURNED";
    const accessoryStatus = accessoryStatusSelect ? escapeHtml(accessoryStatusSelect.value || "GOOD") : "GOOD";
    const accessoryNotes = accessoryNotesTextarea ? escapeHtml(accessoryNotesTextarea.value || "") : "";
    const returnedBy = (returnStatus === "RETURNED" && returnedByInput) ? escapeHtml(returnedByInput.value || "-") : "-";
    const receivedByInput = document.getElementById("modalReceivedByInput");
    const receivedBy = receivedByInput ? escapeHtml(receivedByInput.value || "-") : "-";
    const totalItems = Number(data.total_items || items.length || 0);

    previewBody.innerHTML = ""
        + "<table class='slip-summary-table' data-no-pagination='1'>"
        + "<tr><td>Borrow Code</td><td><span class='borrow-code'>" + borrowCode + "</span></td></tr>"
        + "<tr><td>Total Items</td><td>" + totalItems + "</td></tr>"
        + "<tr><td>Borrower</td><td>" + borrower + "</td></tr>"
        + "<tr><td>Office</td><td>" + office + "</td></tr>"
        + "<tr><td>Purpose</td><td>" + purpose + "</td></tr>"
        + "<tr><td>Release Date</td><td>" + releaseDate + "</td></tr>"
        + "<tr><td>Expected Return</td><td>" + expectedReturn + "</td></tr>"
        + "<tr><td>Status</td><td><strong>" + returnStatus + "</strong></td></tr>"
        + "<tr><td>Accessory Status</td><td><strong>" + accessoryStatus.replace('_', ' ') + "</strong></td></tr>"
        + (accessoryNotes ? "<tr><td>Accessory Notes</td><td>" + accessoryNotes + "</td></tr>" : "")
        + (returnStatus === "RETURNED" ? "<tr><td>Returned By</td><td>" + returnedBy + "</td></tr>" : "")
        + "<tr><td>Received By</td><td>" + receivedBy + "</td></tr>"
        + "</table>"
        + "<h4 class='slip-items-title'>Borrowed Items (" + items.length + ")</h4>"
        + "<table class='slip-items-table' data-no-pagination='1'>"
        + "<thead><tr><th>#</th><th>Item</th><th>Serial Number</th><th>Accessories</th></tr></thead>"
        + "<tbody>" + (itemRows || "<tr><td colspan='4'>No item details found.</td></tr>") + "</tbody>"
        + "</table>";

    previewWrap.classList.remove("hidden");
}

function setReturnSubmitMode(verified){
    const submitBtn = document.getElementById("returnModalSubmitBtn");
    if (!submitBtn) {
        return;
    }
    submitBtn.textContent = verified ? "Confirm Return" : "Verify Code";
}

function highlightBorrowRows(scrollToMatch){
    const input = document.getElementById("borrowCodeSearch");
    const result = document.getElementById("borrowSearchResult");
    const rows = document.querySelectorAll(".return-record-row");
    const keyword = extractBorrowCode(input ? input.value : "");

    let matchCount = 0;
    let firstMatch = null;

    rows.forEach((row) => {
        const rowCode = normalizeCode(row.getAttribute("data-borrow-code"));
        row.classList.remove("return-match");

        if (keyword !== "" && rowCode.includes(keyword)) {
            row.classList.add("return-match");
            matchCount++;
            if (!firstMatch) {
                firstMatch = row;
            }
        }
    });

    if (result) {
        if (keyword === "") {
            result.textContent = "";
        } else if (matchCount === 0) {
            result.textContent = "No record found for code: " + keyword;
        } else {
            result.textContent = matchCount + " matching record(s) highlighted.";
        }
    }

    if (scrollToMatch && firstMatch) {
        firstMatch.scrollIntoView({ behavior: "smooth", block: "center" });
    }
}

function clearBorrowSearch(){
    const input = document.getElementById("borrowCodeSearch");
    if (input) {
        input.value = "";
    }
    highlightBorrowRows(false);
}

function openReturnModal(expectedCode, borrowerName){
    const modal = document.getElementById("returnCodeModal");
    const hiddenCode = document.getElementById("modalSelectedCode");
    const borrowInput = document.getElementById("modalBorrowCodeInput");
    const receivedByInput = document.getElementById("modalReceivedByInput");
    const recordLabel = document.getElementById("returnModalRecordLabel");

    if (!modal || !hiddenCode || !borrowInput) {
        return false;
    }

    const normalizedExpected = normalizeCode(expectedCode);
    currentReturnCode = normalizedExpected;
    verifiedReturnCode = "";
    hiddenCode.value = normalizedExpected;
    borrowInput.value = "";
    borrowInput.readOnly = false;
    returnCodeVerified = false;
    setReturnSubmitMode(false);

    // Reset return status to default
    const returnStatusSelect = document.getElementById("modalReturnStatus");
    if (returnStatusSelect) {
        returnStatusSelect.value = "RETURNED";
        toggleReturnedByField("RETURNED");
    }

    if (receivedByInput) {
        receivedByInput.value = "";
    }

    if (recordLabel) {
        recordLabel.textContent = "Borrower: " + borrowerName + " | Code: " + normalizedExpected;
    }
    renderReturnSlipPreview("");

    modal.classList.add("is-open");
    window.setTimeout(() => {
        borrowInput.focus();
    }, 20);

    return true;
}

function closeReturnModal(){
    const modal = document.getElementById("returnCodeModal");
    const form = document.getElementById("returnCodeForm");
    const recordLabel = document.getElementById("returnModalRecordLabel");

    if (modal) {
        modal.classList.remove("is-open");
    }
    if (form) {
        form.reset();
    }
    if (recordLabel) {
        recordLabel.textContent = "Enter borrowing code to continue.";
    }
    currentReturnCode = "";
    verifiedReturnCode = "";
    const borrowInput = document.getElementById("modalBorrowCodeInput");
    if (borrowInput) {
        borrowInput.readOnly = false;
    }
    returnCodeVerified = false;
    setReturnSubmitMode(false);
    renderReturnSlipPreview("");
}

function openReturnReceiptModal(){
    const modal = document.getElementById("returnReceiptModal");
    const frame = document.getElementById("returnReceiptFrame");
    if (modal) {
        modal.classList.add("is-open");
        document.body.style.overflow = "hidden";
    }
    if (frame && frame.dataset.src && frame.src === "about:blank") {
        frame.src = frame.dataset.src;
    }
}

function closeReturnReceiptModal(){
    const modal = document.getElementById("returnReceiptModal");
    if (modal) {
        modal.classList.remove("is-open");
        document.body.style.overflow = "";
    }
}

function submitReturnModal(event){
    if (event) {
        event.preventDefault();
    }

    const form = document.getElementById("returnCodeForm");
    const hiddenCode = document.getElementById("modalSelectedCode");
    const borrowInput = document.getElementById("modalBorrowCodeInput");

    let selectedCode = extractBorrowCode(hiddenCode ? hiddenCode.value : "");
    let enteredCode = extractBorrowCode(borrowInput ? borrowInput.value : "");

    if (selectedCode === "" && currentReturnCode !== "") {
        selectedCode = currentReturnCode;
    }
    if (enteredCode === "" && returnCodeVerified && verifiedReturnCode !== "") {
        enteredCode = verifiedReturnCode;
    }
    if (selectedCode === "" && enteredCode !== "") {
        selectedCode = enteredCode;
    }

    if (enteredCode === "") {
        showAppAlert("Borrowing code is required.", { title: "Missing Code" });
        return false;
    }

    if (hiddenCode) {
        hiddenCode.value = selectedCode;
    }
    if (borrowInput) {
        borrowInput.value = enteredCode;
    }

    if (selectedCode !== enteredCode) {
        verifiedReturnCode = "";
        returnCodeVerified = false;
        if (borrowInput) {
            borrowInput.readOnly = false;
        }
        setReturnSubmitMode(false);
        renderReturnSlipPreview("");
        showAppAlert("Borrowing code does not match this record.", { title: "Invalid Code" });
        return false;
    }

    if (!returnCodeVerified) {
        returnCodeVerified = true;
        verifiedReturnCode = enteredCode;
        if (borrowInput) {
            borrowInput.value = enteredCode;
            borrowInput.readOnly = true;
        }
        setReturnSubmitMode(true);
        renderReturnSlipPreview(selectedCode);
        showAppAlert("Code verified. Review the receipt, then click Confirm Return.", { title: "Code Verified" });
        return false;
    }

    if (form) {
        form.submit();
    }
    return false;
}

function clearLastReturnedReceipt(){
    window.location.href = "return_item.php?clear_last_return=1";
}

document.addEventListener("DOMContentLoaded", function(){
    const searchInput = document.getElementById("borrowCodeSearch");
    const modal = document.getElementById("returnCodeModal");
    const receiptModal = document.getElementById("returnReceiptModal");
    const returnButtons = document.querySelectorAll(".btn-return-record");
    const borrowInput = document.getElementById("modalBorrowCodeInput");
    const receivedByInput = document.getElementById("modalReceivedByInput");
    const shouldOpenReceiptModal = <?php echo $should_open_receipt_modal ? 'true' : 'false'; ?>;

    if (searchInput) {
        searchInput.addEventListener("keydown", function(event){
            if (event.key === "Enter") {
                event.preventDefault();
                highlightBorrowRows(true);
            }
        });
    }

    if (borrowInput) {
        borrowInput.addEventListener("input", function(){
            returnCodeVerified = false;
            verifiedReturnCode = "";
            setReturnSubmitMode(false);
            renderReturnSlipPreview("");
        });
    }

    if (receivedByInput) {
        receivedByInput.addEventListener("input", function(){
            if (returnCodeVerified) {
                renderReturnSlipPreview(currentReturnCode);
            }
        });
    }

    if (modal) {
        modal.addEventListener("click", function(event){
            if (event.target === modal) {
                closeReturnModal();
            }
        });
    }

    if (receiptModal) {
        receiptModal.addEventListener("click", function(event){
            if (event.target === receiptModal) {
                closeReturnReceiptModal();
            }
        });
    }

    document.addEventListener("keydown", function(event){
        if (event.key === "Escape" && receiptModal && receiptModal.classList.contains("is-open")) {
            closeReturnReceiptModal();
        }
    });

    if (returnButtons.length > 0) {
        returnButtons.forEach((button) => {
            button.addEventListener("click", function(){
                const expectedCode = button.getAttribute("data-borrow-code") || "";
                const borrowerName = button.getAttribute("data-borrower-name") || "-";
                openReturnModal(expectedCode, borrowerName);
            });
        });
    }

    if (shouldOpenReceiptModal) {
        openReturnReceiptModal();
    }
});
</script>

<?php include('../includes/footer.php'); ?>
