<?php
/**
 * Dashboard Module: UNION Set Operation
 * Combines active alerts and recent critical logs
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            'ACTIVE_ALERT' AS source_type,
            a.log_id,
            d.d_name AS device_name,
            dl.log_type,
            dl.message,
            a.alert_time AS event_time,
            dl.severity_level
        FROM alerts a
        INNER JOIN device_logs dl ON a.log_id = dl.log_id
        INNER JOIN devices d ON dl.d_id = d.d_id
        WHERE a.status = 'active'
        
        UNION
        
        SELECT 
            'CRITICAL_LOG' AS source_type,
            dl.log_id,
            d.d_name AS device_name,
            dl.log_type,
            dl.message,
            dl.log_time AS event_time,
            dl.severity_level
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        WHERE dl.severity_level >= 7 
          AND dl.log_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM alerts a WHERE a.log_id = dl.log_id
          )
        
        ORDER BY event_time DESC
        LIMIT 15";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'UNION Set Operation',
    'description' => 'Combined Alert and Critical Log Feed - Merges active alerts with recent critical logs (removes duplicates)',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-layer-group'
];