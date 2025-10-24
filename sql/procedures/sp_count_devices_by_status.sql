-- Simple Procedure: Count Devices with CURSOR
-- Features: CURSOR, LOOP, Variables, IF condition
DROP PROCEDURE IF EXISTS sp_count_devices_by_status;

DELIMITER $$
CREATE PROCEDURE sp_count_devices_by_status()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE device_status VARCHAR(50);
    DECLARE status_count INT;
    
    -- Declare cursor to iterate through statuses
    DECLARE status_cursor CURSOR FOR 
        SELECT status, COUNT(*) as count 
        FROM devices 
        GROUP BY status;
    
    -- Declare handler for end of cursor
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Create temporary table to store results
    CREATE TEMPORARY TABLE IF NOT EXISTS temp_status_counts (
        status VARCHAR(50),
        device_count INT,
        status_label VARCHAR(100)
    );
    
    -- Clear temp table
    DELETE FROM temp_status_counts;
    
    -- Open cursor and loop through results
    OPEN status_cursor;
    
    read_loop: LOOP
        FETCH status_cursor INTO device_status, status_count;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- IF/ELSE: Add label based on status
        IF device_status = 'active' THEN
            INSERT INTO temp_status_counts VALUES (device_status, status_count, 'Active Devices');
        ELSEIF device_status = 'inactive' THEN
            INSERT INTO temp_status_counts VALUES (device_status, status_count, 'Inactive Devices');
        ELSEIF device_status = 'maintenance' THEN
            INSERT INTO temp_status_counts VALUES (device_status, status_count, 'Under Maintenance');
        ELSE
            INSERT INTO temp_status_counts VALUES (device_status, status_count, 'Other Status');
        END IF;
        
    END LOOP;
    
    CLOSE status_cursor;
    
    -- Return results
    SELECT * FROM temp_status_counts ORDER BY device_count DESC;
    
    -- Clean up
    DROP TEMPORARY TABLE IF EXISTS temp_status_counts;
END$$
DELIMITER ;
