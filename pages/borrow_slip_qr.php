<?php
require_once(__DIR__ . '/../config/db.php');

$borrow_code = trim($_GET['code'] ?? '');
$records = array();
$summary = null;
$pdf_link = '';

if ($borrow_code !== '') {
    $safe_code = $conn->real_escape_string($borrow_code);
    $result = $conn->query("SELECT *
                            FROM borrow_records
                            WHERE borrow_code='{$safe_code}'
                            ORDER BY id ASC");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        if (!empty($records)) {
            $summary = $records[0];
            $pdf_link = 'borrow_slip_pdf.php?code=' . rawurlencode($borrow_code);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Slip Copy</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
            padding: 16px;
        }
        .slip-page {
            max-width: 760px;
            margin: 0 auto;
        }
        .slip-card {
            background: #ffffff;
            border: 1px solid #d7e0ea;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .slip-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .slip-title {
            margin: 0;
            font-size: 26px;
            line-height: 1.08;
            color: #1e293b;
        }
        .slip-subtitle {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 13px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #ffedd5;
            color: #b45309;
            border: 1px solid #fdba74;
            font-size: 12px;
            font-weight: 700;
            margin-top: 2px;
        }
        .chip::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: currentColor;
        }
        .code {
            color: #166534;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #d5deea;
            padding: 9px 10px;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }
        th {
            background: #eff4fa;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 12px;
            color: #334155;
        }
        .summary-table td:first-child {
            width: 34%;
            font-weight: 700;
            background: #f8fbff;
        }
        .msg {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            border: none;
            border-radius: 8px;
            padding: 9px 12px;
            background: #1f6a43;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #175233;
        }
        .btn-secondary {
            background: #4b5563;
        }
        .btn-secondary:hover {
            background: #374151;
        }
    </style>
</head>
<body>
    <div class="slip-page">
        <div class="slip-card">
            <?php if ($summary === null): ?>
                <div class="msg">Borrow slip not found for this code.</div>
            <?php else: ?>
                <?php
                $item_count = count($records);
                $status_text = strtoupper(trim((string) ($summary['status'] ?? '')));
                if ($status_text === '') {
                    $status_text = 'UNKNOWN';
                }
                ?>

                <div class="slip-head">
                    <div>
                        <h1 class="slip-title">Borrow Slip Copy</h1>
                        <p class="slip-subtitle">Department of Agriculture</p>
                    </div>
                    <span class="chip"><?php echo htmlspecialchars($status_text); ?></span>
                </div>

                <?php if ($pdf_link !== ''): ?>
                    <p class="slip-link-row">
                        <a href="<?php echo htmlspecialchars($pdf_link); ?>" target="_blank" rel="noopener">Download PDF receipt</a>
                    </p>
                <?php endif; ?>

                <table class="summary-table">
                    <tr><td>Borrow Code</td><td><span class="code"><?php echo htmlspecialchars((string) $summary['borrow_code']); ?></span></td></tr>
                    <tr><td>Total Items</td><td><?php echo $item_count; ?></td></tr>
                    <tr><td>Borrower</td><td><?php echo htmlspecialchars((string) $summary['borrower']); ?></td></tr>
                    <tr><td>Lender</td><td><?php echo htmlspecialchars(trim((string) ($summary['lender'] ?? '')) !== '' ? (string) $summary['lender'] : '-'); ?></td></tr>
                    <tr><td>Office</td><td><?php echo htmlspecialchars((string) $summary['office']); ?></td></tr>
                    <tr><td>Purpose</td><td><?php echo htmlspecialchars((string) $summary['purpose']); ?></td></tr>
                    <tr><td>Release Date</td><td><?php echo htmlspecialchars((string) $summary['release_date']); ?></td></tr>
                    <tr><td>Expected Return</td><td><?php echo htmlspecialchars((string) $summary['expected_return']); ?></td></tr>
                    <tr><td>Returned By</td><td><?php echo htmlspecialchars(trim((string) ($summary['returned_by'] ?? '')) !== '' ? (string) $summary['returned_by'] : '-'); ?></td></tr>
                    <tr><td>Received By</td><td><?php echo htmlspecialchars(trim((string) ($summary['received_by'] ?? '')) !== '' ? (string) $summary['received_by'] : '-'); ?></td></tr>
                    <tr><td>Return Date</td><td><?php echo htmlspecialchars(trim((string) ($summary['return_date'] ?? '')) !== '' ? (string) $summary['return_date'] : '-'); ?></td></tr>
                </table>

                <h3>Borrowed Items (<?php echo $item_count; ?>)</h3>
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

                <div class="actions">
                    <?php if ($pdf_link !== ''): ?>
                        <a href="<?php echo htmlspecialchars($pdf_link); ?>" class="btn" target="_blank" rel="noopener">Download PDF</a>
                    <?php endif; ?>
                    <button type="button" class="btn" onclick="window.print()">Print</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.reload()">Refresh</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
