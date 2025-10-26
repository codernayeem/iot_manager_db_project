<?php
/**
 * Dashboard Module: RIGHT JOIN
 * Shows all locations with their deployed devices (even if no devices)
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            l.loc_id,
            l.loc_name AS location_name,
            l.address,
            COUNT(DISTINCT d.d_id) AS device_count,
            GROUP_CONCAT(d.d_name ORDER BY d.d_name SEPARATOR ', ') AS devices,
            CASE 
                WHEN COUNT(DISTINCT d.d_id) = 0 THEN 'Empty'
                WHEN COUNT(DISTINCT d.d_id) < 3 THEN 'Low Utilization'
                ELSE 'Active'
            END AS location_status
        FROM devices d
        RIGHT JOIN deployments dep ON d.d_id = dep.d_id
        RIGHT JOIN locations l ON dep.loc_id = l.loc_id
        GROUP BY l.loc_id, l.loc_name, l.address
        ORDER BY device_count DESC, l.loc_name
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'RIGHT JOIN Operation',
    'description' => 'All Locations with Device Deployment Count - Shows all locations including empty ones',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-building'
];