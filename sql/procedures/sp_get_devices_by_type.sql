-- Simple Procedure: Get Devices by Type with IF/ELSE and LOOP
-- Features: IF/ELSE, WHILE LOOP, Variables, SELECT with WHERE
DROP PROCEDURE IF EXISTS sp_get_devices_by_type;

DELIMITER $$
CREATE PROCEDURE sp_get_devices_by_type(IN type_id INT)
BEGIN
    DECLARE device_count INT DEFAULT 0;
    DECLARE counter INT DEFAULT 0;
    
    -- IF/ELSE: Check if type exists
    SELECT COUNT(*) INTO device_count FROM device_types WHERE t_id = type_id;
    
    IF device_count = 0 THEN
        -- Type doesn't exist
        SELECT 'Error: Device type not found' as message;
    ELSE
        -- Type exists, get devices
        SELECT 
            d.d_id,
            d.d_name,
            dt.t_name as device_type,
            d.status,
            d.serial_number
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        WHERE d.t_id = type_id;
        
        -- WHILE LOOP: Count devices (example of loop usage)
        SELECT COUNT(*) INTO device_count FROM devices WHERE t_id = type_id;
        SET counter = 0;
        
        WHILE counter < device_count DO
            SET counter = counter + 1;
        END WHILE;
        
        -- Return the count
        SELECT CONCAT('Found ', counter, ' devices') as result_message;
    END IF;
END$$
DELIMITER ;
