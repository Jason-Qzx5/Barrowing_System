<?php
require_once(__DIR__ . '/../config/db.php');

$borrow_code = trim($_GET['code'] ?? '');

if ($borrow_code === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing borrow code.';
    exit;
}

$safe_code = $conn->real_escape_string($borrow_code);
$result = $conn->query("SELECT *
                        FROM borrow_records
                        WHERE borrow_code='{$safe_code}'
                        ORDER BY id ASC");

$records = array();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

if (empty($records)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Borrow slip not found.';
    exit;
}

$summary = $records[0];
$file_code = preg_replace('/[^A-Za-z0-9\-]/', '_', $borrow_code);
if ($file_code === '') {
    $file_code = 'receipt';
}
$filename = 'BorrowSlip-' . $file_code . '.doc';
$status_text = strtoupper(trim((string) ($summary['status'] ?? 'BORROWED')));

header('Content-Type: application/msword; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: public');
header('Cache-Control: private, max-age=0, must-revalidate');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Borrow Slip</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; }
        .title { font-size: 24px; margin: 0 0 2px; }
        .subtitle { margin: 0 0 12px; color: #4b5563; font-size: 13px; }
        .status { margin: 0 0 12px; font-weight: 700; color: #b45309; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #bfc9d4; padding: 8px; text-align: left; vertical-align: top; font-size: 12px; }
        th { background: #ecf2f9; text-transform: uppercase; letter-spacing: 0.04em; }
        .summary td:first-child { width: 34%; font-weight: 700; background: #f8fbff; }
        .section-title { font-size: 14px; margin: 14px 0 8px; }
        .code { font-weight: 700; color: #166534; }
    </style>
</head>
<body>
    <h1 class="title">Borrow Slip</h1>
    <p class="subtitle">Department of Agriculture</p>
    <p class="status">Status: <?php echo htmlspecialchars($status_text); ?></p>

    <table class="summary">
        <tr><td>Borrow Code</td><td><span class="code"><?php echo htmlspecialchars((string) $summary['borrow_code']); ?></span></td></tr>
        <tr><td>Total Items</td><td><?php echo count($records); ?></td></tr>
        <tr><td>Borrower</td><td><?php echo htmlspecialchars((string) $summary['borrower']); ?></td></tr>
        <tr><td>Office</td><td><?php echo htmlspecialchars((string) $summary['office']); ?></td></tr>
        <tr><td>Purpose</td><td><?php echo htmlspecialchars((string) $summary['purpose']); ?></td></tr>
        <tr><td>Release Date</td><td><?php echo htmlspecialchars((string) $summary['release_date']); ?></td></tr>
        <tr><td>Expected Return</td><td><?php echo htmlspecialchars((string) $summary['expected_return']); ?></td></tr>
        <tr><td>Received By</td><td><?php echo htmlspecialchars(trim((string) ($summary['received_by'] ?? '')) !== '' ? (string) $summary['received_by'] : '-'); ?></td></tr>
        <tr><td>Return Date</td><td><?php echo htmlspecialchars(trim((string) ($summary['return_date'] ?? '')) !== '' ? (string) $summary['return_date'] : '-'); ?></td></tr>
    </table>

    <h3 class="section-title">Borrowed Items</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Serial Number</th>
                <th>Accessories</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $index => $record): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars((string) $record['manual_item']); ?></td>
                    <td><?php echo htmlspecialchars((string) $record['asset_code']); ?></td>
                    <td><?php echo htmlspecialchars(trim((string) ($record['accessories'] ?? '')) !== '' ? (string) $record['accessories'] : '-'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
