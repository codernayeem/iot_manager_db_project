<?php
/**
 * Dashboard Module: BETWEEN Operator
 * Shows logs within a specific date range and severity range
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            dl.log_id,
            d.d_name AS device_name,
            dl.log_type,
            dl.severity_level,
            dl.message,
            DATE(dl.log_time) AS log_date,
            TIME(dl.log_time) AS log_time,
            DATEDIFF(CURDATE(), dl.log_time) AS days_ago
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        WHERE dl.severity_level BETWEEN 5 AND 8
          AND dl.log_time BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW()
        ORDER BY dl.severity_level DESC, dl.log_time DESC
        LIMIT 20";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'BETWEEN Operator',
    'description' => 'Recent Medium to High Severity Logs - Uses BETWEEN for date and severity range filtering',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-sliders-h'
];