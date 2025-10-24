-- Trigger: Automatically log when a new device is added
-- Simple AFTER INSERT trigger to track device creation
DROP TRIGGER IF EXISTS trg_log_new_device;

DELIMITER $$
CREATE TRIGGER trg_log_new_device
AFTER INSERT ON devices
FOR EACH ROW
BEGIN
    INSERT INTO device_logs (d_id, log_type, message, severity_level)
    VALUES (NEW.d_id, 'info', CONCAT('New device created: ', NEW.d_name), 1);
END$$
DELIMITER ;
