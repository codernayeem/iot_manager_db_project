<?php
/**
 * Dashboard Module: LEFT JOIN
 * Shows all devices with their deployment locations (even if not deployed)
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            l.loc_name AS location,
            l.address,
            dep.deployed_at,
            CASE 
                WHEN l.loc_name IS NULL THEN 'Not Deployed'
                ELSE 'Deployed'
            END AS deployment_status
        FROM devices d
        LEFT JOIN deployments dep ON d.d_id = dep.d_id
        LEFT JOIN locations l ON dep.loc_id = l.loc_id
        ORDER BY deployment_status DESC, d.d_name
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'LEFT JOIN Operation',
    'description' => 'All Devices with Optional Deployment Locations - Shows all devices including those not yet deployed',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-map-marker-alt'
];