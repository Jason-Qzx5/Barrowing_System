<?php include('../includes/header.php'); ?>

<?php
$feedback_message = '';
$feedback_type = '';
$logged_in_receiver = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : '';
$logged_in_receiver_display = $logged_in_receiver !== '' ? $logged_in_receiver : 'Unknown User';

function normalize_borrow_code_input($raw_value) {
    return strtoupper(trim((string) $raw_value));
}

if (isset($_GET['clear_last_return']) && $_GET['clear_last_return'] === '1') {
    unset($_SESSION['last_returned_code']);
}

if (isset($_POST['return_from_table'])) {
    $selected_code = normalize_borrow_code_input($_POST['selected_code'] ?? '');
    $entered_code = normalize_borrow_code_input($_POST['borrow_code_input'] ?? '');

    if ($selected_code === '' && $entered_code !== '') {
        $selected_code = $entered_code;
    }

    if ($selected_code === '' || $entered_code === '') {
        $feedback_message = 'Borrowing code is required.';
        $feedback_type = 'alert-danger';
    } elseif ($selected_code !== $entered_code) {
        $feedback_message = 'Borrowing code does not match the selected record.';
        $feedback_type = 'alert-danger';
    } else {
        $safe_code = $conn->real_escape_string($selected_code);
        $check = $conn->query("SELECT id FROM borrow_records
                               WHERE borrow_code='{$safe_code}'
                               AND status='BORROWED'");

        if ($check && $check->num_rows > 0) {
            $safe_received_by = $conn->real_escape_string($logged_in_receiver);

            $conn->query("UPDATE borrow_records
                          SET status='RETURNED',
                              received_by='{$safe_received_by}',
                              return_date=CURDATE()
                          WHERE borrow_code='{$safe_code}'
                          AND status='BORROWED'");

            $returned_items = (int) $conn->affected_rows;
            if ($returned_items > 0) {
                $feedback_message = "{$returned_items} item(s) returned successfully. Received by {$logged_in_receiver_display}.";
                $feedback_type = 'alert-success';
                $_SESSION['last_returned_code'] = $selected_code;
            } else {
                $feedback_message = 'No borrowed items were updated.';
                $feedback_type = 'alert-danger';
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
                'received_by' => $logged_in_receiver_display,
                'total_items' => (int) ($row['total_items'] ?? 0),
                'items' => $preview_items
            );
        }
    }
}
?>

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
            <div style="background: #f8fbff; border: 2px solid #0ea5e9; border-radius: 12px; padding: 20px; margin-top: 16px;">
                <h3 style="margin-top: 0; color: #0369a1;">Return Receipt</h3>
                <p style="margin: 2px 0 10px; color: #0f172a; font-size: 13px;">
                    Last returned record is shown below for quick checking.
                </p>
                    
                <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
                    <tr>
                        <td style="padding: 10px; font-weight: bold; width: 30%; background: #ecf2f9; border: 1px solid #d5deea;">Borrow Code</td>
                        <td style="padding: 10px; border: 1px solid #d5deea; color: #166534; font-weight: bold;">
                            <?php echo htmlspecialchars((string) $slip_summary['borrow_code']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Total Items</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo count($slip_records); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Borrower</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo htmlspecialchars((string) $slip_summary['borrower']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Office</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo htmlspecialchars((string) $slip_summary['office']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Purpose</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo htmlspecialchars((string) $slip_summary['purpose']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Release Date</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo htmlspecialchars((string) $slip_summary['release_date']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Expected Return</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo htmlspecialchars((string) $slip_summary['expected_return']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Return Date</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo htmlspecialchars((string) $slip_summary['return_date']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; background: #ecf2f9; border: 1px solid #d5deea;">Received By</td>
                        <td style="padding: 10px; border: 1px solid #d5deea;">
                            <?php echo htmlspecialchars(trim((string) ($slip_summary['received_by'] ?? '')) !== '' ? (string) $slip_summary['received_by'] : '-'); ?>
                        </td>
                    </tr>
                </table>

                <h4 style="margin: 16px 0 8px 0;">Returned Items (<?php echo count($slip_records); ?>)</h4>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
                    <thead>
                        <tr>
                            <th style="padding: 10px; background: #ecf2f9; border: 1px solid #d5deea; text-align: left; font-weight: bold; text-transform: uppercase; font-size: 12px;">#</th>
                            <th style="padding: 10px; background: #ecf2f9; border: 1px solid #d5deea; text-align: left; font-weight: bold; text-transform: uppercase; font-size: 12px;">Item Name</th>
                            <th style="padding: 10px; background: #ecf2f9; border: 1px solid #d5deea; text-align: left; font-weight: bold; text-transform: uppercase; font-size: 12px;">Serial Number</th>
                            <th style="padding: 10px; background: #ecf2f9; border: 1px solid #d5deea; text-align: left; font-weight: bold; text-transform: uppercase; font-size: 12px;">Accessories</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slip_records as $index => $record): ?>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #d5deea;"><?php echo $index + 1; ?></td>
                                <td style="padding: 10px; border: 1px solid #d5deea;">
                                    <?php echo htmlspecialchars((string) $record['manual_item']); ?>
                                </td>
                                <td style="padding: 10px; border: 1px solid #d5deea;">
                                    <?php echo htmlspecialchars((string) $record['asset_code']); ?>
                                </td>
                                <td style="padding: 10px; border: 1px solid #d5deea;">
                                    <?php echo htmlspecialchars(trim((string) ($record['accessories'] ?? '')) !== '' ? (string) $record['accessories'] : '-'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn-secondary" onclick="printReturnReceipt()">
                        Print Receipt
                    </button>
                    <button type="button" class="btn-secondary" onclick="clearLastReturnedReceipt()">
                        Clear Preview
                    </button>
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
            <table class="equipment-table return-records-table js-next-pagination">
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

            <label for="modalReceivedByInput">Received By</label>
            <input
                type="text"
                id="modalReceivedByInput"
                class="readonly-input"
                value="<?php echo htmlspecialchars($logged_in_receiver_display); ?>"
                readonly
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
const returnReceiverName = <?php echo json_encode($logged_in_receiver_display, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let returnCodeVerified = false;
let currentReturnCode = "";
let verifiedReturnCode = "";

function normalizeCode(value){
    return (value || "").trim().toUpperCase();
}

function extractBorrowCode(value){
    return normalizeCode(value);
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
    const receivedBy = escapeHtml(data.received_by || returnReceiverName || "-");
    const totalItems = Number(data.total_items || items.length || 0);

    previewBody.innerHTML = ""
        + "<table class='slip-summary-table'>"
        + "<tr><td>Borrow Code</td><td><span class='borrow-code'>" + borrowCode + "</span></td></tr>"
        + "<tr><td>Total Items</td><td>" + totalItems + "</td></tr>"
        + "<tr><td>Borrower</td><td>" + borrower + "</td></tr>"
        + "<tr><td>Office</td><td>" + office + "</td></tr>"
        + "<tr><td>Purpose</td><td>" + purpose + "</td></tr>"
        + "<tr><td>Release Date</td><td>" + releaseDate + "</td></tr>"
        + "<tr><td>Expected Return</td><td>" + expectedReturn + "</td></tr>"
        + "<tr><td>Received By</td><td>" + receivedBy + "</td></tr>"
        + "</table>"
        + "<h4 class='slip-items-title'>Borrowed Items (" + items.length + ")</h4>"
        + "<table class='slip-items-table'>"
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

function printReturnReceipt(){
    window.print();
}

function clearLastReturnedReceipt(){
    window.location.href = "return_item.php?clear_last_return=1";
}

document.addEventListener("DOMContentLoaded", function(){
    const searchInput = document.getElementById("borrowCodeSearch");
    const modal = document.getElementById("returnCodeModal");
    const returnButtons = document.querySelectorAll(".btn-return-record");
    const borrowInput = document.getElementById("modalBorrowCodeInput");

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

    if (modal) {
        modal.addEventListener("click", function(event){
            if (event.target === modal) {
                closeReturnModal();
            }
        });
    }

    if (returnButtons.length > 0) {
        returnButtons.forEach((button) => {
            button.addEventListener("click", function(){
                const expectedCode = button.getAttribute("data-borrow-code") || "";
                const borrowerName = button.getAttribute("data-borrower-name") || "-";
                openReturnModal(expectedCode, borrowerName);
            });
        });
    }
});
</script>

<?php include('../includes/footer.php'); ?>
