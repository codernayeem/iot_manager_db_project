-- Trigger: Automatically update updated_at timestamp when device is modified
-- BEFORE UPDATE trigger
DROP TRIGGER IF EXISTS trg_devices_updated_at;

DELIMITER $$
CREATE TRIGGER trg_devices_updated_at
BEFORE UPDATE ON devices
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;
