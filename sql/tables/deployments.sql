-- Deployments Table (Junction table for Many-to-Many relationship)
-- Features: Composite Primary Key, Foreign Keys, Timestamp
CREATE TABLE IF NOT EXISTS deployments (
    d_id INT NOT NULL,
    loc_id INT NOT NULL,
    deployed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (d_id, loc_id),
    FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (loc_id) REFERENCES locations(loc_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_deployment_date (deployed_at)
) ENGINE=InnoDB;