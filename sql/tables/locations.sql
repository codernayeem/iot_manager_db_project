-- Locations Table with spatial data type (if MySQL supports it)
-- Features: Primary Key, Auto Increment, Decimal precision, Indexes, Timestamps
CREATE TABLE IF NOT EXISTS locations (
    loc_id INT PRIMARY KEY AUTO_INCREMENT,
    loc_name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location_name (loc_name),
    INDEX idx_coordinates (latitude, longitude)
) ENGINE=InnoDB;