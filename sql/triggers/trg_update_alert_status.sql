-- Trigger: Automatically update alert status when log is resolved
-- AFTER UPDATE trigger - marks alert as resolved when the associated log is resolved
DROP TRIGGER IF EXISTS trg_update_alert_status;

DELIMITER $$
CREATE TRIGGER trg_update_alert_status
AFTER UPDATE ON device_logs
FOR EACH ROW
BEGIN
    -- If log was just resolved, update corresponding alert status
    IF NEW.resolved_by IS NOT NULL AND OLD.resolved_by IS NULL THEN
        UPDATE alerts 
        SET status = 'resolved' 
        WHERE log_id = NEW.log_id AND status = 'active';
    END IF;
END$$
DELIMITER ;
