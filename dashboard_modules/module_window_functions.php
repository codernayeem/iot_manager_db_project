<?php
/**
 * Dashboard Module: Window Functions (Analytical)
 * Demonstrates ROW_NUMBER, RANK, DENSE_RANK, running totals
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            d.d_name AS device_name,
            dt.t_name AS device_type,
            COUNT(dl.log_id) AS log_count,
            ROW_NUMBER() OVER (ORDER BY COUNT(dl.log_id) DESC) AS overall_rank,
            RANK() OVER (PARTITION BY dt.t_name ORDER BY COUNT(dl.log_id) DESC) AS type_rank,
            DENSE_RANK() OVER (ORDER BY COUNT(dl.log_id) DESC) AS dense_rank,
            SUM(COUNT(dl.log_id)) OVER (ORDER BY COUNT(dl.log_id) DESC) AS running_total
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        LEFT JOIN device_logs dl ON d.d_id = dl.d_id
        GROUP BY d.d_id, d.d_name, dt.t_name
        ORDER BY log_count DESC
        LIMIT 15";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Window Functions (Analytical)',
    'description' => 'Device Activity Rankings - Uses ROW_NUMBER, RANK, DENSE_RANK, and running totals',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-ranking-star'
];