<?php
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../tcpdf/tcpdf.php');

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$borrow_code = trim((string) ($_GET['code'] ?? ''));
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
$item_count = count($records);
$status_text = strtoupper(trim((string) ($summary['status'] ?? 'BORROWED')));
if ($status_text === '') {
    $status_text = 'BORROWED';
}

$file_code = preg_replace('/[^A-Za-z0-9\-]/', '_', $borrow_code);
if ($file_code === '') {
    $file_code = 'receipt';
}
$filename = 'BorrowSlip-' . $file_code . '.pdf';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('DA Borrowing System');
$pdf->SetAuthor('Department of Agriculture');
$pdf->SetTitle('Borrow Slip ' . $borrow_code);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

$html = '
<h2 style="text-align:center;margin:0;">Borrow Slip</h2>
<p style="text-align:center;margin:2px 0 10px 0;color:#4b5563;">Department of Agriculture</p>
<p style="margin:0 0 8px 0;"><b>Status:</b> ' . h($status_text) . '</p>

<table border="1" cellpadding="5">
    <tr><td width="35%"><b>Borrow Code</b></td><td width="65%">' . h($summary['borrow_code'] ?? '-') . '</td></tr>
    <tr><td><b>Total Items</b></td><td>' . $item_count . '</td></tr>
    <tr><td><b>Borrower</b></td><td>' . h($summary['borrower'] ?? '-') . '</td></tr>
    <tr><td><b>Office</b></td><td>' . h($summary['office'] ?? '-') . '</td></tr>
    <tr><td><b>Purpose</b></td><td>' . h($summary['purpose'] ?? '-') . '</td></tr>
    <tr><td><b>Release Date</b></td><td>' . h($summary['release_date'] ?? '-') . '</td></tr>
    <tr><td><b>Expected Return</b></td><td>' . h($summary['expected_return'] ?? '-') . '</td></tr>
    <tr><td><b>Received By</b></td><td>' . h(trim((string) ($summary['received_by'] ?? '')) !== '' ? (string) $summary['received_by'] : '-') . '</td></tr>
    <tr><td><b>Return Date</b></td><td>' . h(trim((string) ($summary['return_date'] ?? '')) !== '' ? (string) $summary['return_date'] : '-') . '</td></tr>
</table>

<h3 style="margin:12px 0 6px 0;">Borrowed Items (' . $item_count . ')</h3>
<table border="1" cellpadding="5">
    <tr style="background-color:#ecf2f9;">
        <th width="10%"><b>#</b></th>
        <th width="38%"><b>Item</b></th>
        <th width="24%"><b>Serial Number</b></th>
        <th width="28%"><b>Accessories</b></th>
    </tr>';

foreach ($records as $index => $record) {
    $html .= '
    <tr>
        <td>' . ($index + 1) . '</td>
        <td>' . h($record['manual_item'] ?? '-') . '</td>
        <td>' . h($record['asset_code'] ?? '-') . '</td>
        <td>' . h(trim((string) ($record['accessories'] ?? '')) !== '' ? (string) $record['accessories'] : '-') . '</td>
    </tr>';
}

$html .= '
</table>
<p style="margin-top:10px;color:#6b7280;">Generated: ' . h(date('Y-m-d H:i:s')) . '</p>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($filename, 'D');
exit;
?>

