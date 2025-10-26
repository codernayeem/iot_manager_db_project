<?php
/**
 * Dashboard Module: Correlated Subquery with EXISTS
 * Shows devices that have critical unresolved errors
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
            CONCAT(u.f_name, ' ', u.l_name) AS owner
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE EXISTS (
            SELECT 1 
            FROM device_logs dl
            WHERE dl.d_id = d.d_id 
              AND dl.log_type = 'error'
              AND dl.severity_level > 5
              AND dl.resolved_by IS NULL
        )
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
    'title' => 'Correlated Subquery with EXISTS',
    'description' => 'Devices with Critical Unresolved Errors - Uses EXISTS to find devices with specific log conditions',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-exclamation-triangle'
];