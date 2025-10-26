<?php
/**
 * Dashboard Module: UNION ALL Set Operation
 * Shows all device events including duplicates
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            'ERROR' AS event_category,
            d.d_name AS device_name,
            dl.log_type,
            dl.message,
            dl.log_time,
            dl.severity_level
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        WHERE dl.log_type = 'error'
        
        UNION ALL
        
        SELECT 
            'WARNING' AS event_category,
            d.d_name AS device_name,
            dl.log_type,
            dl.message,
            dl.log_time,
            dl.severity_level
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        WHERE dl.log_type = 'warning'
        
        UNION ALL
        
        SELECT 
            'INFO' AS event_category,
            d.d_name AS device_name,
            dl.log_type,
            dl.message,
            dl.log_time,
            dl.severity_level
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        WHERE dl.log_type = 'info'
        
        ORDER BY log_time DESC
        LIMIT 20";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'UNION ALL Set Operation',
    'description' => 'All Device Events by Category - Combines all log types keeping duplicates (faster than UNION)',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-list'
];