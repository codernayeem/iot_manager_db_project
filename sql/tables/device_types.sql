-- Device Types Table with CHECK constraint (MySQL 8.0+)
-- Features: Primary Key, Auto Increment, Unique Constraint, Indexes
CREATE TABLE IF NOT EXISTS device_types (
    t_id INT PRIMARY KEY AUTO_INCREMENT,
    t_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_name (t_name)
) ENGINE=InnoDB;