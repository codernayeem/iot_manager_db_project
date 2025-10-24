<?php
// Suppress any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start output buffering to catch any unexpected output
ob_start();

require_once '../config/database.php';

class DatabaseAPI {
    private $database;
    
    public function __construct() {
        $this->database = new Database();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                default:
                    throw new Exception('Method not allowed');
            }
        } catch (Exception $e) {
            $this->sendResponse(false, $e->getMessage());
        }
    }
    
    private function handleGet($action) {
        switch ($action) {
            case 'status':
                try {
                    $this->database->getConnection();
                    $status = $this->database->getDatabaseStatus();
                    
                    // Mark as connected if we got here without exception
                    $status['connection'] = true;
                    
                    // Get table row counts if database exists
                    if ($status['database_exists'] && count($status['tables']) > 0) {
                        $conn = $this->database->getConnection();
                        $tableRowCounts = [];
                        foreach ($status['tables'] as $table) {
                            try {
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
                                $stmt->execute();
                                $tableRowCounts[$table] = $stmt->fetchColumn();
                            } catch (Exception $e) {
                                $tableRowCounts[$table] = 0;
                            }
                        }
                        $status['table_row_counts'] = $tableRowCounts;
                    } else {
                        $status['table_row_counts'] = [];
                    }
                    
                    $this->sendResponse(true, 'Database status retrieved', $status);
                } catch (Exception $e) {
                    // If connection fails, return disconnected status
                    $status = [
                        'connection' => false,
                        'database_exists' => false,
                        'tables' => [],
                        'views' => [],
                        'procedures' => [],
                        'functions' => [],
                        'table_row_counts' => []
                    ];
                    $this->sendResponse(true, 'Database status retrieved (disconnected)', $status);
                }
                break;
                
            case 'table_structure':
                $tableName = $_GET['table'] ?? '';
                if (!$tableName) {
                    throw new Exception('Table name is required');
                }
                $structure = $this->getTableStructure($tableName);
                $this->sendResponse(true, 'Table structure retrieved', $structure);
                break;
                
            case 'sql_content':
                $type = $_GET['type'] ?? '';
                $name = $_GET['name'] ?? '';
                $content = $this->getSQLContent($type, $name);
                $this->sendResponse(true, 'SQL content retrieved', ['content' => $content]);
                break;
                
            default:
                throw new Exception('Unknown action');
        }
    }
    
    private function handlePost($action) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // If action is not in URL, get it from POST body
        if (empty($action) && isset($input['action'])) {
            $action = $input['action'];
        }
        
        switch ($action) {
            case 'create_database':
                $result = $this->database->createDatabase();
                $message = $result ? 'Database created successfully' : 'Failed to create database';
                $this->sendResponse($result, $message, ['logs' => ["✅ Database 'iot_device_manager' created successfully"]]);
                break;
                
            case 'create_tables':
                $result = $this->database->createTables();
                $logs = [];
                if ($result) {
                    $logs = [
                        "✅ All tables created successfully",
                        "✅ Views created (v_active_devices, v_device_locations)",
                        "✅ Stored procedures created (sp_count_devices_by_status, sp_get_devices_by_type)",
                        "✅ Functions created (fn_count_user_devices, fn_device_status_text)",
                        "✅ Triggers created (trg_device_updated_at, trg_log_new_device)",
                        "✅ Performance indexes created"
                    ];
                } else {
                    $logs = ["❌ Failed to create tables"];
                }
                $message = $result ? 'Tables created successfully' : 'Failed to create tables';
                $this->sendResponse($result, $message, ['logs' => $logs]);
                break;
                
            case 'insert_sample_data':
                $result = $this->database->insertSampleData();
                $logs = [];
                if ($result) {
                    $logs = [
                        "✅ Comprehensive sample data inserted successfully",
                        "✅ 18 devices across 10 categories",
                        "✅ 6 locations with GPS coordinates",
                        "✅ 5 technician users",
                        "✅ 30 days of realistic log data (3000+ entries)"
                    ];
                } else {
                    $logs = ["❌ Failed to insert sample data"];
                }
                $message = $result ? 'Sample data inserted successfully' : 'Failed to insert sample data';
                $this->sendResponse($result, $message, ['logs' => $logs]);
                break;
                
            case 'reset_database':
                try {
                    // Get config for connection
                    $config = $this->database->getConfig();
                    $host = $config['host'] ?? 'localhost';
                    $username = $config['username'] ?? 'root';
                    $password = $config['password'] ?? '';
                    $dbName = $config['db_name'] ?? 'iot_device_manager';
                    
                    // Connect without specifying database
                    $conn = new PDO("mysql:host=$host", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Drop database
                    $conn->exec("DROP DATABASE IF EXISTS `$dbName`");
                    
                    // Reset this instance
                    $this->database = new Database();
                    
                    $logs = [
                        "✅ Database '$dbName' dropped successfully",
                        "✅ Database connection reset"
                    ];
                    $this->sendResponse(true, 'Database reset successfully', ['logs' => $logs]);
                } catch (Exception $e) {
                    $this->sendResponse(false, 'Reset failed: ' . $e->getMessage(), ['logs' => ["❌ Reset failed: " . $e->getMessage()]]);
                }
                break;
                
            case 'setup_all':
                $logs = [];
                $success = true;
                
                try {
                    // Create database
                    $status = $this->getFreshStatus();
                    if (!$status['database_exists']) {
                        if ($this->database->createDatabase()) {
                            $logs[] = "✅ Database created successfully";
                        } else {
                            $logs[] = "❌ Failed to create database";
                            $success = false;
                        }
                    } else {
                        $logs[] = "ℹ️ Database already exists";
                    }
                    
                    // Create tables
                    if ($success) {
                        $status = $this->getFreshStatus();
                        if (count($status['tables']) < 6) {
                            if ($this->database->createTables()) {
                                $logs[] = "✅ All tables created successfully";
                                $logs[] = "✅ Views, procedures, functions, and indexes created";
                            } else {
                                $logs[] = "❌ Failed to create tables";
                                $success = false;
                            }
                        } else {
                            $logs[] = "ℹ️ Tables already exist";
                        }
                    }
                    
                    // Insert sample data
                    if ($success) {
                        $status = $this->getFreshStatus();
                        if (count($status['tables']) >= 6) {
                            $conn = $this->database->getConnection();
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
                            $stmt->execute();
                            $userCount = $stmt->fetchColumn();
                            
                            if ($userCount == 0) {
                                if ($this->database->insertSampleData()) {
                                    $logs[] = "✅ Comprehensive sample data inserted successfully";
                                    $logs[] = "✅ 18 devices, 6 locations, 30 days of logs added";
                                } else {
                                    $logs[] = "❌ Failed to insert sample data";
                                    $success = false;
                                }
                            } else {
                                $logs[] = "ℹ️ Sample data already exists";
                            }
                        }
                    }
                    
                    if ($success) {
                        $logs[] = "🎉 Complete database setup finished!";
                    }
                    
                    $message = $success ? 'Complete setup successful' : 'Setup partially completed';
                    $this->sendResponse($success, $message, ['logs' => $logs]);
                    
                } catch (Exception $e) {
                    $this->sendResponse(false, 'Setup failed: ' . $e->getMessage(), ['logs' => ["❌ Setup failed: " . $e->getMessage()]]);
                }
                break;
                
            default:
                throw new Exception('Unknown action');
        }
    }
    
    private function getSQLContent($type, $name) {
        $sqlDir = '../sql/';
        $content = '';
        
        switch ($type) {
            case 'create_database':
                $content = file_get_contents($sqlDir . 'create_database.sql');
                break;
                
            case 'table':
                $content = file_get_contents($sqlDir . 'tables/' . $name . '.sql');
                break;
                
            case 'view':
                $content = file_get_contents($sqlDir . 'views/' . $name . '.sql');
                break;
                
            case 'procedure':
                $content = file_get_contents($sqlDir . 'procedures/' . $name . '.sql');
                break;
                
            case 'function':
                $content = file_get_contents($sqlDir . 'functions/' . $name . '.sql');
                break;
                
            case 'procedures':
                // Get all procedures
                $procedures = glob($sqlDir . 'procedures/*.sql');
                $allContent = [];
                foreach ($procedures as $file) {
                    $allContent[] = file_get_contents($file);
                }
                $content = implode("\n\n-- " . str_repeat("=", 50) . "\n\n", $allContent);
                break;
                
            case 'functions':
                // Get all functions
                $functions = glob($sqlDir . 'functions/*.sql');
                $allContent = [];
                foreach ($functions as $file) {
                    $allContent[] = file_get_contents($file);
                }
                $content = implode("\n\n-- " . str_repeat("=", 50) . "\n\n", $allContent);
                break;
                
            case 'trigger':
                $content = file_get_contents($sqlDir . 'triggers/' . $name . '.sql');
                break;
                
            case 'triggers':
                // Get all triggers
                $triggers = glob($sqlDir . 'triggers/*.sql');
                $allContent = [];
                foreach ($triggers as $file) {
                    $allContent[] = file_get_contents($file);
                }
                $content = implode("\n\n-- " . str_repeat("=", 50) . "\n\n", $allContent);
                break;
                
            default:
                throw new Exception('Unknown SQL type');
        }
        
        // Clean up the content
        $content = trim($content);
        
        // Remove SQL comments for cleaner display if needed
        // $content = preg_replace('/^--.*$/m', '', $content);
        // $content = preg_replace('/^\s*$/m', '', $content);
        
        return $content;
    }
    
    private function getFreshStatus() {
        try {
            $this->database->getConnection();
            $status = $this->database->getDatabaseStatus();
            $status['connection'] = true;
            return $status;
        } catch (Exception $e) {
            return [
                'connection' => false,
                'database_exists' => false,
                'tables' => [],
                'views' => [],
                'procedures' => [],
                'functions' => [],
                'table_row_counts' => []
            ];
        }
    }
    
    private function getTableStructure($tableName) {
        try {
            $conn = $this->database->getConnection();
            $dbName = $this->database->getConfig()['db_name'];
            
            // Get column information
            $stmt = $conn->prepare("
                SELECT 
                    COLUMN_NAME,
                    DATA_TYPE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE,
                    COLUMN_KEY,
                    EXTRA,
                    COLUMN_COMMENT
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            ");
            $stmt->execute([$dbName, $tableName]);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get foreign key constraints
            $stmt = $conn->prepare("
                SELECT 
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc 
                    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ?
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");
            $stmt->execute([$dbName, $tableName]);
            $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get indexes
            $stmt = $conn->prepare("SHOW INDEX FROM `$tableName`");
            $stmt->execute();
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get table row count
            $stmt = $conn->prepare("SELECT COUNT(*) FROM `$tableName`");
            $stmt->execute();
            $rowCount = $stmt->fetchColumn();
            
            return [
                'table_name' => $tableName,
                'columns' => $columns,
                'foreign_keys' => $foreignKeys,
                'indexes' => $indexes,
                'row_count' => $rowCount
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to get table structure: ' . $e->getMessage());
        }
    }
    
    private function sendResponse($success, $message, $data = null) {
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        // Clear any buffered output
        ob_clean();
        
        echo json_encode($response);
        exit;
    }
}

// Handle the request with error handling
try {
    $api = new DatabaseAPI();
    $api->handleRequest();
} catch (Exception $e) {
    // Clear buffer and send error response
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'API Error: ' . $e->getMessage()
    ]);
}
?>