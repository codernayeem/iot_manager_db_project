-- View: Device Deployment Summary
-- Shows devices with deployment location and alert information
-- Features: JOINs, LEFT JOINs, Subquery, GROUP BY, Aggregation
CREATE OR REPLACE VIEW v_device_deployment_summary AS
SELECT 
    d.d_id,
    d.d_name,
    d.serial_number,
    d.status,
    dt.t_name AS device_type,
    dt.`desc` AS type_description,
    CONCAT(u.f_name, ' ', u.l_name) AS owner_name,
    u.email AS owner_email,
    l.loc_name AS deployment_location,
    l.address AS deployment_address,
    dep.deployed_at,
    COUNT(DISTINCT a.log_id) AS active_alert_count,
    (SELECT COUNT(*) 
     FROM device_logs dl 
     WHERE dl.d_id = d.d_id AND dl.resolved_by IS NULL) AS unresolved_log_count
FROM devices d
INNER JOIN device_types dt ON d.t_id = dt.t_id
INNER JOIN users u ON d.user_id = u.user_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id
LEFT JOIN locations l ON dep.loc_id = l.loc_id
LEFT JOIN device_logs dl ON d.d_id = dl.d_id
LEFT JOIN alerts a ON dl.log_id = a.log_id AND a.status = 'active'
GROUP BY d.d_id, d.d_name, d.serial_number, d.status, dt.t_name, dt.`desc`, 
         u.f_name, u.l_name, u.email, l.loc_name, l.address, dep.deployed_at;

