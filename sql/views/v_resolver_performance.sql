-- Resolver Performance View
-- Features: Complex aggregation, TIMESTAMPDIFF, Performance metrics, User analysis
CREATE OR REPLACE VIEW v_resolver_performance AS
SELECT 
    u.user_id,
    CONCAT(u.f_name, ' ', u.l_name) as resolver_name,
    u.email,
    COUNT(DISTINCT dl.log_id) as total_resolved,
    COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) as errors_resolved,
    COUNT(DISTINCT CASE WHEN dl.log_type = 'warning' THEN dl.log_id END) as warnings_resolved,
    AVG(TIMESTAMPDIFF(HOUR, dl.log_time, dl.resolved_at)) as avg_resolution_time_hours,
    COUNT(DISTINCT dl.d_id) as devices_worked_on,
    COUNT(DISTINCT dep.loc_id) as locations_covered
FROM users u
INNER JOIN device_logs dl ON u.user_id = dl.resolved_by
INNER JOIN devices d ON dl.d_id = d.d_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
WHERE dl.resolved_at IS NOT NULL
GROUP BY u.user_id, u.f_name, u.l_name, u.email;