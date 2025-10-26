-- Function: Calculate Device Health Score
-- Features: Variables, IF/ELSE, Multiple calculations, Subqueries
-- Returns a health score (0-100) based on device logs and alerts
DROP FUNCTION IF EXISTS fn_get_device_health_score;

DELIMITER $$
CREATE FUNCTION fn_get_device_health_score(p_device_id INT)
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_health_score DECIMAL(5,2) DEFAULT 100.0;
    DECLARE v_error_count INT DEFAULT 0;
    DECLARE v_warning_count INT DEFAULT 0;
    DECLARE v_info_count INT DEFAULT 0;
    DECLARE v_unresolved_count INT DEFAULT 0;
    DECLARE v_active_alert_count INT DEFAULT 0;
    DECLARE v_avg_severity DECIMAL(5,2) DEFAULT 0;
    DECLARE v_device_exists INT DEFAULT 0;
    
    -- Check if device exists
    SELECT COUNT(*) INTO v_device_exists FROM devices WHERE d_id = p_device_id;
    
    IF v_device_exists = 0 THEN
        RETURN -1; -- Device not found
    END IF;
    
    -- Get log counts by type
    SELECT 
        SUM(CASE WHEN log_type = 'error' THEN 1 ELSE 0 END),
        SUM(CASE WHEN log_type = 'warning' THEN 1 ELSE 0 END),
        SUM(CASE WHEN log_type = 'info' THEN 1 ELSE 0 END)
    INTO v_error_count, v_warning_count, v_info_count
    FROM device_logs
    WHERE d_id = p_device_id;
    
    -- Get unresolved log count
    SELECT COUNT(*) INTO v_unresolved_count
    FROM device_logs
    WHERE d_id = p_device_id AND resolved_by IS NULL AND log_type IN ('error', 'warning');
    
    -- Get active alert count
    SELECT COUNT(*) INTO v_active_alert_count
    FROM device_logs dl
    INNER JOIN alerts a ON dl.log_id = a.log_id
    WHERE dl.d_id = p_device_id AND a.status = 'active';
    
    -- Get average severity of errors
    SELECT COALESCE(AVG(severity_level), 0) INTO v_avg_severity
    FROM device_logs
    WHERE d_id = p_device_id AND log_type = 'error';
    
    -- Calculate health score with penalties
    SET v_health_score = 100.0;
    
    -- Penalty for errors (5 points each)
    SET v_health_score = v_health_score - (v_error_count * 5.0);
    
    -- Penalty for warnings (2 points each)
    SET v_health_score = v_health_score - (v_warning_count * 2.0);
    
    -- Extra penalty for unresolved issues (3 points each)
    SET v_health_score = v_health_score - (v_unresolved_count * 3.0);
    
    -- Significant penalty for active alerts (10 points each)
    SET v_health_score = v_health_score - (v_active_alert_count * 10.0);
    
    -- Penalty based on average severity
    SET v_health_score = v_health_score - (v_avg_severity * 2.0);
    
    -- Ensure score is within bounds
    IF v_health_score < 0 THEN
        SET v_health_score = 0;
    ELSEIF v_health_score > 100 THEN
        SET v_health_score = 100;
    END IF;
    
    RETURN v_health_score;
END$$
DELIMITER ;
