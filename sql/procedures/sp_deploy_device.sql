-- Deploy Device Procedure
-- Features: Stored Procedure, Complex Business Logic, Transaction Control, Validation
DROP PROCEDURE IF EXISTS sp_deploy_device;

DELIMITER $$
CREATE PROCEDURE sp_deploy_device(
    IN device_id INT,
    IN location_id INT,
    IN deployed_by_user INT,
    IN deployment_notes TEXT,
    OUT success BOOLEAN,
    OUT message VARCHAR(255)
)
BEGIN
    DECLARE device_count INT DEFAULT 0;
    DECLARE location_count INT DEFAULT 0;
    DECLARE existing_deployment INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET success = FALSE;
        SET message = 'Deployment failed due to database error';
    END;
    
    START TRANSACTION;
    
    -- Validate device exists
    SELECT COUNT(*) INTO device_count FROM devices WHERE d_id = device_id;
    IF device_count = 0 THEN
        SET success = FALSE;
        SET message = 'Device not found';
        ROLLBACK;
    ELSE
        -- Validate location exists
        SELECT COUNT(*) INTO location_count FROM locations WHERE loc_id = location_id;
        IF location_count = 0 THEN
            SET success = FALSE;
            SET message = 'Location not found';
            ROLLBACK;
        ELSE
            -- Check for existing active deployment
            SELECT COUNT(*) INTO existing_deployment 
            FROM deployments 
            WHERE d_id = device_id AND is_active = 1;
            
            IF existing_deployment > 0 THEN
                -- Deactivate existing deployment
                UPDATE deployments SET is_active = 0 WHERE d_id = device_id AND is_active = 1;
            END IF;
            
            -- Create new deployment
            INSERT INTO deployments (d_id, loc_id, deployed_by, deployment_notes, is_active)
            VALUES (device_id, location_id, deployed_by_user, deployment_notes, 1);
            
            -- Update device status
            UPDATE devices SET status = 'active' WHERE d_id = device_id;
            
            SET success = TRUE;
            SET message = 'Device deployed successfully';
            COMMIT;
        END IF;
    END IF;
END$$
DELIMITER ;