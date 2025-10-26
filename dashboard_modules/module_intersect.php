<?php
/**
 * Dashboard Module: INTERSECT Set Operation (Simulated)
 * MySQL doesn't have INTERSECT, so we simulate it using INNER JOIN or EXISTS
 * Shows users who both own devices AND have resolved logs
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT DISTINCT
            u.user_id,
            CONCAT(u.f_name, ' ', u.l_name) AS full_name,
            u.email,
            device_owners.device_count,
            resolvers.resolved_count
        FROM users u
        INNER JOIN (
            SELECT user_id, COUNT(*) AS device_count
            FROM devices
            GROUP BY user_id
        ) AS device_owners ON u.user_id = device_owners.user_id
        INNER JOIN (
            SELECT resolved_by, COUNT(*) AS resolved_count
            FROM device_logs
            WHERE resolved_by IS NOT NULL
            GROUP BY resolved_by
        ) AS resolvers ON u.user_id = resolvers.resolved_by
        ORDER BY device_owners.device_count DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'INTERSECT Operation (Simulated)',
    'description' => 'Active Device Owners Who Also Resolve Issues - Shows intersection of device owners and problem resolvers',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-user-check'
];