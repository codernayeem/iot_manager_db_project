-- Demo Data for Users Table
-- Features: INSERT IGNORE, Password Hashing (Demo purposes)

-- Admin user
INSERT IGNORE INTO users (f_name, l_name, email, password) VALUES 
('System', 'Administrator', 'admin@iot.com', '$2y$10$bpJAqt1HvFRJA.vsdaRTvO2B78mUY0SlKiAitrDLVWNewr/J/is5a');

-- Technician users (all with password: password123)
INSERT IGNORE INTO users (f_name, l_name, email, password) VALUES 
('John', 'Smith', 'john.smith@tech.com', '$2y$10$bpJAqt1HvFRJA.vsdaRTvO2B78mUY0SlKiAitrDLVWNewr/J/is5a'),
('Sarah', 'Johnson', 'sarah.j@tech.com', '$2y$10$bpJAqt1HvFRJA.vsdaRTvO2B78mUY0SlKiAitrDLVWNewr/J/is5a'),
('Mike', 'Brown', 'mike.brown@tech.com', '$2y$10$bpJAqt1HvFRJA.vsdaRTvO2B78mUY0SlKiAitrDLVWNewr/J/is5a'),
('Lisa', 'Davis', 'lisa.davis@tech.com', '$2y$10$bpJAqt1HvFRJA.vsdaRTvO2B78mUY0SlKiAitrDLVWNewr/J/is5a');