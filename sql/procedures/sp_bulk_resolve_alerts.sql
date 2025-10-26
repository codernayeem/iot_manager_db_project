-- Procedure: Bulk Resolve Alerts by Device
-- Features: WHILE LOOP, IF/ELSE, Variables, UPDATE operations
-- Resolves multiple alerts for a device with batch processing
DROP PROCEDURE IF EXISTS sp_bulk_resolve_alerts;

DELIMITER $$
CREATE PROCEDURE sp_bulk_resolve_alerts(
    IN p_device_id INT,
    IN p_resolver_id INT,
    IN p_resolution_notes TEXT,
    OUT p_alerts_resolved INT
)
BEGIN
    DECLARE v_log_id INT;
    DECLARE v_severity INT;
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_total_alerts INT DEFAULT 0;
    DECLARE v_current_index INT DEFAULT 0;
    DECLARE v_resolution_text TEXT;
    
    -- Initialize output parameter
    SET p_alerts_resolved = 0;
    
    -- Check if device exists
    IF NOT EXISTS (SELECT 1 FROM devices WHERE d_id = p_device_id) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Device not found';
    END IF;
    
    -- Check if user exists
    IF NOT EXISTS (SELECT 1 FROM users WHERE user_id = p_resolver_id) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'User not found';
    END IF;
    
    -- Get count of active alerts for this device
    SELECT COUNT(*) INTO v_total_alerts
    FROM alerts a
    INNER JOIN device_logs dl ON a.log_id = dl.log_id
    WHERE dl.d_id = p_device_id AND a.status = 'active';
    
    -- If no alerts to resolve
    IF v_total_alerts = 0 THEN
        SET p_alerts_resolved = 0;
    ELSE
        -- Create temporary table to store log IDs
        CREATE TEMPORARY TABLE IF NOT EXISTS temp_alert_logs (
            log_id INT,
            severity_level INT,
            row_num INT AUTO_INCREMENT PRIMARY KEY
        );
        
        TRUNCATE TABLE temp_alert_logs;
        
        -- Populate temporary table with active alerts
        INSERT INTO temp_alert_logs (log_id, severity_level)
        SELECT dl.log_id, dl.severity_level
        FROM device_logs dl
        INNER JOIN alerts a ON dl.log_id = a.log_id
        WHERE dl.d_id = p_device_id AND a.status = 'active'
        ORDER BY dl.severity_level DESC, dl.log_time ASC;
        
        -- WHILE LOOP to process each alert
        SET v_current_index = 1;
        
        WHILE v_current_index <= v_total_alerts DO
            -- Get log_id from temp table
            SELECT log_id, severity_level INTO v_log_id, v_severity
            FROM temp_alert_logs
            WHERE row_num = v_current_index;
            
            -- Build resolution text based on severity
            IF v_severity >= 8 THEN
                SET v_resolution_text = CONCAT('CRITICAL: ', p_resolution_notes);
            ELSEIF v_severity >= 6 THEN
                SET v_resolution_text = CONCAT('HIGH: ', p_resolution_notes);
            ELSE
                SET v_resolution_text = p_resolution_notes;
            END IF;
            
            -- Update the device log with resolution
            UPDATE device_logs
            SET resolved_by = p_resolver_id,
                resolved_at = CURRENT_TIMESTAMP,
                resolution_notes = v_resolution_text
            WHERE log_id = v_log_id;
            
            -- The trigger trg_update_alert_status will automatically update alert status
            
            SET v_counter = v_counter + 1;
            SET v_current_index = v_current_index + 1;
            
        END WHILE;
        
        SET p_alerts_resolved = v_counter;
        
        -- Clean up
        DROP TEMPORARY TABLE IF EXISTS temp_alert_logs;
    END IF;
    
    -- Return summary
    SELECT 
        p_device_id AS device_id,
        p_alerts_resolved AS alerts_resolved,
        v_total_alerts AS total_alerts_found,
        CONCAT(u.f_name, ' ', u.l_name) AS resolved_by_name
    FROM users u
    WHERE u.user_id = p_resolver_id;
    
END$$
DELIMITER ;
