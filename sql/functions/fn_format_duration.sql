-- Format Duration Function
-- Features: User Defined Function, String Manipulation, Utility Function
DROP FUNCTION IF EXISTS fn_format_duration;

DELIMITER $$
CREATE FUNCTION fn_format_duration(hours DECIMAL(10,2))
RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE days INT;
    DECLARE remaining_hours INT;
    DECLARE result VARCHAR(50);
    
    IF hours IS NULL OR hours < 0 THEN
        RETURN 'N/A';
    END IF;
    
    SET days = FLOOR(hours / 24);
    SET remaining_hours = hours % 24;
    
    IF days > 0 THEN
        SET result = CONCAT(days, 'd ', remaining_hours, 'h');
    ELSE
        SET result = CONCAT(remaining_hours, 'h');
    END IF;
    
    RETURN result;
END$$
DELIMITER ;