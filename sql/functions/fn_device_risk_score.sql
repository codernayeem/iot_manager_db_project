-- Device Risk Score Function
-- Features: User Defined Function, Risk Assessment Algorithm, Complex Calculations
DROP FUNCTION IF EXISTS fn_device_risk_score;

DELIMITER $$
CREATE FUNCTION fn_device_risk_score(device_id INT)
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE error_count INT DEFAULT 0;
    DECLARE warning_count INT DEFAULT 0;
    DECLARE days_since_maintenance INT DEFAULT 0;
    DECLARE risk_score INT DEFAULT 0;
    
    -- Count recent errors and warnings
    SELECT 
        COUNT(CASE WHEN log_type = 'error' THEN 1 END),
        COUNT(CASE WHEN log_type = 'warning' THEN 1 END)
    INTO error_count, warning_count
    FROM device_logs 
    WHERE d_id = device_id 
    AND log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Check days since last maintenance
    SELECT COALESCE(DATEDIFF(NOW(), last_maintenance), 365)
    INTO days_since_maintenance
    FROM devices 
    WHERE d_id = device_id;
    
    -- Calculate risk score
    SET risk_score = (error_count * 10) + (warning_count * 2) + 
                   CASE 
                       WHEN days_since_maintenance > 365 THEN 20
                       WHEN days_since_maintenance > 180 THEN 10
                       WHEN days_since_maintenance > 90 THEN 5
                       ELSE 0
                   END;
    
    RETURN LEAST(100, risk_score);
END$$
DELIMITER ;