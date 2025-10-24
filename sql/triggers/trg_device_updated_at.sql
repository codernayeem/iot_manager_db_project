-- Trigger: Automatically update updated_at timestamp when device is modified
-- Simple BEFORE UPDATE trigger
DROP TRIGGER IF EXISTS trg_device_updated_at;

DELIMITER $$
CREATE TRIGGER trg_device_updated_at
BEFORE UPDATE ON devices
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END$$
DELIMITER ;
