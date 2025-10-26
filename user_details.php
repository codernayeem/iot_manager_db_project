<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: Complex JOINs, Subqueries, Aggregations, Window Functions
 * User details page with comprehensive activity history
 */

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id == 0) {
    header("Location: users.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Get user details with comprehensive statistics
$userQuery = "
    SELECT 
        u.*,
        COUNT(DISTINCT d.d_id) as device_count,
        COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) as active_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'inactive' THEN d.d_id END) as inactive_devices,
        (SELECT COUNT(*) FROM device_logs dl 
         INNER JOIN devices d2 ON dl.d_id = d2.d_id 
         WHERE d2.user_id = u.user_id) as total_logs_for_devices,
        (SELECT COUNT(*) FROM device_logs dl 
         WHERE dl.resolved_by = u.user_id) as logs_resolved,
        (SELECT COUNT(*) FROM device_logs dl 
         INNER JOIN devices d3 ON dl.d_id = d3.d_id 
         WHERE d3.user_id = u.user_id AND dl.log_type = 'error' AND dl.resolved_by IS NULL) as unresolved_errors,
        (SELECT COUNT(*) FROM deployments dep
         INNER JOIN devices d4 ON dep.d_id = d4.d_id
         WHERE d4.user_id = u.user_id) as deployments_made,
        (SELECT MAX(dep.deployed_at) FROM deployments dep
         INNER JOIN devices d5 ON dep.d_id = d5.d_id
         WHERE d5.user_id = u.user_id) as last_deployment
    FROM users u
    LEFT JOIN devices d ON u.user_id = d.user_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
";

$stmt = $conn->prepare($userQuery);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error_message'] = "User not found!";
    header("Location: users.php");
    exit;
}

// Get user's devices
$devicesQuery = "
    SELECT 
        d.d_id,
        d.d_name,
        d.serial_number,
        d.status,
        dt.t_name as device_type,
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id) as log_count,
        (SELECT MAX(dl.log_time) FROM device_logs dl WHERE dl.d_id = d.d_id) as last_activity
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    WHERE d.user_id = ?
    ORDER BY d.updated_at DESC
    LIMIT 10
";

$devicesStmt = $conn->prepare($devicesQuery);
$devicesStmt->execute([$user_id]);
$userDevices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent resolutions by this user
$resolutionsQuery = "
    SELECT 
        dl.log_id,
        dl.message,
        dl.log_type,
        dl.resolved_at,
        dl.resolution_notes,
        d.d_name,
        dt.t_name as device_type
    FROM device_logs dl
    INNER JOIN devices d ON dl.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    WHERE dl.resolved_by = ?
    ORDER BY dl.resolved_at DESC
    LIMIT 10
";

$resolutionsStmt = $conn->prepare($resolutionsQuery);
$resolutionsStmt->execute([$user_id]);
$resolutions = $resolutionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's deployments
$deploymentsQuery = "
    SELECT 
        dep.deployed_at,
        d.d_name,
        dt.t_name as device_type,
        l.loc_name,
        l.address
    FROM deployments dep
    INNER JOIN devices d ON dep.d_id = d.d_id
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN locations l ON dep.loc_id = l.loc_id
    WHERE d.user_id = ?
    ORDER BY dep.deployed_at DESC
    LIMIT 10
";

$deploymentsStmt = $conn->prepare($deploymentsQuery);
$deploymentsStmt->execute([$user_id]);
$deployments = $deploymentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-user mr-3"></i>User Details
                </h1>
                <p class="text-gray-600">Complete profile and activity history</p>
            </div>
            <div class="flex space-x-2">
                <a href="edit_user.php?id=<?php echo $user_id; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-edit mr-2"></i>Edit User
                </a>
                <a href="users.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
        
        <!-- User Profile Card -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <div class="flex items-start justify-between">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-24 h-24 bg-blue-600 rounded-full">
                        <i class="fas fa-user text-white text-4xl"></i>
                    </div>
                    <div class="ml-6">
                        <h2 class="text-2xl font-bold text-gray-800">
                            <?php echo htmlspecialchars($user['f_name'] . ' ' . $user['l_name']); ?>
                        </h2>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                        <div class="flex space-x-4 text-sm text-gray-500">
                            <span><i class="fas fa-calendar-alt mr-1"></i>Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                            <span><i class="fas fa-clock mr-1"></i>Last Update: <?php echo date('M j, Y', strtotime($user['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-block px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                        <i class="fas fa-check-circle mr-1"></i>Active
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Total Devices</h3>
                    <i class="fas fa-microchip text-blue-600 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900"><?php echo $user['device_count']; ?></p>
                <div class="mt-2 text-xs text-gray-500">
                    <span class="text-green-600"><?php echo $user['active_devices']; ?> active</span> • 
                    <span class="text-red-600"><?php echo $user['error_devices']; ?> error</span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Device Logs</h3>
                    <i class="fas fa-list-alt text-purple-600 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900"><?php echo $user['total_logs_for_devices']; ?></p>
                <div class="mt-2 text-xs text-gray-500">
                    <span class="text-red-600"><?php echo $user['unresolved_errors']; ?> unresolved</span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Logs Resolved</h3>
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900"><?php echo $user['logs_resolved']; ?></p>
                <div class="mt-2 text-xs text-gray-500">
                    Issues resolved by user
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Deployments</h3>
                    <i class="fas fa-map-marker-alt text-orange-600 text-xl"></i>
                </div>
                <p class="text-3xl font-bold text-gray-900"><?php echo $user['deployments_made']; ?></p>
                <div class="mt-2 text-xs text-gray-500">
                    <?php if ($user['last_deployment']): ?>
                        Last: <?php echo date('M j', strtotime($user['last_deployment'])); ?>
                    <?php else: ?>
                        No deployments yet
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User's Devices -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-microchip mr-2"></i>User's Devices (<?php echo count($userDevices); ?>)
            </h2>
            
            <?php if (!empty($userDevices)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logs</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($userDevices as $device): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($device['d_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($device['serial_number']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($device['device_type']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $device['status'] == 'active' ? 'bg-green-100 text-green-800' : 
                                                       ($device['status'] == 'error' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo ucfirst($device['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $device['log_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $device['last_activity'] ? date('M j, H:i', strtotime($device['last_activity'])) : 'Never'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="device_details.php?id=<?php echo $device['d_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <a href="devices.php?owner=<?php echo $user['user_id']; ?>" class="text-blue-600 hover:text-blue-800">
                        View all devices <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No devices registered yet</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Resolutions -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-check-circle mr-2"></i>Recent Issue Resolutions (<?php echo count($resolutions); ?>)
            </h2>
            
            <?php if (!empty($resolutions)): ?>
                <div class="space-y-4">
                    <?php foreach ($resolutions as $resolution): ?>
                        <div class="border-l-4 border-green-500 bg-gray-50 p-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="px-2 py-1 bg-<?php echo $resolution['log_type'] == 'error' ? 'red' : 'yellow'; ?>-100 text-<?php echo $resolution['log_type'] == 'error' ? 'red' : 'yellow'; ?>-800 text-xs rounded mr-2">
                                            <?php echo ucfirst($resolution['log_type']); ?>
                                        </span>
                                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($resolution['d_name']); ?></span>
                                        <span class="text-xs text-gray-500 ml-2">(<?php echo htmlspecialchars($resolution['device_type']); ?>)</span>
                                    </div>
                                    <p class="text-sm text-gray-700 mb-2"><?php echo htmlspecialchars($resolution['message']); ?></p>
                                    <?php if ($resolution['resolution_notes']): ?>
                                        <p class="text-sm text-green-700"><strong>Resolution:</strong> <?php echo htmlspecialchars($resolution['resolution_notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right text-xs text-gray-500 ml-4">
                                    <?php echo date('M j, Y H:i', strtotime($resolution['resolved_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No resolutions recorded yet</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Deployments -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-map-marker-alt mr-2"></i>Recent Deployments (<?php echo count($deployments); ?>)
            </h2>
            
            <?php if (!empty($deployments)): ?>
                <div class="space-y-4">
                    <?php foreach ($deployments as $deployment): ?>
                        <div class="border-l-4 border-blue-500 bg-gray-50 p-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($deployment['d_name']); ?></span>
                                        <span class="text-xs text-gray-500 ml-2">(<?php echo htmlspecialchars($deployment['device_type']); ?>)</span>
                                    </div>
                                    <p class="text-sm text-gray-700">
                                        <i class="fas fa-map-marker-alt text-gray-400"></i> 
                                        <?php echo htmlspecialchars($deployment['loc_name']); ?> - <?php echo htmlspecialchars($deployment['address']); ?>
                                    </p>
                                </div>
                                <div class="text-right text-xs text-gray-500 ml-4">
                                    <?php echo date('M j, Y H:i', strtotime($deployment['deployed_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No deployments recorded yet</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
