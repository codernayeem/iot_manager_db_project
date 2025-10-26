<?php
/**
 * Dashboard Module: Saved FUNCTION - Device Health Score
 * Demonstrates using a calculated health score function
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            dt.t_name AS device_type,
            fn_get_device_health_score(d.d_id) AS health_score,
            CASE 
                WHEN fn_get_device_health_score(d.d_id) >= 80 THEN 'Excellent'
                WHEN fn_get_device_health_score(d.d_id) >= 60 THEN 'Good'
                WHEN fn_get_device_health_score(d.d_id) >= 40 THEN 'Fair'
                WHEN fn_get_device_health_score(d.d_id) >= 20 THEN 'Poor'
                WHEN fn_get_device_health_score(d.d_id) >= 0 THEN 'Critical'
                ELSE 'Unknown'
            END AS health_status,
            d.status
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        ORDER BY health_score DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Saved FUNCTION: fn_get_device_health_score',
    'description' => 'Device Health Scoring - Uses complex calculations with variables, subqueries, and conditional logic',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-heartbeat'
];