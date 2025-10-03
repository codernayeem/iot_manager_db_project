-- Resolve Issue Procedure
-- Features: Stored Procedure, Issue Resolution Logic, Validation, Transaction Control
DROP PROCEDURE IF EXISTS sp_resolve_issue;

DELIMITER $$
CREATE PROCEDURE sp_resolve_issue(
    IN log_id INT,
    IN resolver_user_id INT,
    IN resolution_notes TEXT,
    OUT success BOOLEAN,
    OUT message VARCHAR(255)
)
BEGIN
    DECLARE log_count INT DEFAULT 0;
    DECLARE already_resolved INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET success = FALSE;
        SET message = 'Resolution failed due to database error';
    END;
    
    START TRANSACTION;
    
    -- Check if log exists
    SELECT COUNT(*) INTO log_count FROM device_logs WHERE log_id = log_id;
    IF log_count = 0 THEN
        SET success = FALSE;
        SET message = 'Log entry not found';
        ROLLBACK;
    ELSE
        -- Check if already resolved
        SELECT COUNT(*) INTO already_resolved 
        FROM device_logs 
        WHERE log_id = log_id AND resolved_by IS NOT NULL;
        
        IF already_resolved > 0 THEN
            SET success = FALSE;
            SET message = 'Issue already resolved';
            ROLLBACK;
        ELSE
            -- Mark as resolved
            UPDATE device_logs 
            SET resolved_by = resolver_user_id,
                resolved_at = NOW(),
                resolution_notes = resolution_notes
            WHERE log_id = log_id;
            
            SET success = TRUE;
            SET message = 'Issue resolved successfully';
            COMMIT;
        END IF;
    END IF;
END$$
DELIMITER ;