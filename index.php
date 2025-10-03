<?php
session_start();
require_once 'config/database.php';

/**
 * SQL Features Used: CREATE DATABASE, CREATE TABLE with constraints
 */

$database = new Database();

// Create database if not exists
$database->createDatabase();

// Create tables with all constraints
$tablesCreated = $database->createTables();

if ($tablesCreated) {
    // Insert sample data if tables are empty
    $conn = $database->getConnection();
    
    // Check if we need to insert sample data
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        insertSampleData($conn);
    }
}

function insertSampleData($conn) {
    try {
        // SQL Feature: Transaction with COMMIT/ROLLBACK
        $conn->beginTransaction();
        
        // Insert Users - SQL Feature: INSERT with multiple values
        $sql = "INSERT INTO users (f_name, l_name, email, password) VALUES 
                ('John', 'Doe', 'john.doe@email.com', ?),
                ('Jane', 'Smith', 'jane.smith@email.com', ?),
                ('Admin', 'User', 'admin@iot.com', ?),
                ('Bob', 'Johnson', 'bob.johnson@email.com', ?),
                ('Alice', 'Brown', 'alice.brown@email.com', ?)";
        
        $stmt = $conn->prepare($sql);
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt->execute([$hashedPassword, $hashedPassword, $adminPassword, $hashedPassword, $hashedPassword]);
        
        // Insert Device Types
        $sql = "INSERT INTO device_types (t_name, description, icon) VALUES 
                ('Drone', 'Unmanned aerial vehicles for surveillance and monitoring', 'drone-icon.png'),
                ('CCTV Camera', 'Closed-circuit television cameras for security monitoring', 'camera-icon.png'),
                ('Raspberry Pi', 'Single-board computers for various IoT applications', 'raspberry-icon.png'),
                ('Sensor Node', 'Environmental sensors for data collection', 'sensor-icon.png'),
                ('Smart Lock', 'IoT-enabled door locks for security', 'lock-icon.png')";
        
        $conn->exec($sql);
        
        // Insert Locations
        $sql = "INSERT INTO locations (loc_name, address, latitude, longitude) VALUES 
                ('Main Office', '123 Tech Street, Silicon Valley, CA', 37.4419, -122.1430),
                ('Warehouse A', '456 Storage Ave, Industrial District', 37.4219, -122.1630),
                ('Branch Office', '789 Business Blvd, Downtown', 37.4619, -122.1230),
                ('Data Center', '321 Server Road, Tech Park', 37.4319, -122.1530),
                ('Remote Site', '654 Field Lane, Rural Area', 37.4519, -122.1330)";
        
        $conn->exec($sql);
        
        // Insert Devices - SQL Feature: INSERT with subqueries
        $sql = "INSERT INTO devices (d_name, t_id, user_id, serial_number, status, purchase_date, warranty_expiry) VALUES 
                ('Security Drone Alpha', 1, 1, 'DRN-001-2024', 'active', '2024-01-15', '2026-01-15'),
                ('Perimeter Camera 01', 2, 2, 'CAM-001-2024', 'active', '2024-02-01', '2027-02-01'),
                ('IoT Controller Pi', 3, 1, 'RPI-001-2024', 'maintenance', '2024-01-20', '2026-01-20'),
                ('Temperature Sensor', 4, 3, 'SEN-001-2024', 'active', '2024-03-01', '2026-03-01'),
                ('Main Entrance Lock', 5, 2, 'LCK-001-2024', 'active', '2024-01-10', '2026-01-10'),
                ('Patrol Drone Beta', 1, 4, 'DRN-002-2024', 'inactive', '2024-02-15', '2026-02-15'),
                ('Backup Camera 02', 2, 5, 'CAM-002-2024', 'error', '2024-01-25', '2027-01-25')";
        
        $conn->exec($sql);
        
        // Insert Deployments - SQL Feature: INSERT with foreign key references
        $sql = "INSERT INTO deployments (d_id, loc_id, deployed_by, deployment_notes, is_active) VALUES 
                (1, 1, 3, 'Deployed for main office security monitoring', 1),
                (2, 1, 3, 'Monitoring main entrance', 1),
                (3, 2, 1, 'Warehouse automation controller', 1),
                (4, 3, 2, 'Branch office environmental monitoring', 1),
                (5, 1, 3, 'Main office access control', 1),
                (6, 4, 4, 'Data center surveillance', 0),
                (7, 5, 5, 'Remote site backup monitoring', 1)";
        
        $conn->exec($sql);
        
        // Insert Device Logs - SQL Feature: INSERT with timestamp functions
        $sql = "INSERT INTO device_logs (d_id, log_type, message, severity_level, resolved_by, resolved_at) VALUES 
                (1, 'info', 'Device started successfully', 1, NULL, NULL),
                (1, 'warning', 'Low battery detected - 15% remaining', 2, NULL, NULL),
                (2, 'info', 'Camera recording started', 1, NULL, NULL),
                (2, 'error', 'Network connection lost', 3, 3, NOW()),
                (3, 'warning', 'High CPU temperature detected', 2, NULL, NULL),
                (3, 'error', 'System overheating - automatic shutdown initiated', 4, 1, NOW()),
                (4, 'info', 'Temperature reading: 23.5°C', 1, NULL, NULL),
                (5, 'info', 'Access granted to user: john.doe', 1, NULL, NULL),
                (6, 'error', 'Motor malfunction detected', 4, NULL, NULL),
                (7, 'warning', 'Storage capacity 85% full', 2, 5, NOW())";
        
        $conn->exec($sql);
        
        // SQL Feature: COMMIT transaction
        $conn->commit();
        echo "Sample data inserted successfully!";
        
    } catch (Exception $e) {
        // SQL Feature: ROLLBACK on error
        $conn->rollback();
        echo "Error inserting sample data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Device Manager - Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full">
        <div class="text-center">
            <i class="fas fa-database text-4xl text-blue-600 mb-4"></i>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">IoT Device Manager</h1>
            <p class="text-gray-600 mb-6">Database Setup Complete</p>
            
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <p><i class="fas fa-check-circle mr-2"></i>Database and tables created successfully</p>
                <p><i class="fas fa-data mr-2"></i>Sample data inserted</p>
            </div>
            
            <div class="space-y-3">
                <a href="login.php" class="block w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to System
                </a>
                
                <a href="sql_features.php" class="block w-full bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700 transition">
                    <i class="fas fa-code mr-2"></i>View SQL Features
                </a>
                
                <a href="register.php" class="block w-full bg-gray-600 text-white py-2 px-4 rounded hover:bg-gray-700 transition">
                    <i class="fas fa-user-plus mr-2"></i>Register New User
                </a>
            </div>
            
            <div class="mt-6 text-sm text-gray-500">
                <p><strong>Default Login:</strong></p>
                <p>Email: admin@iot.com</p>
                <p>Password: admin123</p>
            </div>
        </div>
    </div>
</body>
</html>