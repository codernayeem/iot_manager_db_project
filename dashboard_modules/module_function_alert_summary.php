<?php
/**
 * Dashboard Module: Saved FUNCTION - Alert Summary
 * Demonstrates using a user-defined function
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d.d_id,
            d.d_name AS device_name,
            d.serial_number,
            dt.t_name AS device_type,
            fn_get_alert_summary(d.d_id) AS alert_summary,
            d.status AS current_status
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        ORDER BY d.d_id
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Saved FUNCTION: fn_get_alert_summary',
    'description' => 'Device Alert Statistics - Uses custom function with IF/ELSE, variables, and string manipulation',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-bell'
];