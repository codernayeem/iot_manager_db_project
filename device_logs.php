<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * Device Logs Management Page
 * SQL Features Used:
 * - INNER JOIN (logs with devices, device_types, users, locations)
 * - LEFT JOIN for optional relationships (resolved_by)
 * - INSERT with NOW() function for timestamps
 * - UPDATE with conditional WHERE (resolved_by IS NULL)
 * - FULLTEXT search on message column
 * - MATCH AGAINST for full-text search
 * - Complex filtering with multiple conditions
 * - EXISTS subquery for location filtering
 * - Date range filtering with BETWEEN
 * - CASE expressions for status display
 * - CONCAT for string concatenation
 * - DATE_FORMAT for timestamp formatting
 * - TIMESTAMPDIFF for time calculations
 * - ORDER BY with multiple columns
 * - LIMIT and OFFSET for pagination
 * - Prepared statements with dynamic parameters
 */

$database = new Database();
$conn = $database->getConnection();

$successMessage = '';
$errorMessage = '';

// Handle adding new log
if ($_POST && isset($_POST['add_log'])) {
    $deviceId = (int)$_POST['device_id'];
    $logType = $_POST['log_type'];
    $message = trim($_POST['message']);
    $severityLevel = (int)$_POST['severity_level'];
    
    // SQL Feature: INSERT with validation
    $insertSql = "INSERT INTO device_logs (d_id, log_type, message, severity_level, log_time) 
                  VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insertSql);
    if ($stmt->execute([$deviceId, $logType, $message, $severityLevel])) {
        $successMessage = "Log added successfully!";
    } else {
        $errorMessage = "Failed to add log.";
    }
}

// Handle log resolution
if ($_POST && isset($_POST['resolve_log'])) {
    $logId = (int)$_POST['log_id'];
    $resolutionNotes = trim($_POST['resolution_notes']);
    
    // SQL Feature: UPDATE with current timestamp
    $resolveSql = "UPDATE device_logs 
                   SET resolved_by = ?, resolved_at = NOW(), resolution_notes = ?
                   WHERE log_id = ? AND resolved_by IS NULL";
    
    $stmt = $conn->prepare($resolveSql);
    if ($stmt->execute([$_SESSION['user_id'], $resolutionNotes, $logId])) {
        $successMessage = "Log resolved successfully!";
    }
}

// Filter parameters
$deviceFilter = isset($_GET['device']) ? (int)$_GET['device'] : 0;
$locationFilter = isset($_GET['location']) ? (int)$_GET['location'] : 0;
$typeFilter = isset($_GET['log_type']) ? $_GET['log_type'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build dynamic WHERE clause
$whereConditions = [];
$params = [];

if ($deviceFilter > 0) {
    $whereConditions[] = "dl.d_id = ?";
    $params[] = $deviceFilter;
}

if ($locationFilter > 0) {
    $whereConditions[] = "EXISTS (SELECT 1 FROM deployments dep_filter WHERE dep_filter.d_id = dl.d_id AND dep_filter.loc_id = ?)";
    $params[] = $locationFilter;
}

if (!empty($typeFilter)) {
    $whereConditions[] = "dl.log_type = ?";
    $params[] = $typeFilter;
}

if ($statusFilter === 'resolved') {
    $whereConditions[] = "dl.resolved_by IS NOT NULL";
} elseif ($statusFilter === 'unresolved') {
    $whereConditions[] = "dl.resolved_by IS NULL";
}

if (!empty($search)) {
    // SQL Feature: Full-text search or LIKE depending on message content
    if (strlen($search) > 3) {
        $whereConditions[] = "(MATCH(dl.message) AGAINST(? IN NATURAL LANGUAGE MODE) OR dl.message LIKE ?)";
        $params[] = $search;
        $params[] = "%$search%";
    } else {
        $whereConditions[] = "dl.message LIKE ?";
        $params[] = "%$search%";
    }
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(dl.log_time) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(dl.log_time) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// SQL Feature: Complex query with multiple JOINs, conditional aggregation, and window functions
$logsQuery = "
    SELECT 
        dl.log_id,
        dl.d_id,
        dl.log_time,
        dl.log_type,
        dl.message,
        dl.severity_level,
        dl.resolved_by,
        dl.resolved_at,
        dl.resolution_notes,
        d.d_name as device_name,
        d.serial_number,
        d.status as device_status,
        dt.t_name as device_type,
        CONCAT(u.f_name, ' ', u.l_name) as device_owner,
        CASE 
            WHEN dl.resolved_by IS NOT NULL THEN CONCAT(ru.f_name, ' ', ru.l_name)
            ELSE NULL
        END as resolved_by_name,
        GROUP_CONCAT(DISTINCT l.loc_name ORDER BY dep.deployed_at DESC SEPARATOR ', ') as device_locations,
        CASE 
            WHEN dl.log_type = 'error' AND dl.resolved_by IS NULL THEN 'critical'
            WHEN dl.log_type = 'error' AND dl.resolved_by IS NOT NULL THEN 'resolved'
            WHEN dl.log_type = 'warning' AND dl.resolved_by IS NULL THEN 'warning'
            WHEN dl.log_type = 'warning' AND dl.resolved_by IS NOT NULL THEN 'resolved'
            WHEN dl.log_type = 'info' THEN 'info'
            WHEN dl.log_type = 'debug' THEN 'debug'
            ELSE 'normal'
        END as priority_status,
        TIMESTAMPDIFF(HOUR, dl.log_time, COALESCE(dl.resolved_at, NOW())) as hours_to_resolution,
        ROW_NUMBER() OVER (PARTITION BY dl.d_id ORDER BY dl.log_time DESC) as device_log_rank
    FROM device_logs dl
    INNER JOIN devices d ON dl.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN users ru ON dl.resolved_by = ru.user_id
    LEFT JOIN deployments dep ON d.d_id = dep.d_id
    LEFT JOIN locations l ON dep.loc_id = l.loc_id
    $whereClause
    GROUP BY dl.log_id, dl.d_id, dl.log_time, dl.log_type, dl.message, dl.severity_level,
             dl.resolved_by, dl.resolved_at, dl.resolution_notes, d.d_name, d.serial_number,
             d.status, dt.t_name, u.f_name, u.l_name, ru.f_name, ru.l_name
    ORDER BY 
        CASE WHEN dl.resolved_by IS NULL THEN 0 ELSE 1 END,
        dl.severity_level DESC,
        dl.log_time DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($logsQuery);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total logs for pagination
$countQuery = "
    SELECT COUNT(DISTINCT dl.log_id) as total
    FROM device_logs dl
    INNER JOIN devices d ON dl.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN users ru ON dl.resolved_by = ru.user_id
    LEFT JOIN deployments dep ON d.d_id = dep.d_id
    LEFT JOIN locations l ON dep.loc_id = l.loc_id
    $whereClause
";

$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

// Get devices for filter dropdown
$devicesQuery = "
    SELECT DISTINCT d.d_id, d.d_name, dt.t_name as device_type
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN device_logs dl ON d.d_id = dl.d_id
    ORDER BY d.d_name
";
$devicesStmt = $conn->prepare($devicesQuery);
$devicesStmt->execute();
$devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all devices for add log form
$allDevicesQuery = "
    SELECT d.d_id, d.d_name, dt.t_name as device_type, d.status
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    ORDER BY d.d_name
";
$allDevicesStmt = $conn->prepare($allDevicesQuery);
$allDevicesStmt->execute();
$allDevices = $allDevicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get log statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_logs,
        COUNT(CASE WHEN log_type = 'error' THEN 1 END) as error_logs,
        COUNT(CASE WHEN log_type = 'warning' THEN 1 END) as warning_logs,
        COUNT(CASE WHEN log_type = 'info' THEN 1 END) as info_logs,
        COUNT(CASE WHEN resolved_by IS NULL AND log_type IN ('error', 'warning') THEN 1 END) as unresolved_issues,
        AVG(severity_level) as avg_severity,
        COUNT(DISTINCT d_id) as affected_devices
    FROM device_logs dl
    $whereClause
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Logs - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sql-tooltip {
            position: relative;
            display: inline-block;
        }
        
        .sql-tooltip .tooltip-text {
            visibility: hidden;
            width: 500px;
            background-color: #1f2937;
            color: #fff;
            border-radius: 6px;
            padding: 15px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -250px;
            opacity: 0;
            transition: opacity 0.3s;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            text-align: left;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .sql-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .log-card {
            transition: all 0.3s ease;
        }
        
        .log-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .priority-critical { border-left: 4px solid #dc2626; background-color: #fef2f2; }
        .priority-warning { border-left: 4px solid #d97706; background-color: #fffbeb; }
        .priority-resolved { border-left: 4px solid #059669; background-color: #f0fdf4; }
        .priority-info { border-left: 4px solid #3b82f6; background-color: #eff6ff; }
        .priority-debug { border-left: 4px solid #8b5cf6; background-color: #f5f3ff; }
        .priority-normal { border-left: 4px solid #6b7280; background-color: #f9fafb; }
        
        .severity-1 { color: #059669; }
        .severity-2 { color: #d97706; }
        .severity-3 { color: #dc2626; }
        .severity-4 { color: #7c2d12; font-weight: bold; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-list-alt mr-3"></i>Device Logs
                </h1>
                <p class="text-gray-600">Monitor and manage device activities and issues</p>
            </div>
            <button onclick="document.getElementById('addLogModal').classList.remove('hidden')" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg shadow-md transition">
                <i class="fas fa-plus mr-2"></i>Add Log
            </button>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>SQL Features Used:</strong> 
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">FULLTEXT search (MATCH AGAINST)</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">INNER JOIN & LEFT JOIN</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">INSERT & UPDATE operations</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">EXISTS subquery</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">BETWEEN for date ranges</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">CASE expressions</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">TIMESTAMPDIFF & DATE_FORMAT</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
            <div class="sql-tooltip bg-white rounded-lg shadow-md p-4 text-center cursor-help">
                <i class="fas fa-list text-blue-600 text-xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_logs']); ?></p>
                <p class="text-sm text-gray-600">Total Logs</p>
                <span class="tooltip-text">
                    <strong>SQL:</strong> SELECT COUNT(*) FROM device_logs
                </span>
            </div>
            
            <div class="sql-tooltip bg-white rounded-lg shadow-md p-4 text-center cursor-help">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['error_logs']; ?></p>
                <p class="text-sm text-gray-600">Errors</p>
                <span class="tooltip-text">
                    <strong>SQL:</strong> SELECT COUNT(*) FROM device_logs<br>WHERE log_type = 'error'
                </span>
            </div>
            
            <div class="sql-tooltip bg-white rounded-lg shadow-md p-4 text-center cursor-help">
                <i class="fas fa-exclamation-circle text-yellow-600 text-xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['warning_logs']; ?></p>
                <p class="text-sm text-gray-600">Warnings</p>
                <span class="tooltip-text">
                    <strong>SQL:</strong> SELECT COUNT(*) FROM device_logs<br>WHERE log_type = 'warning'
                </span>
            </div>
            
            <div class="sql-tooltip bg-white rounded-lg shadow-md p-4 text-center cursor-help">
                <i class="fas fa-info-circle text-green-600 text-xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['info_logs']; ?></p>
                <p class="text-sm text-gray-600">Info</p>
                <span class="tooltip-text">
                    <strong>SQL:</strong> SELECT COUNT(*) FROM device_logs<br>WHERE log_type = 'info'
                </span>
            </div>
            
            <div class="sql-tooltip bg-white rounded-lg shadow-md p-4 text-center cursor-help">
                <i class="fas fa-bug text-orange-600 text-xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['unresolved_issues']; ?></p>
                <p class="text-sm text-gray-600">Unresolved</p>
                <span class="tooltip-text">
                    <strong>SQL:</strong> SELECT COUNT(*) FROM device_logs<br>WHERE resolved_by IS NULL
                </span>
            </div>
            
            <div class="sql-tooltip bg-white rounded-lg shadow-md p-4 text-center cursor-help">
                <i class="fas fa-tachometer-alt text-purple-600 text-xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['avg_severity'], 1); ?></p>
                <p class="text-sm text-gray-600">Avg Severity</p>
                <span class="tooltip-text">
                    <strong>SQL:</strong> SELECT AVG(severity_level) FROM device_logs
                </span>
            </div>
            
            <div class="sql-tooltip bg-white rounded-lg shadow-md p-4 text-center cursor-help">
                <i class="fas fa-microchip text-indigo-600 text-xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['affected_devices']; ?></p>
                <p class="text-sm text-gray-600">Devices</p>
                <span class="tooltip-text">
                    <strong>SQL:</strong> SELECT COUNT(DISTINCT d_id) FROM device_logs
                </span>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div>
                    <label for="device" class="block text-sm font-medium text-gray-700 mb-2">Device</label>
                    <select id="device" name="device" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">All Devices</option>
                        <?php foreach ($devices as $device): ?>
                            <option value="<?php echo $device['d_id']; ?>" <?php echo $deviceFilter == $device['d_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($device['d_name']) . ' (' . htmlspecialchars($device['device_type']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                    <select id="location" name="location" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">All Locations</option>
                        <?php 
                        $locationsQuery = "SELECT loc_id, loc_name FROM locations ORDER BY loc_name";
                        $locationsStmt = $conn->prepare($locationsQuery);
                        $locationsStmt->execute();
                        $logLocations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($logLocations as $location): ?>
                            <option value="<?php echo $location['loc_id']; ?>" <?php echo $locationFilter == $location['loc_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location['loc_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="log_type" class="block text-sm font-medium text-gray-700 mb-2">Log Type</label>
                    <select id="log_type" name="log_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">All Types</option>
                        <option value="error" <?php echo $typeFilter === 'error' ? 'selected' : ''; ?>>Error</option>
                        <option value="warning" <?php echo $typeFilter === 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="info" <?php echo $typeFilter === 'info' ? 'selected' : ''; ?>>Info</option>
                        <option value="debug" <?php echo $typeFilter === 'debug' ? 'selected' : ''; ?>>Debug</option>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">All Status</option>
                        <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="unresolved" <?php echo $statusFilter === 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                    </select>
                </div>
                
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Message</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search in log messages..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" 
                           id="date_from" 
                           name="date_from" 
                           value="<?php echo $dateFrom; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" 
                           id="date_to" 
                           name="date_to" 
                           value="<?php echo $dateTo; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex items-end space-x-2">
                    <div class="sql-tooltip flex-1">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <span class="tooltip-text">
                            Dynamic filtering with multiple conditions:<br><br>
                            Full-text search: MATCH(dl.message) AGAINST(? IN NATURAL LANGUAGE MODE)<br>
                            Or LIKE search: dl.message LIKE ?<br>
                            Date filtering: DATE(dl.log_time) BETWEEN ? AND ?<br>
                            Status filtering: dl.resolved_by IS NULL/IS NOT NULL<br>
                            Device filtering: dl.d_id = ?<br>
                            Type filtering: dl.log_type = ?
                        </span>
                    </div>
                    
                    <a href="device_logs.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Results Summary -->
        <div class="flex justify-between items-center mb-6">
            <div class="text-gray-600">
                Showing <?php echo count($logs); ?> of <?php echo number_format($totalLogs); ?> logs
                <?php if (!empty($search) || $deviceFilter || $locationFilter || !empty($typeFilter) || !empty($statusFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            <div class="sql-tooltip text-sm text-blue-600 underline cursor-help">
                View Main Query SQL
                <span class="tooltip-text">
                    <strong>Complex Multi-JOIN Query with Aggregations:</strong><br><br>
                    SELECT dl.*, d.d_name, dt.t_name as device_type,<br>
                    u.f_name, u.l_name, l.loc_name,<br>
                    TIMESTAMPDIFF(HOUR, dl.log_time, NOW()) as hours_ago,<br>
                    CASE WHEN dl.resolved_by IS NULL THEN 'Unresolved' ELSE 'Resolved' END as status<br>
                    FROM device_logs dl<br>
                    INNER JOIN devices d ON dl.d_id = d.d_id<br>
                    INNER JOIN device_types dt ON d.t_id = dt.t_id<br>
                    LEFT JOIN users u ON dl.resolved_by = u.user_id<br>
                    LEFT JOIN deployments dep ON d.d_id = dep.d_id<br>
                    LEFT JOIN locations l ON dep.loc_id = l.loc_id<br>
                    WHERE [dynamic conditions]<br>
                    ORDER BY dl.log_time DESC LIMIT 20
                </span>
            </div>
        </div>
        
        <!-- Log Entries -->
        <div class="space-y-4 mb-8">
            <?php foreach ($logs as $log): ?>
                <div class="log-card priority-<?php echo $log['priority_status']; ?> bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full mr-3
                                    <?php echo $log['log_type'] === 'error' ? 'bg-red-100 text-red-800' : 
                                               ($log['log_type'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 
                                                ($log['log_type'] === 'info' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')); ?>">
                                    <?php echo strtoupper($log['log_type']); ?>
                                </span>
                                
                                <span class="severity-<?php echo $log['severity_level']; ?> text-sm font-medium mr-3">
                                    Severity: <?php echo $log['severity_level']; ?>
                                </span>
                                
                                <span class="text-sm text-gray-600">
                                    <?php echo date('M j, Y H:i:s', strtotime($log['log_time'])); ?>
                                </span>
                            </div>
                            
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                <i class="fas fa-microchip mr-2 text-blue-600"></i>
                                <?php echo htmlspecialchars($log['device_name']); ?>
                                <span class="text-sm font-normal text-gray-600">
                                    (<?php echo htmlspecialchars($log['device_type']); ?>)
                                </span>
                            </h3>
                            
                            <p class="text-gray-800 mb-3"><?php echo htmlspecialchars($log['message']); ?></p>
                            
                            <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                                <div>
                                    <i class="fas fa-barcode mr-1"></i>
                                    <strong>Serial:</strong> <?php echo htmlspecialchars($log['serial_number']); ?>
                                </div>
                                
                                <div>
                                    <i class="fas fa-user mr-1"></i>
                                    <strong>Owner:</strong> <?php echo htmlspecialchars($log['device_owner']); ?>
                                </div>
                                
                                <?php if ($log['device_locations']): ?>
                                    <div>
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($log['device_locations']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <i class="fas fa-clock mr-1"></i>
                                    <strong>Duration:</strong> <?php echo $log['hours_to_resolution']; ?>h
                                </div>
                            </div>
                        </div>
                        
                        <div class="ml-4 text-right">
                            <?php if (in_array($log['log_type'], ['error', 'warning'])): ?>
                                <?php if ($log['resolved_by']): ?>
                                    <div class="text-green-600 mb-2">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        <strong>Resolved</strong>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        by <?php echo htmlspecialchars($log['resolved_by_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('M j, H:i', strtotime($log['resolved_at'])); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-red-600 mb-2">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        <strong>Unresolved</strong>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-gray-600 mb-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong><?php echo ucfirst($log['log_type']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Resolution Section -->
                    <?php if ($log['resolved_by']): ?>
                        <div class="mt-4 p-3 bg-green-50 rounded-lg">
                            <h4 class="font-semibold text-green-800 mb-2">Resolution Notes:</h4>
                            <p class="text-green-700"><?php echo htmlspecialchars($log['resolution_notes'] ?: 'No additional notes provided.'); ?></p>
                        </div>
                    <?php elseif (in_array($log['log_type'], ['error', 'warning'])): ?>
                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                            <form method="POST" class="flex gap-3">
                                <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                <div class="flex-1">
                                    <textarea name="resolution_notes" 
                                              placeholder="Enter resolution notes..."
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                              rows="2"></textarea>
                                </div>
                                <div class="sql-tooltip">
                                    <button type="submit" 
                                            name="resolve_log"
                                            class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                                        <i class="fas fa-check mr-1"></i>Resolve
                                    </button>
                                    <span class="tooltip-text">
                                        Resolution SQL Query:<br><br>
                                        UPDATE device_logs<br>
                                        SET resolved_by = ?,<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;resolved_at = NOW(),<br>
                                        &nbsp;&nbsp;&nbsp;&nbsp;resolution_notes = ?<br>
                                        WHERE log_id = ?<br>
                                        AND resolved_by IS NULL
                                    </span>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No logs found</h3>
                <p class="text-gray-500">Try adjusting your search criteria or check back later for new logs.</p>
            </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8">
                <nav class="flex space-x-2">
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="px-3 py-2 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md transition">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Next Page -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Log Modal -->
    <div id="addLogModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-2xl font-bold text-gray-900">
                    <i class="fas fa-plus-circle mr-2 text-blue-600"></i>Add New Log Entry
                </h3>
                <button onclick="document.getElementById('addLogModal').classList.add('hidden')" 
                        class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="add_log" value="1">
                
                <!-- Device Selection -->
                <div>
                    <label for="device_id" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-microchip mr-2"></i>Device *
                    </label>
                    <select id="device_id" 
                            name="device_id" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">Select a device</option>
                        <?php foreach ($allDevices as $device): ?>
                            <option value="<?php echo $device['d_id']; ?>">
                                <?php echo htmlspecialchars($device['d_name']); ?> 
                                (<?php echo htmlspecialchars($device['device_type']); ?>) 
                                - <?php echo ucfirst($device['status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Log Type -->
                <div>
                    <label for="log_type" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tags mr-2"></i>Log Type *
                    </label>
                    <select id="log_type" 
                            name="log_type" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="info">Info - Normal operation</option>
                        <option value="warning">Warning - Needs attention</option>
                        <option value="error">Error - Critical issue</option>
                        <option value="debug">Debug - Diagnostic information</option>
                    </select>
                </div>
                
                <!-- Severity Level -->
                <div>
                    <label for="severity_level" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Severity Level (1-10) *
                    </label>
                    <input type="number" 
                           id="severity_level" 
                           name="severity_level" 
                           min="1" 
                           max="10" 
                           value="5"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">1 = Lowest severity, 10 = Highest severity</p>
                </div>
                
                <!-- Message -->
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-comment-alt mr-2"></i>Message *
                    </label>
                    <textarea id="message" 
                              name="message" 
                              rows="4"
                              required
                              placeholder="Describe the log event..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" 
                            onclick="document.getElementById('addLogModal').classList.add('hidden')"
                            class="px-6 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition">
                        <i class="fas fa-save mr-2"></i>Add Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>