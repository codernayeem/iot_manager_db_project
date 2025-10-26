<?php
/**
 * Dashboard Module: GROUP_CONCAT
 * Shows advanced string aggregation with GROUP_CONCAT
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            l.loc_id,
            l.loc_name AS location,
            l.address,
            COUNT(DISTINCT d.d_id) AS device_count,
            GROUP_CONCAT(
                DISTINCT d.d_name 
                ORDER BY d.d_name 
                SEPARATOR ' | '
            ) AS device_list,
            GROUP_CONCAT(
                DISTINCT dt.t_name 
                ORDER BY dt.t_name 
                SEPARATOR ', '
            ) AS device_types,
            GROUP_CONCAT(
                DISTINCT CONCAT(u.f_name, ' ', u.l_name)
                ORDER BY u.l_name
                SEPARATOR '; '
            ) AS owners
        FROM locations l
        LEFT JOIN deployments dep ON l.loc_id = dep.loc_id
        LEFT JOIN devices d ON dep.d_id = d.d_id
        LEFT JOIN device_types dt ON d.t_id = dt.t_id
        LEFT JOIN users u ON d.user_id = u.user_id
        GROUP BY l.loc_id, l.loc_name, l.address
        ORDER BY device_count DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'GROUP_CONCAT Aggregation',
    'description' => 'Location Device Summary - Uses GROUP_CONCAT to combine multiple rows into comma-separated lists',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-list-ul'
];