<?php
session_start();
require_once 'config/database.php';

/**
 * Database Management Dashboard
 * SQL Features: Database creation, Table management, Advanced SQL objects
 */

$database = new Database();
$message = '';
$error = '';
$logs = [];

// Initialize connection
$database->getConnection();

// Get current database status first
try {
    $dbStatus = $database->getDatabaseStatus();
} catch (Exception $e) {
    $dbStatus = [
        'connected' => false,
        'database_exists' => false,
        'tables' => [],
        'views' => [],
        'procedures' => [],
        'functions' => [],
        'table_info' => []
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
    }
}

// Refresh database status after actions
if (isset($_POST['action'])) {
    try {
        $dbStatus = $database->getDatabaseStatus();
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
                                      onmouseover="showTooltip(this, 'CREATE TABLE <?php echo $table; ?> (...)')"
                                      onmouseout="hideTooltip()">
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
        
        <!-- Management Actions -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-cogs mr-2"></i>Database Management
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
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <!-- Create Database -->
                <form method="POST">
                    <input type="hidden" name="action" value="create_database">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-3 rounded hover:bg-blue-700 transition text-sm <?php echo $dbStatus['database_exists'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                            <?php echo $dbStatus['database_exists'] ? 'disabled' : ''; ?>
                            onmouseover="showTooltip(this, 'CREATE DATABASE iot_device_manager')"
                            onmouseout="hideTooltip()">
                        <i class="fas fa-database mr-1"></i>
                        Create DB
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
                        Insert Data
                    </button>
                </form>
                
                <!-- Reset Database -->
                <form method="POST" onsubmit="return confirm('This will delete ALL data. Are you sure?')">
                    <input type="hidden" name="action" value="reset_database">
                    <button type="submit" class="w-full bg-red-600 text-white py-2 px-3 rounded hover:bg-red-700 transition text-sm"
                            onmouseover="showTooltip(this, 'DROP DATABASE iot_device_manager')"
                            onmouseout="hideTooltip()">
                        <i class="fas fa-trash mr-1"></i>
                        Reset
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h2 class="text-lg font-bold text-gray-800 mb-3">
                <i class="fas fa-compass mr-2"></i>Application Access
            </h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <a href="login.php" class="bg-blue-600 text-white py-2 px-3 rounded text-center hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-sign-in-alt mr-1"></i>Login
                </a>
                
                <a href="register.php" class="bg-gray-600 text-white py-2 px-3 rounded text-center hover:bg-gray-700 transition text-sm">
                    <i class="fas fa-user-plus mr-1"></i>Register
                </a>
                
                <a href="sql_features.php" class="bg-green-600 text-white py-2 px-3 rounded text-center hover:bg-green-700 transition text-sm">
                    <i class="fas fa-code mr-1"></i>SQL Features
                </a>
                
                <a href="dashboard.php" class="bg-purple-600 text-white py-2 px-3 rounded text-center hover:bg-purple-700 transition text-sm">
                    <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                </a>
            </div>
            
            <!-- Default Credentials -->
            <div class="mt-4 bg-gray-50 rounded p-3">
                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Default Login Credentials:</h4>
                <div class="text-xs text-gray-600 space-y-1">
                    <p><strong>Admin:</strong> admin@iotmanager.com / admin123</p>
                    <p><strong>Technician:</strong> john.smith@tech.com / password123</p>
                </div>
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
            
            // Table structure definitions
            const tableStructures = {
                'users': `
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
                                <tr><td class="border px-4 py-2 font-mono">created_at</td><td class="border px-4 py-2">TIMESTAMP</td><td class="border px-4 py-2">DEFAULT CURRENT_TIMESTAMP</td><td class="border px-4 py-2">Creation timestamp</td></tr>
                                <tr><td class="border px-4 py-2 font-mono">updated_at</td><td class="border px-4 py-2">TIMESTAMP</td><td class="border px-4 py-2">ON UPDATE CURRENT_TIMESTAMP</td><td class="border px-4 py-2">Last update timestamp</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <h5 class="font-bold">Indexes:</h5>
                        <ul class="text-sm text-gray-600 mt-2">
                            <li>• <code>idx_email</code> - Index on email column for fast lookups</li>
                            <li>• <code>idx_name</code> - Composite index on first_name, last_name</li>
                        </ul>
                    </div>
                `,
                'devices': `
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
                                <tr><td class="border px-4 py-2 font-mono">last_seen</td><td class="border px-4 py-2">TIMESTAMP</td><td class="border px-4 py-2">DEFAULT CURRENT_TIMESTAMP</td><td class="border px-4 py-2">Last communication</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <h5 class="font-bold">Foreign Keys:</h5>
                        <ul class="text-sm text-gray-600 mt-2">
                            <li>• <code>fk_device_type</code> - devices.type_id → device_types.type_id</li>
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
        
        // Close modals when clicking outside
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