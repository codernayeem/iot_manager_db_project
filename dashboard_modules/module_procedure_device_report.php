<?php
/**
 * Dashboard Module: Saved PROCEDURE - Device Report
 * Demonstrates calling a stored procedure with CURSOR and LOOP
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Call the stored procedure
$sql = "CALL sp_generate_device_report(NULL)";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Saved PROCEDURE: sp_generate_device_report',
    'description' => 'Comprehensive Device Report - Uses CURSOR, LOOP, temporary tables, and complex calculations',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-file-alt'
];