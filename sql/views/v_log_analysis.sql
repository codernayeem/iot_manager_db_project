-- Log Analysis View
-- Features: DATE functions, Aggregation, AVG, Window functions concept, Time-based filtering
CREATE OR REPLACE VIEW v_log_analysis AS
SELECT 
    DATE(dl.log_time) as log_date,
    dl.log_type,
    d.d_name,
    dt.t_name as device_type,
    l.loc_name,
    COUNT(*) as log_count,
    AVG(dl.severity_level) as avg_severity,
    COUNT(CASE WHEN dl.resolved_by IS NULL THEN 1 END) as unresolved_count,
    COUNT(CASE WHEN dl.resolved_by IS NOT NULL THEN 1 END) as resolved_count
FROM device_logs dl
INNER JOIN devices d ON dl.d_id = d.d_id
INNER JOIN device_types dt ON d.t_id = dt.t_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
LEFT JOIN locations l ON dep.loc_id = l.loc_id
WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY DATE(dl.log_time), dl.log_type, d.d_name, dt.t_name, l.loc_name;