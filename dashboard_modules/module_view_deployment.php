<?php
/**
 * Dashboard Module: Saved VIEW - Device Deployment Summary
 * Demonstrates using a pre-created view
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d_id,
            d_name,
            serial_number,
            status,
            device_type,
            owner_name,
            owner_email,
            deployment_location,
            deployment_address,
            deployed_at,
            active_alert_count,
            unresolved_log_count
        FROM v_device_deployment_summary
        ORDER BY active_alert_count DESC, unresolved_log_count DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Saved VIEW: Device Deployment Summary',
    'description' => 'Comprehensive device overview from v_device_deployment_summary view - Combines JOINs, subqueries, and aggregations',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-eye'
];