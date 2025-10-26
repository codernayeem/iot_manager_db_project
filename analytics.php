<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * Enhanced Analytics with Advanced Filtering
 * SQL Features: Window Functions, CTEs, Complex Filtering, Subqueries, Aggregations
 */

$database = new Database();
$conn = $database->getConnection();

// Get filter parameters
$selectedDevice = isset($_GET['device']) ? (int)$_GET['device'] : null;
$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : null;
$selectedLocation = isset($_GET['location']) ? (int)$_GET['location'] : null;
$selectedUser = isset($_GET['user']) ? (int)$_GET['user'] : null;
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '30';
$logType = isset($_GET['log_type']) ? $_GET['log_type'] : '';

// Build dynamic WHERE clause
$whereConditions = [];
$params = [];

if ($selectedDevice) {
    $whereConditions[] = "dl.d_id = ?";
    $params[] = $selectedDevice;
}

if ($selectedCategory) {
    $whereConditions[] = "dt.t_id = ?";
    $params[] = $selectedCategory;
}

if ($selectedLocation) {
    $whereConditions[] = "dep.loc_id = ?";
    $params[] = $selectedLocation;
}

if ($selectedUser) {
    $whereConditions[] = "dl.resolved_by = ?";
    $params[] = $selectedUser;
}

if ($logType) {
    $whereConditions[] = "dl.log_type = ?";
    $params[] = $logType;
}

$whereClause = !empty($whereConditions) ? 'AND ' . implode(' AND ', $whereConditions) : '';

// SQL Feature: Complex CTE with window functions and dynamic filtering
$trendsQuery = "
    WITH daily_stats AS (
        SELECT 
            DATE(dl.log_time) as log_date,
            dl.log_type,
            d.d_name,
            dt.t_name as device_category,
            l.loc_name,
            COUNT(*) as log_count,
            AVG(dl.severity_level) as avg_severity,
            MAX(dl.severity_level) as max_severity,
            COUNT(CASE WHEN dl.resolved_by IS NULL THEN 1 END) as unresolved_count
        FROM device_logs dl
        INNER JOIN devices d ON dl.d_id = d.d_id
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        INNER JOIN deployments dep ON d.d_id = dep.d_id
        INNER JOIN locations l ON dep.loc_id = l.loc_id
        WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
        $whereClause
        GROUP BY DATE(dl.log_time), dl.log_type, d.d_name, dt.t_name, l.loc_name
    ),
    ranked_daily AS (
        SELECT 
            *,
            ROW_NUMBER() OVER (PARTITION BY log_date ORDER BY log_count DESC) as daily_rank,
            LAG(log_count, 1) OVER (PARTITION BY d_name, log_type ORDER BY log_date) as prev_day_count,
            AVG(log_count) OVER (PARTITION BY d_name, log_type ORDER BY log_date ROWS BETWEEN 6 PRECEDING AND CURRENT ROW) as rolling_avg
        FROM daily_stats
    )
    SELECT 
        *,
        CASE 
            WHEN prev_day_count IS NULL THEN 0
            WHEN prev_day_count = 0 THEN 100
            ELSE ROUND(((log_count - prev_day_count) * 100.0 / prev_day_count), 2)
        END as growth_rate
    FROM ranked_daily
    ORDER BY log_date DESC, daily_rank ASC
    LIMIT 100
";

array_unshift($params, $dateRange);
$stmt = $conn->prepare($trendsQuery);
$stmt->execute($params);
$trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reset params for next query
$params = array_slice($params, 1);

// SQL Feature: Top resolvers analysis with advanced metrics
$resolversQuery = "
    SELECT 
        u.user_id,
        CONCAT(u.f_name, ' ', u.l_name) as resolver_name,
        u.email,
        COUNT(DISTINCT dl.log_id) as total_resolved,
        COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) as errors_resolved,
        COUNT(DISTINCT CASE WHEN dl.log_type = 'warning' THEN dl.log_id END) as warnings_resolved,
        AVG(dl.severity_level) as avg_severity_handled,
        AVG(TIMESTAMPDIFF(HOUR, dl.log_time, dl.resolved_at)) as avg_resolution_time_hours,
        MIN(dl.resolved_at) as first_resolution,
        MAX(dl.resolved_at) as latest_resolution,
        COUNT(DISTINCT dl.d_id) as devices_worked_on,
        COUNT(DISTINCT dep.loc_id) as locations_covered,
        RANK() OVER (ORDER BY COUNT(DISTINCT dl.log_id) DESC) as resolver_rank,
        ROUND(
            COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) * 100.0 / 
            NULLIF(COUNT(DISTINCT dl.log_id), 0), 2
        ) as error_resolution_rate
    FROM users u
    INNER JOIN device_logs dl ON u.user_id = dl.resolved_by
    INNER JOIN devices d ON dl.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN deployments dep ON d.d_id = dep.d_id
    WHERE dl.resolved_at IS NOT NULL
    AND dl.log_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
    $whereClause
    GROUP BY u.user_id, u.f_name, u.l_name, u.email
    HAVING total_resolved > 0
    ORDER BY total_resolved DESC, avg_resolution_time_hours ASC
";

array_unshift($params, $dateRange);
$stmt = $conn->prepare($resolversQuery);
$stmt->execute($params);
$resolvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reset params again
$params = array_slice($params, 1);

// SQL Feature: Device performance analysis
$devicePerformanceQuery = "
    SELECT 
        d.d_id,
        d.d_name,
        dt.t_name as device_type,
        l.loc_name,
        d.status,
        COUNT(dl.log_id) as total_logs,
        COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as error_count,
        COUNT(CASE WHEN dl.log_type = 'warning' THEN 1 END) as warning_count,
        COUNT(CASE WHEN dl.log_type = 'info' THEN 1 END) as info_count,
        AVG(dl.severity_level) as avg_severity,
        MAX(dl.log_time) as last_log_time,
        COUNT(CASE WHEN dl.resolved_by IS NULL AND dl.log_type IN ('error', 'warning') THEN 1 END) as unresolved_issues,
        ROUND(
            COUNT(CASE WHEN dl.log_type = 'info' THEN 1 END) * 100.0 / 
            NULLIF(COUNT(dl.log_id), 0), 2
        ) as health_score,
        RANK() OVER (ORDER BY COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) DESC) as error_rank
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN deployments dep ON d.d_id = dep.d_id
    INNER JOIN locations l ON dep.loc_id = l.loc_id
    LEFT JOIN device_logs dl ON d.d_id = dl.d_id 
        AND dl.log_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
    WHERE 1=1 $whereClause
    GROUP BY d.d_id, d.d_name, dt.t_name, l.loc_name, d.status
    ORDER BY total_logs DESC, error_count DESC
";

array_unshift($params, $dateRange);
$stmt = $conn->prepare($devicePerformanceQuery);
$stmt->execute($params);
$devicePerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$devicesQuery = "SELECT d_id, d_name FROM devices ORDER BY d_name";
$deviceOptions = $conn->query($devicesQuery)->fetchAll(PDO::FETCH_ASSOC);

$categoriesQuery = "SELECT t_id, t_name FROM device_types ORDER BY t_name";
$categoryOptions = $conn->query($categoriesQuery)->fetchAll(PDO::FETCH_ASSOC);

$locationsQuery = "SELECT loc_id, loc_name FROM locations ORDER BY loc_name";
$locationOptions = $conn->query($locationsQuery)->fetchAll(PDO::FETCH_ASSOC);

$usersQuery = "SELECT user_id, CONCAT(f_name, ' ', l_name) as name FROM users ORDER BY f_name";
$userOptions = $conn->query($usersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data
$chartData = [
    'dates' => [],
    'errors' => [],
    'warnings' => [],
    'info' => [],
    'debug' => []
];

$dailyData = [];
foreach ($trends as $trend) {
    $date = $trend['log_date'];
    $type = $trend['log_type'];
    $count = $trend['log_count'];
    
    if (!isset($dailyData[$date])) {
        $dailyData[$date] = ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];
    }
    $dailyData[$date][$type] += $count;
}

ksort($dailyData);
foreach ($dailyData as $date => $data) {
    $chartData['dates'][] = $date;
    $chartData['errors'][] = $data['error'];
    $chartData['warnings'][] = $data['warning'];
    $chartData['info'][] = $data['info'];
    $chartData['debug'][] = $data['debug'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Analytics - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-chart-line mr-3"></i>Analytics Dashboard
                </h1>
                <p class="text-gray-600">Filtering and trend analysis</p>
            </div>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Advanced SQL Features:</strong> CTEs (WITH clause), Window Functions (ROW_NUMBER, LAG, AVG OVER), 
                        PARTITION BY, Complex filtering, Subqueries, Growth rate calculations, Rolling averages, Dynamic date ranges
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-filter mr-2"></i>Filters
            </h2>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Device</label>
                    <select name="device" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All Devices</option>
                        <?php foreach ($deviceOptions as $device): ?>
                            <option value="<?php echo $device['d_id']; ?>" <?php echo $selectedDevice == $device['d_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($device['d_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categoryOptions as $category): ?>
                            <option value="<?php echo $category['t_id']; ?>" <?php echo $selectedCategory == $category['t_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['t_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <select name="location" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All Locations</option>
                        <?php foreach ($locationOptions as $location): ?>
                            <option value="<?php echo $location['loc_id']; ?>" <?php echo $selectedLocation == $location['loc_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location['loc_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Resolver</label>
                    <select name="user" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All Resolvers</option>
                        <?php foreach ($userOptions as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $selectedUser == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Log Type</label>
                    <select name="log_type" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="">All Types</option>
                        <option value="error" <?php echo $logType === 'error' ? 'selected' : ''; ?>>Errors</option>
                        <option value="warning" <?php echo $logType === 'warning' ? 'selected' : ''; ?>>Warnings</option>
                        <option value="info" <?php echo $logType === 'info' ? 'selected' : ''; ?>>Info</option>
                        <option value="debug" <?php echo $logType === 'debug' ? 'selected' : ''; ?>>Debug</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <select name="date_range" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition text-sm">
                        <i class="fas fa-search mr-1"></i>Apply Filters
                    </button>
                </div>
            </form>
            
            <?php if ($selectedDevice || $selectedCategory || $selectedLocation || $selectedUser || $logType): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="text-sm text-gray-600">Active filters:</span>
                    <?php if ($selectedDevice): ?>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Device: <?php echo htmlspecialchars(array_column($deviceOptions, 'd_name', 'd_id')[$selectedDevice]); ?></span>
                    <?php endif; ?>
                    <?php if ($selectedCategory): ?>
                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Category: <?php echo htmlspecialchars(array_column($categoryOptions, 't_name', 't_id')[$selectedCategory]); ?></span>
                    <?php endif; ?>
                    <?php if ($selectedLocation): ?>
                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">Location: <?php echo htmlspecialchars(array_column($locationOptions, 'loc_name', 'loc_id')[$selectedLocation]); ?></span>
                    <?php endif; ?>
                    <?php if ($logType): ?>
                        <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs">Type: <?php echo ucfirst($logType); ?></span>
                    <?php endif; ?>
                    <a href="?" class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs hover:bg-gray-200">
                        <i class="fas fa-times mr-1"></i>Clear all
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Log Trends Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-2">
                    <i class="fas fa-chart-line mr-2"></i>Log Trends Over Time
                    <span class="sql-tooltip text-sm font-normal text-blue-600 underline cursor-help ml-2">
                        (View CTE Query)
                        <span class="tooltip-text">
                            <strong>CTE with Window Functions:</strong><br><br>
                            WITH daily_stats AS (<br>
                            &nbsp;&nbsp;SELECT DATE(dl.log_time) as log_date, dl.log_type,<br>
                            &nbsp;&nbsp;COUNT(*) as log_count, AVG(dl.severity_level) as avg_severity<br>
                            &nbsp;&nbsp;FROM device_logs dl ... GROUP BY DATE(dl.log_time), dl.log_type<br>
                            ),<br>
                            ranked_daily AS (<br>
                            &nbsp;&nbsp;SELECT *, ROW_NUMBER() OVER (PARTITION BY log_date ORDER BY log_count DESC),<br>
                            &nbsp;&nbsp;LAG(log_count, 1) OVER (PARTITION BY d_name, log_type ORDER BY log_date),<br>
                            &nbsp;&nbsp;AVG(log_count) OVER (... ROWS BETWEEN 6 PRECEDING AND CURRENT ROW) as rolling_avg<br>
                            &nbsp;&nbsp;FROM daily_stats<br>
                            )<br>
                            SELECT *, ((log_count - prev_day_count) * 100.0 / prev_day_count) as growth_rate<br>
                            FROM ranked_daily
                        </span>
                    </span>
                </h3>
                <canvas id="trendsChart" width="400" height="200"></canvas>
            </div>
            
            <!-- Device Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-pie mr-2"></i>Log Distribution by Type
                </h3>
                <canvas id="distributionChart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <!-- Top Resolvers -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-2">
                <i class="fas fa-user-cog mr-2"></i>Top Issue Resolvers
                <span class="sql-tooltip text-sm font-normal text-blue-600 underline cursor-help ml-2">
                    (View Query)
                    <span class="tooltip-text">
                        <strong>Resolver Performance Query:</strong><br><br>
                        SELECT u.f_name, u.l_name, u.email,<br>
                        COUNT(*) as total_resolved,<br>
                        COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as errors_resolved,<br>
                        COUNT(CASE WHEN dl.log_type = 'warning' THEN 1 END) as warnings_resolved,<br>
                        AVG(TIMESTAMPDIFF(HOUR, dl.log_time, dl.resolved_at)) as avg_resolution_time_hours,<br>
                        COUNT(DISTINCT dl.d_id) as devices_worked_on,<br>
                        ROUND(COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) * 100.0 / COUNT(*), 2) as error_resolution_rate,<br>
                        ROW_NUMBER() OVER (ORDER BY COUNT(*) DESC) as resolver_rank<br>
                        FROM users u INNER JOIN device_logs dl ON u.user_id = dl.resolved_by<br>
                        GROUP BY u.user_id ORDER BY total_resolved DESC
                    </span>
                </span>
            </h3>
            
            <?php if (!empty($resolvers)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resolver</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total Resolved</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Errors</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warnings</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Avg Time (hrs)</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Devices</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Error Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($resolvers as $resolver): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            <?php echo $resolver['resolver_rank'] == 1 ? 'bg-yellow-100 text-yellow-800' : 
                                                     ($resolver['resolver_rank'] <= 3 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                            #<?php echo $resolver['resolver_rank']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($resolver['resolver_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($resolver['email']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                        <span class="font-semibold text-blue-600"><?php echo $resolver['total_resolved']; ?></span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-red-600 font-semibold">
                                        <?php echo $resolver['errors_resolved']; ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-yellow-600 font-semibold">
                                        <?php echo $resolver['warnings_resolved']; ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($resolver['avg_resolution_time_hours'], 1); ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $resolver['devices_worked_on']; ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs
                                            <?php echo $resolver['error_resolution_rate'] >= 50 ? 'bg-red-100 text-red-800' : 
                                                     ($resolver['error_resolution_rate'] >= 25 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                            <?php echo $resolver['error_resolution_rate']; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-user-slash text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">No resolver data found for the selected filters.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Device Performance -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-microchip mr-2"></i>Device Performance Analysis
            </h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total Logs</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Errors</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warnings</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Health Score</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unresolved</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($devicePerformance as $device): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($device['d_name']); ?></div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($device['device_type']); ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($device['loc_name']); ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo $device['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo ucfirst($device['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                    <?php echo $device['total_logs']; ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-red-600 font-semibold">
                                    <?php echo $device['error_count']; ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-yellow-600 font-semibold">
                                    <?php echo $device['warning_count']; ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                            <div class="h-2 rounded-full <?php echo $device['health_score'] >= 70 ? 'bg-green-500' : ($device['health_score'] >= 40 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                                                 style="width: <?php echo $device['health_score']; ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-600"><?php echo number_format($device['health_score'], 1); ?>%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <?php if ($device['unresolved_issues'] > 0): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <?php echo $device['unresolved_issues']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-green-600 text-sm">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartData['dates']); ?>,
                datasets: [
                    {
                        label: 'Errors',
                        data: <?php echo json_encode($chartData['errors']); ?>,
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Warnings',
                        data: <?php echo json_encode($chartData['warnings']); ?>,
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Info',
                        data: <?php echo json_encode($chartData['info']); ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Distribution Chart
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const totalErrors = <?php echo array_sum($chartData['errors']); ?>;
        const totalWarnings = <?php echo array_sum($chartData['warnings']); ?>;
        const totalInfo = <?php echo array_sum($chartData['info']); ?>;
        const totalDebug = <?php echo array_sum($chartData['debug']); ?>;
        
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Errors', 'Warnings', 'Info', 'Debug'],
                datasets: [{
                    data: [totalErrors, totalWarnings, totalInfo, totalDebug],
                    backgroundColor: [
                        'rgb(239, 68, 68)',
                        'rgb(245, 158, 11)',
                        'rgb(59, 130, 246)',
                        'rgb(107, 114, 128)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>