<?php
/**
 * Dashboard Module: Inline View Subquery (FROM clause)
 * Shows user activity statistics using inline view
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            user_info.user_id,
            user_info.full_name,
            user_info.email,
            user_info.device_count,
            user_info.total_logs,
            ROUND(user_info.total_logs / user_info.device_count, 2) AS avg_logs_per_device
        FROM (
            SELECT 
                u.user_id,
                CONCAT(u.f_name, ' ', u.l_name) AS full_name,
                u.email,
                COUNT(DISTINCT d.d_id) AS device_count,
                COUNT(dl.log_id) AS total_logs
            FROM users u
            LEFT JOIN devices d ON u.user_id = d.user_id
            LEFT JOIN device_logs dl ON d.d_id = dl.d_id
            GROUP BY u.user_id, u.f_name, u.l_name, u.email
        ) AS user_info
        WHERE user_info.device_count > 0
        ORDER BY user_info.total_logs DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Inline View Subquery (FROM Clause)',
    'description' => 'User Activity Statistics - Uses subquery in FROM clause to create temporary result set',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-users'
];