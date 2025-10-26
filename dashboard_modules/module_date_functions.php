<?php
/**
 * Dashboard Module: Date/Time Functions
 * Demonstrates DATE, TIME, DATETIME functions and calculations
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            dl.log_id,
            d.d_name AS device_name,
            dl.log_type,
            DATE(dl.log_time) AS log_date,
            TIME(dl.log_time) AS log_time_only,
            YEAR(dl.log_time) AS log_year,
            MONTH(dl.log_time) AS log_month,
            DAY(dl.log_time) AS log_day,
            DAYNAME(dl.log_time) AS day_name,
            MONTHNAME(dl.log_time) AS month_name,
            DATEDIFF(CURDATE(), dl.log_time) AS days_ago,
            TIMESTAMPDIFF(HOUR, dl.log_time, NOW()) AS hours_ago,
            DATE_FORMAT(dl.log_time, '%W, %M %e, %Y at %H:%i') AS formatted_time,
            CASE 
                WHEN TIMESTAMPDIFF(HOUR, dl.log_time, NOW()) < 1 THEN 'Just now'
                WHEN TIMESTAMPDIFF(HOUR, dl.log_time, NOW()) < 24 THEN 'Today'
                WHEN TIMESTAMPDIFF(DAY, dl.log_time, NOW()) < 7 THEN 'This week'
                WHEN TIMESTAMPDIFF(DAY, dl.log_time, NOW()) < 30 THEN 'This month'
                ELSE 'Older'
            END AS time_category
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        ORDER BY dl.log_time DESC
        LIMIT 15";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'Date & Time Functions',
    'description' => 'Log Timestamp Analysis - Uses DATE, TIME, DATEDIFF, TIMESTAMPDIFF, DATE_FORMAT, and more',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-clock'
];