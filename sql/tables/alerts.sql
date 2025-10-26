-- Alerts Table linked to device_logs
-- Features: Primary Key (log_id), Foreign Key, ENUM, Timestamps, Indexes
-- Alerts are auto-created from high severity error logs (severity_level > 5)
CREATE TABLE IF NOT EXISTS alerts (
    log_id INT PRIMARY KEY,
    alert_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    message TEXT NOT NULL,
    status ENUM('active', 'resolved') DEFAULT 'active',
    FOREIGN KEY (log_id) REFERENCES device_logs(log_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_alert_status (status),
    INDEX idx_alert_time (alert_time)
) ENGINE=InnoDB;
