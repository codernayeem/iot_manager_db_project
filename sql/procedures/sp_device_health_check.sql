-- Device Health Check Procedure
-- Features: Stored Procedure, IN/OUT parameters, Variables, Control Flow, Calculations
DROP PROCEDURE IF EXISTS sp_device_health_check;

DELIMITER $$
CREATE PROCEDURE sp_device_health_check(
    IN device_id INT,
    IN days_back INT,
    OUT health_score DECIMAL(5,2),
    OUT status_message VARCHAR(255)
)
BEGIN
    DECLARE error_count INT DEFAULT 0;
    DECLARE warning_count INT DEFAULT 0;
    DECLARE total_logs INT DEFAULT 0;
    DECLARE last_log_date DATE;
    
    -- Set default value for days_back if null or 0
    IF days_back IS NULL OR days_back <= 0 THEN
        SET days_back = 30;
    END IF;
    
    -- Get log statistics
    SELECT 
        COUNT(*),
        COUNT(CASE WHEN log_type = 'error' THEN 1 END),
        COUNT(CASE WHEN log_type = 'warning' THEN 1 END),
        MAX(DATE(log_time))
    INTO total_logs, error_count, warning_count, last_log_date
    FROM device_logs 
    WHERE d_id = device_id 
    AND log_time >= DATE_SUB(NOW(), INTERVAL days_back DAY);
    
    -- Calculate health score
    IF total_logs = 0 THEN
        SET health_score = 0;
        SET status_message = 'No data available';
    ELSE
        SET health_score = ((total_logs - error_count - (warning_count * 0.5)) / total_logs) * 100;
        
        IF health_score >= 90 THEN
            SET status_message = 'Excellent health';
        ELSEIF health_score >= 70 THEN
            SET status_message = 'Good health';
        ELSEIF health_score >= 50 THEN
            SET status_message = 'Fair health - needs attention';
        ELSE
            SET status_message = 'Poor health - immediate action required';
        END IF;
    END IF;
END$$
DELIMITER ;