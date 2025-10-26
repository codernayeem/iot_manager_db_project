<?php
/**
 * Dashboard Module: HAVING Clause
 * Filters grouped results - shows users with multiple devices
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            u.user_id,
            CONCAT(u.f_name, ' ', u.l_name) AS owner_name,
            u.email,
            COUNT(DISTINCT d.d_id) AS device_count,
            COUNT(DISTINCT dt.t_id) AS device_type_count,
            GROUP_CONCAT(DISTINCT dt.t_name ORDER BY dt.t_name SEPARATOR ', ') AS device_types,
            SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) AS active_devices,
            SUM(CASE WHEN d.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_devices,
            AVG(YEAR(CURDATE()) - YEAR(d.purchase_date)) AS avg_device_age
        FROM users u
        INNER JOIN devices d ON u.user_id = d.user_id
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        GROUP BY u.user_id, u.f_name, u.l_name, u.email
        HAVING device_count >= 2
        ORDER BY device_count DESC, active_devices DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'HAVING Clause Filtering',
    'description' => 'Users with 2+ Devices - Uses HAVING to filter aggregated results (vs WHERE for row filtering)',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-filter'
];