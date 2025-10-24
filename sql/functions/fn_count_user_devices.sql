-- Simple Function: Count User Devices with IF condition
-- Features: Variables, IF/ELSE, SELECT INTO, RETURN
DROP FUNCTION IF EXISTS fn_count_user_devices;

DELIMITER $$
CREATE FUNCTION fn_count_user_devices(user_id_param INT)
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE device_count INT DEFAULT 0;
    DECLARE user_exists INT DEFAULT 0;
    
    -- Variable: Check if user exists
    SELECT COUNT(*) INTO user_exists FROM users WHERE user_id = user_id_param;
    
    -- IF/ELSE: Only count if user exists
    IF user_exists > 0 THEN
        SELECT COUNT(*) INTO device_count
        FROM devices
        WHERE user_id = user_id_param;
    ELSE
        SET device_count = -1; -- Return -1 if user doesn't exist
    END IF;
    
    RETURN device_count;
END$$
DELIMITER ;
