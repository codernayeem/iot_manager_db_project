-- Function: Get Alert Statistics Summary
-- Features: IF/ELSE, Variables, String manipulation, Calculations
-- Returns a formatted string with alert statistics
DROP FUNCTION IF EXISTS fn_get_alert_summary;

DELIMITER $$
CREATE FUNCTION fn_get_alert_summary(p_device_id INT)
RETURNS VARCHAR(255)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_active_count INT DEFAULT 0;
    DECLARE v_resolved_count INT DEFAULT 0;
    DECLARE v_total_count INT DEFAULT 0;
    DECLARE v_critical_count INT DEFAULT 0;
    DECLARE v_result VARCHAR(255);
    DECLARE v_status_text VARCHAR(50);
    
    -- Get alert counts
    SELECT 
        SUM(CASE WHEN a.status = 'active' THEN 1 ELSE 0 END),
        SUM(CASE WHEN a.status = 'resolved' THEN 1 ELSE 0 END),
        COUNT(*),
        SUM(CASE WHEN a.status = 'active' AND dl.severity_level >= 8 THEN 1 ELSE 0 END)
    INTO v_active_count, v_resolved_count, v_total_count, v_critical_count
    FROM device_logs dl
    INNER JOIN alerts a ON dl.log_id = a.log_id
    WHERE dl.d_id = p_device_id;
    
    -- Handle NULL values
    SET v_active_count = COALESCE(v_active_count, 0);
    SET v_resolved_count = COALESCE(v_resolved_count, 0);
    SET v_total_count = COALESCE(v_total_count, 0);
    SET v_critical_count = COALESCE(v_critical_count, 0);
    
    -- Determine status text based on active alerts
    IF v_critical_count > 0 THEN
        SET v_status_text = 'CRITICAL';
    ELSEIF v_active_count > 5 THEN
        SET v_status_text = 'HIGH ALERT';
    ELSEIF v_active_count > 0 THEN
        SET v_status_text = 'ATTENTION NEEDED';
    ELSEIF v_total_count > 0 THEN
        SET v_status_text = 'ALL RESOLVED';
    ELSE
        SET v_status_text = 'NO ALERTS';
    END IF;
    
    -- Build result string
    IF v_total_count = 0 THEN
        SET v_result = 'No alerts recorded';
    ELSE
        SET v_result = CONCAT(
            v_status_text, ' - ',
            'Active: ', v_active_count, ', ',
            'Resolved: ', v_resolved_count, ', ',
            'Total: ', v_total_count
        );
        
        IF v_critical_count > 0 THEN
            SET v_result = CONCAT(v_result, ' (', v_critical_count, ' critical)');
        END IF;
    END IF;
    
    RETURN v_result;
END$$
DELIMITER ;
