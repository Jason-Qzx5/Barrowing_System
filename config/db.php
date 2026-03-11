<?php

$conn = new mysqli("localhost","root","","da_borrowing_db");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Backward-compatible schema patches for older database versions.
$schema_updates = array(
    "lender" => "ALTER TABLE borrow_records ADD COLUMN lender varchar(150) DEFAULT NULL AFTER asset_code",
    "returned_by" => "ALTER TABLE borrow_records ADD COLUMN returned_by varchar(150) DEFAULT NULL AFTER expected_return",
    "received_by" => "ALTER TABLE borrow_records ADD COLUMN received_by varchar(150) DEFAULT NULL AFTER returned_by",
    "return_date" => "ALTER TABLE borrow_records ADD COLUMN return_date date DEFAULT NULL AFTER received_by",
    "accessory_status" => "ALTER TABLE borrow_records ADD COLUMN accessory_status enum('GOOD','LOST','DAMAGED','NOT_INCLUDED') DEFAULT 'GOOD' AFTER status",
    "accessory_notes" => "ALTER TABLE borrow_records ADD COLUMN accessory_notes text DEFAULT NULL AFTER accessory_status"
);

foreach ($schema_updates as $column_name => $alter_sql) {
    $column_check = $conn->query("SHOW COLUMNS FROM borrow_records LIKE '" . $conn->real_escape_string($column_name) . "'");
    if ($column_check && $column_check->num_rows === 0) {
        $conn->query($alter_sql);
    }
}

// Optional public base URL for QR links.
// Example: http://192.168.1.20/Borrowing_system/pages
// Leave empty to auto-detect.
$public_base_url = '';

?>
