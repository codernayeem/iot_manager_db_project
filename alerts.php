<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'alert_time';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Validate sort column
$allowedSortColumns = ['log_id', 'alert_time', 'status', 'd_name', 'message'];
if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'alert_time';
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(a.message LIKE ? OR d.d_name LIKE ? OR dt.t_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "a.status = ?";
    $params[] = $statusFilter;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// SQL Feature: Complex query with JOINs, filtering, and ordering
$alertsQuery = "
    SELECT 
        a.log_id,
        a.alert_time,
        a.message,
        a.status,
        d.d_id,
        d.d_name,
        d.serial_number,
        dt.t_name as device_type,
        dl.log_type,
        dl.severity_level,
        l.loc_name,
        u.f_name,
        u.l_name
    FROM alerts a
    INNER JOIN device_logs dl ON a.log_id = dl.log_id
    INNER JOIN devices d ON dl.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN deployments dep ON d.d_id = dep.d_id
    LEFT JOIN locations l ON dep.loc_id = l.loc_id
    $whereClause
    GROUP BY a.log_id, a.alert_time, a.message, a.status, d.d_id, d.d_name, 
             d.serial_number, dt.t_name, dl.log_type, dl.severity_level, u.f_name, u.l_name
    ORDER BY $sortBy $sortOrder
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($alertsQuery);
$stmt->execute($params);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(DISTINCT a.log_id) as total
    FROM alerts a
    INNER JOIN device_logs dl ON a.log_id = dl.log_id
    INNER JOIN devices d ON dl.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    $whereClause
";

$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalAlerts = $countStmt->fetchColumn();
$totalPages = ceil($totalAlerts / $limit);

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_alerts,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_alerts,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_alerts
    FROM alerts
";

$statsStmt = $conn->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-bell mr-3"></i>System Alerts
            </h1>
            <p class="text-gray-600">Monitor and manage device alerts</p>
        </div>
        
        <!-- SQL Info Box -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400 text-xl"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>SQL Features Used:</strong>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">INNER JOIN (alerts → device_logs → devices)</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">LEFT JOIN for optional locations</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">UPDATE for status changes</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">TRIGGER (auto-creates alerts from high-severity logs)</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">Dynamic filtering & sorting</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-bell text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Alerts</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_alerts']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-exclamation-circle text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Active Alerts</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $stats['active_alerts']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Resolved</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $stats['resolved_alerts']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Device, message..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="alert_time" <?php echo $sortBy === 'alert_time' ? 'selected' : ''; ?>>Alert Time</option>
                        <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                        <option value="d_name" <?php echo $sortBy === 'd_name' ? 'selected' : ''; ?>>Device Name</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                    <select name="order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="desc" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="asc" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
                
                <div class="md:col-span-4 flex justify-end space-x-2">
                    <a href="alerts.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">
                        <i class="fas fa-redo mr-2"></i>Reset
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Alerts Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if (empty($alerts)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-6xl mb-4"></i>
                    <p class="text-xl">No alerts found</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alert ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($alerts as $alert): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                        #<?php echo $alert['log_id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($alert['alert_time'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($alert['alert_time'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <a href="device_details.php?id=<?php echo $alert['d_id']; ?>" class="text-blue-600 hover:underline">
                                                <?php echo htmlspecialchars($alert['d_name']); ?>
                                            </a>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($alert['device_type']); ?>
                                            <?php if ($alert['loc_name']): ?>
                                                • <?php echo htmlspecialchars($alert['loc_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-md">
                                            <?php echo htmlspecialchars($alert['message']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                            <?php 
                                            if ($alert['severity_level'] >= 7) echo 'bg-red-100 text-red-800';
                                            elseif ($alert['severity_level'] >= 4) echo 'bg-orange-100 text-orange-800';
                                            else echo 'bg-yellow-100 text-yellow-800';
                                            ?>">
                                            Level <?php echo $alert['severity_level']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                            <?php echo $alert['status'] === 'active' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                            <i class="fas fa-<?php echo $alert['status'] === 'active' ? 'exclamation-circle' : 'check-circle'; ?> mr-1"></i>
                                            <?php echo ucfirst($alert['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($alert['f_name'] . ' ' . $alert['l_name']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo $offset + 1; ?></span>
                                to <span class="font-medium"><?php echo min($offset + $limit, $totalAlerts); ?></span>
                                of <span class="font-medium"><?php echo $totalAlerts; ?></span> results
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo $sortBy; ?>&order=<?php echo strtolower($sortOrder); ?>" 
                                       class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo $sortBy; ?>&order=<?php echo strtolower($sortOrder); ?>" 
                                       class="px-3 py-2 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo $sortBy; ?>&order=<?php echo strtolower($sortOrder); ?>" 
                                       class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
