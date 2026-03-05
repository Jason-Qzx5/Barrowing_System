<?php include('../includes/header.php'); ?>

<?php
$feedback_message = '';
$feedback_type = '';
$entered_code = '';
$returned_records = array();
$returned_summary = null;
$logged_in_receiver = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : '';
$logged_in_receiver_display = $logged_in_receiver !== '' ? $logged_in_receiver : 'Unknown User';

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

    if ($entered_code === '') {
        $feedback_message = 'Borrow code is required.';
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
                $safe_received_by = $conn->real_escape_string($logged_in_receiver);

                $conn->query("UPDATE borrow_records
                              SET status='RETURNED',
                                  received_by='{$safe_received_by}',
                                  return_date=CURDATE()
                              WHERE borrow_code='{$safe_code}'
                              AND status='BORROWED'");

                $updated_rows = (int) $conn->affected_rows;
                if ($updated_rows > 0) {
                    $feedback_message = "{$updated_rows} item(s) returned successfully. Received by {$logged_in_receiver_display}.";
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
?>

<div class="card">
<h3>Return Item (Using Borrow Code)</h3>

<?php if ($feedback_message !== ''): ?>
    <div class="<?php echo $feedback_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></div>
<?php endif; ?>

<form method="POST" onsubmit="return confirmReturn(event, this);">
    <label>Enter Borrow Code</label>
    <input type="text" name="borrow_code" required placeholder="Example: DA-20260224-ABC12" value="<?php echo htmlspecialchars($entered_code); ?>">
    <button type="submit" name="return_code">Return Item</button>
</form>

<?php if ($returned_summary !== null): ?>
    <div class="return-details">
        <h4>Returned Data</h4>
        <table class="details-table js-next-pagination">
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
                <th>Return Date</th>
                <td><?php echo htmlspecialchars((string) ($returned_summary['return_date'] ?? '-')); ?></td>
            </tr>
            <tr>
                <th>Received By</th>
                <td>
                    <?php echo htmlspecialchars(trim((string) ($returned_summary['received_by'] ?? '')) !== '' ? (string) $returned_summary['received_by'] : '-'); ?>
                </td>
            </tr>
        </table>

        <h4>Returned Items (<?php echo count($returned_records); ?>)</h4>
        <table class="details-table js-next-pagination">
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
