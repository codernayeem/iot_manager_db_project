-- Device Logs Table with Full-text search
-- Features: Primary Key, Auto Increment, Foreign Keys, ENUM, Full-text Index, Multiple Indexes
CREATE TABLE IF NOT EXISTS device_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    d_id INT NOT NULL,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    log_type ENUM('error', 'warning', 'info', 'debug') NOT NULL,
    message TEXT NOT NULL,
    severity_level INT DEFAULT 1,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_device_logs (d_id, log_time),
    INDEX idx_log_type (log_type),
    INDEX idx_severity (severity_level),
    INDEX idx_unresolved (resolved_by, log_type),
    FULLTEXT idx_message_search (message)
) ENGINE=InnoDB;