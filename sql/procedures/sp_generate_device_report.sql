-- Procedure: Generate Device Report with detailed statistics
-- Features: CURSOR, LOOP, Variables, IF/ELSE, Temporary table
-- Uses cursor to iterate through devices and calculate comprehensive statistics
DROP PROCEDURE IF EXISTS sp_generate_device_report;

DELIMITER $$
CREATE PROCEDURE sp_generate_device_report(IN device_status VARCHAR(20))
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_device_id INT;
    DECLARE v_device_name VARCHAR(100);
    DECLARE v_serial_number VARCHAR(100);
    DECLARE v_owner_name VARCHAR(100);
    DECLARE v_log_count INT;
    DECLARE v_error_count INT;
    DECLARE v_warning_count INT;
    DECLARE v_alert_count INT;
    DECLARE v_health_score DECIMAL(5,2);
    
    -- Declare cursor to iterate through devices
    DECLARE device_cursor CURSOR FOR 
        SELECT d.d_id, d.d_name, d.serial_number, CONCAT(u.f_name, ' ', u.l_name) AS owner
        FROM devices d
        INNER JOIN users u ON d.user_id = u.user_id
        WHERE device_status IS NULL OR d.status = device_status;
    
    -- Declare handler for end of cursor
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Create temporary table to store report results
    CREATE TEMPORARY TABLE IF NOT EXISTS temp_device_report (
        device_id INT,
        device_name VARCHAR(100),
        serial_number VARCHAR(100),
        owner_name VARCHAR(100),
        total_logs INT,
        error_logs INT,
        warning_logs INT,
        active_alerts INT,
        health_score DECIMAL(5,2),
        health_status VARCHAR(20)
    );
    
    -- Clear temp table
    TRUNCATE TABLE temp_device_report;
    
    -- Open cursor and loop through devices
    OPEN device_cursor;
    
    read_loop: LOOP
        FETCH device_cursor INTO v_device_id, v_device_name, v_serial_number, v_owner_name;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Get log statistics for this device
        SELECT COUNT(*) INTO v_log_count
        FROM device_logs
        WHERE d_id = v_device_id;
        
        SELECT COUNT(*) INTO v_error_count
        FROM device_logs
        WHERE d_id = v_device_id AND log_type = 'error';
        
        SELECT COUNT(*) INTO v_warning_count
        FROM device_logs
        WHERE d_id = v_device_id AND log_type = 'warning';
        
        -- Get active alert count
        SELECT COUNT(*) INTO v_alert_count
        FROM device_logs dl
        INNER JOIN alerts a ON dl.log_id = a.log_id
        WHERE dl.d_id = v_device_id AND a.status = 'active';
        
        -- Calculate health score (100 - penalties)
        SET v_health_score = 100.0;
        SET v_health_score = v_health_score - (v_error_count * 5.0);
        SET v_health_score = v_health_score - (v_warning_count * 2.0);
        SET v_health_score = v_health_score - (v_alert_count * 10.0);
        
        -- Ensure health score is between 0 and 100
        IF v_health_score < 0 THEN
            SET v_health_score = 0;
        END IF;
        
        -- Insert into temporary table with calculated health status
        INSERT INTO temp_device_report 
        VALUES (
            v_device_id,
            v_device_name,
            v_serial_number,
            v_owner_name,
            v_log_count,
            v_error_count,
            v_warning_count,
            v_alert_count,
            v_health_score,
            CASE 
                WHEN v_health_score >= 80 THEN 'Excellent'
                WHEN v_health_score >= 60 THEN 'Good'
                WHEN v_health_score >= 40 THEN 'Fair'
                WHEN v_health_score >= 20 THEN 'Poor'
                ELSE 'Critical'
            END
        );
        
    END LOOP;
    
    CLOSE device_cursor;
    
    -- Return results ordered by health score
    SELECT * FROM temp_device_report ORDER BY health_score DESC, error_logs DESC;
    
    -- Clean up
    DROP TEMPORARY TABLE IF EXISTS temp_device_report;
END$$
DELIMITER ;
