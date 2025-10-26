<?php
/**
 * Dashboard Module: CASE WHEN Expressions
 * Demonstrates conditional logic in queries
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d.d_id,
            d.d_name AS device_name,
            d.status,
            CASE d.status
                WHEN 'error' THEN '🔴 Critical'
                WHEN 'warning' THEN '🟡 Warning'
                WHEN 'info' THEN '🟢 Healthy'
                ELSE '⚪ Unknown'
            END AS status_display,
            CASE 
                WHEN DATEDIFF(CURDATE(), d.purchase_date) > 1095 THEN 'Legacy (3+ years)'
                WHEN DATEDIFF(CURDATE(), d.purchase_date) > 730 THEN 'Mature (2+ years)'
                WHEN DATEDIFF(CURDATE(), d.purchase_date) > 365 THEN 'Established (1+ year)'
                ELSE 'New (< 1 year)'
            END AS device_age_category,
            d.purchase_date,
            CASE 
                WHEN (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'error') > 10 THEN 'High Activity'
                WHEN (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'error') > 5 THEN 'Medium Activity'
                WHEN (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'error') > 0 THEN 'Low Activity'
                ELSE 'No Issues'
            END AS issue_level
        FROM devices d
        ORDER BY d.purchase_date DESC
        LIMIT 15";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'CASE WHEN Conditional Logic',
    'description' => 'Device Categorization - Uses simple and searched CASE expressions for conditional logic',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-code-branch'
];