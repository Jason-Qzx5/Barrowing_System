<?php include('../includes/header.php'); ?>

<?php
$feedback_message = '';
$feedback_type = '';
$borrow_post_submitted = ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['confirm_borrow']));

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_equipment'])) {
    $item_name = $conn->real_escape_string(trim($_POST['item_name'] ?? ''));
    $asset_code = $conn->real_escape_string(trim($_POST['asset_code'] ?? ''));
    $brand = $conn->real_escape_string(trim($_POST['brand'] ?? ''));
    $accessories = $conn->real_escape_string(trim($_POST['accessories'] ?? ''));

    $sql = "INSERT INTO items (item_name, asset_code, brand, accessories)
            VALUES ('$item_name', '$asset_code', '$brand', '$accessories')";

    if ($conn->query($sql) === TRUE) {
        $feedback_message = 'Equipment added successfully.';
        $feedback_type = 'alert-success';
    } else {
        $feedback_message = 'Error: ' . $conn->error;
        $feedback_type = 'alert-danger';
    }
}

$result_check = $conn->query("SELECT COUNT(*) AS count FROM items");
$count_row = $result_check ? $result_check->fetch_assoc() : ['count' => 0];
$result = $conn->query("SELECT * FROM items ORDER BY id DESC");
$has_records = $result && $result->num_rows > 0;

$borrow_code = '';
$borrower = '';
$office = '';
$purpose = '';
$release = '';
$expected = '';
$slip_items = [];
$skipped_items = [];
$cancel_feedback = '';
$cancel_feedback_type = '';
$slip_url = '';
$slip_download_url = '';
$slip_view_url = '';
$slip_qr_image_src = '';

function is_private_ipv4_address($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    if (strpos($ip, '10.') === 0) {
        return true;
    }
    if (strpos($ip, '192.168.') === 0) {
        return true;
    }
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) {
        return true;
    }

    return false;
}

function is_virtual_adapter_ipv4($ip) {
    $virtual_prefixes = array(
        '192.168.56.',   // VirtualBox host-only (common)
        '192.168.57.',
        '192.168.122.',  // libvirt
        '10.0.2.',       // VirtualBox NAT
        '172.17.',       // Docker bridge ranges
        '172.18.',
        '172.19.',
        '172.20.',
        '172.21.',
        '172.22.',
        '172.23.',
        '172.24.',
        '172.25.',
        '172.26.',
        '172.27.',
        '172.28.',
        '172.29.',
        '172.30.',
        '172.31.'
    );

    foreach ($virtual_prefixes as $prefix) {
        if (strpos($ip, $prefix) === 0) {
            return true;
        }
    }

    return false;
}

function detect_best_lan_ipv4() {
    $candidates = array();
    $server_addr = trim((string) ($_SERVER['SERVER_ADDR'] ?? ''));
    if ($server_addr !== '') {
        $candidates[] = $server_addr;
    }

    $hostname = gethostname();
    if ($hostname !== false && $hostname !== '') {
        $host_ips = @gethostbynamel($hostname);
        if (is_array($host_ips)) {
            $candidates = array_merge($candidates, $host_ips);
        }
    }

    $candidates = array_values(array_unique($candidates));
    $fallback_private = '';

    foreach ($candidates as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            continue;
        }
        if ($ip === '127.0.0.1' || strpos($ip, '169.254.') === 0) {
            continue;
        }
        if (!is_private_ipv4_address($ip)) {
            continue;
        }

        if (!is_virtual_adapter_ipv4($ip)) {
            return $ip;
        }

        if ($fallback_private === '') {
            $fallback_private = $ip;
        }
    }

    return $fallback_private;
}

if (isset($_GET['finalize'])) {
    unset($_SESSION['pending_borrow_code']);
}

if (isset($_GET['cancel_code'])) {
    $cancel_code = trim($_GET['cancel_code']);

    if ($cancel_code !== '') {
        $safe_cancel_code = $conn->real_escape_string($cancel_code);
        $check_cancel = $conn->query("SELECT id FROM borrow_records
                                      WHERE borrow_code='{$safe_cancel_code}'
                                      AND status='BORROWED'");

        if ($check_cancel && $check_cancel->num_rows > 0) {
            $conn->query("DELETE FROM borrow_records
                          WHERE borrow_code='{$safe_cancel_code}'
                          AND status='BORROWED'");

            $removed_rows = (int) $conn->affected_rows;
            if ($removed_rows > 0) {
                $cancel_feedback = "Borrow transaction cancelled successfully. {$removed_rows} item(s) were removed.";
                $cancel_feedback_type = 'alert-success';
            } else {
                $cancel_feedback = "Cancel failed. No borrowed item was removed.";
                $cancel_feedback_type = 'alert-danger';
            }
        } else {
            $cancel_feedback = "Cancel failed. No active borrowed item found for this code.";
            $cancel_feedback_type = 'alert-danger';
        }
    } else {
        $cancel_feedback = 'Invalid cancel request.';
        $cancel_feedback_type = 'alert-danger';
    }

    unset($_SESSION['pending_borrow_code']);
}

if ($borrow_post_submitted) {
    $borrower = trim($_POST['borrower'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $release = trim($_POST['release'] ?? '');
    $expected = trim($_POST['expected'] ?? '');

    $safe_borrower = $conn->real_escape_string($borrower);
    $safe_office = $conn->real_escape_string($office);
    $safe_purpose = $conn->real_escape_string($purpose);
    $safe_release = $conn->real_escape_string($release);
    $safe_expected = $conn->real_escape_string($expected);

    if (!empty($_POST['selected_item']) && is_array($_POST['selected_item'])) {
        $borrow_code = 'DA-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
        $safe_borrow_code = $conn->real_escape_string($borrow_code);

        foreach ($_POST['selected_item'] as $index => $item_id_raw) {
            $item_id = (int) $item_id_raw;
            if ($item_id <= 0) {
                continue;
            }

            $query = $conn->query("SELECT * FROM items WHERE id='{$item_id}'");
            if (!$query || $query->num_rows === 0) {
                continue;
            }

            $item = $query->fetch_assoc();
            $manual_item = trim($item['item_name'] ?? '');
            $asset_code = trim($item['asset_code'] ?? '');

            $accessory_list = [];
            if (isset($_POST['accessories'][$index]) && is_array($_POST['accessories'][$index])) {
                foreach ($_POST['accessories'][$index] as $accessory) {
                    $clean_accessory = trim($accessory);
                    if ($clean_accessory !== '') {
                        $accessory_list[] = $clean_accessory;
                    }
                }
            }
            $accessories = !empty($accessory_list) ? implode(' / ', $accessory_list) : '-';

            $safe_asset_code = $conn->real_escape_string($asset_code);
            $check = $conn->query("SELECT id FROM borrow_records
                                   WHERE item_id='{$item_id}'
                                   AND status='BORROWED'");

            if ($check && $check->num_rows === 0) {
                $safe_manual_item = $conn->real_escape_string($manual_item);
                $safe_accessories = $conn->real_escape_string($accessories);

                $insert_sql = "INSERT INTO borrow_records
                (item_id,borrow_code,manual_item,asset_code,accessories,
                borrower,office,purpose,release_date,expected_return,status)
                VALUES
                ('{$item_id}','{$safe_borrow_code}','{$safe_manual_item}','{$safe_asset_code}','{$safe_accessories}',
                '{$safe_borrower}','{$safe_office}','{$safe_purpose}','{$safe_release}','{$safe_expected}','BORROWED')";

                if ($conn->query($insert_sql)) {
                    $slip_items[] = [
                        'item_name' => $manual_item,
                        'asset_code' => $asset_code,
                        'accessories' => $accessories
                    ];
                } else {
                    $skipped_items[] = $manual_item . " (save failed)";
                }
            } else {
                $skipped_items[] = $manual_item . " (already borrowed)";
            }
        }

        if (!empty($slip_items)) {
            $_SESSION['pending_borrow_code'] = $borrow_code;

            $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $script_dir = rtrim($script_dir, '/');
            $configured_base_url = trim((string) ($public_base_url ?? ''));

            if ($configured_base_url !== '') {
                // Optional override (recommended for LAN/mobile scanning):
                // e.g. $public_base_url = "http://192.168.1.20/Borrowing_system/pages";
                $base_slip_url = rtrim($configured_base_url, '/');
            } else {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

                $host_name = $host;
                $host_port = '';
                if (preg_match('/^\[(.*)\]:(\d+)$/', $host, $matches)) {
                    $host_name = $matches[1];
                    $host_port = ':' . $matches[2];
                } elseif (substr_count($host, ':') === 1 && preg_match('/^([^:]+):(\d+)$/', $host, $matches)) {
                    $host_name = $matches[1];
                    $host_port = ':' . $matches[2];
                }

                if ($host_name === 'localhost' || $host_name === '127.0.0.1' || $host_name === '::1') {
                    $lan_ip = detect_best_lan_ipv4();
                    if ($lan_ip !== '') {
                        $host = $lan_ip . $host_port;
                    }
                }

                $base_slip_url = $scheme . '://' . $host . $script_dir;
            }

            $encoded_code = rawurlencode($borrow_code);
            $slip_url = $base_slip_url . '/borrow_slip_qr.php?code=' . $encoded_code;
            $slip_download_url = $base_slip_url . '/borrow_slip_pdf.php?code=' . $encoded_code;
            $slip_view_url = $slip_url;

            $barcode_lib = __DIR__ . '/../tcpdf/tcpdf_barcodes_2d.php';
            if (is_file($barcode_lib)) {
                require_once($barcode_lib);

                try {
                    $qr = new TCPDF2DBarcode($slip_url, 'QRCODE,H');
                    $png_data = $qr->getBarcodePngData(4, 4, array(0, 0, 0));

                    if (is_string($png_data) && $png_data !== '') {
                        $slip_qr_image_src = 'data:image/png;base64,' . base64_encode($png_data);
                    } elseif (is_object($png_data) && class_exists('Imagick') && ($png_data instanceof Imagick)) {
                        $png_data->setImageFormat('png');
                        $slip_qr_image_src = 'data:image/png;base64,' . base64_encode($png_data->getImageBlob());
                    } else {
                        $svg_code = $qr->getBarcodeSVGcode(3, 3, 'black');
                        if (is_string($svg_code) && $svg_code !== '') {
                            $slip_qr_image_src = 'data:image/svg+xml;base64,' . base64_encode($svg_code);
                        }
                    }
                } catch (Exception $e) {
                    $slip_qr_image_src = '';
                }
            }
        }
    }
}
?>
        <h2 class="page-title">Add Equipment</h2>
<div class="equipment-page">
    <section class="card equipment-card" id="addEquipmentCard">
        <?php if ($feedback_message !== ''): ?>
            <div class="<?php echo $feedback_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>

        <form method="POST" id="addEquipmentForm" class="equipment-form">
            <div class="equipment-form-grid">
                <div class="form-group">
                    <label class="label-with-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="4" y="5" width="16" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"></rect>
                            <line x1="8" y1="10" x2="16" y2="10" stroke="currentColor" stroke-width="2"></line>
                        </svg>
                        Item Name
                    </label>
                    <input type="text" name="item_name" required>
                </div>
                <div class="form-group">
                    <label class="label-with-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 7h3v3H8zm5 0h3v3h-3zM8 12h3v3H8zm5 0h3v3h-3z" fill="currentColor"></path>
                            <rect x="4" y="4" width="16" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="2"></rect>
                        </svg>
                        Serial Number
                    </label>
                    <input type="text" name="asset_code">
                </div>
                <div class="form-group">
                    <label class="label-with-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 20l6-2 10-10-4-4L6 14l-2 6z" fill="none" stroke="currentColor" stroke-width="2"></path>
                        </svg>
                        Brand
                    </label>
                    <input type="text" name="brand">
                </div>
                <div class="form-group">
                    <label class="label-with-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"></rect>
                            <line x1="7" y1="10" x2="17" y2="10" stroke="currentColor" stroke-width="2"></line>
                            <line x1="7" y1="14" x2="13" y2="14" stroke="currentColor" stroke-width="2"></line>
                        </svg>
                        Accessories
                    </label>
                    <input type="text" name="accessories">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_equipment" class="toolbar-btn submit-equipment-btn">Add Equipment</button>
            </div>
        </form>
    </section>
 </div>


    <div class="borrow-wrapper" id="borrowSection">
        <h2 class="page-title">Borrow Equipment</h2>

        <?php if ($cancel_feedback !== ''): ?>
            <div class="<?php echo $cancel_feedback_type; ?>">
                <?php echo htmlspecialchars($cancel_feedback); ?>
            </div>
        <?php endif; ?>

        <?php if ($borrow_post_submitted && empty($slip_items)): ?>
            <div class="alert-danger">No items were borrowed. Selected item(s) may already be borrowed or invalid.</div>
        <?php endif; ?>

        <?php if (!empty($skipped_items)): ?>
            <div class="alert-danger">
                Skipped item(s): <?php echo htmlspecialchars(implode(', ', $skipped_items)); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($slip_items)): ?>
        <div id="borrowModal" class="modal is-open">
            <div class="modal-content borrow-slip-modal">
                <div class="printable-slip">
                    <?php $total_borrowed_items = count($slip_items); ?>
                    <div class="slip-qr-layout">
                        <div class="slip-qr-main">
                            <h2>Borrow Slip</h2>
                            <p class="slip-qr-note">Scan QR code to open receipt page and download PDF receipt.</p>
                        </div>
                        <div class="slip-qr-side">
                            <?php if ($slip_qr_image_src !== ''): ?>
                                <img src="<?php echo htmlspecialchars($slip_qr_image_src); ?>" alt="Borrow slip QR code" class="slip-qr-image">
                            <?php else: ?>
                                <div class="slip-qr-unavailable">QR unavailable</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($slip_url !== '' || $slip_download_url !== ''): ?>
                        <p class="slip-link-row">
                            <?php if ($slip_download_url !== ''): ?>
                                <a href="<?php echo htmlspecialchars($slip_download_url); ?>" target="_blank" rel="noopener">Download PDF receipt</a>
                            <?php endif; ?>
                            <?php if ($slip_url !== ''): ?>
                                <?php if ($slip_download_url !== ''): ?> | <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($slip_url); ?>" target="_blank" rel="noopener">Open receipt page</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <table class="slip-summary-table">
                        <tr><td>Borrow Code</td><td><span class="borrow-code"><?php echo htmlspecialchars($borrow_code); ?></span></td></tr>
                        <tr><td>Total Items</td><td><?php echo $total_borrowed_items; ?></td></tr>
                        <tr><td>Borrower</td><td><?php echo htmlspecialchars($borrower); ?></td></tr>
                        <tr><td>Office</td><td><?php echo htmlspecialchars($office); ?></td></tr>
                        <tr><td>Purpose</td><td><?php echo htmlspecialchars($purpose); ?></td></tr>
                        <tr><td>Release Date</td><td><?php echo htmlspecialchars($release); ?></td></tr>
                        <tr><td>Expected Return</td><td><?php echo htmlspecialchars($expected); ?></td></tr>
                    </table>

                    <h3 class="slip-items-title">Borrowed Items (<?php echo $total_borrowed_items; ?>)</h3>
                    <table class="slip-items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Serial Number</th>
                                <th>Accessories</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slip_items as $key => $slip_item): ?>
                                <tr class="slip-item-row">
                                    <td><?php echo $key + 1; ?></td>
                                    <td><?php echo htmlspecialchars($slip_item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($slip_item['asset_code']); ?></td>
                                    <td><?php echo htmlspecialchars($slip_item['accessories']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (!empty($skipped_items)): ?>
                        <p class="slip-warning">
                            Skipped item(s): <?php echo htmlspecialchars(implode(', ', $skipped_items)); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="printCode()">Print Slip</button>
                    <button type="button" class="btn-secondary" onclick="closeBorrowModal()">Done</button>
                    <button type="button" class="btn-danger" onclick="cancelBorrowTransaction('<?php echo htmlspecialchars($borrow_code, ENT_QUOTES); ?>')">Cancel Borrow</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div id="itemContainer">
                <div class="itemCard" data-item-index="0">
                    <div class="itemLeft">
                        <div class="selectArea">
                            <label>Select Equipment</label>
                            <div class="equipment-search">
                                <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"></circle>
                                    <line x1="16.65" y1="16.65" x2="21" y2="21" stroke="currentColor" stroke-width="2"></line>
                                </svg>
                                <input type="text" placeholder="Search equipment..." oninput="filterEquipment(this)">
                            </div>
                            <select name="selected_item[]" required>
                                <option value="">-- Select Equipment --</option>
                                <?php
                                $available_result = $conn->query("
                                SELECT * FROM items
                                WHERE id NOT IN(
                                    SELECT item_id FROM borrow_records WHERE status='BORROWED'
                                )
                                ORDER BY item_name ASC");

                                while ($row = $available_result->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>{$row['item_name']} - {$row['asset_code']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="accessories-section">
                        <label>Accessories</label>
                        <div class="accessories-box">
                            <input type="text" name="accessories[0][]" placeholder="Accessory">
                        </div>
                        <div class="acc-actions">
                            <button type="button" class="add-acc" onclick="addAccessory(this)">Add Accessory</button>
                            <button type="button" class="btn-secondary cancel-acc" onclick="cancelAccessory(this)">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="add-item" onclick="addItem()">Add More Item</button>

            <div class="borrow-details-card">
                <div class="borrow-info">
                    <div>
                        <label>Borrower Name</label>
                        <input type="text" name="borrower" required>
                    </div>
                    <div>
                        <label>Office</label>
                        <select name="office" required>
                            <option value="">-- Select Office --</option>
                            <?php
                            $office_query = $conn->query("SELECT * FROM offices ORDER BY office_name ASC");
                            while ($office = $office_query->fetch_assoc()) {
                                echo "<option>{$office['office_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="full">
                        <label>Purpose</label>
                        <textarea name="purpose" required></textarea>
                    </div>
                    <div>
                        <label>Release Date</label>
                        <input type="date" name="release" required>
                    </div>
                    <div>
                        <label>Expected Return</label>
                        <input type="date" name="expected" required>
                    </div>
                </div>
                <button type="submit" name="confirm_borrow" class="confirm-btn">Confirm Borrow</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentRecordFilter = "all";

function focusAddEquipment() {
    const form = document.getElementById("addEquipmentForm");
    if (!form) { return; }

    form.reset();
    form.scrollIntoView({ behavior: "smooth", block: "start" });

    const firstInput = form.querySelector("input[name='item_name']");
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 160);
    }
}

function syncRecordSearch(value) {
    document.querySelectorAll(".records-search-input").forEach((input) => {
        if (input.value !== value) {
            input.value = value;
        }
    });
    applyRecordFilters();
}

function setRecordFilter(value) {
    currentRecordFilter = value || "all";
    const topFilter = document.getElementById("recordFilterTop");
    if (topFilter && topFilter.value !== currentRecordFilter) {
        topFilter.value = currentRecordFilter;
    }
    applyRecordFilters();
}

function applyRecordFilters() {
    const masterSearchInput = document.querySelector(".records-search-input");
    const query = masterSearchInput ? masterSearchInput.value.trim().toLowerCase() : "";
    const rows = document.querySelectorAll("#equipmentRecordBody tr[data-status]");
    let visibleRows = 0;

    rows.forEach((row) => {
        const rowStatus = row.getAttribute("data-status");
        const rowText = row.innerText.toLowerCase();
        const matchesQuery = rowText.includes(query);
        const matchesStatus = currentRecordFilter === "all" || rowStatus === currentRecordFilter;
        const shouldShow = matchesQuery && matchesStatus;

        row.style.display = shouldShow ? "" : "none";
        if (shouldShow) {
            visibleRows++;
        }
    });

    const emptyRow = document.getElementById("recordEmptyState");
    if (emptyRow) {
        emptyRow.style.display = visibleRows === 0 ? "" : "none";
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    const initialFilter = (params.get("filter") || "all").toLowerCase();
    const allowedFilters = ["all", "available", "borrowed"];

    if (allowedFilters.includes(initialFilter)) {
        setRecordFilter(initialFilter);
    } else {
        applyRecordFilters();
    }
});
</script>

<script>
let itemIndex = 1;

function addItem() {
    let container = document.getElementById("itemContainer");
    let clone = document.querySelector(".itemCard").cloneNode(true);

    clone.setAttribute("data-item-index", itemIndex);
    clone.querySelector("select").selectedIndex = 0;
    clone.querySelectorAll("select option").forEach((opt) => {
        opt.hidden = false;
    });

    let searchInput = clone.querySelector(".equipment-search input");
    if (searchInput) {
        searchInput.value = "";
    }

    let box = clone.querySelector(".accessories-box");
    box.innerHTML = `<input type="text" name="accessories[${itemIndex}][]" placeholder="Accessory">`;
    container.appendChild(clone);
    itemIndex++;
}

function addAccessory(btn) {
    let card = btn.closest(".itemCard");
    let itemId = card ? card.getAttribute("data-item-index") : 0;
    let box = card.querySelector(".accessories-box");
    let input = document.createElement("input");
    input.type = "text";
    input.name = "accessories[" + itemId + "][]";
    input.placeholder = "Accessory";
    box.appendChild(input);
}

function cancelAccessory(btn) {
    let card = btn.closest(".itemCard");
    if (!card) { return; }

    let box = card.querySelector(".accessories-box");
    let inputs = box.querySelectorAll("input");

    if (inputs.length > 1) {
        inputs[inputs.length - 1].remove();
        return;
    }

    if (inputs.length === 1) {
        inputs[0].value = "";
    }
}

function filterEquipment(input) {
    let card = input.closest(".itemCard");
    if (!card) { return; }

    let select = card.querySelector("select[name='selected_item[]']");
    if (!select) { return; }

    let query = input.value.trim().toLowerCase();
    let options = Array.from(select.options);

    options.forEach((opt, idx) => {
        if (idx === 0) {
            opt.hidden = false;
            return;
        }
        opt.hidden = !opt.text.toLowerCase().includes(query);
    });

    let selected = select.options[select.selectedIndex];
    if (selected && selected.hidden) {
        select.selectedIndex = 0;
    }
}

function closeBorrowModal() {
    const modal = document.getElementById("borrowModal");
    if (modal) {
        modal.classList.remove("is-open");
        modal.style.display = "none";
        modal.remove();
    }

    window.setTimeout(() => {
        window.location.replace("borrow_item.php?finalize=1");
    }, 40);
}

function closeModal() {
    closeBorrowModal();
}

async function cancelBorrowTransaction(code) {
    if (!code) { return; }

    const confirmed = await showAppConfirm(
        "Cancel this borrow transaction? This will remove all borrowed items under this code.",
        { title: "Cancel Borrow", okLabel: "Yes, Cancel" }
    );

    if (!confirmed) {
        return;
    }

    window.location.replace("borrow_item.php?cancel_code=" + encodeURIComponent(code) + "#borrowSection");
}

function printCode() {
    const modal = document.getElementById("borrowModal");
    if (!modal) { return; }
    const printableSlip = modal.querySelector(".printable-slip");
    if (!printableSlip) { return; }

    const win = window.open("", "_blank");
    if (!win) { return; }

    const printedAt = new Date().toLocaleString();
    win.document.write(`
    <html>
    <head>
        <title>Borrow Slip</title>
        <style>
            @page{size:A5 portrait;margin:5mm}
            *{box-sizing:border-box}
            html,body{width:100%;height:100%;margin:0;padding:0;background:#fff;color:#111827;font-family:Arial,sans-serif}
            .receipt-sheet{
                width:138mm;
                min-height:200mm;
                margin:0 auto;
                display:flex;
                flex-direction:column;
                gap:2mm;
            }
            .printable-slip{
                width:100%;
                border:1px solid #4b5563;
                border-radius:0;
                padding:3mm;
                display:flex;
                flex-direction:column;
            }
            .receipt-head{text-align:center;margin:0 0 1mm}
            .receipt-head .dept{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#4b5563}
            .receipt-head .title{font-size:20px;font-weight:700;line-height:1.1;margin-top:1px}
            .receipt-head .time{font-size:11px;color:#4b5563;margin-top:2px}
            .slip-qr-layout{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:3px}
            .slip-qr-main{flex:1 1 auto;min-width:0}
            .printable-slip h2{margin:0;font-size:20px;line-height:1.1}
            .slip-qr-note{margin:4px 0 0;font-size:11px;color:#4b5563}
            .slip-qr-side{flex:none;width:90px;text-align:center}
            .slip-qr-image{display:block;width:86px;height:86px;border:1px solid #9ca3af;padding:2px;background:#fff}
            .slip-link-row{margin:4px 0 2px;font-size:11px}
            .slip-link-row a{color:#1d4ed8;text-decoration:none}
            .slip-link-row a:hover{text-decoration:underline}
            h3{margin:7px 0 4px;font-size:15px}
            table{width:100%;border-collapse:collapse;margin-top:3px;table-layout:fixed}
            th,td{
                border:1px solid #9ca3af;
                padding:4px 5px;
                text-align:left;
                vertical-align:top;
                word-break:break-word;
                font-size:12px;
                line-height:1.2;
            }
            th{
                background:#e9eef5;
                text-transform:uppercase;
                letter-spacing:.04em;
                font-size:11px;
            }
            .slip-summary-table td:first-child{font-weight:700;width:36%;background:#f5f8fc}
            .slip-items-table td:first-child{width:30px;text-align:center;font-weight:700}
            .slip-warning{font-size:12px;padding:6px 8px;margin-top:6px}
            .printable-slip, table, tr, th, td{page-break-inside:avoid}
        </style>
    </head>
    <body>
        <div class="receipt-sheet">
            <div class="receipt-head">
                <div class="dept">Department of Agriculture</div>
                <div class="title">Borrow Slip</div>
                <div class="time">${printedAt}</div>
            </div>
            ${printableSlip.outerHTML}
        </div>
    </body>
    </html>
    `);
    win.document.close();
    win.focus();
    win.print();
}

(function cleanCancelCodeParamFromUrl() {
    const url = new URL(window.location.href);
    if (!url.searchParams.has("cancel_code")) {
        return;
    }

    url.searchParams.delete("cancel_code");
    const nextQuery = url.searchParams.toString();
    const nextUrl = url.pathname + (nextQuery ? "?" + nextQuery : "") + (url.hash || "");
    window.history.replaceState({}, document.title, nextUrl);
})();
</script>

<?php include('../includes/footer.php'); ?>
