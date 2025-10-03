<?php
/**
 * Database Configuration
 * SQL Features Used: Basic Connection, Database Selection
 */

require_once __DIR__ . '/ConfigManager.php';

class Database {
    private $configManager;
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $charset;
    public $conn;

    public function __construct($configFile = null) {
        $this->configManager = new ConfigManager($configFile);
        $this->loadConfigValues();
    }
    
    /**
     * Load configuration values from ConfigManager
     */
    private function loadConfigValues() {
        $this->host = $this->configManager->get('host');
        $this->db_name = $this->configManager->get('db_name');
        $this->username = $this->configManager->get('username');
        $this->password = $this->configManager->get('password');
        $this->port = $this->configManager->get('port', 3306);
        $this->charset = $this->configManager->get('charset', 'utf8');
    }
    
    /**
     * Update database configuration
     */
    public function updateConfig($config) {
        $validation = $this->configManager->update($config)->validate();
        if (!$validation['valid']) {
            throw new Exception('Invalid configuration: ' . implode(', ', $validation['errors']));
        }
        
        $this->configManager->saveConfig();
        $this->loadConfigValues();
        
        // Reset connection to use new config
        $this->conn = null;
        
        return true;
    }
    
    /**
     * Get current configuration
     */
    public function getConfig() {
        return $this->configManager->getAll();
    }
    
    /**
     * Get the configured database name
     */
    public function getDatabaseName() {
        return $this->db_name;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        return $this->configManager->testConnection();
    }

    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }
        
        try {
            // Try to connect to the specific database first
            $dsn = "mysql:host=" . $this->host . 
                   ";port=" . $this->port . 
                   ";dbname=" . $this->db_name . 
                   ";charset=" . $this->charset;
                   
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $exception) {
            // If database doesn't exist, connect without database name
            try {
                $dsn = "mysql:host=" . $this->host . 
                       ";port=" . $this->port . 
                       ";charset=" . $this->charset;
                       
                $this->conn = new PDO($dsn, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                
            } catch(PDOException $e) {
                throw new Exception("Connection error: " . $e->getMessage());
            }
        }
        
        return $this->conn;
    }

    /**
     * SQL Feature: Database Creation from External File
     * Creates database if it doesn't exist using external SQL file
     */
    public function createDatabase() {
        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";charset=" . $this->charset;
            $conn = new PDO($dsn, $this->username, $this->password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Use external SQL file for database creation
            $sqlFile = __DIR__ . "/../sql/create_database.sql";
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $sql = str_replace('{DB_NAME}', $this->db_name, $sql);
                $conn->exec($sql);
            } else {
                // Fallback to inline SQL if file doesn't exist
                $sql = "CREATE DATABASE IF NOT EXISTS " . $this->db_name . " CHARACTER SET " . $this->charset . " COLLATE " . $this->charset . "_general_ci";
                $conn->exec($sql);
            }
            
            // Update our connection to use the new database
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            return true;
        } catch(PDOException $e) {
            throw new Exception("Database creation failed: " . $e->getMessage());
        }
    }

    /**
     * SQL Feature: Table Creation from External Files
     * Executes SQL files to create tables with proper dependencies
     */
    public function createTables() {
        $this->getConnection();
        
        try {
            // Table creation order matters due to foreign key dependencies
            $tableFiles = [
                'users.sql',
                'device_types.sql',
                'locations.sql', 
                'devices.sql',
                'deployments.sql',
                'device_logs.sql'
            ];
            
            foreach ($tableFiles as $file) {
                $this->executeSQLFile("sql/tables/$file");
            }

            // Create advanced SQL objects (views, procedures, functions, indexes)
            $this->createAdvancedSQLObjects();

            return true;
        } catch(PDOException $e) {
            echo "Error creating tables: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Create advanced SQL objects from external files
     */
    public function createAdvancedSQLObjects() {
        try {
            // Create Views
            $this->createViews();
            
            // Create User-Defined Functions
            $this->createFunctions();
            
            // Create Stored Procedures
            $this->createProcedures();
            
            // Create Optimized Indexes
            $this->createIndexes();
            
            return true;
        } catch(PDOException $e) {
            echo "Error creating advanced SQL objects: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Create database views from external files
     */
    private function createViews() {
        $viewFiles = [
            'v_device_summary.sql',
            'v_log_analysis.sql',
            'v_resolver_performance.sql'
        ];
        
        foreach ($viewFiles as $file) {
            $this->executeSQLFile("sql/views/$file");
        }
    }
    
    /**
     * Create stored procedures from external files
     */
    private function createProcedures() {
        $procedureFiles = [
            'sp_device_health_check.sql',
            'sp_cleanup_old_logs.sql',
            'sp_deploy_device.sql',
            'sp_resolve_issue.sql'
        ];
        
        foreach ($procedureFiles as $file) {
            $this->executeSQLFile("sql/procedures/$file");
        }
    }
    
    /**
     * Create user-defined functions from external files
     */
    private function createFunctions() {
        $functionFiles = [
            'fn_calculate_uptime.sql',
            'fn_device_risk_score.sql',
            'fn_format_duration.sql'
        ];
        
        foreach ($functionFiles as $file) {
            $this->executeSQLFile("sql/functions/$file");
        }
    }
    
    /**
     * Create optimized indexes from external file
     */
    private function createIndexes() {
        $this->executeSQLFile("sql/indexes/performance_indexes.sql");
    }
    
    /**
     * Insert comprehensive sample data using external SQL files
     */
    public function insertSampleData() {
        $this->getConnection();
        
        try {
            $this->conn->beginTransaction();
            
            // Execute demo data files in dependency order (excluding device logs)
            $dataFiles = [
                'users_data.sql',
                'device_types_data.sql', 
                'locations_data.sql',
                'devices_data.sql',
                'deployments_data.sql'
                // Note: device_logs are generated programmatically, not from static file
            ];
            
            foreach ($dataFiles as $file) {
                $this->executeSQLFile("sql/demo_data/$file");
            }
            
            // Generate realistic log data programmatically (this is the correct approach)
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
     * Utility method to execute SQL from external files
     */
    private function executeSQLFile($filePath) {
        $fullPath = __DIR__ . "/../" . $filePath;
        
        if (!file_exists($fullPath)) {
            throw new Exception("SQL file not found: $fullPath");
        }
        
        $sql = file_get_contents($fullPath);
        if ($sql === false) {
            throw new Exception("Unable to read SQL file: $fullPath");
        }
        
        // Replace database name placeholder with actual database name
        $sql = str_replace('{DB_NAME}', $this->db_name, $sql);
        
        // Handle multi-statement files (for procedures and functions)
        if (strpos($sql, 'DELIMITER') !== false) {
            // For files with DELIMITER (stored procedures/functions)
            $this->executeSQLWithDelimiter($sql);
        } else {
            // For regular SQL files
            $this->conn->exec($sql);
        }
    }
    
    /**
     * Execute SQL with custom delimiter handling
     */
    private function executeSQLWithDelimiter($sql) {
        // Remove comments and split by custom delimiter
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by DELIMITER changes
        $parts = preg_split('/DELIMITER\s+(.+)\s*$/m', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $delimiter = ';';
        for ($i = 0; $i < count($parts); $i++) {
            if ($i % 2 == 1) {
                // This is a delimiter change
                $delimiter = trim($parts[$i]);
            } else {
                // This is SQL content
                $statements = explode($delimiter, $parts[$i]);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && $statement !== $delimiter) {
                        try {
                            $this->conn->exec($statement);
                        } catch(PDOException $e) {
                            // Log error but continue
                            error_log("SQL execution warning: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Get SQL definition from file for display purposes
     */
    public function getSQLDefinition($type, $name) {
        $filePath = '';
        switch($type) {
            case 'database':
                $filePath = __DIR__ . "/../sql/create_database.sql";
                break;
            case 'table':
                $filePath = __DIR__ . "/../sql/tables/$name.sql";
                break;
            case 'view':
                $filePath = __DIR__ . "/../sql/views/$name.sql";
                break;
            case 'procedure':
                $filePath = __DIR__ . "/../sql/procedures/$name.sql";
                break;
            case 'function':
                $filePath = __DIR__ . "/../sql/functions/$name.sql";
                break;
            case 'index':
                $filePath = __DIR__ . "/../sql/indexes/$name.sql";
                break;
        }
        
        if (file_exists($filePath)) {
            $sql = file_get_contents($filePath);
            // Replace database name placeholder for display
            $sql = str_replace('{DB_NAME}', $this->db_name, $sql);
            return $sql;
        }
        
        return "-- Definition file not found: $filePath";
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
            $stmt = $this->conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$this->db_name]);
            $status['database_exists'] = ($stmt->fetchColumn() !== false);
            
            if (!$status['database_exists']) {
                return $status;
            }
            
            // Now try to use the database
            try {
                $this->conn->exec("USE " . $this->db_name);
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
            $stmt = $this->conn->prepare("SHOW PROCEDURE STATUS WHERE Db = ?");
            $stmt->execute([$this->db_name]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $status['procedures'][] = $row['Name'];
            }
            
            // Get functions
            $stmt = $this->conn->prepare("SHOW FUNCTION STATUS WHERE Db = ?");
            $stmt->execute([$this->db_name]);
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
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
            $stmt->execute([$this->db_name, $tableName]);
            $info['columns'] = $stmt->fetchColumn();
            
            // Get table size
            $stmt = $this->conn->prepare("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = ? 
                AND table_name = ?
            ");
            $stmt->execute([$this->db_name, $tableName]);
            $size = $stmt->fetchColumn();
            $info['size'] = $size ? $size . ' MB' : '< 0.01 MB';
            
            // Get creation time
            $stmt = $this->conn->prepare("
                SELECT create_time 
                FROM information_schema.tables 
                WHERE table_schema = ? 
                AND table_name = ?
            ");
            $stmt->execute([$this->db_name, $tableName]);
            $created = $stmt->fetchColumn();
            $info['created'] = $created ? date('Y-m-d H:i', strtotime($created)) : 'Unknown';
            
            return $info;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Generate realistic log data for the last 30 days
     * 
     * This method programmatically creates device logs with:
     * - Realistic time distribution (3-15 logs per device per day)
     * - Device-specific error patterns (error/maintenance devices have more issues)
     * - Weighted log type distribution based on device status
     * - Automatic resolution simulation for errors/warnings
     * - Dynamic message templates with variable data
     * - Severity levels based on log type
     * 
     * This approach is superior to static SQL data because:
     * - Always generates current date-relative data
     * - Scales to any number of devices automatically  
     * - Creates realistic patterns and correlations
     * - Simulates actual IoT device behavior
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