<?php
/**
 * Dashboard Module: EXCEPT/MINUS Set Operation (Simulated)
 * MySQL doesn't have EXCEPT, so we simulate it using LEFT JOIN with NULL check
 * Shows devices that have NO alerts
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            dt.t_name AS device_type,
            CONCAT(u.f_name, ' ', u.l_name) AS owner,
            (SELECT COUNT(*) 
             FROM device_logs dl 
             WHERE dl.d_id = d.d_id) AS total_logs
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE NOT EXISTS (
            SELECT 1 
            FROM device_logs dl
            INNER JOIN alerts a ON dl.log_id = a.log_id
            WHERE dl.d_id = d.d_id
        )
        ORDER BY total_logs DESC, d.d_name
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'EXCEPT/MINUS Operation (Simulated)',
    'description' => 'Healthy Devices Without Alerts - Shows devices that exist but have no alerts (set difference)',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-check-circle'
];