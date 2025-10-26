-- Trigger: Automatically update updated_at timestamp when location is modified
-- BEFORE UPDATE trigger
DROP TRIGGER IF EXISTS trg_locations_updated_at;

DELIMITER $$
CREATE TRIGGER trg_locations_updated_at
BEFORE UPDATE ON locations
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;
