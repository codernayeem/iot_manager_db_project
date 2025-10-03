<?php
session_start();
require_once 'config/database.php';

/**
 * Database Management Dashboard
 * SQL Features: Database creation, Table management, Advanced SQL objects
 */

// Handle database setup request
if (isset($_GET['setup_db']) && $_GET['setup_db'] == '1') {
    try {
        $database = new Database();
        $database->createDatabase();
        $database->createTables();
        $database->insertSampleData();
        
        $_SESSION['success_message'] = 'Database setup completed successfully!';
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Database setup failed: ' . $e->getMessage();
    }
}

// Handle configuration updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_config'])) {
    try {
        $database = new Database();
        $newConfig = [
            'host' => $_POST['host'] ?? '',
            'db_name' => $_POST['db_name'] ?? '',
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'port' => (int)($_POST['port'] ?? 3306),
            'charset' => $_POST['charset'] ?? 'utf8'
        ];
        
        $database->updateConfig($newConfig);
        $_SESSION['success_message'] = 'Configuration updated successfully!';
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Configuration update failed: ' . $e->getMessage();
    }
}

// Get current configuration and database status
$database = new Database();
$currentConfig = $database->getConfig();
$message = '';
$error = '';
$logs = [];

// Display session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Initialize connection and get database status
try {
    $database->getConnection();
    $dbStatus = $database->getDatabaseStatus();
    
    // Get table row counts if database exists
    if ($dbStatus['database_exists'] && count($dbStatus['tables']) > 0) {
        $conn = $database->getConnection();
        $tableRowCounts = [];
        foreach ($dbStatus['tables'] as $table) {
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
                $stmt->execute();
                $tableRowCounts[$table] = $stmt->fetchColumn();
            } catch (Exception $e) {
                $tableRowCounts[$table] = 0;
            }
        }
        $dbStatus['table_row_counts'] = $tableRowCounts;
    } else {
        $dbStatus['table_row_counts'] = [];
    }
} catch (Exception $e) {
    $dbStatus = [
        'connected' => false,
        'database_exists' => false,
        'tables' => [],
        'views' => [],
        'procedures' => [],
        'functions' => [],
        'table_info' => [],
        'table_row_counts' => []
    ];
    $error = "Status check failed: " . $e->getMessage();
}

// Handle actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'do_all':
            // Create database
            if (!$dbStatus['database_exists']) {
                if ($database->createDatabase()) {
                    $logs[] = "✅ Database 'iot_device_manager' created successfully";
                } else {
                    $logs[] = "❌ Failed to create database";
                    break;
                }
            }
            
            // Create tables
            if (count($dbStatus['tables']) < 6) {
                if ($database->createTables()) {
                    $logs[] = "✅ All tables created successfully";
                    $logs[] = "✅ Views, procedures, functions, and indexes created";
                } else {
                    $logs[] = "❌ Failed to create tables";
                    break;
                }
            }
            
            // Insert sample data
            $conn = $database->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $userCount = $stmt->fetchColumn();
            
            if ($userCount == 0) {
                if ($database->insertSampleData()) {
                    $logs[] = "✅ Comprehensive sample data inserted successfully";
                    $logs[] = "✅ 18 devices, 6 locations, 30 days of logs added";
                } else {
                    $logs[] = "❌ Failed to insert sample data";
                }
            } else {
                $logs[] = "ℹ️ Sample data already exists";
            }
            
            $logs[] = "🎉 Complete database setup finished!";
            break;
            
        case 'create_database':
            if ($database->createDatabase()) {
                $logs[] = "✅ Database 'iot_device_manager' created successfully";
            } else {
                $logs[] = "❌ Failed to create database";
            }
            break;
            
        case 'create_tables':
            if ($database->createTables()) {
                $logs[] = "✅ All tables created successfully";
                $logs[] = "✅ Views created (v_device_summary, v_log_analysis, v_resolver_performance)";
                $logs[] = "✅ Stored procedures created (sp_device_health_check, sp_cleanup_old_logs, sp_deploy_device, sp_resolve_issue)";
                $logs[] = "✅ Functions created (fn_calculate_uptime, fn_device_risk_score, fn_format_duration)";
                $logs[] = "✅ Performance indexes created";
            } else {
                $logs[] = "❌ Failed to create tables";
            }
            break;
            
        case 'insert_sample_data':
            if ($database->insertSampleData()) {
                $logs[] = "✅ Comprehensive sample data inserted successfully";
                $logs[] = "✅ 18 devices across 10 categories";
                $logs[] = "✅ 6 locations with GPS coordinates";
                $logs[] = "✅ 5 technician users";
                $logs[] = "✅ 30 days of realistic log data (3000+ entries)";
            } else {
                $logs[] = "❌ Failed to insert sample data";
            }
            break;
            
        case 'reset_database':
            try {
                $conn = new PDO("mysql:host=localhost", "root", "");
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Drop database and recreate
                $conn->exec("DROP DATABASE IF EXISTS iot_device_manager");
                $logs[] = "✅ Database dropped successfully";
                
                // Reinitialize database connection
                $database = new Database();
                $database->getConnection();
                $logs[] = "✅ Database connection reinitialized";
                
            } catch (Exception $e) {
                $logs[] = "❌ Reset failed: " . $e->getMessage();
            }
            break;
            
        case 'update_config':
            try {
                $host = $_POST['host'] ?? 'localhost';
                $db_name = $_POST['db_name'] ?? 'iot_device_manager';
                $username = $_POST['username'] ?? 'root';
                $password = $_POST['password'] ?? '';
                
                // Validate connection first
                $testConn = new PDO("mysql:host=$host", $username, $password);
                $testConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $logs[] = "✅ Connection test successful";
                
                // Update the configuration file
                if (updateDatabaseConfig($host, $db_name, $username, $password)) {
                    $logs[] = "✅ Database configuration updated successfully";
                    $logs[] = "ℹ️ Please refresh the page to apply new settings";
                } else {
                    $logs[] = "❌ Failed to update configuration file";
                }
                
            } catch (Exception $e) {
                $logs[] = "❌ Configuration update failed: " . $e->getMessage();
            }
            break;
    }
}

// Refresh database status after actions
if (isset($_POST['action'])) {
    try {
        $dbStatus = $database->getDatabaseStatus();
        
        // Get table row counts if database exists
        if ($dbStatus['database_exists'] && count($dbStatus['tables']) > 0) {
            $conn = $database->getConnection();
            $tableRowCounts = [];
            foreach ($dbStatus['tables'] as $table) {
                try {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM `$table`");
                    $stmt->execute();
                    $tableRowCounts[$table] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $tableRowCounts[$table] = 0;
                }
            }
            $dbStatus['table_row_counts'] = $tableRowCounts;
        } else {
            $dbStatus['table_row_counts'] = [];
        }
    } catch (Exception $e) {
        $error .= " Status refresh failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Device Manager - Database Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-good { color: #10b981; }
        .status-missing { color: #ef4444; }
        .status-partial { color: #f59e0b; }
        
        .log-entry {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-6">
            <i class="fas fa-database text-3xl text-blue-600 mb-2"></i>
            <h1 class="text-2xl font-bold text-gray-800 mb-1">IoT Device Manager</h1>
            <p class="text-md text-gray-600">Database Management Dashboard</p>
            
            <!-- Quick Configuration Access -->
            <div class="mt-4 flex justify-center gap-2">
                <a href="db_config.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm transition-colors">
                    <i class="fas fa-cog"></i> Database Config
                </a>
                <span class="text-xs text-gray-500 flex items-center">
                    <span class="w-2 h-2 bg-<?php echo $dbStatus['connected'] ? 'green' : 'red'; ?>-500 rounded-full mr-1"></span>
                    <?php echo htmlspecialchars($currentConfig['host'] . ':' . ($currentConfig['port'] ?? 3306) . '/' . $currentConfig['db_name']); ?>
                </span>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded mb-3 text-sm">
                <i class="fas fa-check-circle mr-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded mb-3 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Action Logs -->
        <?php if (!empty($logs)): ?>
            <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                <h3 class="text-md font-bold text-gray-800 mb-3">
                    <i class="fas fa-list mr-2"></i>Recent Actions
                </h3>
                <div class="space-y-1">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-entry p-2 bg-gray-50 rounded text-xs font-mono">
                            <?php echo $log; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Database Status Overview -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-heartbeat mr-2"></i>Database Status
            </h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <!-- Database Status -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Database</h3>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span>Connection:</span>
                            <span class="<?php echo $dbStatus['connected'] ? 'status-good' : 'status-missing'; ?>">
                                <i class="fas fa-<?php echo $dbStatus['connected'] ? 'check' : 'times'; ?>"></i>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Database:</span>
                            <span class="<?php echo $dbStatus['database_exists'] ? 'status-good' : 'status-missing'; ?>" 
                                  onclick="showSQLModal('create_database')" 
                                  title="Click to see SQL commands"
                                  style="cursor: pointer;">
                                <i class="fas fa-<?php echo $dbStatus['database_exists'] ? 'check' : 'times'; ?>"></i>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Tables Status -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Tables (<?php echo count($dbStatus['tables']); ?>/6)</h3>
                    <div class="space-y-1 text-xs">
                        <?php foreach (['users', 'device_types', 'devices', 'locations', 'deployments', 'device_logs'] as $table): ?>
                            <div class="flex justify-between">
                                <span onclick="showTableModal('<?php echo $table; ?>')" 
                                      title="Click for table details" 
                                      style="cursor: pointer; text-decoration: underline;">
                                    <?php echo $table; ?>:
                                </span>
                                <span class="<?php echo in_array($table, $dbStatus['tables']) ? 'status-good' : 'status-missing'; ?>"
                                      onclick="showSQLModal('table_<?php echo $table; ?>')"
                                      title="Click to see CREATE TABLE SQL"
                                      style="cursor: pointer;">
                                    <i class="fas fa-<?php echo in_array($table, $dbStatus['tables']) ? 'check' : 'times'; ?>"></i>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Views Status -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Views (<?php echo count($dbStatus['views']); ?>/3)</h3>
                    <div class="space-y-1 text-xs">
                        <?php foreach (['v_device_summary', 'v_log_analysis', 'v_resolver_performance'] as $view): ?>
                            <div class="flex justify-between">
                                <span onclick="showSQLModal('view_<?php echo $view; ?>')" 
                                      title="Click to see SQL" 
                                      style="cursor: pointer; text-decoration: underline;">
                                    <?php echo str_replace('v_', '', $view); ?>:
                                </span>
                                <span class="<?php echo in_array($view, $dbStatus['views']) ? 'status-good' : 'status-missing'; ?>"
                                      onmouseover="showTooltip(this, 'CREATE VIEW <?php echo $view; ?> AS ...')"
                                      onmouseout="hideTooltip()">
                                    <i class="fas fa-<?php echo in_array($view, $dbStatus['views']) ? 'check' : 'times'; ?>"></i>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Procedures & Functions -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Procedures & Functions</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span onclick="showSQLModal('procedures')" 
                                  title="Click to see SQL" 
                                  style="cursor: pointer; text-decoration: underline;">
                                Procedures:
                            </span>
                            <span class="<?php echo count($dbStatus['procedures']) >= 4 ? 'status-good' : 'status-missing'; ?>"
                                  onmouseover="showTooltip(this, 'CREATE PROCEDURE sp_...')"
                                  onmouseout="hideTooltip()">
                                <?php echo count($dbStatus['procedures']); ?>/4
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span onclick="showSQLModal('functions')" 
                                  title="Click to see SQL" 
                                  style="cursor: pointer; text-decoration: underline;">
                                Functions:
                            </span>
                            <span class="<?php echo count($dbStatus['functions']) >= 3 ? 'status-good' : 'status-missing'; ?>"
                                  onmouseover="showTooltip(this, 'CREATE FUNCTION fn_...')"
                                  onmouseout="hideTooltip()">
                                <?php echo count($dbStatus['functions']); ?>/3
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Table Overview Section -->
        <?php if ($dbStatus['database_exists'] && count($dbStatus['tables']) > 0): ?>
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-table mr-2"></i>Table Overview
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($dbStatus['tables'] as $table): ?>
                <div class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition cursor-pointer"
                     onclick="showTableModal('<?php echo $table; ?>')">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-semibold text-gray-800 text-sm">
                            <i class="fas fa-table mr-1 text-blue-600"></i>
                            <?php echo $table; ?>
                        </h3>
                        <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded">
                            <?php echo isset($dbStatus['table_row_counts'][$table]) ? number_format($dbStatus['table_row_counts'][$table]) : '0'; ?> rows
                        </span>
                    </div>
                    
                    <div class="text-xs text-gray-600 space-y-1">
                        <?php 
                        $descriptions = [
                            'users' => 'System users and administrators',
                            'device_types' => 'Categories of IoT devices',
                            'devices' => 'Registered IoT devices',
                            'locations' => 'Device deployment locations',
                            'deployments' => 'Device-location assignments',
                            'device_logs' => 'Device activity and error logs'
                        ];
                        echo $descriptions[$table] ?? 'Database table';
                        ?>
                    </div>
                    
                    <div class="mt-3 flex justify-between items-center">
                        <span class="text-xs text-blue-600 hover:text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>View Details
                        </span>
                        <span class="text-xs text-green-600 hover:text-green-800"
                              onclick="event.stopPropagation(); showSQLModal('table_<?php echo $table; ?>')">
                            <i class="fas fa-code mr-1"></i>View SQL
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Management Actions -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-cogs mr-2"></i>Database Management & Application Access
            </h2>
            
            <!-- Do All Button -->
            <div class="mb-4">
            <form method="POST" class="text-center">
                <input type="hidden" name="action" value="do_all">
                <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition text-sm font-medium"
                    onmouseover="showTooltip(this, 'CREATE DATABASE + CREATE TABLES + INSERT DATA')"
                    onmouseout="hideTooltip()">
                <i class="fas fa-magic mr-2"></i>Setup Complete Database
                </button>
                <p class="text-xs text-gray-500 mt-1">Creates database, tables, views, procedures, functions & inserts sample data</p>
            </form>
            </div>
            
            <div class="grid grid-cols-3 gap-6">
            <!-- Left Side - Database Actions -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3">Database Operations</h3>
                <div class="space-y-3">
                <!-- Create Database -->
                <form method="POST">
                    <input type="hidden" name="action" value="create_database">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-3 rounded hover:bg-blue-700 transition text-sm <?php echo $dbStatus['database_exists'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                        <?php echo $dbStatus['database_exists'] ? 'disabled' : ''; ?>
                        onmouseover="showTooltip(this, 'CREATE DATABASE iot_device_manager')"
                        onmouseout="hideTooltip()">
                    <i class="fas fa-database mr-1"></i>
                    Create Database
                    </button>
                </form>
                
                <!-- Create Tables -->
                <form method="POST">
                    <input type="hidden" name="action" value="create_tables">
                    <button type="submit" class="w-full bg-green-600 text-white py-2 px-3 rounded hover:bg-green-700 transition text-sm <?php echo !$dbStatus['database_exists'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                        <?php echo !$dbStatus['database_exists'] ? 'disabled' : ''; ?>
                        onmouseover="showTooltip(this, 'CREATE TABLES + VIEWS + PROCEDURES + FUNCTIONS')"
                        onmouseout="hideTooltip()">
                    <i class="fas fa-table mr-1"></i>
                    Create Tables
                    </button>
                </form>
                
                <!-- Insert Sample Data -->
                <form method="POST">
                    <input type="hidden" name="action" value="insert_sample_data">
                    <button type="submit" class="w-full bg-orange-600 text-white py-2 px-3 rounded hover:bg-orange-700 transition text-sm <?php echo count($dbStatus['tables']) < 6 ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                        <?php echo count($dbStatus['tables']) < 6 ? 'disabled' : ''; ?>
                        onmouseover="showTooltip(this, 'INSERT INTO tables VALUES (...)')"
                        onmouseout="hideTooltip()">
                    <i class="fas fa-download mr-1"></i>
                    Insert Sample Data
                    </button>
                </form>
                
                <!-- Reset Database -->
                <form method="POST" onsubmit="return confirm('This will delete ALL data. Are you sure?')">
                    <input type="hidden" name="action" value="reset_database">
                    <button type="submit" class="w-full bg-red-600 text-white py-2 px-3 rounded hover:bg-red-700 transition text-sm"
                        onmouseover="showTooltip(this, 'DROP DATABASE iot_device_manager')"
                        onmouseout="hideTooltip()">
                    <i class="fas fa-trash mr-1"></i>
                    Reset Database
                    </button>
                </form>
                </div>
            </div>
            
            <!-- Middle - Database Configuration -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3">Database Configuration</h3>
                <div class="bg-gray-50 rounded p-3 mb-4">
                    <h4 class="font-semibold text-gray-800 mb-2 text-sm">Current Settings:</h4>
                    <div class="text-xs text-gray-600 space-y-1">
                        <p><strong>Host:</strong> <?php echo htmlspecialchars($currentConfig['host'] ?? 'localhost'); ?></p>
                        <p><strong>Database:</strong> <?php echo htmlspecialchars($currentConfig['db_name'] ?? 'iot_device_manager'); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($currentConfig['username'] ?? 'root'); ?></p>
                        <p><strong>Password:</strong> <?php echo $currentConfig['password'] ? str_repeat('*', strlen($currentConfig['password'])) : 'No password'; ?></p>
                    </div>
                </div>
                
                <button onclick="showConfigModal()" class="w-full bg-indigo-600 text-white py-2 px-3 rounded hover:bg-indigo-700 transition text-sm">
                    <i class="fas fa-cog mr-1"></i>Edit Configuration
                </button>
            </div>
            
            <!-- Right Side - Application Access -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3">Application Access</h3>
                <div class="space-y-3">
                <a href="login.php" class="block bg-blue-600 text-white py-2 px-3 rounded text-center hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-sign-in-alt mr-1"></i>Login
                </a>
                
                <a href="register.php" class="block bg-gray-600 text-white py-2 px-3 rounded text-center hover:bg-gray-700 transition text-sm">
                    <i class="fas fa-user-plus mr-1"></i>Register
                </a>
                
                <a href="sql_features.php" class="block bg-green-600 text-white py-2 px-3 rounded text-center hover:bg-green-700 transition text-sm">
                    <i class="fas fa-code mr-1"></i>SQL Features
                </a>
                
                <a href="dashboard.php" class="block bg-purple-600 text-white py-2 px-3 rounded text-center hover:bg-purple-700 transition text-sm">
                    <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                </a>
                </div>
                
            </div>
            </div>
        </div>
    </div>
    
    <!-- Database Configuration Modal -->
    <div id="configModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="bg-indigo-600 text-white p-4 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold">Database Configuration</h3>
                        <button onclick="closeConfigModal()" class="text-white hover:text-gray-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update_config">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Database Host</label>
                            <input type="text" name="host" value="<?php echo htmlspecialchars($currentConfig['host'] ?? 'localhost'); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="localhost" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
                            <input type="text" name="db_name" value="<?php echo htmlspecialchars($currentConfig['db_name'] ?? 'iot_device_manager'); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="iot_device_manager" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($currentConfig['username'] ?? 'root'); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="root" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" name="password" value="<?php echo htmlspecialchars($currentConfig['password'] ?? ''); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="Leave empty for no password">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex space-x-3">
                        <button type="button" onclick="closeConfigModal()" 
                                class="flex-1 bg-gray-600 text-white py-2 px-4 rounded hover:bg-gray-700 transition text-sm">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition text-sm">
                            <i class="fas fa-save mr-1"></i>Save & Test
                        </button>
                    </div>
                    
                    <div class="mt-4 bg-yellow-50 p-3 rounded text-xs text-yellow-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Warning:</strong> Changes will test the connection first. The page will need to be refreshed after saving.
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- SQL Modal -->
    <div id="sqlModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-[80vh] overflow-y-auto">
                <div class="bg-blue-600 text-white p-4 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="sqlModalTitle">SQL Commands</h3>
                        <button onclick="closeSQLModal()" class="text-white hover:text-gray-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6" id="sqlModalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Table Details Modal -->
    <div id="tableModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-5xl w-full max-h-[80vh] overflow-y-auto">
                <div class="bg-green-600 text-white p-4 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="tableModalTitle">Table Structure</h3>
                        <button onclick="closeTableModal()" class="text-white hover:text-gray-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6" id="tableModalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tooltip -->
    <div id="tooltip" class="fixed bg-gray-800 text-white p-2 rounded text-xs z-50 hidden max-w-xs"></div>
    
    <script>
        // Tooltip functions
        let tooltipTimeout;
        
        function showTooltip(element, text) {
            clearTimeout(tooltipTimeout);
            const tooltip = document.getElementById('tooltip');
            tooltip.textContent = text;
            tooltip.classList.remove('hidden');
            
            const rect = element.getBoundingClientRect();
            tooltip.style.left = (rect.left + window.scrollX) + 'px';
            tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 5) + 'px';
        }
        
        function hideTooltip() {
            tooltipTimeout = setTimeout(() => {
                document.getElementById('tooltip').classList.add('hidden');
            }, 100);
        }
        
        // SQL Modal functions
        function showSQLModal(type) {
            const modal = document.getElementById('sqlModal');
            const title = document.getElementById('sqlModalTitle');
            const content = document.getElementById('sqlModalContent');
            
            let sqlContent = '';
            
            switch(type) {
                case 'create_database':
                    title.textContent = 'Create Database SQL';
                    sqlContent = `
                        <h4 class="font-bold mb-2">Database Creation</h4>
                        <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>CREATE DATABASE IF NOT EXISTS iot_device_manager 
CHARACTER SET utf8 
COLLATE utf8_general_ci;</code></pre>
                        <p class="mt-2 text-sm text-gray-600">Creates the main database with UTF-8 character set for international support.</p>
                    `;
                    break;
                    
                case 'procedures':
                    title.textContent = 'Stored Procedures SQL';
                    sqlContent = `
                        <h4 class="font-bold mb-2">Sample Stored Procedure: Device Health Check</h4>
                        <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>CREATE PROCEDURE sp_device_health_check(
    IN device_id INT,
    OUT health_status VARCHAR(20),
    OUT last_activity DATETIME,
    OUT issue_count INT
)
BEGIN
    DECLARE recent_errors INT DEFAULT 0;
    DECLARE last_log DATETIME;
    
    -- Get last activity
    SELECT MAX(log_time) INTO last_log 
    FROM device_logs 
    WHERE d_id = device_id;
    
    SET last_activity = last_log;
    
    -- Count recent errors (last 24 hours)
    SELECT COUNT(*) INTO recent_errors 
    FROM device_logs 
    WHERE d_id = device_id 
    AND log_type = 'error' 
    AND log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    SET issue_count = recent_errors;
    
    -- Determine health status
    IF last_log IS NULL THEN
        SET health_status = 'No Data';
    ELSEIF last_log < DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN
        SET health_status = 'Offline';
    ELSEIF recent_errors > 5 THEN
        SET health_status = 'Critical';
    ELSEIF recent_errors > 2 THEN
        SET health_status = 'Warning';
    ELSE
        SET health_status = 'Healthy';
    END IF;
END;</code></pre>
                        <p class="mt-2 text-sm text-gray-600">Advanced procedure with error handling, conditional logic, and output parameters.</p>
                    `;
                    break;
                    
                case 'functions':
                    title.textContent = 'User-Defined Functions SQL';
                    sqlContent = `
                        <h4 class="font-bold mb-2">Sample Function: Calculate Device Uptime</h4>
                        <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>CREATE FUNCTION fn_calculate_uptime(device_id INT, days_back INT) 
RETURNS DECIMAL(5,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE total_hours DECIMAL(10,2);
    DECLARE error_hours DECIMAL(10,2);
    DECLARE uptime_percentage DECIMAL(5,2);
    
    SET total_hours = days_back * 24;
    
    -- Calculate hours with errors
    SELECT COALESCE(COUNT(*) * 0.5, 0) INTO error_hours
    FROM device_logs 
    WHERE d_id = device_id 
    AND log_type = 'error'
    AND log_time >= DATE_SUB(NOW(), INTERVAL days_back DAY);
    
    -- Calculate uptime percentage
    SET uptime_percentage = ((total_hours - error_hours) / total_hours) * 100;
    
    -- Ensure result is between 0 and 100
    IF uptime_percentage < 0 THEN SET uptime_percentage = 0; END IF;
    IF uptime_percentage > 100 THEN SET uptime_percentage = 100; END IF;
    
    RETURN uptime_percentage;
END;</code></pre>
                        <p class="mt-2 text-sm text-gray-600">Function with mathematical calculations and conditional logic for device uptime.</p>
                    `;
                    break;
                    
                default:
                    if (type.startsWith('view_')) {
                        const viewName = type.replace('view_', '');
                        title.textContent = 'View: ' + viewName;
                        sqlContent = `
                            <h4 class="font-bold mb-2">View Definition</h4>
                            <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>CREATE VIEW ${viewName} AS
SELECT 
    d.d_id,
    d.device_name,
    dt.t_name as device_type,
    COUNT(dl.log_id) as total_logs,
    SUM(CASE WHEN dl.log_type = 'error' THEN 1 ELSE 0 END) as error_count,
    MAX(dl.log_time) as last_activity
FROM devices d
LEFT JOIN device_types dt ON d.type_id = dt.type_id
LEFT JOIN device_logs dl ON d.d_id = dl.d_id
WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY d.d_id, d.device_name, dt.t_name;</code></pre>
                            <p class="mt-2 text-sm text-gray-600">Complex view with JOINs, aggregation, and window functions.</p>
                        `;
                    } else if (type.startsWith('table_')) {
                        const tableName = type.replace('table_', '');
                        title.textContent = 'CREATE TABLE: ' + tableName;
                        
                        const tableSQLs = {
                            'users': `CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    f_name VARCHAR(50) NOT NULL,
    l_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'technician', 'viewer') DEFAULT 'technician',
    phone VARCHAR(20),
    department VARCHAR(50),
    hire_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_name (f_name, l_name),
    INDEX idx_user_type (user_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;`,
                            'device_types': `CREATE TABLE device_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    t_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    power_consumption DECIMAL(8,2),
    operating_temp_min INT,
    operating_temp_max INT,
    warranty_months INT DEFAULT 12,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_type_name (t_name),
    INDEX idx_manufacturer (manufacturer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;`,
                            'devices': `CREATE TABLE devices (
    d_id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(100) NOT NULL,
    device_id VARCHAR(50) UNIQUE NOT NULL,
    type_id INT,
    status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'active',
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    firmware_version VARCHAR(20),
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    installation_date DATE,
    warranty_expiry DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (type_id) REFERENCES device_types(type_id) ON DELETE SET NULL,
    INDEX idx_device_id (device_id),
    INDEX idx_status (status),
    INDEX idx_type (type_id),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;`,
                            'locations': `CREATE TABLE locations (
    loc_id INT AUTO_INCREMENT PRIMARY KEY,
    loc_name VARCHAR(100) NOT NULL,
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    country VARCHAR(50) DEFAULT 'USA',
    postal_code VARCHAR(20),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    timezone VARCHAR(50) DEFAULT 'UTC',
    facility_type VARCHAR(50),
    contact_person VARCHAR(100),
    contact_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_city (city),
    INDEX idx_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;`,
                            'deployments': `CREATE TABLE deployments (
    deployment_id INT AUTO_INCREMENT PRIMARY KEY,
    d_id INT NOT NULL,
    loc_id INT NOT NULL,
    deployment_date DATE NOT NULL,
    deployed_by INT,
    installation_notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    maintenance_schedule ENUM('weekly', 'monthly', 'quarterly', 'annually') DEFAULT 'monthly',
    next_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE,
    FOREIGN KEY (loc_id) REFERENCES locations(loc_id) ON DELETE CASCADE,
    FOREIGN KEY (deployed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_active_deployment (d_id, is_active),
    INDEX idx_location (loc_id),
    INDEX idx_deployment_date (deployment_date),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;`,
                            'device_logs': `CREATE TABLE device_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    d_id INT NOT NULL,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    log_type ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    message TEXT NOT NULL,
    error_code VARCHAR(20),
    severity INT DEFAULT 1,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_device_time (d_id, log_time),
    INDEX idx_log_type (log_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (resolved),
    INDEX idx_log_time (log_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;`
                        };
                        
                        sqlContent = `
                            <h4 class="font-bold mb-2">Table Creation SQL</h4>
                            <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>${tableSQLs[tableName] || 'SQL not available for this table'}</code></pre>
                            <p class="mt-2 text-sm text-gray-600">Complete table definition with constraints, indexes, and foreign keys.</p>
                        `;
                    }
            }
            
            content.innerHTML = sqlContent;
            modal.classList.remove('hidden');
        }
        
        function closeSQLModal() {
            document.getElementById('sqlModal').classList.add('hidden');
        }
        
        // Table Modal functions
        function showTableModal(tableName) {
            const modal = document.getElementById('tableModal');
            const title = document.getElementById('tableModalTitle');
            const content = document.getElementById('tableModalContent');
            
            title.textContent = 'Table: ' + tableName;
            
            // Get row count for display
            const rowCounts = <?php echo json_encode($dbStatus['table_row_counts'] ?? []); ?>;
            
            // Table structure definitions
            const tableStructures = {
                'users': `
                    <div class="mb-4 bg-blue-50 p-3 rounded">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-blue-800">Table: users</h4>
                            <div class="text-sm text-blue-600">
                                <span class="mr-4"><i class="fas fa-table mr-1"></i>Rows: ${rowCounts.users || 0}</span>
                                <button onclick="showSQLModal('table_users')" class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700">
                                    <i class="fas fa-code mr-1"></i>View SQL
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-blue-700 mt-1">System users and administrators</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-4 py-2">Column</th>
                                    <th class="border px-4 py-2">Type</th>
                                    <th class="border px-4 py-2">Constraints</th>
                                    <th class="border px-4 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="border px-4 py-2 font-mono">user_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">PRIMARY KEY, AUTO_INCREMENT</td><td class="border px-4 py-2">Unique user identifier</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">f_name</td><td class="border px-4 py-2">VARCHAR(50)</td><td class="border px-4 py-2">NOT NULL</td><td class="border px-4 py-2">First name</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">l_name</td><td class="border px-4 py-2">VARCHAR(50)</td><td class="border px-4 py-2">NOT NULL</td><td class="border px-4 py-2">Last name</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">email</td><td class="border px-4 py-2">VARCHAR(100)</td><td class="border px-4 py-2">UNIQUE, NOT NULL</td><td class="border px-4 py-2">Email address</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">password</td><td class="border px-4 py-2">VARCHAR(255)</td><td class="border px-4 py-2">NOT NULL</td><td class="border px-4 py-2">Hashed password</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">user_type</td><td class="border px-4 py-2">ENUM</td><td class="border px-4 py-2">DEFAULT 'technician'</td><td class="border px-4 py-2">admin, technician, viewer</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">created_at</td><td class="border px-4 py-2">TIMESTAMP</td><td class="border px-4 py-2">DEFAULT CURRENT_TIMESTAMP</td><td class="border px-4 py-2">Creation timestamp</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">updated_at</td><td class="border px-4 py-2">TIMESTAMP</td><td class="border px-4 py-2">ON UPDATE CURRENT_TIMESTAMP</td><td class="border px-4 py-2">Last update timestamp</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <h5 class="font-bold">Indexes:</h5>
                            <ul class="text-sm text-gray-600 mt-2">
                                <li>• <code>idx_email</code> - Fast email lookups</li>
                                <li>• <code>idx_name</code> - Name-based searches</li>
                                <li>• <code>idx_user_type</code> - Role filtering</li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="font-bold">Features:</h5>
                            <ul class="text-sm text-gray-600 mt-2">
                                <li>• Role-based access control</li>
                                <li>• Automatic timestamps</li>
                                <li>• Unique email constraint</li>
                            </ul>
                        </div>
                    </div>
                `,
                'devices': `
                    <div class="mb-4 bg-green-50 p-3 rounded">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-green-800">Table: devices</h4>
                            <div class="text-sm text-green-600">
                                <span class="mr-4"><i class="fas fa-table mr-1"></i>Rows: ${rowCounts.devices || 0}</span>
                                <button onclick="showSQLModal('table_devices')" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">
                                    <i class="fas fa-code mr-1"></i>View SQL
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-green-700 mt-1">Registered IoT devices</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-4 py-2">Column</th>
                                    <th class="border px-4 py-2">Type</th>
                                    <th class="border px-4 py-2">Constraints</th>
                                    <th class="border px-4 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="border px-4 py-2 font-mono">d_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">PRIMARY KEY, AUTO_INCREMENT</td><td class="border px-4 py-2">Device ID</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">device_name</td><td class="border px-4 py-2">VARCHAR(100)</td><td class="border px-4 py-2">NOT NULL</td><td class="border px-4 py-2">Device name</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">device_id</td><td class="border px-4 py-2">VARCHAR(50)</td><td class="border px-4 py-2">UNIQUE, NOT NULL</td><td class="border px-4 py-2">Unique device identifier</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">type_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">FOREIGN KEY</td><td class="border px-4 py-2">References device_types(type_id)</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">status</td><td class="border px-4 py-2">ENUM</td><td class="border px-4 py-2">DEFAULT 'active'</td><td class="border px-4 py-2">active, inactive, maintenance, error</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">ip_address</td><td class="border px-4 py-2">VARCHAR(45)</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">IPv4/IPv6 address</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">firmware_version</td><td class="border px-4 py-2">VARCHAR(20)</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">Current firmware version</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">last_seen</td><td class="border px-4 py-2">TIMESTAMP</td><td class="border px-4 py-2">DEFAULT CURRENT_TIMESTAMP</td><td class="border px-4 py-2">Last communication</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <h5 class="font-bold">Foreign Keys:</h5>
                            <ul class="text-sm text-gray-600 mt-2">
                                <li>• <code>fk_device_type</code> - devices.type_id → device_types.type_id</li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="font-bold">Indexes:</h5>
                            <ul class="text-sm text-gray-600 mt-2">
                                <li>• <code>idx_device_id</code> - Unique device lookup</li>
                                <li>• <code>idx_status</code> - Status filtering</li>
                                <li>• <code>idx_last_seen</code> - Activity tracking</li>
                            </ul>
                        </div>
                    </div>
                `,
                'device_types': `
                    <div class="mb-4 bg-purple-50 p-3 rounded">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-purple-800">Table: device_types</h4>
                            <div class="text-sm text-purple-600">
                                <span class="mr-4"><i class="fas fa-table mr-1"></i>Rows: ${rowCounts.device_types || 0}</span>
                                <button onclick="showSQLModal('table_device_types')" class="bg-purple-600 text-white px-3 py-1 rounded text-xs hover:bg-purple-700">
                                    <i class="fas fa-code mr-1"></i>View SQL
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-purple-700 mt-1">Categories and specifications of IoT devices</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-4 py-2">Column</th>
                                    <th class="border px-4 py-2">Type</th>
                                    <th class="border px-4 py-2">Constraints</th>
                                    <th class="border px-4 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="border px-4 py-2 font-mono">type_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">PRIMARY KEY, AUTO_INCREMENT</td><td class="border px-4 py-2">Type ID</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">t_name</td><td class="border px-4 py-2">VARCHAR(50)</td><td class="border px-4 py-2">NOT NULL, UNIQUE</td><td class="border px-4 py-2">Type name</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">description</td><td class="border px-4 py-2">TEXT</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">Type description</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">manufacturer</td><td class="border px-4 py-2">VARCHAR(100)</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">Device manufacturer</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">power_consumption</td><td class="border px-4 py-2">DECIMAL(8,2)</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">Power consumption in watts</td></tr>
                            </tbody>
                        </table>
                    </div>
                `,
                'locations': `
                    <div class="mb-4 bg-indigo-50 p-3 rounded">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-indigo-800">Table: locations</h4>
                            <div class="text-sm text-indigo-600">
                                <span class="mr-4"><i class="fas fa-table mr-1"></i>Rows: ${rowCounts.locations || 0}</span>
                                <button onclick="showSQLModal('table_locations')" class="bg-indigo-600 text-white px-3 py-1 rounded text-xs hover:bg-indigo-700">
                                    <i class="fas fa-code mr-1"></i>View SQL
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-indigo-700 mt-1">Device deployment locations with GPS coordinates</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-4 py-2">Column</th>
                                    <th class="border px-4 py-2">Type</th>
                                    <th class="border px-4 py-2">Constraints</th>
                                    <th class="border px-4 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="border px-4 py-2 font-mono">loc_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">PRIMARY KEY, AUTO_INCREMENT</td><td class="border px-4 py-2">Location ID</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">loc_name</td><td class="border px-4 py-2">VARCHAR(100)</td><td class="border px-4 py-2">NOT NULL</td><td class="border px-4 py-2">Location name</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">address</td><td class="border px-4 py-2">TEXT</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">Street address</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">latitude</td><td class="border px-4 py-2">DECIMAL(10,8)</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">GPS latitude</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">longitude</td><td class="border px-4 py-2">DECIMAL(11,8)</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">GPS longitude</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">contact_person</td><td class="border px-4 py-2">VARCHAR(100)</td><td class="border px-4 py-2">NULL</td><td class="border px-4 py-2">Site contact</td></tr>
                            </tbody>
                        </table>
                    </div>
                `,
                'deployments': `
                    <div class="mb-4 bg-yellow-50 p-3 rounded">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-yellow-800">Table: deployments</h4>
                            <div class="text-sm text-yellow-600">
                                <span class="mr-4"><i class="fas fa-table mr-1"></i>Rows: ${rowCounts.deployments || 0}</span>
                                <button onclick="showSQLModal('table_deployments')" class="bg-yellow-600 text-white px-3 py-1 rounded text-xs hover:bg-yellow-700">
                                    <i class="fas fa-code mr-1"></i>View SQL
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-yellow-700 mt-1">Device-location assignments and deployment tracking</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-4 py-2">Column</th>
                                    <th class="border px-4 py-2">Type</th>
                                    <th class="border px-4 py-2">Constraints</th>
                                    <th class="border px-4 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="border px-4 py-2 font-mono">deployment_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">PRIMARY KEY, AUTO_INCREMENT</td><td class="border px-4 py-2">Deployment ID</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">d_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">FOREIGN KEY, NOT NULL</td><td class="border px-4 py-2">Device reference</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">loc_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">FOREIGN KEY, NOT NULL</td><td class="border px-4 py-2">Location reference</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">deployment_date</td><td class="border px-4 py-2">DATE</td><td class="border px-4 py-2">NOT NULL</td><td class="border px-4 py-2">Date deployed</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">deployed_by</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">FOREIGN KEY</td><td class="border px-4 py-2">User who deployed</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">is_active</td><td class="border px-4 py-2">BOOLEAN</td><td class="border px-4 py-2">DEFAULT TRUE</td><td class="border px-4 py-2">Active deployment</td></tr>
                            </tbody>
                        </table>
                    </div>
                `,
                'device_logs': `
                    <div class="mb-4 bg-red-50 p-3 rounded">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-red-800">Table: device_logs</h4>
                            <div class="text-sm text-red-600">
                                <span class="mr-4"><i class="fas fa-table mr-1"></i>Rows: ${rowCounts.device_logs || 0}</span>
                                <button onclick="showSQLModal('table_device_logs')" class="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700">
                                    <i class="fas fa-code mr-1"></i>View SQL
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-red-700 mt-1">Device activity logs, errors, and resolution tracking</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border px-4 py-2">Column</th>
                                    <th class="border px-4 py-2">Type</th>
                                    <th class="border px-4 py-2">Constraints</th>
                                    <th class="border px-4 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="border px-4 py-2 font-mono">log_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">PRIMARY KEY, AUTO_INCREMENT</td><td class="border px-4 py-2">Log entry ID</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">d_id</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">FOREIGN KEY, NOT NULL</td><td class="border px-4 py-2">Device reference</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">log_time</td><td class="border px-4 py-2">TIMESTAMP</td><td class="border px-4 py-2">DEFAULT CURRENT_TIMESTAMP</td><td class="border px-4 py-2">Log timestamp</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">log_type</td><td class="border px-4 py-2">ENUM</td><td class="border px-4 py-2">DEFAULT 'info'</td><td class="border px-4 py-2">info, warning, error, critical</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">message</td><td class="border px-4 py-2">TEXT</td><td class="border px-4 py-2">NOT NULL</td><td class="border px-4 py-2">Log message</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">resolved</td><td class="border px-4 py-2">BOOLEAN</td><td class="border px-4 py-2">DEFAULT FALSE</td><td class="border px-4 py-2">Issue resolved</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">resolved_by</td><td class="border px-4 py-2">INT</td><td class="border px-4 py-2">FOREIGN KEY</td><td class="border px-4 py-2">User who resolved</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <h5 class="font-bold">Key Features:</h5>
                        <ul class="text-sm text-gray-600 mt-2">
                            <li>• High-volume logging with efficient indexing</li>
                            <li>• Resolution tracking workflow</li>
                            <li>• Severity-based categorization</li>
                            <li>• Performance optimized for time-series queries</li>
                        </ul>
                    </div>
                `
            };
            
            content.innerHTML = tableStructures[tableName] || '<p>Table structure not available</p>';
            modal.classList.remove('hidden');
        }
        
        function closeTableModal() {
            document.getElementById('tableModal').classList.add('hidden');
        }
        
        // Configuration Modal functions
        function showConfigModal() {
            document.getElementById('configModal').classList.remove('hidden');
        }
        
        function closeConfigModal() {
            document.getElementById('configModal').classList.add('hidden');
        }
        
        // Close modals when clicking outside
        document.getElementById('configModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfigModal();
            }
        });
        
        document.getElementById('sqlModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSQLModal();
            }
        });
        
        document.getElementById('tableModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTableModal();
            }
        });
    </script>
</body>
</html>