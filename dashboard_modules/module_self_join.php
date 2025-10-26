<?php
/**
 * Dashboard Module: Self Join
 * Shows devices owned by the same user (device pairs)
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            CONCAT(u.f_name, ' ', u.l_name) AS owner_name,
            u.email,
            d1.d_name AS device_1,
            d1.serial_number AS serial_1,
            d2.d_name AS device_2,
            d2.serial_number AS serial_2,
            dt1.t_name AS type_1,
            dt2.t_name AS type_2
        FROM devices d1
        INNER JOIN devices d2 ON d1.user_id = d2.user_id AND d1.d_id < d2.d_id
        INNER JOIN users u ON d1.user_id = u.user_id
        INNER JOIN device_types dt1 ON d1.t_id = dt1.t_id
        INNER JOIN device_types dt2 ON d2.t_id = dt2.t_id
        ORDER BY u.user_id, d1.d_id
        LIMIT 15";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Self Join Operation',
    'description' => 'Users with Multiple Devices - Joins devices table to itself to find device pairs owned by same user',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-clone'
];