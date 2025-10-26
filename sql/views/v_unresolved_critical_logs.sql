-- View: Unresolved Critical Logs
-- Shows critical logs that need attention with comprehensive device and user information
-- Features: Multiple JOINs, WHERE clause filtering, CASE statement
CREATE OR REPLACE VIEW v_unresolved_critical_logs AS
SELECT 
    dl.log_id,
    dl.d_id,
    d.d_name AS device_name,
    d.serial_number,
    dt.t_name AS device_type,
    CONCAT(u.f_name, ' ', u.l_name) AS device_owner,
    dl.log_time,
    dl.log_type,
    dl.message,
    dl.severity_level,
    CASE 
        WHEN dl.severity_level >= 8 THEN 'Critical'
        WHEN dl.severity_level >= 6 THEN 'High'
        WHEN dl.severity_level >= 4 THEN 'Medium'
        ELSE 'Low'
    END AS severity_category,
    a.alert_time,
    a.status AS alert_status,
    l.loc_name AS device_location,
    DATEDIFF(CURRENT_TIMESTAMP, dl.log_time) AS days_unresolved
FROM device_logs dl
INNER JOIN devices d ON dl.d_id = d.d_id
INNER JOIN device_types dt ON d.t_id = dt.t_id
INNER JOIN users u ON d.user_id = u.user_id
LEFT JOIN alerts a ON dl.log_id = a.log_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id
LEFT JOIN locations l ON dep.loc_id = l.loc_id
WHERE dl.resolved_by IS NULL 
  AND dl.log_type = 'error'
  AND dl.severity_level > 5
ORDER BY dl.severity_level DESC, dl.log_time ASC;

