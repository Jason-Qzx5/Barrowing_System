<?php

require_once('../tcpdf/tcpdf.php');
include('../config/db.php');

// =======================
// CREATE PDF (LANDSCAPE)
// =======================
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('DA Borrowing System');
$pdf->SetAuthor('Department of Agriculture');
$pdf->SetTitle('Equipment Borrowing Logbook');

$pdf->SetMargins(10,10,10);
$pdf->AddPage();

// =======================
// HEADER
// =======================
$html = '
<h2 style="text-align:center;">DEPARTMENT OF AGRICULTURE</h2>
<h4 style="text-align:center;">Equipment Borrowing Logbook</h4>
<br>
';

// =======================
// FILTER LOGIC
// =======================
$filter = $_GET['filter_type'] ?? 'today';
$where = "";
$returned_where = "";

if($filter == "today"){
    $where = "DATE(release_date)=CURDATE()";
    $returned_where = "DATE(return_date)=CURDATE()";
}
elseif($filter == "week"){
    $where = "release_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $returned_where = "return_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
}
elseif($filter == "custom" && isset($_GET['start_date'], $_GET['end_date'])){
    $start = trim((string) $_GET['start_date']);
    $end   = trim((string) $_GET['end_date']);

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $safe_start = $conn->real_escape_string($start);
        $safe_end = $conn->real_escape_string($end);
        $where = "release_date BETWEEN '{$safe_start}' AND '{$safe_end}'";
        $returned_where = "return_date BETWEEN '{$safe_start}' AND '{$safe_end}'";
    }
}

// =======================
// QUERY DATABASE
// =======================
$sql = "SELECT * FROM borrow_records";

if($where != ""){
    $sql .= " WHERE $where";
}

$sql .= " ORDER BY id DESC";

$result = $conn->query($sql);

$returned_sql = "SELECT * FROM borrow_records WHERE status='RETURNED'";

if($returned_where != ""){
    $returned_sql .= " AND $returned_where";
}

$returned_sql .= " ORDER BY return_date DESC, id DESC";
$returned_result = $conn->query($returned_sql);

// =======================
// TABLE DESIGN
// =======================
$html .= '
<table border="1" cellpadding="4">
<tr style="background-color:#e8f5e9;">
<th width="30"><b>ID</b></th>
<th width="90"><b>Item</b></th>
<th width="70"><b>Asset Code</b></th>
<th width="80"><b>Borrower</b></th>
<th width="70"><b>Office</b></th>
<th width="120"><b>Purpose</b></th>
<th width="70"><b>Release</b></th>
<th width="70"><b>Expected</b></th>
<th width="50"><b>Status</b></th>
</tr>
';

if($result && $result->num_rows > 0){

    while($row = $result->fetch_assoc()){

        $color = ($row['status']=="BORROWED") ? "red":"green";
        $id = htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $manual_item = htmlspecialchars((string) ($row['manual_item'] ?? ''), ENT_QUOTES, 'UTF-8');
        $asset_code = htmlspecialchars((string) ($row['asset_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $borrower = htmlspecialchars((string) ($row['borrower'] ?? ''), ENT_QUOTES, 'UTF-8');
        $office = htmlspecialchars((string) ($row['office'] ?? ''), ENT_QUOTES, 'UTF-8');
        $purpose = htmlspecialchars((string) ($row['purpose'] ?? ''), ENT_QUOTES, 'UTF-8');
        $release_date = htmlspecialchars((string) ($row['release_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $expected_return = htmlspecialchars((string) ($row['expected_return'] ?? ''), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8');

        $html .= '
        <tr>
        <td>'.$id.'</td>
        <td>'.$manual_item.'</td>
        <td>'.$asset_code.'</td>
        <td>'.$borrower.'</td>
        <td>'.$office.'</td>
        <td>'.$purpose.'</td>
        <td>'.$release_date.'</td>
        <td>'.$expected_return.'</td>
        <td style="color:'.$color.';"><b>'.$status.'</b></td>
        </tr>';
    }

}else{
    $html .= '<tr><td colspan="9">No records found</td></tr>';
}

$html .= '</table><br><br>';

// =======================
// RETURNED RECORDS TABLE
// =======================
$html .= '
<h4>Returned Records</h4>
<table border="1" cellpadding="4">
<tr style="background-color:#e8f5e9;">
<th width="25"><b>ID</b></th>
<th width="80"><b>Borrow Code</b></th>
<th width="75"><b>Item</b></th>
<th width="60"><b>Asset Code</b></th>
<th width="60"><b>Borrower</b></th>
<th width="50"><b>Office</b></th>
<th width="45"><b>Return Date</b></th>
<th width="55"><b>Received By</b></th>
<th width="30"><b>Status</b></th>
</tr>
';

if($returned_result && $returned_result->num_rows > 0){
    while($returned_row = $returned_result->fetch_assoc()){
        $returned_id = htmlspecialchars((string) ($returned_row['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $returned_code = htmlspecialchars((string) ($returned_row['borrow_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $returned_item = htmlspecialchars((string) ($returned_row['manual_item'] ?? ''), ENT_QUOTES, 'UTF-8');
        $returned_asset = htmlspecialchars((string) ($returned_row['asset_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $returned_borrower = htmlspecialchars((string) ($returned_row['borrower'] ?? ''), ENT_QUOTES, 'UTF-8');
        $returned_office = htmlspecialchars((string) ($returned_row['office'] ?? ''), ENT_QUOTES, 'UTF-8');
        $returned_date = trim((string) ($returned_row['return_date'] ?? ''));
        if ($returned_date === '' || $returned_date === '0000-00-00') {
            $returned_date = '-';
        }
        $returned_date = htmlspecialchars($returned_date, ENT_QUOTES, 'UTF-8');

        $received_by = trim((string) ($returned_row['received_by'] ?? ''));
        if ($received_by === '') {
            $received_by = '-';
        }
        $received_by = htmlspecialchars($received_by, ENT_QUOTES, 'UTF-8');

        $returned_status = htmlspecialchars((string) ($returned_row['status'] ?? 'RETURNED'), ENT_QUOTES, 'UTF-8');

        $html .= '
        <tr>
        <td>'.$returned_id.'</td>
        <td>'.$returned_code.'</td>
        <td>'.$returned_item.'</td>
        <td>'.$returned_asset.'</td>
        <td>'.$returned_borrower.'</td>
        <td>'.$returned_office.'</td>
        <td>'.$returned_date.'</td>
        <td>'.$received_by.'</td>
        <td style="color:green;"><b>'.$returned_status.'</b></td>
        </tr>';
    }
}else{
    $html .= '<tr><td colspan="9">No returned records found</td></tr>';
}

$html .= '</table><br><br>

<table width="100%">
<tr>
<td>Prepared by:<br><br>________________________</td>
<td align="right">Approved by:<br><br>________________________</td>
</tr>
</table>
';

// =======================
// OUTPUT PDF DOWNLOAD
// =======================
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('DA_Logbook_Report.pdf', 'D');

exit;
?>
