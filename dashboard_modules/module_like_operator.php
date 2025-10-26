<?php
/**
 * Dashboard Module: LIKE Pattern Matching
 * Shows pattern matching with wildcards
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            'SENSOR_DEVICES' AS pattern_type,
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            dt.t_name AS device_type
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        WHERE d.d_name LIKE '%sensor%'
           OR dt.t_name LIKE '%sensor%'
        
        UNION ALL
        
        SELECT 
            'STARTS_WITH_A_OR_B' AS pattern_type,
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            dt.t_name AS device_type
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        WHERE d.d_name LIKE 'A%' OR d.d_name LIKE 'B%'
        
        UNION ALL
        
        SELECT 
            'GMAIL_USERS' AS pattern_type,
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            CONCAT(u.f_name, ' ', u.l_name) AS device_type
        FROM devices d
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE u.email LIKE '%@gmail.com'
        
        ORDER BY pattern_type, device_name
        LIMIT 20";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'LIKE Pattern Matching',
    'description' => 'Text Search with Wildcards - Uses LIKE with % wildcards for flexible pattern matching',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-search'
];