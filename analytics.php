<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * Merged Analytics Dashboard with Advanced Filtering
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

// Build date condition based on date range
$dateCondition = "dl.log_time >= DATE_SUB(NOW(), INTERVAL $dateRange DAY)";

// SQL Feature: Window Functions with RANK and ROW_NUMBER
$devicePerformanceQuery = "
    WITH device_stats AS (
        SELECT 
            d.d_id,
            d.d_name,
            dt.t_name as device_type,
            d.status,
            COUNT(dl.log_id) as total_logs,
            COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as error_count,
            COUNT(CASE WHEN dl.log_type = 'warning' THEN 1 END) as warning_count,
            COUNT(CASE WHEN dl.log_type = 'info' THEN 1 END) as info_count,
            AVG(dl.severity_level) as avg_severity,
            MAX(dl.log_time) as last_log_time,
            COUNT(CASE WHEN dl.log_type = 'error' AND dl.resolved_by IS NULL THEN 1 END) as unresolved_errors,
            DATEDIFF(NOW(), MAX(dl.log_time)) as days_since_last_log
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        LEFT JOIN device_logs dl ON d.d_id = dl.d_id AND $dateCondition
        GROUP BY d.d_id, d.d_name, dt.t_name, d.status
    ),
    ranked_devices AS (
        SELECT *,
            RANK() OVER (ORDER BY error_count DESC, avg_severity DESC) as error_rank,
            RANK() OVER (ORDER BY total_logs DESC) as activity_rank,
            ROW_NUMBER() OVER (PARTITION BY device_type ORDER BY error_count DESC) as type_error_rank,
            CASE 
                WHEN error_count = 0 AND warning_count <= 2 THEN 'Excellent'
                WHEN error_count <= 2 AND warning_count <= 5 THEN 'Good'
                WHEN error_count <= 5 OR warning_count <= 10 THEN 'Fair'
                ELSE 'Poor'
            END as performance_rating,
            CASE 
                WHEN days_since_last_log <= 1 THEN 'Very Active'
                WHEN days_since_last_log <= 7 THEN 'Active'
                WHEN days_since_last_log <= 30 THEN 'Moderate'
                ELSE 'Inactive'
            END as activity_level
        FROM device_stats
    )
    SELECT * FROM ranked_devices
    ORDER BY error_rank, activity_rank
";

$stmt = $conn->prepare($devicePerformanceQuery);
$stmt->execute();
$devicePerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL Feature: Complex aggregation with multiple JOINs and date functions
$dailyActivityQuery = "
    SELECT 
        DATE(dl.log_time) as log_date,
        COUNT(DISTINCT dl.d_id) as active_devices,
        COUNT(dl.log_id) as total_logs,
        COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as errors,
        COUNT(CASE WHEN dl.log_type = 'warning' THEN 1 END) as warnings,
        COUNT(CASE WHEN dl.log_type = 'info' THEN 1 END) as info_logs,
        AVG(dl.severity_level) as avg_severity,
        COUNT(CASE WHEN dl.resolved_by IS NOT NULL THEN 1 END) as resolved_issues
    FROM device_logs dl
    WHERE $dateCondition
    GROUP BY DATE(dl.log_time)
    ORDER BY log_date DESC
    LIMIT 30
";

$stmt = $conn->prepare($dailyActivityQuery);
$stmt->execute();
$dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL Feature: Aggregation by device type with percentile calculations
$deviceTypeAnalysisQuery = "
    SELECT 
        dt.t_name as device_type,
        COUNT(DISTINCT d.d_id) as total_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) as active_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'error' THEN d.d_id END) as error_devices,
        ROUND(COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) * 100.0 / COUNT(DISTINCT d.d_id), 2) as uptime_percentage,
        COUNT(dl.log_id) as total_logs,
        COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as total_errors,
        COUNT(CASE WHEN dl.log_type = 'error' AND dl.resolved_by IS NULL THEN 1 END) as unresolved_errors,
        ROUND(AVG(dl.severity_level), 2) as avg_severity,
        COUNT(DISTINCT dep.loc_id) as deployed_locations,
        MIN(d.purchase_date) as oldest_device,
        MAX(d.purchase_date) as newest_device
    FROM device_types dt
    LEFT JOIN devices d ON dt.t_id = d.t_id
    LEFT JOIN device_logs dl ON d.d_id = dl.d_id AND $dateCondition
    LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
    GROUP BY dt.t_id, dt.t_name
    HAVING total_devices > 0
    ORDER BY total_devices DESC, uptime_percentage DESC
";

$stmt = $conn->prepare($deviceTypeAnalysisQuery);
$stmt->execute();
$deviceTypeAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL Feature: Location-based analysis with geospatial concepts
$locationAnalysisQuery = "
    SELECT 
        l.loc_name,
        l.address,
        COUNT(DISTINCT dep.d_id) as deployed_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) as active_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'error' THEN d.d_id END) as error_devices,
        COUNT(dl.log_id) as total_logs,
        COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as total_errors,
        ROUND(AVG(CASE WHEN dl.log_type = 'error' THEN dl.severity_level END), 2) as avg_error_severity,
        GROUP_CONCAT(DISTINCT dt.t_name ORDER BY dt.t_name SEPARATOR ', ') as device_types,
        COUNT(DISTINCT dt.t_id) as type_diversity,
        MAX(dl.log_time) as last_activity
    FROM locations l
    INNER JOIN deployments dep ON l.loc_id = dep.loc_id AND dep.is_active = 1
    INNER JOIN devices d ON dep.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    LEFT JOIN device_logs dl ON d.d_id = dl.d_id AND $dateCondition
    GROUP BY l.loc_id, l.loc_name, l.address
    ORDER BY deployed_devices DESC, total_errors ASC
";

$stmt = $conn->prepare($locationAnalysisQuery);
$stmt->execute();
$locationAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL Feature: Time-based trending analysis
$trendsQuery = "
    SELECT 
        YEARWEEK(dl.log_time) as year_week,
        WEEK(dl.log_time) as week_num,
        YEAR(dl.log_time) as year,
        COUNT(dl.log_id) as total_logs,
        COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as errors,
        COUNT(DISTINCT dl.d_id) as active_devices,
        AVG(dl.severity_level) as avg_severity,
        LAG(COUNT(dl.log_id)) OVER (ORDER BY YEARWEEK(dl.log_time)) as prev_week_logs,
        ROUND(
            (COUNT(dl.log_id) - LAG(COUNT(dl.log_id)) OVER (ORDER BY YEARWEEK(dl.log_time))) * 100.0 / 
            NULLIF(LAG(COUNT(dl.log_id)) OVER (ORDER BY YEARWEEK(dl.log_time)), 0), 2
        ) as week_over_week_change
    FROM device_logs dl
    WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
    GROUP BY YEARWEEK(dl.log_time), WEEK(dl.log_time), YEAR(dl.log_time)
    ORDER BY year_week DESC
    LIMIT 12
";

$stmt = $conn->prepare($trendsQuery);
$stmt->execute();
$trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - IoT Device Manager</title>
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
            width: 600px;
            background-color: #1f2937;
            color: #fff;
            border-radius: 6px;
            padding: 15px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -300px;
            opacity: 0;
            transition: opacity 0.3s;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            text-align: left;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .sql-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        .performance-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .excellent { background-color: #dcfce7; color: #166534; }
        .good { background-color: #dbeafe; color: #1e40af; }
        .fair { background-color: #fef3c7; color: #d97706; }
        .poor { background-color: #fee2e2; color: #dc2626; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-chart-bar mr-3"></i>Advanced Analytics
            </h1>
            <p class="text-gray-600">Comprehensive analysis of device performance and system metrics</p>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Advanced SQL Features:</strong> Window Functions (RANK, ROW_NUMBER, LAG), Common Table Expressions (WITH), 
                        Complex aggregations, Date functions (YEARWEEK, DATEDIFF), Percentile calculations, Conditional aggregation
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Date Range Selector -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="range" class="block text-sm font-medium text-gray-700 mb-2">Time Range</label>
                    <select id="range" name="range" class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" onchange="toggleCustomDates()">
                        <option value="7" <?php echo $dateRange === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $dateRange === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $dateRange === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="365" <?php echo $dateRange === '365' ? 'selected' : ''; ?>>Last year</option>
                        <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom range</option>
                    </select>
                </div>
                
                <div id="custom-dates" class="<?php echo $dateRange !== 'custom' ? 'hidden' : ''; ?> flex gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $customStart; ?>" 
                               class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $customEnd; ?>" 
                               class="px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="sql-tooltip">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-chart-line mr-2"></i>Update Analytics
                    </button>
                    <span class="tooltip-text">
                        Date filtering applied to all analytics queries:<br><br>
                        WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL <?php echo $dateRange; ?> DAY)<br><br>
                        Or for custom range:<br>
                        WHERE dl.log_time BETWEEN 'start_date 00:00:00' AND 'end_date 23:59:59'
                    </span>
                </div>
            </form>
        </div>
        
        <!-- Device Performance Analysis -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="sql-tooltip inline-block mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-trophy mr-2 text-yellow-500"></i>Device Performance Rankings
                </h2>
                <span class="tooltip-text">
                    Advanced SQL with Window Functions and CTEs:<br><br>
                    WITH device_stats AS (<br>
                    &nbsp;&nbsp;SELECT d.d_id, d.d_name, dt.t_name,<br>
                    &nbsp;&nbsp;COUNT(dl.log_id) as total_logs,<br>
                    &nbsp;&nbsp;COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as error_count,<br>
                    &nbsp;&nbsp;AVG(dl.severity_level) as avg_severity<br>
                    &nbsp;&nbsp;FROM devices d<br>
                    &nbsp;&nbsp;INNER JOIN device_types dt ON d.t_id = dt.t_id<br>
                    &nbsp;&nbsp;LEFT JOIN device_logs dl ON d.d_id = dl.d_id<br>
                    &nbsp;&nbsp;GROUP BY d.d_id, d.d_name, dt.t_name<br>
                    ),<br>
                    ranked_devices AS (<br>
                    &nbsp;&nbsp;SELECT *,<br>
                    &nbsp;&nbsp;RANK() OVER (ORDER BY error_count DESC) as error_rank,<br>
                    &nbsp;&nbsp;ROW_NUMBER() OVER (PARTITION BY device_type ORDER BY error_count DESC) as type_error_rank<br>
                    &nbsp;&nbsp;FROM device_stats<br>
                    )<br>
                    SELECT * FROM ranked_devices ORDER BY error_rank
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Performance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Errors</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Severity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unresolved</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (array_slice($devicePerformance, 0, 10) as $index => $device): ?>
                            <tr class="<?php echo $index < 3 ? 'bg-blue-50' : ''; ?>">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if ($index === 0): ?>
                                            <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                                        <?php elseif ($index === 1): ?>
                                            <i class="fas fa-medal text-gray-400 mr-2"></i>
                                        <?php elseif ($index === 2): ?>
                                            <i class="fas fa-award text-orange-500 mr-2"></i>
                                        <?php endif; ?>
                                        <span class="font-medium">#<?php echo $device['error_rank']; ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap font-medium text-gray-900">
                                    <?php echo htmlspecialchars($device['d_name']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($device['device_type']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="performance-badge <?php echo strtolower($device['performance_rating']); ?>">
                                        <?php echo $device['performance_rating']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $device['activity_level']; ?>
                                    <br><small>(<?php echo $device['total_logs']; ?> logs)</small>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="<?php echo $device['error_count'] > 0 ? 'text-red-600 font-semibold' : 'text-green-600'; ?>">
                                        <?php echo $device['error_count']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($device['avg_severity'], 2); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php if ($device['unresolved_errors'] > 0): ?>
                                        <span class="text-red-600 font-semibold"><?php echo $device['unresolved_errors']; ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Device Type Analysis -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="sql-tooltip inline-block mb-6">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-chart-pie mr-2 text-blue-500"></i>Device Type Analysis
                    </h2>
                    <span class="tooltip-text">
                        Complex aggregation with percentage calculations:<br><br>
                        SELECT dt.t_name,<br>
                        COUNT(DISTINCT d.d_id) as total_devices,<br>
                        COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) as active_devices,<br>
                        ROUND(COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) * 100.0 /<br>
                        COUNT(DISTINCT d.d_id), 2) as uptime_percentage,<br>
                        COUNT(dl.log_id) as total_logs,<br>
                        COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as total_errors<br>
                        FROM device_types dt<br>
                        LEFT JOIN devices d ON dt.t_id = d.t_id<br>
                        LEFT JOIN device_logs dl ON d.d_id = dl.d_id<br>
                        GROUP BY dt.t_id, dt.t_name<br>
                        ORDER BY total_devices DESC
                    </span>
                </div>
                
                <?php foreach ($deviceTypeAnalysis as $type): ?>
                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($type['device_type']); ?></h3>
                            <span class="text-lg font-bold text-blue-600"><?php echo $type['total_devices']; ?> devices</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Uptime:</span>
                                <span class="font-semibold text-green-600"><?php echo $type['uptime_percentage']; ?>%</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Total Logs:</span>
                                <span class="font-semibold"><?php echo number_format($type['total_logs']); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Errors:</span>
                                <span class="font-semibold text-red-600"><?php echo $type['total_errors']; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Locations:</span>
                                <span class="font-semibold"><?php echo $type['deployed_locations']; ?></span>
                            </div>
                        </div>
                        
                        <!-- Progress bar for uptime -->
                        <div class="mt-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $type['uptime_percentage']; ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Location Analysis -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="sql-tooltip inline-block mb-6">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-map-marker-alt mr-2 text-green-500"></i>Location Analysis
                    </h2>
                    <span class="tooltip-text">
                        Location-based analysis with device diversity metrics:<br><br>
                        SELECT l.loc_name, l.address,<br>
                        COUNT(DISTINCT dep.d_id) as deployed_devices,<br>
                        COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) as active_devices,<br>
                        GROUP_CONCAT(DISTINCT dt.t_name ORDER BY dt.t_name) as device_types,<br>
                        COUNT(DISTINCT dt.t_id) as type_diversity<br>
                        FROM locations l<br>
                        INNER JOIN deployments dep ON l.loc_id = dep.loc_id<br>
                        INNER JOIN devices d ON dep.d_id = d.d_id<br>
                        INNER JOIN device_types dt ON d.t_id = dt.t_id<br>
                        GROUP BY l.loc_id, l.loc_name, l.address<br>
                        ORDER BY deployed_devices DESC
                    </span>
                </div>
                
                <?php foreach ($locationAnalysis as $location): ?>
                    <div class="mb-4 p-4 border border-gray-200 rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($location['loc_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($location['address']); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-bold text-green-600"><?php echo $location['deployed_devices']; ?></span>
                                <p class="text-xs text-gray-500">devices</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 text-sm mb-2">
                            <div>
                                <span class="text-gray-600">Active:</span>
                                <span class="font-semibold text-green-600"><?php echo $location['active_devices']; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Errors:</span>
                                <span class="font-semibold text-red-600"><?php echo $location['error_devices']; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Total Logs:</span>
                                <span class="font-semibold"><?php echo number_format($location['total_logs']); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Diversity:</span>
                                <span class="font-semibold"><?php echo $location['type_diversity']; ?> types</span>
                            </div>
                        </div>
                        
                        <div class="text-xs text-gray-600">
                            <strong>Types:</strong> <?php echo htmlspecialchars($location['device_types']); ?>
                        </div>
                        
                        <?php if ($location['last_activity']): ?>
                            <div class="text-xs text-gray-500 mt-1">
                                Last activity: <?php echo date('M j, Y H:i', strtotime($location['last_activity'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Trends Analysis -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="sql-tooltip inline-block mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-chart-line mr-2 text-purple-500"></i>Weekly Trends Analysis
                </h2>
                <span class="tooltip-text">
                    Time-based trending with LAG window function:<br><br>
                    SELECT YEARWEEK(dl.log_time) as year_week,<br>
                    COUNT(dl.log_id) as total_logs,<br>
                    COUNT(CASE WHEN dl.log_type = 'error' THEN 1 END) as errors,<br>
                    COUNT(DISTINCT dl.d_id) as active_devices,<br>
                    LAG(COUNT(dl.log_id)) OVER (ORDER BY YEARWEEK(dl.log_time)) as prev_week_logs,<br>
                    ROUND((COUNT(dl.log_id) - LAG(COUNT(dl.log_id)) OVER (ORDER BY YEARWEEK(dl.log_time))) * 100.0 /<br>
                    NULLIF(LAG(COUNT(dl.log_id)) OVER (ORDER BY YEARWEEK(dl.log_time)), 0), 2) as week_over_week_change<br>
                    FROM device_logs dl<br>
                    WHERE dl.log_time >= DATE_SUB(NOW(), INTERVAL 12 WEEK)<br>
                    GROUP BY YEARWEEK(dl.log_time)<br>
                    ORDER BY year_week DESC
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Week</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Logs</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Errors</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Active Devices</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Severity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">WoW Change</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($trends as $trend): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    Week <?php echo $trend['week_num']; ?>, <?php echo $trend['year']; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($trend['total_logs']); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <span class="<?php echo $trend['errors'] > 0 ? 'text-red-600 font-semibold' : 'text-green-600'; ?>">
                                        <?php echo $trend['errors']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $trend['active_devices']; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($trend['avg_severity'], 2); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <?php if ($trend['week_over_week_change'] !== null): ?>
                                        <span class="<?php echo $trend['week_over_week_change'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                            <?php echo $trend['week_over_week_change'] > 0 ? '+' : ''; ?><?php echo number_format($trend['week_over_week_change'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Activity Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-activity mr-2"></i>Daily Activity
                </h3>
                <canvas id="activityChart" width="400" height="200"></canvas>
            </div>
            
            <!-- Error Distribution Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Error Distribution
                </h3>
                <canvas id="errorChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        function toggleCustomDates() {
            const range = document.getElementById('range').value;
            const customDates = document.getElementById('custom-dates');
            if (range === 'custom') {
                customDates.classList.remove('hidden');
            } else {
                customDates.classList.add('hidden');
            }
        }
        
        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityData = <?php echo json_encode(array_reverse($dailyActivity)); ?>;
        
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: activityData.map(d => d.log_date),
                datasets: [{
                    label: 'Total Logs',
                    data: activityData.map(d => d.total_logs),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Errors',
                    data: activityData.map(d => d.errors),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Error Distribution Chart
        const errorCtx = document.getElementById('errorChart').getContext('2d');
        const typeData = <?php echo json_encode($deviceTypeAnalysis); ?>;
        
        new Chart(errorCtx, {
            type: 'doughnut',
            data: {
                labels: typeData.map(d => d.device_type),
                datasets: [{
                    data: typeData.map(d => d.total_errors),
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(139, 92, 246, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>
</html>