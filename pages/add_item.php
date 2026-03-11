<?php
$query = http_build_query($_GET);
$target = 'borrow_item.php';

if ($query !== '') {
    $target .= '?' . $query;
}

$target .= '#addEquipmentCard';
header('Location: ' . $target);
exit();
