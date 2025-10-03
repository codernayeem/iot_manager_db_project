-- Deployments Table (Junction table for Many-to-Many relationship)
-- Features: Primary Key, Auto Increment, Foreign Keys, Unique Constraint, Boolean, Indexes
CREATE TABLE IF NOT EXISTS deployments (
    deployment_id INT PRIMARY KEY AUTO_INCREMENT,
    d_id INT NOT NULL,
    loc_id INT NOT NULL,
    deployed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deployed_by INT NOT NULL,
    deployment_notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (loc_id) REFERENCES locations(loc_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (deployed_by) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY unique_active_deployment (d_id, loc_id, is_active),
    INDEX idx_device_location (d_id, loc_id),
    INDEX idx_deployment_date (deployed_at)
) ENGINE=InnoDB;