<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: Multiple JOINs, COUNT, GROUP BY, Subqueries, CASE statements
 * Dashboard with comprehensive statistics and data visualization
 */

$database = new Database();
$conn = $database->getConnection();

// Initialize default values
$stats = [
    'total_devices' => 0,
    'active_devices' => 0,
    'error_devices' => 0,
    'unresolved_errors' => 0,
    'total_locations' => 0,
    'active_users' => 0
];
$deviceStatusCounts = [];
$activeDevicesList = [];
$deviceTypes = [];
$recentLogs = [];
$locations = [];

// Check if tables exist before querying
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'devices'");
    if ($checkTable->rowCount() > 0) {
        // SQL Feature: Multiple SELECT subqueries with COUNT and WHERE
        // Simple SQL: SELECT COUNT(*) with multiple subqueries
        $dashboardStats = "
            SELECT 
                (SELECT COUNT(*) FROM devices) as total_devices,
                (SELECT COUNT(*) FROM devices WHERE status = 'active') as active_devices,
                (SELECT COUNT(*) FROM devices WHERE status = 'error') as error_devices,
                (SELECT COUNT(*) FROM device_logs WHERE log_type = 'error' AND resolved_by IS NULL) as unresolved_errors,
                (SELECT COUNT(*) FROM locations) as total_locations,
                (SELECT COUNT(DISTINCT user_id) FROM devices) as active_users
        ";

        $stmt = $conn->prepare($dashboardStats);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // SQL Feature: Using stored procedure to count devices by status
        // CALL sp_count_devices_by_status()
        try {
            $statusStmt = $conn->query("CALL sp_count_devices_by_status()");
            $deviceStatusCounts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
            $statusStmt->closeCursor();
        } catch (Exception $e) {
            $deviceStatusCounts = [];
        }
    }
} catch (PDOException $e) {
    // Database not ready yet, use default values
}

// Only query if database is ready
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'devices'");
    if ($checkTable->rowCount() > 0) {
        // SQL Feature: LEFT JOIN with GROUP BY and aggregation functions (COUNT, SUM, CASE)
        // JOIN: device_types LEFT JOIN devices, GROUP BY with aggregate functions
        $devicesByType = "
            SELECT 
                dt.t_name,
                COUNT(d.d_id) as device_count,
                SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN d.status = 'error' THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN d.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count
            FROM device_types dt
            LEFT JOIN devices d ON dt.t_id = d.t_id
            GROUP BY dt.t_id, dt.t_name
            ORDER BY device_count DESC
        ";

        $stmt = $conn->prepare($devicesByType);
        $stmt->execute();
        $deviceTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // SQL Feature: Using VIEW v_active_devices to get active devices
        // VIEW usage: SELECT from view instead of complex JOIN
        try {
            $activeDevicesQuery = "SELECT * FROM v_active_devices LIMIT 5";
            $activeDevicesStmt = $conn->query($activeDevicesQuery);
            $activeDevicesList = $activeDevicesStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $activeDevicesList = [];
        }

        // SQL Feature: Complex JOIN with date functions and conditional aggregation
        $recentActivity = "
            SELECT 
                dl.log_id,
                d.d_name,
                dt.t_name as device_type,
                dl.log_type,
                dl.message,
                dl.log_time,
                dl.severity_level,
                CASE 
                    WHEN dl.resolved_by IS NOT NULL THEN CONCAT(u.f_name, ' ', u.l_name)
                    ELSE 'Unresolved'
                END as resolver,
                CASE 
                    WHEN dl.log_type = 'error' AND dl.resolved_by IS NULL THEN 'pending'
                    WHEN dl.log_type = 'error' AND dl.resolved_by IS NOT NULL THEN 'resolved'
                    ELSE 'normal'
                END as status
            FROM device_logs dl
            INNER JOIN devices d ON dl.d_id = d.d_id
            INNER JOIN device_types dt ON d.t_id = dt.t_id
            LEFT JOIN users u ON dl.resolved_by = u.user_id
            WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY dl.log_time DESC, dl.severity_level DESC
            LIMIT 10
        ";

        $stmt = $conn->prepare($recentActivity);
        $stmt->execute();
        $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // SQL Feature: Using VIEW v_device_locations to get device locations
        // VIEW usage: SELECT from view for simplified location query
        try {
            $topLocations = "
                SELECT 
                    loc_name,
                    address,
                    COUNT(*) as device_count
                FROM v_device_locations
                WHERE loc_name IS NOT NULL
                GROUP BY loc_name, address
                ORDER BY device_count DESC
                LIMIT 5
            ";
            
            $stmt = $conn->prepare($topLocations);
            $stmt->execute();
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $locations = [];
        }
    }
} catch (PDOException $e) {
    // Database not ready yet, use default values
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sql-tooltip {
            position: relative;
            display: inline-block;
        }
        
        .sql-tooltip .tooltip-text {
            visibility: hidden;
            width: 400px;
            background-color: #1f2937;
            color: #fff;
            border-radius: 6px;
            padding: 15px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -200px;
            opacity: 0;
            transition: opacity 0.3s;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            text-align: left;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .sql-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-error { background-color: #fee2e2; color: #dc2626; }
        .status-maintenance { background-color: #fef3c7; color: #d97706; }
        .status-inactive { background-color: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
            </h1>
            <p class="text-gray-600">Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
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
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">2 Views</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">1 Stored Procedure (CURSOR, LOOP)</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">2 Functions (IF/ELSE)</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">JOINs, Subqueries, GROUP BY, CASE</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-microchip text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Devices</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_devices']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(*) FROM devices
                </span>
            </div>
            
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Devices</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['active_devices']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(*) FROM devices<br>
                    WHERE status = 'active'
                </span>
            </div>
            
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Error Devices</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['error_devices']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(*) FROM devices<br>
                    WHERE status = 'error'
                </span>
            </div>
            
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-bug text-orange-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Unresolved</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['unresolved_errors']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(*) FROM device_logs<br>
                    WHERE log_type = 'error'<br>
                    AND resolved_by IS NULL
                </span>
            </div>
            
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-map-marker-alt text-purple-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Locations</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_locations']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(*) FROM locations
                </span>
            </div>
            
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-indigo-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Users</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['active_users']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(DISTINCT user_id)<br>
                    FROM devices
                </span>
            </div>
        </div>
        
        <!-- Database Features Demo -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Stored Procedure: sp_count_devices_by_status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-cogs mr-2"></i>Device Status Count
                    </h2>
                    <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">STORED PROCEDURE</span>
                </div>
                <div class="bg-gray-50 p-3 rounded mb-4">
                    <code class="text-xs text-gray-700">
                        <strong>CALL</strong> sp_count_devices_by_status()<br>
                        <span class="text-gray-500">Features: CURSOR, LOOP, IF/ELSEIF, Variables</span>
                    </code>
                </div>
                <div class="space-y-2">
                    <?php if (!empty($deviceStatusCounts)): ?>
                        <?php foreach ($deviceStatusCounts as $statusItem): ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                <div>
                                    <span class="font-semibold text-gray-800"><?php echo ucfirst($statusItem['status']); ?></span>
                                    <p class="text-xs text-gray-500"><?php echo $statusItem['status_label']; ?></p>
                                </div>
                                <span class="text-2xl font-bold text-blue-600"><?php echo $statusItem['device_count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- View: v_active_devices -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-eye mr-2"></i>Active Devices
                    </h2>
                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">VIEW</span>
                </div>
                <div class="bg-gray-50 p-3 rounded mb-4">
                    <code class="text-xs text-gray-700">
                        <strong>SELECT * FROM</strong> v_active_devices<br>
                        <span class="text-gray-500">Features: INNER JOIN, WHERE</span>
                    </code>
                </div>
                <div class="space-y-2">
                    <?php if (!empty($activeDevicesList)): ?>
                        <?php foreach ($activeDevicesList as $device): ?>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-microchip text-green-600 mr-2"></i>
                                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($device['d_name']); ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500 ml-6"><?php echo htmlspecialchars($device['device_type']); ?> • <?php echo htmlspecialchars($device['owner_name']); ?></p>
                                </div>
                                <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded"><?php echo $device['status']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No active devices</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Device Types Analysis -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="sql-tooltip inline-block">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-pie mr-2"></i>Devices by Type
                    </h2>
                    <span class="tooltip-text">
                        SQL Query with LEFT JOIN and GROUP BY:<br><br>
                        SELECT dt.t_name, COUNT(d.d_id) as device_count,<br>
                        SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) as active_count,<br>
                        SUM(CASE WHEN d.status = 'error' THEN 1 ELSE 0 END) as error_count,<br>
                        SUM(CASE WHEN d.status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count<br>
                        FROM device_types dt<br>
                        LEFT JOIN devices d ON dt.t_id = d.t_id<br>
                        GROUP BY dt.t_id, dt.t_name<br>
                        ORDER BY device_count DESC
                    </span>
                </div>
                
                <div class="space-y-4">
                    <?php foreach ($deviceTypes as $type): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-microchip text-blue-600 text-xl mr-3"></i>
                                <div>
                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($type['t_name']); ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo $type['device_count']; ?> devices</p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <span class="status-badge status-active"><?php echo $type['active_count']; ?> Active</span>
                                <?php if ($type['error_count'] > 0): ?>
                                    <span class="status-badge status-error"><?php echo $type['error_count']; ?> Error</span>
                                <?php endif; ?>
                                <?php if ($type['maintenance_count'] > 0): ?>
                                    <span class="status-badge status-maintenance"><?php echo $type['maintenance_count']; ?> Maintenance</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Top Locations using VIEW -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-map-marker-alt mr-2"></i>Top Locations
                    </h2>
                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">VIEW</span>
                </div>
                <div class="bg-gray-50 p-3 rounded mb-4">
                    <code class="text-xs text-gray-700">
                        <strong>SELECT</strong> loc_name, address, COUNT(*)<br>
                        <strong>FROM</strong> v_device_locations<br>
                        <strong>GROUP BY</strong> loc_name<br>
                        <span class="text-gray-500">Features: VIEW with LEFT JOIN</span>
                    </code>
                </div>
                
                <div class="space-y-4">
                    <?php if (!empty($locations)): ?>
                        <?php foreach ($locations as $location): ?>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($location['loc_name']); ?></h3>
                                    <div class="text-right">
                                        <span class="text-lg font-bold text-blue-600"><?php echo $location['device_count']; ?></span>
                                        <p class="text-xs text-gray-500">devices</p>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($location['address']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No location data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <div class="sql-tooltip inline-block">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-clock mr-2"></i>Recent Activity
                </h2>
                <span class="tooltip-text">
                    Complex Query with Multiple JOINs and CASE statements:<br><br>
                    SELECT dl.log_id, d.d_name, dt.t_name as device_type,<br>
                    dl.log_type, dl.message, dl.log_time, dl.severity_level,<br>
                    CASE WHEN dl.resolved_by IS NOT NULL<br>
                    THEN CONCAT(u.f_name, ' ', u.l_name)<br>
                    ELSE 'Unresolved' END as resolver,<br>
                    CASE WHEN dl.log_type = 'error' AND dl.resolved_by IS NULL<br>
                    THEN 'pending'<br>
                    WHEN dl.log_type = 'error' AND dl.resolved_by IS NOT NULL<br>
                    THEN 'resolved' ELSE 'normal' END as status<br>
                    FROM device_logs dl<br>
                    INNER JOIN devices d ON dl.d_id = d.d_id<br>
                    INNER JOIN device_types dt ON d.t_id = dt.t_id<br>
                    LEFT JOIN users u ON dl.resolved_by = u.user_id<br>
                    WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)<br>
                    ORDER BY dl.log_time DESC, dl.severity_level DESC
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Log Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($log['d_name']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['device_type']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $log['log_type'] == 'error' ? 'bg-red-100 text-red-800' : 
                                                   ($log['log_type'] == 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                        <?php echo ucfirst($log['log_type']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900 max-w-xs truncate">
                                    <?php echo htmlspecialchars($log['message']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, H:i', strtotime($log['log_time'])); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo $log['status'] == 'resolved' ? 'bg-green-100 text-green-800' : 
                                                   ($log['status'] == 'pending' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="add_device.php" class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg shadow-md transition">
                <i class="fas fa-plus-circle text-2xl mb-2"></i>
                <h3 class="text-lg font-semibold">Add New Device</h3>
                <p class="text-sm opacity-90">Register a new IoT device</p>
            </a>
            
            <a href="device_logs.php" class="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow-md transition">
                <i class="fas fa-list-alt text-2xl mb-2"></i>
                <h3 class="text-lg font-semibold">View All Logs</h3>
                <p class="text-sm opacity-90">Monitor device activities</p>
            </a>
            
            <a href="analytics.php" class="bg-purple-600 hover:bg-purple-700 text-white p-6 rounded-lg shadow-md transition">
                <i class="fas fa-chart-bar text-2xl mb-2"></i>
                <h3 class="text-lg font-semibold">Analytics</h3>
                <p class="text-sm opacity-90">View detailed reports</p>
            </a>
        </div>
    </div>
</body>
</html>