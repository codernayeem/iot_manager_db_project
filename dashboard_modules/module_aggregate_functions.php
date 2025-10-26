<?php
/**
 * Dashboard Module: Aggregate Functions
 * Demonstrates COUNT, SUM, AVG, MIN, MAX, GROUP BY
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            dt.t_name AS device_type,
            COUNT(d.d_id) AS total_devices,
            COUNT(DISTINCT d.user_id) AS unique_owners,
            AVG(YEAR(CURDATE()) - YEAR(d.purchase_date)) AS avg_age_years,
            MIN(d.purchase_date) AS oldest_purchase,
            MAX(d.purchase_date) AS newest_purchase,
            SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN d.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
        FROM device_types dt
        LEFT JOIN devices d ON dt.t_id = d.t_id
        GROUP BY dt.t_id, dt.t_name
        HAVING COUNT(d.d_id) > 0
        ORDER BY total_devices DESC";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Aggregate Functions & GROUP BY',
    'description' => 'Device Type Statistics - Demonstrates COUNT, AVG, MIN, MAX, SUM with GROUP BY and HAVING',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-chart-bar'
];