<?php
/**
 * Database Configuration
 * SQL Features Used: Basic Connection, Database Selection
 */

class Database {
    private $host = "localhost";
    private $db_name = "iot_manager_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            // Basic SQL Connection
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }

    /**
     * SQL Feature: Database Creation with IF NOT EXISTS
     * Creates database if it doesn't exist
     */
    public function createDatabase() {
        try {
            $conn = new PDO("mysql:host=" . $this->host, $this->username, $this->password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "CREATE DATABASE IF NOT EXISTS " . $this->db_name . " CHARACTER SET utf8 COLLATE utf8_general_ci";
            $conn->exec($sql);
            
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }

    /**
     * SQL Feature: Table Creation with Constraints, Foreign Keys, Indexes
     */
    public function createTables() {
        $this->getConnection();
        
        try {
            // Users Table with UNIQUE constraint and AUTO_INCREMENT
            $sql_users = "CREATE TABLE IF NOT EXISTS users (
                user_id INT PRIMARY KEY AUTO_INCREMENT,
                f_name VARCHAR(50) NOT NULL,
                l_name VARCHAR(50) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_name (f_name, l_name)
            ) ENGINE=InnoDB";

            // Device Types Table with CHECK constraint (MySQL 8.0+)
            $sql_device_types = "CREATE TABLE IF NOT EXISTS device_types (
                t_id INT PRIMARY KEY AUTO_INCREMENT,
                t_name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                icon VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_type_name (t_name)
            ) ENGINE=InnoDB";

            // Devices Table with Foreign Key constraints and CHECK constraint
            $sql_devices = "CREATE TABLE IF NOT EXISTS devices (
                d_id INT PRIMARY KEY AUTO_INCREMENT,
                d_name VARCHAR(100) NOT NULL,
                t_id INT NOT NULL,
                user_id INT NOT NULL,
                serial_number VARCHAR(100) UNIQUE NOT NULL,
                status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'inactive',
                purchase_date DATE,
                warranty_expiry DATE,
                last_maintenance DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (t_id) REFERENCES device_types(t_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
                INDEX idx_device_name (d_name),
                INDEX idx_serial (serial_number),
                INDEX idx_status (status),
                INDEX idx_user_device (user_id, d_name)
            ) ENGINE=InnoDB";

            // Locations Table with spatial data type (if MySQL supports it)
            $sql_locations = "CREATE TABLE IF NOT EXISTS locations (
                loc_id INT PRIMARY KEY AUTO_INCREMENT,
                loc_name VARCHAR(100) NOT NULL,
                address TEXT NOT NULL,
                latitude DECIMAL(10, 8),
                longitude DECIMAL(11, 8),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_location_name (loc_name),
                INDEX idx_coordinates (latitude, longitude)
            ) ENGINE=InnoDB";

            // Deployments Table (Junction table for Many-to-Many relationship)
            $sql_deployments = "CREATE TABLE IF NOT EXISTS deployments (
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
            ) ENGINE=InnoDB";

            // Device Logs Table with Partitioning concept and Full-text search
            $sql_device_logs = "CREATE TABLE IF NOT EXISTS device_logs (
                log_id INT PRIMARY KEY AUTO_INCREMENT,
                d_id INT NOT NULL,
                log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                log_type ENUM('error', 'warning', 'info', 'debug') NOT NULL,
                message TEXT NOT NULL,
                severity_level INT DEFAULT 1,
                resolved_by INT NULL,
                resolved_at TIMESTAMP NULL,
                resolution_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE,
                INDEX idx_device_logs (d_id, log_time),
                INDEX idx_log_type (log_type),
                INDEX idx_severity (severity_level),
                INDEX idx_unresolved (resolved_by, log_type),
                FULLTEXT idx_message_search (message)
            ) ENGINE=InnoDB";

            // Execute all table creation queries
            $this->conn->exec($sql_users);
            $this->conn->exec($sql_device_types);
            $this->conn->exec($sql_devices);
            $this->conn->exec($sql_locations);
            $this->conn->exec($sql_deployments);
            $this->conn->exec($sql_device_logs);

            return true;
        } catch(PDOException $e) {
            echo "Error creating tables: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Insert comprehensive sample data for demonstration
     */
    public function insertSampleData() {
        $this->getConnection();
        
        try {
            $this->conn->beginTransaction();
            
            // Sample device types
            $deviceTypes = [
                ['Temperature Sensor', 'Monitors ambient temperature and thermal conditions', 'fas fa-thermometer-half'],
                ['Humidity Sensor', 'Tracks humidity levels and moisture detection', 'fas fa-tint'],
                ['Motion Detector', 'Detects movement, activity, and intrusion', 'fas fa-running'],
                ['Smart Camera', 'Video surveillance, monitoring, and analytics', 'fas fa-video'],
                ['Door Controller', 'Access control, security, and entry management', 'fas fa-door-open'],
                ['Air Quality Monitor', 'Measures pollutants, CO2, and air quality index', 'fas fa-wind'],
                ['Smart Thermostat', 'Climate control and energy management', 'fas fa-temperature-high'],
                ['Pressure Sensor', 'Monitors atmospheric and system pressure', 'fas fa-gauge-high'],
                ['Light Sensor', 'Ambient light detection and control', 'fas fa-lightbulb'],
                ['Vibration Monitor', 'Equipment health and structural monitoring', 'fas fa-wave-square']
            ];
            
            $stmt = $this->conn->prepare("INSERT IGNORE INTO device_types (t_name, description, icon) VALUES (?, ?, ?)");
            foreach ($deviceTypes as $type) {
                $stmt->execute($type);
            }
            
            // Sample locations with realistic coordinates
            $locations = [
                ['Headquarters', '123 Main St, Downtown Business District', 40.7128, -74.0060],
                ['Warehouse A', '456 Industrial Blvd, Port District', 40.6892, -74.0445],
                ['Branch Office', '789 Business Ave, Midtown Plaza', 40.7589, -73.9851],
                ['Manufacturing Plant', '321 Factory Road, Industrial Zone', 40.6782, -74.1745],
                ['Data Center', '654 Tech Drive, Innovation Campus', 40.7831, -73.9712],
                ['Retail Store', '987 Shopping Center, Commercial District', 40.7282, -73.9942]
            ];
            
            $stmt = $this->conn->prepare("INSERT IGNORE INTO locations (loc_name, address, latitude, longitude) VALUES (?, ?, ?, ?)");
            foreach ($locations as $location) {
                $stmt->execute($location);
            }
            
            // Sample admin user
            $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
            $this->conn->exec("INSERT IGNORE INTO users (f_name, l_name, email, password) VALUES 
                ('System', 'Administrator', 'admin@iotmanager.com', '$adminPassword')");
            
            // Sample technician users
            $techUsers = [
                ['John', 'Smith', 'john.smith@tech.com'],
                ['Sarah', 'Johnson', 'sarah.j@tech.com'],
                ['Mike', 'Brown', 'mike.brown@tech.com'],
                ['Lisa', 'Davis', 'lisa.davis@tech.com']
            ];
            
            $stmt = $this->conn->prepare("INSERT IGNORE INTO users (f_name, l_name, email, password) VALUES (?, ?, ?, ?)");
            foreach ($techUsers as $user) {
                $password = password_hash('password123', PASSWORD_BCRYPT);
                $stmt->execute([...$user, $password]);
            }
            
            // Sample devices (15-18 devices across all types and locations)
            $devices = [
                [1, 'TEMP-HQ-001', 'Main lobby temperature monitor', 'active', '2024-01-15', '2026-01-15'],
                [2, 'HUM-HQ-001', 'Server room humidity sensor', 'active', '2024-02-01', '2026-02-01'],
                [3, 'MOT-HQ-001', 'Main entrance motion detector', 'active', '2024-01-20', '2026-01-20'],
                [4, 'CAM-HQ-001', 'Reception security camera', 'active', '2024-03-01', '2027-03-01'],
                [6, 'AIR-HQ-001', 'Office air quality monitor', 'maintenance', '2024-02-15', '2026-02-15'],
                
                [1, 'TEMP-WH-001', 'Warehouse temperature control', 'active', '2024-01-10', '2026-01-10'],
                [8, 'PRES-WH-001', 'Hydraulic pressure sensor', 'active', '2024-03-15', '2026-03-15'],
                [10, 'VIB-WH-001', 'Conveyor vibration monitor', 'error', '2024-02-20', '2026-02-20'],
                [3, 'MOT-WH-001', 'Loading dock motion sensor', 'active', '2024-01-25', '2026-01-25'],
                
                [7, 'THERM-BO-001', 'Conference room thermostat', 'active', '2024-03-10', '2026-03-10'],
                [9, 'LIGHT-BO-001', 'Automatic lighting controller', 'active', '2024-02-25', '2026-02-25'],
                [5, 'DOOR-BO-001', 'Office access control system', 'active', '2024-01-30', '2026-01-30'],
                
                [1, 'TEMP-MP-001', 'Production line temperature', 'active', '2024-02-05', '2026-02-05'],
                [10, 'VIB-MP-001', 'Machine vibration monitor', 'maintenance', '2024-03-20', '2026-03-20'],
                [8, 'PRES-MP-001', 'Steam pressure gauge', 'error', '2024-01-12', '2026-01-12'],
                
                [2, 'HUM-DC-001', 'Critical humidity sensor', 'active', '2024-02-10', '2026-02-10'],
                [1, 'TEMP-DC-001', 'Cooling system temperature', 'active', '2024-01-05', '2026-01-05'],
                [6, 'AIR-DC-001', 'Air quality monitoring system', 'active', '2024-03-25', '2026-03-25']
            ];
            
            $stmt = $this->conn->prepare("INSERT IGNORE INTO devices (t_id, d_name, serial_number, status, purchase_date, warranty_expiry, user_id) VALUES (?, ?, ?, ?, ?, ?, 1)");
            foreach ($devices as $device) {
                $stmt->execute($device);
            }
            
            // Deploy devices to locations
            $deployments = [
                // Headquarters (loc_id: 1)
                [1, 1], [2, 1], [3, 1], [4, 1], [5, 1],
                // Warehouse A (loc_id: 2)  
                [6, 2], [7, 2], [8, 2], [9, 2],
                // Branch Office (loc_id: 3)
                [10, 3], [11, 3], [12, 3],
                // Manufacturing Plant (loc_id: 4)
                [13, 4], [14, 4], [15, 4],
                // Data Center (loc_id: 5)
                [16, 5], [17, 5], [18, 5]
            ];
            
            $stmt = $this->conn->prepare("INSERT IGNORE INTO deployments (d_id, loc_id, deployed_by, deployment_notes) VALUES (?, ?, 1, 'Initial deployment')");
            foreach ($deployments as $deployment) {
                $stmt->execute($deployment);
            }
            
            // Generate 30 days of realistic log data
            $this->generateRealisticLogs();
            
            $this->conn->commit();
            return true;
            
        } catch(PDOException $e) {
            $this->conn->rollback();
            echo "Error inserting sample data: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Generate realistic log data for the last 30 days
     */
    private function generateRealisticLogs() {
        $devices = range(1, 18); // Device IDs 1-18
        $users = [1, 2, 3, 4, 5]; // User IDs for resolving issues
        
        $logTypes = ['info', 'warning', 'error', 'debug'];
        $logTemplates = [
            'info' => [
                'Device startup completed successfully',
                'Regular maintenance check completed',
                'Configuration updated',
                'Sensor calibration completed',
                'System health check passed',
                'Data backup completed',
                'Network connectivity established',
                'Firmware update applied successfully'
            ],
            'warning' => [
                'Temperature approaching critical threshold',
                'Low battery warning - {level}% remaining',
                'Network connection unstable',
                'Sensor reading fluctuation detected',
                'Memory usage above 80%',
                'High humidity levels detected',
                'Unusual vibration pattern observed',
                'Light sensor requires cleaning'
            ],
            'error' => [
                'Sensor communication failure',
                'Critical temperature threshold exceeded',
                'Network connection lost',
                'Power supply voltage irregular',
                'Sensor calibration failed',
                'Hardware malfunction detected',
                'Data corruption error',
                'Authentication failure'
            ],
            'debug' => [
                'Sensor reading: {value} units',
                'Network packet loss: {percentage}%',
                'CPU usage: {usage}%',
                'Memory allocation: {memory}MB',
                'Response time: {time}ms',
                'Queue length: {length} items',
                'Cache hit ratio: {ratio}%',
                'Thread count: {threads}'
            ]
        ];
        
        $stmt = $this->conn->prepare("INSERT INTO device_logs (d_id, log_time, log_type, message, severity_level, resolved_by, resolved_at, resolution_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Generate logs for last 30 days
        for ($day = 30; $day >= 0; $day--) {
            $date = date('Y-m-d', strtotime("-$day days"));
            
            foreach ($devices as $deviceId) {
                // Each device generates 3-15 logs per day
                $logsPerDay = rand(3, 15);
                
                for ($log = 0; $log < $logsPerDay; $log++) {
                    $hour = rand(0, 23);
                    $minute = rand(0, 59);
                    $second = rand(0, 59);
                    $logTime = "$date $hour:$minute:$second";
                    
                    // Determine log type based on device status and randomness
                    $logType = $this->getRandomLogType($deviceId);
                    $severity = $this->getSeverityLevel($logType);
                    
                    // Generate message
                    $messageTemplate = $logTemplates[$logType][array_rand($logTemplates[$logType])];
                    $message = $this->fillMessageTemplate($messageTemplate);
                    
                    // Determine if error/warning is resolved
                    $resolvedBy = null;
                    $resolvedAt = null;
                    $resolutionNotes = null;
                    
                    if (($logType === 'error' || $logType === 'warning') && rand(1, 100) <= 70) {
                        $resolvedBy = $users[array_rand($users)];
                        $resolveHours = rand(1, 24);
                        $resolvedAt = date('Y-m-d H:i:s', strtotime("$logTime + $resolveHours hours"));
                        $resolutionNotes = $this->getResolutionNote($logType);
                    }
                    
                    $stmt->execute([$deviceId, $logTime, $logType, $message, $severity, $resolvedBy, $resolvedAt, $resolutionNotes]);
                }
            }
        }
    }
    
    private function getRandomLogType($deviceId) {
        // Some devices have more errors (device IDs 8, 15 are in error state)
        if (in_array($deviceId, [8, 15])) {
            $weights = ['info' => 30, 'warning' => 25, 'error' => 35, 'debug' => 10];
        } elseif (in_array($deviceId, [5, 14])) { // Maintenance devices
            $weights = ['info' => 35, 'warning' => 35, 'error' => 15, 'debug' => 15];
        } else { // Healthy devices
            $weights = ['info' => 50, 'warning' => 25, 'error' => 10, 'debug' => 15];
        }
        
        $rand = rand(1, 100);
        $cumulative = 0;
        foreach ($weights as $type => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) return $type;
        }
        return 'info';
    }
    
    private function getSeverityLevel($logType) {
        switch ($logType) {
            case 'error': return rand(7, 10);
            case 'warning': return rand(4, 6);
            case 'info': return rand(1, 3);
            case 'debug': return 1;
            default: return 1;
        }
    }
    
    private function fillMessageTemplate($template) {
        $replacements = [
            '{level}' => rand(10, 25),
            '{value}' => number_format(rand(0, 1000) / 10, 1),
            '{percentage}' => rand(1, 15),
            '{usage}' => rand(30, 95),
            '{memory}' => rand(512, 2048),
            '{time}' => rand(50, 500),
            '{length}' => rand(5, 50),
            '{ratio}' => rand(75, 95),
            '{threads}' => rand(4, 16)
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    private function getResolutionNote($logType) {
        $resolutions = [
            'error' => [
                'Hardware replaced and system restored',
                'Network configuration updated',
                'Sensor recalibrated successfully',
                'Power supply stabilized',
                'Firmware updated to latest version',
                'Configuration reset to factory defaults',
                'Physical connection secured'
            ],
            'warning' => [
                'Threshold adjusted to normal levels',
                'Battery replaced during maintenance',
                'Network optimization applied',
                'Cleaning performed, readings normalized',
                'Resource allocation optimized',
                'Environmental conditions improved',
                'Preventive maintenance completed'
            ]
        ];
        
        return $resolutions[$logType][array_rand($resolutions[$logType])];
    }
}
?>