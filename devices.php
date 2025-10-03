<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: Complex JOINs, Pagination, Search with LIKE, ORDER BY
 * Device management with advanced filtering and search capabilities
 */

$database = new Database();
$conn = $database->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'updated_at';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build WHERE clause dynamically
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(d.d_name LIKE ? OR d.serial_number LIKE ? OR u.f_name LIKE ? OR u.l_name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($typeFilter > 0) {
    $whereConditions[] = "d.t_id = ?";
    $params[] = $typeFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "d.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// SQL Feature: Complex query with multiple JOINs, subqueries, and conditional WHERE
$devicesQuery = "
    SELECT 
        d.d_id,
        d.d_name,
        d.serial_number,
        d.status,
        d.purchase_date,
        d.warranty_expiry,
        d.created_at,
        d.updated_at,
        dt.t_name as device_type,
        dt.icon as device_icon,
        CONCAT(u.f_name, ' ', u.l_name) as owner_name,
        u.email as owner_email,
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'error' AND dl.resolved_by IS NULL) as unresolved_errors,
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id) as total_logs,
        (SELECT MAX(dl.log_time) FROM device_logs dl WHERE dl.d_id = d.d_id) as last_activity,
        GROUP_CONCAT(DISTINCT l.loc_name ORDER BY dep.deployed_at DESC SEPARATOR ', ') as deployed_locations,
        COUNT(DISTINCT dep.loc_id) as location_count
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
    LEFT JOIN locations l ON dep.loc_id = l.loc_id
    $whereClause
    GROUP BY d.d_id, d.d_name, d.serial_number, d.status, d.purchase_date, d.warranty_expiry, 
             d.created_at, d.updated_at, dt.t_name, dt.icon, u.f_name, u.l_name, u.email
    ORDER BY $sortBy $sortOrder
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($devicesQuery);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total devices for pagination
$countQuery = "
    SELECT COUNT(DISTINCT d.d_id) as total
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
    LEFT JOIN locations l ON dep.loc_id = l.loc_id
    $whereClause
";

$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalDevices = $countStmt->fetchColumn();
$totalPages = ceil($totalDevices / $limit);

// Get device types for filter dropdown
$typesQuery = "SELECT t_id, t_name FROM device_types ORDER BY t_name";
$typesStmt = $conn->prepare($typesQuery);
$typesStmt->execute();
$deviceTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devices - IoT Device Manager</title>
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
        
        .device-card {
            transition: all 0.3s ease;
        }
        
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
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
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-microchip mr-3"></i>Device Management
                </h1>
                <p class="text-gray-600">Manage and monitor your IoT devices</p>
            </div>
            <a href="add_device.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-plus mr-2"></i>Add New Device
            </a>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>SQL Features:</strong> Complex JOINs (INNER, LEFT), Subqueries, GROUP BY, GROUP_CONCAT, LIMIT/OFFSET pagination, Dynamic WHERE clauses, LIKE operator, ORDER BY
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Device name, serial, owner..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Device Type</label>
                    <select id="type" name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">All Types</option>
                        <?php foreach ($deviceTypes as $type): ?>
                            <option value="<?php echo $type['t_id']; ?>" <?php echo $typeFilter == $type['t_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['t_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="maintenance" <?php echo $statusFilter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="error" <?php echo $statusFilter === 'error' ? 'selected' : ''; ?>>Error</option>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select id="sort" name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="updated_at" <?php echo $sortBy === 'updated_at' ? 'selected' : ''; ?>>Last Updated</option>
                        <option value="d.d_name" <?php echo $sortBy === 'd.d_name' ? 'selected' : ''; ?>>Device Name</option>
                        <option value="dt.t_name" <?php echo $sortBy === 'dt.t_name' ? 'selected' : ''; ?>>Device Type</option>
                        <option value="d.status" <?php echo $sortBy === 'd.status' ? 'selected' : ''; ?>>Status</option>
                        <option value="d.purchase_date" <?php echo $sortBy === 'd.purchase_date' ? 'selected' : ''; ?>>Purchase Date</option>
                    </select>
                    <input type="hidden" name="order" value="<?php echo $sortOrder; ?>">
                </div>
                
                <div class="flex items-end space-x-2">
                    <div class="sql-tooltip">
                        <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <span class="tooltip-text">
                            Dynamic SQL Query with conditional WHERE clauses:<br><br>
                            SELECT d.*, dt.t_name, CONCAT(u.f_name, ' ', u.l_name) as owner_name,<br>
                            (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id<br>
                            AND dl.log_type = 'error' AND dl.resolved_by IS NULL) as unresolved_errors,<br>
                            GROUP_CONCAT(DISTINCT l.loc_name) as deployed_locations<br>
                            FROM devices d<br>
                            INNER JOIN device_types dt ON d.t_id = dt.t_id<br>
                            INNER JOIN users u ON d.user_id = u.user_id<br>
                            LEFT JOIN deployments dep ON d.d_id = dep.d_id<br>
                            LEFT JOIN locations l ON dep.loc_id = l.loc_id<br>
                            WHERE (conditions based on filters)<br>
                            GROUP BY d.d_id<br>
                            ORDER BY <?php echo $sortBy; ?> <?php echo $sortOrder; ?><br>
                            LIMIT <?php echo $limit; ?> OFFSET <?php echo $offset; ?>
                        </span>
                    </div>
                    
                    <a href="devices.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Results Summary -->
        <div class="flex justify-between items-center mb-6">
            <div class="text-gray-600">
                Showing <?php echo count($devices); ?> of <?php echo $totalDevices; ?> devices
                <?php if (!empty($search) || $typeFilter || !empty($statusFilter)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-600">Sort:</span>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => $sortBy, 'order' => $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                   class="text-blue-600 hover:text-blue-800">
                    <?php echo $sortOrder === 'ASC' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'; ?>
                </a>
            </div>
        </div>
        
        <!-- Device Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php foreach ($devices as $device): ?>
                <div class="device-card bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1">
                                <?php echo htmlspecialchars($device['d_name']); ?>
                            </h3>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($device['device_type']); ?></p>
                        </div>
                        <span class="status-badge status-<?php echo $device['status']; ?>">
                            <?php echo ucfirst($device['status']); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Serial:</span>
                            <span class="font-mono text-gray-800"><?php echo htmlspecialchars($device['serial_number']); ?></span>
                        </div>
                        
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Owner:</span>
                            <span class="text-gray-800"><?php echo htmlspecialchars($device['owner_name']); ?></span>
                        </div>
                        
                        <?php if ($device['deployed_locations']): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Locations:</span>
                                <span class="text-gray-800 text-right max-w-32 truncate" title="<?php echo htmlspecialchars($device['deployed_locations']); ?>">
                                    <?php echo htmlspecialchars($device['deployed_locations']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total Logs:</span>
                            <span class="text-gray-800"><?php echo $device['total_logs']; ?></span>
                        </div>
                        
                        <?php if ($device['unresolved_errors'] > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-red-600">Unresolved Errors:</span>
                                <span class="text-red-600 font-semibold"><?php echo $device['unresolved_errors']; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($device['last_activity']): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Last Activity:</span>
                                <span class="text-gray-800"><?php echo date('M j, H:i', strtotime($device['last_activity'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex space-x-2">
                        <a href="device_details.php?id=<?php echo $device['d_id']; ?>" 
                           class="flex-1 bg-blue-600 text-white text-center py-2 rounded hover:bg-blue-700 transition">
                            <i class="fas fa-eye mr-1"></i>View
                        </a>
                        
                        <?php if ($_SESSION['user_id'] == $device['owner_name'] || $_SESSION['user_email'] == 'admin@iot.com'): ?>
                            <a href="edit_device.php?id=<?php echo $device['d_id']; ?>" 
                               class="flex-1 bg-green-600 text-white text-center py-2 rounded hover:bg-green-700 transition">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </a>
                        <?php endif; ?>
                        
                        <a href="device_logs.php?device_id=<?php echo $device['d_id']; ?>" 
                           class="flex-1 bg-purple-600 text-white text-center py-2 rounded hover:bg-purple-700 transition">
                            <i class="fas fa-list mr-1"></i>Logs
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($devices)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No devices found</h3>
                <p class="text-gray-500 mb-4">Try adjusting your search criteria or add a new device.</p>
                <a href="add_device.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>Add New Device
                </a>
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
</body>
</html>