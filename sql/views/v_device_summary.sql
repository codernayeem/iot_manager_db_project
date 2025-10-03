-- Device Summary View
-- Features: Complex JOIN, Aggregation, GROUP BY, Date Functions, CASE statements
CREATE OR REPLACE VIEW v_device_summary AS
SELECT 
    d.d_id,
    d.d_name,
    dt.t_name as device_type,
    l.loc_name as location,
    d.status,
    d.serial_number,
    CONCAT(u.f_name, ' ', u.l_name) as owner_name,
    COUNT(dl.log_id) as total_logs,
    COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as error_count,
    COUNT(CASE WHEN dl.log_type = 'warning' THEN 1 END) as warning_count,
    MAX(dl.log_time) as last_log_time,
    DATEDIFF(NOW(), MAX(dl.log_time)) as days_since_last_log
FROM devices d
INNER JOIN device_types dt ON d.t_id = dt.t_id
INNER JOIN users u ON d.user_id = u.user_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
LEFT JOIN locations l ON dep.loc_id = l.loc_id
LEFT JOIN device_logs dl ON d.d_id = dl.d_id
GROUP BY d.d_id, d.d_name, dt.t_name, l.loc_name, d.status, d.serial_number, u.f_name, u.l_name;