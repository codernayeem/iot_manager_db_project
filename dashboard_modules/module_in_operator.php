<?php
/**
 * Dashboard Module: IN and NOT IN Operators
 * Shows devices with specific status values
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            'PROBLEMATIC' AS category,
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            d.status,
            dt.t_name AS device_type,
            CONCAT(u.f_name, ' ', u.l_name) AS owner
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE d.status IN ('error', 'warning')
        
        UNION ALL
        
        SELECT 
            'HEALTHY' AS category,
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            d.status,
            dt.t_name AS device_type,
            CONCAT(u.f_name, ' ', u.l_name) AS owner
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE d.status NOT IN ('error', 'warning')
        
        ORDER BY category, device_name
        LIMIT 20";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'IN & NOT IN Operators',
    'description' => 'Device Categorization - Uses IN and NOT IN to filter devices by status categories',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-filter'
];