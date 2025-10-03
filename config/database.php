<?php
/**
 * Database Configuration
 * SQL Features Used: Basic Connection, Database Selection
 */

class Database {
    private $host = "localhost";
    private $db_name = "iot_device_manager";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            // Try to connect to the specific database first
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // If database doesn't exist, connect without database name
            try {
                $this->conn = new PDO("mysql:host=" . $this->host, 
                                    $this->username, $this->password);
                $this->conn->exec("set names utf8");
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $e) {
                echo "Connection error: " . $e->getMessage();
            }
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
            
            // Update our connection to use the new database
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
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

            // Create advanced SQL objects (views, procedures, functions, indexes)
            $this->createAdvancedSQLObjects();

            return true;
        } catch(PDOException $e) {
            echo "Error creating tables: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Create advanced SQL objects (views, procedures, functions, indexes)
     */
    public function createAdvancedSQLObjects() {
        try {
            // Create Views
            $this->createViews();
            
            // Create Stored Procedures
            $this->createProcedures();
            
            // Create User-Defined Functions
            $this->createFunctions();
            
            // Create Optimized Indexes
            $this->createIndexes();
            
            return true;
        } catch(PDOException $e) {
            echo "Error creating advanced SQL objects: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Create database views
     */
    private function createViews() {
        // Device Summary View
        $view_device_summary = "
        CREATE OR REPLACE VIEW v_device_summary AS
        SELECT 
            d.d_id,
            d.d_name,
            dt.t_name as device_type,
            l.loc_name as location,
            d.status,
            d.serial_number,
            CONCAT(u.f_name, ' ', u.l_name) as owner_name,
            COUNT(dl.log_id) as total_logs,
            COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as error_count,
            COUNT(CASE WHEN dl.log_type = 'warning' THEN 1 END) as warning_count,
            MAX(dl.log_time) as last_log_time,
            DATEDIFF(NOW(), MAX(dl.log_time)) as days_since_last_log
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        INNER JOIN users u ON d.user_id = u.user_id
        LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
        LEFT JOIN locations l ON dep.loc_id = l.loc_id
        LEFT JOIN device_logs dl ON d.d_id = dl.d_id
        GROUP BY d.d_id, d.d_name, dt.t_name, l.loc_name, d.status, d.serial_number, u.f_name, u.l_name
        ";
        
        // Log Analysis View
        $view_log_analysis = "
        CREATE OR REPLACE VIEW v_log_analysis AS
        SELECT 
            DATE(dl.log_time) as log_date,
            dl.log_type,
            d.d_name,
            dt.t_name as device_type,
            l.loc_name,
            COUNT(*) as log_count,
            AVG(dl.severity_level) as avg_severity,
            COUNT(CASE WHEN dl.resolved_by IS NULL THEN 1 END) as unresolved_count,
            COUNT(CASE WHEN dl.resolved_by IS NOT NULL THEN 1 END) as resolved_count
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
        LEFT JOIN locations l ON dep.loc_id = l.loc_id
        WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY DATE(dl.log_time), dl.log_type, d.d_name, dt.t_name, l.loc_name
        ";
        
        // Resolver Performance View
        $view_resolver_performance = "
        CREATE OR REPLACE VIEW v_resolver_performance AS
        SELECT 
            u.user_id,
            CONCAT(u.f_name, ' ', u.l_name) as resolver_name,
            u.email,
            COUNT(DISTINCT dl.log_id) as total_resolved,
            COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) as errors_resolved,
            COUNT(DISTINCT CASE WHEN dl.log_type = 'warning' THEN dl.log_id END) as warnings_resolved,
            AVG(TIMESTAMPDIFF(HOUR, dl.log_time, dl.resolved_at)) as avg_resolution_time_hours,
            COUNT(DISTINCT dl.d_id) as devices_worked_on,
            COUNT(DISTINCT dep.loc_id) as locations_covered
        FROM users u
        INNER JOIN device_logs dl ON u.user_id = dl.resolved_by
        INNER JOIN devices d ON dl.d_id = d.d_id
        LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
        WHERE dl.resolved_at IS NOT NULL
        GROUP BY u.user_id, u.f_name, u.l_name, u.email
        ";
        
        $this->conn->exec($view_device_summary);
        $this->conn->exec($view_log_analysis);
        $this->conn->exec($view_resolver_performance);
    }
    
    /**
     * Create stored procedures
     */
    private function createProcedures() {
        // Drop existing procedures if they exist
        $dropStatements = [
            "DROP PROCEDURE IF EXISTS sp_device_health_check",
            "DROP PROCEDURE IF EXISTS sp_cleanup_old_logs", 
            "DROP PROCEDURE IF EXISTS sp_deploy_device",
            "DROP PROCEDURE IF EXISTS sp_resolve_issue"
        ];
        
        foreach ($dropStatements as $drop) {
            try {
                $this->conn->exec($drop);
            } catch(PDOException $e) {
                // Ignore errors if procedures don't exist
            }
        }
        
        // Device Health Check Procedure
        $proc_health_check = "
        CREATE PROCEDURE sp_device_health_check(
            IN device_id INT,
            IN days_back INT,
            OUT health_score DECIMAL(5,2),
            OUT status_message VARCHAR(255)
        )
        BEGIN
            DECLARE error_count INT DEFAULT 0;
            DECLARE warning_count INT DEFAULT 0;
            DECLARE total_logs INT DEFAULT 0;
            DECLARE last_log_date DATE;
            
            -- Set default value for days_back if null or 0
            IF days_back IS NULL OR days_back <= 0 THEN
                SET days_back = 30;
            END IF;
            
            -- Get log statistics
            SELECT 
                COUNT(*),
                COUNT(CASE WHEN log_type = 'error' THEN 1 END),
                COUNT(CASE WHEN log_type = 'warning' THEN 1 END),
                MAX(DATE(log_time))
            INTO total_logs, error_count, warning_count, last_log_date
            FROM device_logs 
            WHERE d_id = device_id 
            AND log_time >= DATE_SUB(NOW(), INTERVAL days_back DAY);
            
            -- Calculate health score
            IF total_logs = 0 THEN
                SET health_score = 0;
                SET status_message = 'No data available';
            ELSE
                SET health_score = ((total_logs - error_count - (warning_count * 0.5)) / total_logs) * 100;
                
                IF health_score >= 90 THEN
                    SET status_message = 'Excellent health';
                ELSEIF health_score >= 70 THEN
                    SET status_message = 'Good health';
                ELSEIF health_score >= 50 THEN
                    SET status_message = 'Fair health - needs attention';
                ELSE
                    SET status_message = 'Poor health - immediate action required';
                END IF;
            END IF;
        END";
        
        try {
            $this->conn->exec($proc_health_check);
        } catch(PDOException $e) {
            error_log("Failed to create sp_device_health_check: " . $e->getMessage());
        }
        
        // Cleanup Old Logs Procedure
        $proc_cleanup = "
        CREATE PROCEDURE sp_cleanup_old_logs(
            IN days_to_keep INT,
            OUT deleted_count INT
        )
        BEGIN
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                RESIGNAL;
            END;
            
            -- Set default value if null or invalid
            IF days_to_keep IS NULL OR days_to_keep <= 0 THEN
                SET days_to_keep = 365;
            END IF;
            
            START TRANSACTION;
            
            SELECT COUNT(*) INTO deleted_count 
            FROM device_logs 
            WHERE log_time < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
            
            DELETE FROM device_logs 
            WHERE log_time < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
            
            COMMIT;
        END
        ";
        
        try {
            $this->conn->exec($proc_cleanup);
        } catch(PDOException $e) {
            error_log("Failed to create sp_cleanup_old_logs: " . $e->getMessage());
        }
        
        // Deploy Device Procedure
        $proc_deploy = "
        CREATE PROCEDURE sp_deploy_device(
            IN device_id INT,
            IN location_id INT,
            IN deployed_by_user INT,
            IN deployment_notes TEXT,
            OUT success BOOLEAN,
            OUT message VARCHAR(255)
        )
        BEGIN
            DECLARE device_count INT DEFAULT 0;
            DECLARE location_count INT DEFAULT 0;
            DECLARE existing_deployment INT DEFAULT 0;
            
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                SET success = FALSE;
                SET message = 'Deployment failed due to database error';
            END;
            
            START TRANSACTION;
            
            -- Validate device exists
            SELECT COUNT(*) INTO device_count FROM devices WHERE d_id = device_id;
            IF device_count = 0 THEN
                SET success = FALSE;
                SET message = 'Device not found';
                ROLLBACK;
            ELSE
                -- Validate location exists
                SELECT COUNT(*) INTO location_count FROM locations WHERE loc_id = location_id;
                IF location_count = 0 THEN
                    SET success = FALSE;
                    SET message = 'Location not found';
                    ROLLBACK;
                ELSE
                    -- Check for existing active deployment
                    SELECT COUNT(*) INTO existing_deployment 
                    FROM deployments 
                    WHERE d_id = device_id AND is_active = 1;
                    
                    IF existing_deployment > 0 THEN
                        -- Deactivate existing deployment
                        UPDATE deployments SET is_active = 0 WHERE d_id = device_id AND is_active = 1;
                    END IF;
                    
                    -- Create new deployment
                    INSERT INTO deployments (d_id, loc_id, deployed_by, deployment_notes, is_active)
                    VALUES (device_id, location_id, deployed_by_user, deployment_notes, 1);
                    
                    -- Update device status
                    UPDATE devices SET status = 'active' WHERE d_id = device_id;
                    
                    SET success = TRUE;
                    SET message = 'Device deployed successfully';
                    COMMIT;
                END IF;
            END IF;
        END
        ";
        
        // Resolve Issue Procedure
        $proc_resolve = "
        CREATE PROCEDURE sp_resolve_issue(
            IN log_id INT,
            IN resolver_user_id INT,
            IN resolution_notes TEXT,
            OUT success BOOLEAN,
            OUT message VARCHAR(255)
        )
        BEGIN
            DECLARE log_count INT DEFAULT 0;
            DECLARE already_resolved INT DEFAULT 0;
            
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                SET success = FALSE;
                SET message = 'Resolution failed due to database error';
            END;
            
            START TRANSACTION;
            
            -- Check if log exists
            SELECT COUNT(*) INTO log_count FROM device_logs WHERE log_id = log_id;
            IF log_count = 0 THEN
                SET success = FALSE;
                SET message = 'Log entry not found';
                ROLLBACK;
            ELSE
                -- Check if already resolved
                SELECT COUNT(*) INTO already_resolved 
                FROM device_logs 
                WHERE log_id = log_id AND resolved_by IS NOT NULL;
                
                IF already_resolved > 0 THEN
                    SET success = FALSE;
                    SET message = 'Issue already resolved';
                    ROLLBACK;
                ELSE
                    -- Mark as resolved
                    UPDATE device_logs 
                    SET resolved_by = resolver_user_id,
                        resolved_at = NOW(),
                        resolution_notes = resolution_notes
                    WHERE log_id = log_id;
                    
                    SET success = TRUE;
                    SET message = 'Issue resolved successfully';
                    COMMIT;
                END IF;
            END IF;
        END
        ";
        
        try {
            $this->conn->exec($proc_deploy);
        } catch(PDOException $e) {
            error_log("Failed to create sp_deploy_device: " . $e->getMessage());
        }
        
        try {
            $this->conn->exec($proc_resolve);
        } catch(PDOException $e) {
            error_log("Failed to create sp_resolve_issue: " . $e->getMessage());
        }
    }
    
    /**
     * Create user-defined functions
     */
    private function createFunctions() {
        // Drop existing functions if they exist
        $this->conn->exec("DROP FUNCTION IF EXISTS fn_calculate_uptime");
        $this->conn->exec("DROP FUNCTION IF EXISTS fn_device_risk_score");
        $this->conn->exec("DROP FUNCTION IF EXISTS fn_format_duration");
        
        // Calculate Uptime Function
        $func_uptime = "
        CREATE FUNCTION fn_calculate_uptime(device_id INT, days_back INT)
        RETURNS DECIMAL(5,2)
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            DECLARE total_hours DECIMAL(10,2);
            DECLARE error_hours DECIMAL(10,2);
            DECLARE uptime_percentage DECIMAL(5,2);
            
            SET total_hours = days_back * 24;
            
            SELECT COALESCE(SUM(
                CASE 
                    WHEN log_type = 'error' THEN 1.0
                    ELSE 0.0 
                END
            ), 0) INTO error_hours
            FROM device_logs 
            WHERE d_id = device_id 
            AND log_time >= DATE_SUB(NOW(), INTERVAL days_back DAY);
            
            SET uptime_percentage = GREATEST(0, ((total_hours - error_hours) / total_hours) * 100);
            
            RETURN uptime_percentage;
        END
        ";
        
        // Device Risk Score Function
        $func_risk = "
        CREATE FUNCTION fn_device_risk_score(device_id INT)
        RETURNS INT
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            DECLARE error_count INT DEFAULT 0;
            DECLARE warning_count INT DEFAULT 0;
            DECLARE days_since_maintenance INT DEFAULT 0;
            DECLARE risk_score INT DEFAULT 0;
            
            -- Count recent errors and warnings
            SELECT 
                COUNT(CASE WHEN log_type = 'error' THEN 1 END),
                COUNT(CASE WHEN log_type = 'warning' THEN 1 END)
            INTO error_count, warning_count
            FROM device_logs 
            WHERE d_id = device_id 
            AND log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY);
            
            -- Check days since last maintenance
            SELECT COALESCE(DATEDIFF(NOW(), last_maintenance), 365)
            INTO days_since_maintenance
            FROM devices 
            WHERE d_id = device_id;
            
            -- Calculate risk score
            SET risk_score = (error_count * 10) + (warning_count * 2) + 
                           CASE 
                               WHEN days_since_maintenance > 365 THEN 20
                               WHEN days_since_maintenance > 180 THEN 10
                               WHEN days_since_maintenance > 90 THEN 5
                               ELSE 0
                           END;
            
            RETURN LEAST(100, risk_score);
        END
        ";
        
        // Format Duration Function
        $func_duration = "
        CREATE FUNCTION fn_format_duration(hours DECIMAL(10,2))
        RETURNS VARCHAR(50)
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            DECLARE days INT;
            DECLARE remaining_hours INT;
            DECLARE result VARCHAR(50);
            
            IF hours IS NULL OR hours < 0 THEN
                RETURN 'N/A';
            END IF;
            
            SET days = FLOOR(hours / 24);
            SET remaining_hours = hours % 24;
            
            IF days > 0 THEN
                SET result = CONCAT(days, 'd ', remaining_hours, 'h');
            ELSE
                SET result = CONCAT(remaining_hours, 'h');
            END IF;
            
            RETURN result;
        END
        ";
        
        $this->conn->exec($func_uptime);
        $this->conn->exec($func_risk);
        $this->conn->exec($func_duration);
    }
    
    /**
     * Create optimized indexes
     */
    private function createIndexes() {
        // Performance indexes for common queries
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_device_logs_time_type ON device_logs(log_time, log_type)",
            "CREATE INDEX IF NOT EXISTS idx_device_logs_device_time ON device_logs(d_id, log_time DESC)",
            "CREATE INDEX IF NOT EXISTS idx_device_logs_resolved ON device_logs(resolved_by, resolved_at)",
            "CREATE INDEX IF NOT EXISTS idx_device_logs_severity ON device_logs(severity_level, log_type)",
            "CREATE INDEX IF NOT EXISTS idx_deployments_active ON deployments(is_active, d_id, loc_id)",
            "CREATE INDEX IF NOT EXISTS idx_devices_status_type ON devices(status, t_id)",
            "CREATE INDEX IF NOT EXISTS idx_devices_maintenance ON devices(last_maintenance, status)",
            "CREATE INDEX IF NOT EXISTS idx_device_logs_composite ON device_logs(d_id, log_type, log_time, resolved_by)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->conn->exec($index);
            } catch(PDOException $e) {
                // Index might already exist, continue
            }
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
     * Get comprehensive database status information
     * SQL Features: SHOW TABLES, SHOW PROCEDURE STATUS, SHOW FUNCTION STATUS, 
     * INFORMATION_SCHEMA queries, COUNT queries
     */
    public function getDatabaseStatus() {
        $status = [
            'connected' => false,
            'database_exists' => false,
            'tables' => [],
            'views' => [],
            'procedures' => [],
            'functions' => [],
            'table_info' => []
        ];
        
        try {
            // Ensure we have a connection
            if ($this->conn === null) {
                $this->getConnection();
            }
            
            // Check connection
            $status['connected'] = ($this->conn !== null);
            
            if (!$status['connected']) {
                return $status;
            }
            
            // Check if database exists
            $stmt = $this->conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'iot_device_manager'");
            $stmt->execute();
            $status['database_exists'] = ($stmt->fetchColumn() !== false);
            
            if (!$status['database_exists']) {
                return $status;
            }
            
            // Now try to use the database
            try {
                $this->conn->exec("USE iot_device_manager");
            } catch (PDOException $e) {
                // If we can't use the database, it might not exist
                $status['database_exists'] = false;
                return $status;
            }
            
            // Get tables
            $stmt = $this->conn->prepare("SHOW TABLES");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $status['tables'][] = $row[0];
            }
            
            // Get views
            $stmt = $this->conn->prepare("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $status['views'][] = $row[0];
            }
            
            // Get procedures
            $stmt = $this->conn->prepare("SHOW PROCEDURE STATUS WHERE Db = 'iot_device_manager'");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $status['procedures'][] = $row['Name'];
            }
            
            // Get functions
            $stmt = $this->conn->prepare("SHOW FUNCTION STATUS WHERE Db = 'iot_device_manager'");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $status['functions'][] = $row['Name'];
            }
            
            // Get detailed table information
            $tables = ['users', 'device_types', 'devices', 'locations', 'deployments', 'device_logs'];
            foreach ($tables as $table) {
                if (in_array($table, $status['tables'])) {
                    $tableInfo = $this->getTableInfo($table);
                    if ($tableInfo) {
                        $status['table_info'][] = $tableInfo;
                    }
                }
            }
            
        } catch (Exception $e) {
            // Handle errors silently for status checking
        }
        
        return $status;
    }
    
    /**
     * Get detailed information about a specific table
     * SQL Features: INFORMATION_SCHEMA queries, COUNT, DESCRIBE
     */
    private function getTableInfo($tableName) {
        try {
            $info = ['name' => $tableName];
            
            // Get row count
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM $tableName");
            $stmt->execute();
            $info['rows'] = $stmt->fetchColumn();
            
            // Get column count
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'iot_device_manager' AND TABLE_NAME = ?");
            $stmt->execute([$tableName]);
            $info['columns'] = $stmt->fetchColumn();
            
            // Get table size
            $stmt = $this->conn->prepare("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = 'iot_device_manager' 
                AND table_name = ?
            ");
            $stmt->execute([$tableName]);
            $size = $stmt->fetchColumn();
            $info['size'] = $size ? $size . ' MB' : '< 0.01 MB';
            
            // Get creation time
            $stmt = $this->conn->prepare("
                SELECT create_time 
                FROM information_schema.tables 
                WHERE table_schema = 'iot_device_manager' 
                AND table_name = ?
            ");
            $stmt->execute([$tableName]);
            $created = $stmt->fetchColumn();
            $info['created'] = $created ? date('Y-m-d H:i', strtotime($created)) : 'Unknown';
            
            return $info;
            
        } catch (Exception $e) {
            return null;
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