-- Cleanup Old Logs Procedure
-- Features: Stored Procedure, Transaction Control, Error Handling, Data Maintenance
DROP PROCEDURE IF EXISTS sp_cleanup_old_logs;

DELIMITER $$
CREATE PROCEDURE sp_cleanup_old_logs(
    IN days_to_keep INT,
    OUT deleted_count INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    -- Set default value if null or invalid
    IF days_to_keep IS NULL OR days_to_keep <= 0 THEN
        SET days_to_keep = 365;
    END IF;
    
    START TRANSACTION;
    
    SELECT COUNT(*) INTO deleted_count 
    FROM device_logs 
    WHERE log_time < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    DELETE FROM device_logs 
    WHERE log_time < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    COMMIT;
END$$
DELIMITER ;