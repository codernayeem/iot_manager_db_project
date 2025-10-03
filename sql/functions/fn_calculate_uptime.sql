-- Calculate Uptime Function
-- Features: User Defined Function, Mathematical Calculations, Data Analysis
DROP FUNCTION IF EXISTS fn_calculate_uptime;

DELIMITER $$
CREATE FUNCTION fn_calculate_uptime(device_id INT, days_back INT)
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_hours DECIMAL(10,2);
    DECLARE error_hours DECIMAL(10,2);
    DECLARE uptime_percentage DECIMAL(5,2);
    
    SET total_hours = days_back * 24;
    
    SELECT COALESCE(SUM(
        CASE 
            WHEN log_type = 'error' THEN 1.0
            ELSE 0.0 
        END
    ), 0) INTO error_hours
    FROM device_logs 
    WHERE d_id = device_id 
    AND log_time >= DATE_SUB(NOW(), INTERVAL days_back DAY);
    
    SET uptime_percentage = GREATEST(0, ((total_hours - error_hours) / total_hours) * 100);
    
    RETURN uptime_percentage;
END$$
DELIMITER ;