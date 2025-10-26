-- Trigger: Automatically create alert from high severity error logs
-- AFTER INSERT trigger - creates alert for error logs with severity_level > 5
DROP TRIGGER IF EXISTS trg_create_alert_from_log;

DELIMITER $$
CREATE TRIGGER trg_create_alert_from_log
AFTER INSERT ON device_logs
FOR EACH ROW
BEGIN
    -- Only create alert for error logs with high severity (> 5)
    IF NEW.log_type = 'error' AND NEW.severity_level > 5 THEN
        INSERT INTO alerts (log_id, alert_time, message, status)
        VALUES (NEW.log_id, NEW.log_time, NEW.message, 'active');
    END IF;
END$$
DELIMITER ;
