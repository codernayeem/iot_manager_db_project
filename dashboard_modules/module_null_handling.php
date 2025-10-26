<?php
/**
 * Dashboard Module: NULL Handling
 * Shows IS NULL, IS NOT NULL, COALESCE, IFNULL
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            dl.log_id,
            d.d_name AS device_name,
            dl.log_type,
            dl.message,
            dl.resolved_by,
            CASE 
                WHEN dl.resolved_by IS NULL THEN 'UNRESOLVED'
                ELSE 'RESOLVED'
            END AS resolution_status,
            IFNULL(dl.resolution_notes, 'No resolution notes') AS notes,
            COALESCE(
                CONCAT(u.f_name, ' ', u.l_name),
                'Not Resolved Yet'
            ) AS resolver_name,
            IFNULL(
                TIMESTAMPDIFF(HOUR, dl.log_time, dl.resolved_at),
                TIMESTAMPDIFF(HOUR, dl.log_time, NOW())
            ) AS hours_to_resolve
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        LEFT JOIN users u ON dl.resolved_by = u.user_id
        WHERE dl.log_type IN ('error', 'warning')
        ORDER BY 
            CASE WHEN dl.resolved_by IS NULL THEN 0 ELSE 1 END,
            dl.log_time DESC
        LIMIT 15";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'NULL Handling Functions',
    'description' => 'Resolution Status Tracking - Uses IS NULL, IS NOT NULL, COALESCE, and IFNULL for NULL handling',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-question-circle'
];