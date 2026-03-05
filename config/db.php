<?php

$conn = new mysqli("localhost","root","","da_borrowing_db");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Optional public base URL for QR links.
// Example: http://192.168.1.20/Borrowing_system/pages
// Leave empty to auto-detect.
$public_base_url = '';

?>
