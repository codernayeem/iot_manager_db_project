<?php
/**
 * Dashboard Module: Saved VIEW - Unresolved Critical Logs
 * Demonstrates using a complex view with CASE statements
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            log_id,
            device_name,
            serial_number,
            device_type,
            device_owner,
            log_type,
            message,
            severity_level,
            severity_category,
            device_location,
            days_unresolved,
            log_time
        FROM v_unresolved_critical_logs
        ORDER BY severity_level DESC, days_unresolved DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Saved VIEW: Unresolved Critical Logs',
    'description' => 'Critical issues from v_unresolved_critical_logs view - Uses CASE, multiple JOINs, and calculated fields',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-exclamation-circle'
];