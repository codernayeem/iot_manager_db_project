-- Simple Function: Get Device Status with IF/ELSE
-- Features: IF/ELSEIF/ELSE, Variables, RETURN
DROP FUNCTION IF EXISTS fn_device_status_text;

DELIMITER $$
CREATE FUNCTION fn_device_status_text(status_param VARCHAR(50))
RETURNS VARCHAR(100)
DETERMINISTIC
BEGIN
    DECLARE result_text VARCHAR(100);
    DECLARE status_upper VARCHAR(50);
    
    -- Variable: Convert to uppercase
    SET status_upper = UPPER(status_param);
    
    -- IF/ELSEIF/ELSE: Check status and assign text
    IF status_upper = 'ACTIVE' THEN
        SET result_text = 'Device is Active and Running';
    ELSEIF status_upper = 'INACTIVE' THEN
        SET result_text = 'Device is Inactive';
    ELSEIF status_upper = 'MAINTENANCE' THEN
        SET result_text = 'Device Under Maintenance';
    ELSEIF status_upper = 'ERROR' THEN
        SET result_text = 'Device Has Error';
    ELSE
        SET result_text = 'Unknown Device Status';
    END IF;
    
    RETURN result_text;
END$$
DELIMITER ;
