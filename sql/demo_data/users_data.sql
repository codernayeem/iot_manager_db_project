-- Demo Data for Users Table
-- Features: INSERT IGNORE, Password Hashing (Demo purposes)

-- Admin user
INSERT IGNORE INTO users (f_name, l_name, email, password) VALUES 
('System', 'Administrator', 'admin@iot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Technician users (all with password: password123)
INSERT IGNORE INTO users (f_name, l_name, email, password) VALUES 
('John', 'Smith', 'john.smith@tech.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Sarah', 'Johnson', 'sarah.j@tech.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Mike', 'Brown', 'mike.brown@tech.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Lisa', 'Davis', 'lisa.davis@tech.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');