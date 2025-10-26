-- Trigger: Automatically update updated_at timestamp when user is modified
-- BEFORE UPDATE trigger
DROP TRIGGER IF EXISTS trg_users_updated_at;

DELIMITER $$
CREATE TRIGGER trg_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;
