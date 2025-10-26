<?php
/**
 * Dashboard Module: Cross Join (Cartesian Product)
 * Shows all possible device type and location combinations
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            dt.t_name AS device_type,
            l.loc_name AS location,
            l.address,
            COUNT(DISTINCT d.d_id) AS deployed_count,
            CASE 
                WHEN COUNT(DISTINCT d.d_id) > 0 THEN 'Active'
                ELSE 'Potential'
            END AS deployment_status
        FROM device_types dt
        CROSS JOIN locations l
        LEFT JOIN devices d ON dt.t_id = d.t_id
        LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.loc_id = l.loc_id
        GROUP BY dt.t_id, dt.t_name, l.loc_id, l.loc_name, l.address
        ORDER BY deployed_count DESC, dt.t_name, l.loc_name
        LIMIT 20";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'CROSS JOIN (Cartesian Product)',
    'description' => 'All Device Type × Location Combinations - Shows actual and potential deployment scenarios',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-grip'
];