<?php
/**
 * Dashboard Module: Scalar Subquery
 * Shows devices with log count calculated using scalar subquery
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            d.status,
            (SELECT COUNT(*) 
             FROM device_logs dl 
             WHERE dl.d_id = d.d_id) AS total_logs,
            (SELECT COUNT(*) 
             FROM device_logs dl 
             WHERE dl.d_id = d.d_id AND dl.log_type = 'error') AS error_logs,
            (SELECT COUNT(*) 
             FROM device_logs dl 
             WHERE dl.d_id = d.d_id AND dl.resolved_by IS NULL) AS unresolved_logs
        FROM devices d
        ORDER BY total_logs DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Scalar Subquery',
    'description' => 'Device Log Statistics - Uses subqueries in SELECT clause to calculate log counts for each device',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-calculator'
];