<?php include('../includes/header.php'); ?>

<?php
$feedback_message = '';
$feedback_type = '';
$entered_code = '';
$returned_records = array();
$returned_summary = null;

function normalize_borrow_code_input($raw_value) {
    $value = trim((string) $raw_value);
    if ($value === '') {
        return '';
    }

    $query = '';
    $parsed_url = parse_url($value);
    if (is_array($parsed_url) && isset($parsed_url['query'])) {
        $query = (string) $parsed_url['query'];
    } else {
        $query_pos = strpos($value, '?');
        if ($query_pos !== false) {
            $query = (string) substr($value, $query_pos + 1);
        }
    }

    if ($query !== '') {
        $query_params = array();
        parse_str($query, $query_params);
        foreach ($query_params as $key => $param_value) {
            if (strcasecmp((string) $key, 'code') === 0) {
                return strtoupper(trim((string) $param_value));
            }
        }
    }

    if (preg_match('/(?:^|[?&])code=([^&#\s]+)/i', $value, $matches)) {
        return strtoupper(trim(rawurldecode((string) $matches[1])));
    }

    return strtoupper($value);
}

if (isset($_POST['return_code'])) {
    $entered_code = normalize_borrow_code_input($_POST['borrow_code'] ?? '');
    $returned_by = trim($_POST['returned_by'] ?? '');
    $received_by = trim($_POST['received_by'] ?? '');
    $return_status = $_POST['return_status'] ?? 'RETURNED';
    $accessory_status = $_POST['accessory_status'] ?? 'GOOD';
    $accessory_notes = trim($_POST['accessory_notes'] ?? '');

    if ($entered_code === '') {
        $feedback_message = 'Borrow code is required.';
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
        $safe_code = $conn->real_escape_string($entered_code);
        $check = $conn->query("SELECT *
                               FROM borrow_records
                               WHERE borrow_code='{$safe_code}'
                               ORDER BY id ASC");

        $records = array();
        if ($check) {
            while ($row = $check->fetch_assoc()) {
                $records[] = $row;
            }
        }

        if (empty($records)) {
            $feedback_message = 'Invalid borrow code.';
            $feedback_type = 'alert-danger';
        } else {
            $has_borrowed = false;
            foreach ($records as $row) {
                if (strtoupper(trim((string) ($row['status'] ?? ''))) === 'BORROWED') {
                    $has_borrowed = true;
                    break;
                }
            }

            if (!$has_borrowed) {
                $feedback_message = 'This borrowing code is already fully returned.';
                $feedback_type = 'alert-danger';
                $returned_records = $records;
                $returned_summary = $records[0];
            } else {
                $safe_received_by = $conn->real_escape_string($received_by);
                $safe_returned_by = $conn->real_escape_string($returned_by);
                $safe_return_status = $conn->real_escape_string($return_status);
                $safe_accessory_status = $conn->real_escape_string($accessory_status);
                $safe_accessory_notes = $conn->real_escape_string($accessory_notes);

                $update_ok = $conn->query("UPDATE borrow_records
                                           SET status='{$safe_return_status}',
                                               accessory_status='{$safe_accessory_status}',
                                               accessory_notes='{$safe_accessory_notes}',
                                               returned_by='{$safe_returned_by}',
                                               received_by='{$safe_received_by}',
                                               return_date=CURDATE()
                                           WHERE borrow_code='{$safe_code}'
                                           AND status='BORROWED'");

                if (!$update_ok) {
                    $feedback_message = 'Failed to update return record: ' . $conn->error;
                    $feedback_type = 'alert-danger';
                } else {
                    $updated_rows = (int) $conn->affected_rows;
                    if ($updated_rows > 0) {
                        $status_text = strtolower($return_status);
                        $feedback_message = "{$updated_rows} item(s) marked as {$status_text}. ";
                        if ($return_status === 'RETURNED') {
                            $feedback_message .= "Returned by {$returned_by}, received by {$received_by}.";
                        } else {
                            $feedback_message .= "Received by {$received_by}.";
                        }
                        $feedback_type = 'alert-success';

                        $refresh = $conn->query("SELECT *
                                                 FROM borrow_records
                                                 WHERE borrow_code='{$safe_code}'
                                                 ORDER BY id ASC");

                        if ($refresh) {
                            while ($row = $refresh->fetch_assoc()) {
                                $returned_records[] = $row;
                            }
                        }

                        if (!empty($returned_records)) {
                            $returned_summary = $returned_records[0];
                        }
                    } else {
                        $feedback_message = 'No borrowed items were updated.';
                        $feedback_type = 'alert-danger';
                    }
                }
            }
        }
    }
}
?>

<div class="card">
<h3>Return Item (Using Borrow Code)</h3>

<?php if ($feedback_message !== ''): ?>
    <div class="<?php echo $feedback_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></div>
<?php endif; ?>

<form method="POST" onsubmit="return confirmReturn(event, this);">
    <label>Enter Borrow Code</label>
    <input type="text" name="borrow_code" required placeholder="Example: DA-20260224-ABC12" value="<?php echo htmlspecialchars($entered_code); ?>">
    <label>Return Status</label>
    <select name="return_status">
        <option value="RETURNED">Returned</option>
        <option value="LOST">Lost</option>
        <option value="DAMAGED">Damaged</option>
    </select>
    <label>Accessory Status</label>
    <select name="accessory_status">
        <option value="GOOD">Good Condition</option>
        <option value="LOST">Lost</option>
        <option value="DAMAGED">Damaged</option>
        <option value="NOT_INCLUDED">Not Included</option>
    </select>
    <label>Accessory Notes (Optional)</label>
    <textarea name="accessory_notes" placeholder="Any notes about accessory condition..." rows="2"></textarea>
    <label>Returned By</label>
    <input type="text" name="returned_by" required placeholder="Enter name of person returning items">
    <label>Received By</label>
    <input type="text" name="received_by" required placeholder="Enter name of person who received items">
    <button type="submit" name="return_code">Return Item</button>
</form>

<?php if ($returned_summary !== null): ?>
    <div class="return-details">
        <h4>Returned Data</h4>
        <table class="details-table js-next-pagination" data-no-pagination="1">
            <tr>
                <th>Borrow Code</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['borrow_code'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Borrower</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['borrower'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Office</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['office'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Purpose</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['purpose'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Release Date</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['release_date'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Expected Return</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['expected_return'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <span style="font-weight: bold; color: <?php 
                        $status = strtoupper(trim((string) ($returned_summary['status'] ?? 'RETURNED')));
                        echo $status === 'RETURNED' ? '#166534' : ($status === 'LOST' ? '#dc2626' : ($status === 'DAMAGED' ? '#d97706' : '#6b7280'));
                    ?>;">
                        <?php echo htmlspecialchars(ucfirst(strtolower($status))); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Return Date</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['return_date'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Returned By</th>
                <td>
                    <?php echo htmlspecialchars(trim((string) ($returned_summary['returned_by'] ?? '')) !== '' ? (string) $returned_summary['returned_by'] : '-'); ?>
                </td>
            </tr>
            <tr>
                <th>Received By</th>
                <td>
                    <?php echo htmlspecialchars(trim((string) ($returned_summary['received_by'] ?? '')) !== '' ? (string) $returned_summary['received_by'] : '-'); ?>
                </td>
            </tr>
        </table>

        <h4>Returned Items (<?php echo count($returned_records); ?>)</h4>
        <table class="details-table js-next-pagination" data-no-pagination="1">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Serial Number</th>
                    <th>Accessories</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returned_records as $index => $row): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['manual_item'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['asset_code'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars(trim((string) ($row['accessories'] ?? '')) !== '' ? (string) $row['accessories'] : '-'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['status'] ?? '-')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

</div>

<script>
function confirmReturn(event, form){
    if (event) {
        event.preventDefault();
    }

    showAppConfirm(
        "Are you sure you want to return this item?",
        { title: "Confirm Return", okLabel: "Yes, Return" }
    ).then((confirmed) => {
        if (confirmed && form) {
            form.submit();
        }
    });

    return false;
}
</script>

<?php include('../includes/footer.php'); ?>
