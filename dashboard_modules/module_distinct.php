<?php
/**
 * Dashboard Module: DISTINCT
 * Shows unique value selection
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            dt.t_name AS device_type,
            COUNT(DISTINCT d.user_id) AS unique_owners,
            COUNT(DISTINCT d.d_id) AS total_devices,
            COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) AS active_devices,
            COUNT(DISTINCT CASE WHEN d.status = 'inactive' THEN d.d_id END) AS inactive_devices,
            COUNT(DISTINCT dep.loc_id) AS deployed_locations,
            GROUP_CONCAT(DISTINCT l.loc_name ORDER BY l.loc_name SEPARATOR ', ') AS location_names,
            COUNT(DISTINCT dl.log_id) AS total_logs,
            COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) AS error_logs
        FROM device_types dt
        LEFT JOIN devices d ON dt.t_id = d.t_id
        LEFT JOIN deployments dep ON d.d_id = dep.d_id
        LEFT JOIN locations l ON dep.loc_id = l.loc_id
        LEFT JOIN device_logs dl ON d.d_id = dl.d_id
        GROUP BY dt.t_id, dt.t_name
        HAVING total_devices > 0
        ORDER BY total_devices DESC";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'DISTINCT & COUNT DISTINCT',
    'description' => 'Unique Value Analysis - Uses DISTINCT to count unique owners, locations, and logs per device type',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-fingerprint'
];