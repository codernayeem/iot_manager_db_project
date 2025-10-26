-- Devices Table with Foreign Key constraints
-- Features: Primary Key, Auto Increment, Foreign Keys, ENUM, Indexes, Timestamps
CREATE TABLE IF NOT EXISTS devices (
    d_id INT PRIMARY KEY AUTO_INCREMENT,
    d_name VARCHAR(100) NOT NULL,
    t_id INT NOT NULL,
    user_id INT NOT NULL,
    serial_number VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'inactive',
    purchase_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (t_id) REFERENCES device_types(t_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_device_name (d_name),
    INDEX idx_serial (serial_number),
    INDEX idx_status (status),
    INDEX idx_user_device (user_id, d_name)
) ENGINE=InnoDB;