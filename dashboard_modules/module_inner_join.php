<?php
/**
 * Dashboard Module: INNER JOIN
 * Shows devices with their types and owners using INNER JOIN
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
        d.d_id,
        d.d_name AS device_name,
        d.serial_number,
        d.status,
        dt.t_name AS device_type,
        CONCAT(u.f_name, ' ', u.l_name) AS owner_name,
        u.email AS owner_email,
        d.purchase_date
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    ORDER BY d.d_name
    LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $data[] = $row;
}
}

return [
'title' => 'INNER JOIN Operation',
'description' => 'Devices with Types and Owners - Shows only devices that have both type and owner',
'sql' => $sql,
'data' => $data,
'icon' => 'fa-link'
];