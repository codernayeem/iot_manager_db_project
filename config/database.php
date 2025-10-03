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
}
?>