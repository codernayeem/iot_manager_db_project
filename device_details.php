<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

$deviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($deviceId <= 0) {
    header("Location: devices.php");
    exit;
}

// Handle POST requests
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    // Update device information
    if ($action === 'update_device') {
        $name = trim($_POST['device_name']);
        $status = $_POST['status'];
        
        if (!empty($name)) {
            try {
                $conn->beginTransaction();
                
                // Update device - trigger will automatically set updated_at
                $updateQuery = "UPDATE devices SET d_name = ?, status = ? WHERE d_id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->execute([$name, $status, $deviceId]);
                
                // Log the update
                $logQuery = "INSERT INTO device_logs (d_id, log_type, message, severity_level) 
                            VALUES (?, 'info', ?, 1)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->execute([$deviceId, "Device updated by " . $_SESSION['user_name']]);
                
                $conn->commit();
                $success = "Device updated successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error updating device: " . $e->getMessage();
            }
        }
    }
    
    // Delete device
    if ($action === 'delete_device') {
        try {
            $conn->beginTransaction();
            
            // Delete in correct order due to foreign key constraints
            // 1. Delete alerts (references device_logs)
            $conn->prepare("DELETE FROM alerts WHERE log_id IN (SELECT log_id FROM device_logs WHERE d_id = ?)")
                 ->execute([$deviceId]);
            
            // 2. Delete device logs
            $conn->prepare("DELETE FROM device_logs WHERE d_id = ?")->execute([$deviceId]);
            
            // 3. Delete deployments
            $conn->prepare("DELETE FROM deployments WHERE d_id = ?")->execute([$deviceId]);
            
            // 4. Finally delete device
            $conn->prepare("DELETE FROM devices WHERE d_id = ?")->execute([$deviceId]);
            
            $conn->commit();
            header("Location: devices.php?success=Device deleted successfully");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting device: " . $e->getMessage();
        }
    }
    
    // Add deployment
    if ($action === 'add_deployment') {
        $locationId = (int)$_POST['location_id'];
        
        if ($locationId > 0) {
            try {
                $conn->beginTransaction();
                
                // Check if already deployed to this location
                $checkQuery = "SELECT COUNT(*) FROM deployments WHERE d_id = ? AND loc_id = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->execute([$deviceId, $locationId]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception("Device is already deployed to this location.");
                }
                
                // Add deployment (composite PK: d_id, loc_id)
                $deployQuery = "INSERT INTO deployments (d_id, loc_id) VALUES (?, ?)";
                $deployStmt = $conn->prepare($deployQuery);
                $deployStmt->execute([$deviceId, $locationId]);
                
                // Log the deployment
                $logQuery = "INSERT INTO device_logs (d_id, log_type, message, severity_level) 
                            VALUES (?, 'info', ?, 1)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->execute([$deviceId, "Device deployed to new location by " . $_SESSION['user_name']]);
                
                $conn->commit();
                $success = "Device deployed successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
    
    // Remove deployment
    if ($action === 'remove_deployment') {
        $locationId = (int)$_POST['location_id'];
        
        if ($locationId > 0) {
            try {
                $conn->beginTransaction();
                
                // Delete deployment
                $deleteQuery = "DELETE FROM deployments WHERE d_id = ? AND loc_id = ?";
                $deleteStmt = $conn->prepare($deleteQuery);
                $deleteStmt->execute([$deviceId, $locationId]);
                
                // Log the removal
                $logQuery = "INSERT INTO device_logs (d_id, log_type, message, severity_level) 
                            VALUES (?, 'info', ?, 1)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->execute([$deviceId, "Device removed from location by " . $_SESSION['user_name']]);
                
                $conn->commit();
                $success = "Deployment removed successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error removing deployment: " . $e->getMessage();
            }
        }
    }
}

// Get device information using view
$deviceQuery = "
    SELECT 
        d.*,
        dt.t_name as device_type,
        dt.desc as type_desc,
        CONCAT(u.f_name, ' ', u.l_name) as owner_name,
        u.email as owner_email,
        u.user_id as owner_id,
        fn_get_device_health_score(d.d_id) as health_score,
        fn_get_alert_summary(d.d_id) as alert_summary
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    WHERE d.d_id = ?
";

$stmt = $conn->prepare($deviceQuery);
$stmt->execute([$deviceId]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: devices.php?error=Device not found");
    exit;
}

// Get deployment summary
$deploymentsQuery = "
    SELECT 
        dep.d_id,
        dep.loc_id,
        dep.deployed_at,
        l.loc_name,
        l.address,
        DATEDIFF(NOW(), dep.deployed_at) as days_deployed
    FROM deployments dep
    INNER JOIN locations l ON dep.loc_id = l.loc_id
    WHERE dep.d_id = ?
    ORDER BY dep.deployed_at DESC
";

$deploymentsStmt = $conn->prepare($deploymentsQuery);
$deploymentsStmt->execute([$deviceId]);
$deployments = $deploymentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get available locations (not currently deployed)
$availableLocationsQuery = "
    SELECT l.loc_id, l.loc_name, l.address 
    FROM locations l
    WHERE l.loc_id NOT IN (
        SELECT dep.loc_id 
        FROM deployments dep 
        WHERE dep.d_id = ?
    )
    ORDER BY l.loc_name
";
$availableStmt = $conn->prepare($availableLocationsQuery);
$availableStmt->execute([$deviceId]);
$availableLocations = $availableStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent logs
$logsQuery = "
    SELECT 
        dl.*,
        a.log_id as has_alert,
        a.status as alert_status,
        CONCAT(resolver.f_name, ' ', resolver.l_name) as resolver_name
    FROM device_logs dl
    LEFT JOIN alerts a ON dl.log_id = a.log_id
    LEFT JOIN users resolver ON dl.resolved_by = resolver.user_id
    WHERE dl.d_id = ?
    ORDER BY dl.log_time DESC
    LIMIT 20
";

$logsStmt = $conn->prepare($logsQuery);
$logsStmt->execute([$deviceId]);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_logs,
        SUM(CASE WHEN log_type = 'error' THEN 1 ELSE 0 END) as total_errors,
        SUM(CASE WHEN log_type = 'warning' THEN 1 ELSE 0 END) as total_warnings,
        SUM(CASE WHEN log_type = 'info' THEN 1 ELSE 0 END) as total_info,
        SUM(CASE WHEN resolved_by IS NULL AND log_type IN ('error', 'warning') THEN 1 ELSE 0 END) as unresolved
    FROM device_logs
    WHERE d_id = ?
";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute([$deviceId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$healthClass = $device['health_score'] >= 80 ? 'text-green-600' : ($device['health_score'] >= 60 ? 'text-yellow-600' : 'text-red-600');
$healthBgClass = $device['health_score'] >= 80 ? 'bg-green-100' : ($device['health_score'] >= 60 ? 'bg-yellow-100' : 'bg-red-100');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($device['d_name']); ?> - Device Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-info { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .status-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .status-error { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <div class="flex items-center mb-2">
                    <a href="devices.php" class="text-blue-600 hover:text-blue-800 mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-microchip mr-3"></i><?php echo htmlspecialchars($device['d_name']); ?>
                    </h1>
                    <span class="px-4 py-2 rounded-lg font-semibold ml-4 status-<?php echo $device['status']; ?>">
                        <?php echo ucfirst($device['status']); ?>
                    </span>
                </div>
                <p class="text-gray-600">
                    <?php echo htmlspecialchars($device['device_type']); ?> • 
                    Serial: <?php echo htmlspecialchars($device['serial_number']); ?>
                </p>
            </div>
            
            <div class="flex space-x-3">
                <?php if ($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com'): ?>
                    <button onclick="document.getElementById('delete-modal').classList.remove('hidden')" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-trash mr-2"></i>Delete Device
                    </button>
                <?php endif; ?>
                <a href="devices.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-list mr-2"></i>All Devices
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Health Score</p>
                        <p class="text-3xl font-bold <?php echo $healthClass; ?>">
                            <?php echo $device['health_score']; ?>
                        </p>
                    </div>
                    <div class="<?php echo $healthBgClass; ?> p-3 rounded-full">
                        <i class="fas fa-heartbeat text-2xl <?php echo $healthClass; ?>"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Logs</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_logs']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-list text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Errors / Warnings</p>
                        <p class="text-3xl font-bold text-red-600">
                            <?php echo $stats['total_errors']; ?> / <?php echo $stats['total_warnings']; ?>
                        </p>
                    </div>
                    <div class="bg-red-100 p-3 rounded-full">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Deployments</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo count($deployments); ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-map-marker-alt text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Device Info & Edit -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>Device Information
                </h3>
                
                <?php if ($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com'): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_device">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Device Name</label>
                            <input type="text" name="device_name" required
                                   value="<?php echo htmlspecialchars($device['d_name']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                <option value="info" <?php echo $device['status'] === 'info' ? 'selected' : ''; ?>>Info - Normal Operation</option>
                                <option value="warning" <?php echo $device['status'] === 'warning' ? 'selected' : ''; ?>>Warning - Needs Attention</option>
                                <option value="error" <?php echo $device['status'] === 'error' ? 'selected' : ''; ?>>Error - Critical Issue</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <p class="text-gray-800"><?php echo htmlspecialchars($device['device_type']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
                                <p class="text-gray-800 font-mono text-sm"><?php echo htmlspecialchars($device['serial_number']); ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
                                <p class="text-gray-800"><?php echo htmlspecialchars($device['owner_name']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Date</label>
                                <p class="text-gray-800">
                                    <?php echo $device['purchase_date'] ? date('M j, Y', strtotime($device['purchase_date'])) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-save mr-2"></i>Update Device
                        </button>
                    </form>
                <?php else: ?>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Type</label>
                                <p class="text-gray-800"><?php echo htmlspecialchars($device['device_type']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Serial Number</label>
                                <p class="text-gray-800 font-mono"><?php echo htmlspecialchars($device['serial_number']); ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Owner</label>
                                <p class="text-gray-800"><?php echo htmlspecialchars($device['owner_name']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Purchase Date</label>
                                <p class="text-gray-800">
                                    <?php echo $device['purchase_date'] ? date('M j, Y', strtotime($device['purchase_date'])) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-center">
                            <i class="fas fa-lock mr-2 text-yellow-600"></i>
                            <span class="text-yellow-800">Only the owner can edit this device</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Deployment Management -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>Deployments
                    </h3>
                    <?php if (($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com') && !empty($availableLocations)): ?>
                        <button onclick="document.getElementById('deploy-form').classList.toggle('hidden')" 
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition">
                            <i class="fas fa-plus mr-1"></i>Deploy
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Add Deployment Form -->
                <?php if (($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com') && !empty($availableLocations)): ?>
                    <form method="POST" id="deploy-form" class="hidden mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <input type="hidden" name="action" value="add_deployment">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Location</label>
                        <div class="flex space-x-2">
                            <select name="location_id" required class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500">
                                <option value="">Choose...</option>
                                <?php foreach ($availableLocations as $location): ?>
                                    <option value="<?php echo $location['loc_id']; ?>">
                                        <?php echo htmlspecialchars($location['loc_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-check"></i>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <!-- Deployments List -->
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php if (empty($deployments)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-map-marker-alt text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600">No deployments yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($deployments as $deployment): ?>
                            <div class="border border-green-200 bg-green-50 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800">
                                            <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                                            <?php echo htmlspecialchars($deployment['loc_name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($deployment['address']); ?></p>
                                    </div>
                                    <?php if ($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com'): ?>
                                        <form method="POST" onsubmit="return confirm('Remove this deployment?')">
                                            <input type="hidden" name="action" value="remove_deployment">
                                            <input type="hidden" name="location_id" value="<?php echo $deployment['loc_id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Deployed: <?php echo date('M j, Y', strtotime($deployment['deployed_at'])); ?>
                                    (<?php echo $deployment['days_deployed']; ?> days)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Logs -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-list-alt mr-2 text-purple-600"></i>Recent Activity Logs
            </h3>
            
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-list-alt text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600">No logs yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="border rounded-lg p-4 <?php 
                            echo $log['log_type'] === 'error' ? 'bg-red-50 border-red-200' : 
                                ($log['log_type'] === 'warning' ? 'bg-yellow-50 border-yellow-200' : 'bg-blue-50 border-blue-200'); 
                        ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 rounded text-xs font-medium <?php 
                                        echo $log['log_type'] === 'error' ? 'bg-red-100 text-red-800' : 
                                            ($log['log_type'] === 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); 
                                    ?>">
                                        <?php echo strtoupper($log['log_type']); ?>
                                    </span>
                                    <span class="text-sm font-medium text-gray-600">Severity: <?php echo $log['severity_level']; ?></span>
                                    <?php if (!empty($log['has_alert'])): ?>
                                        <span class="px-2 py-1 rounded text-xs font-medium <?php 
                                            echo $log['alert_status'] === 'active' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-600'; 
                                        ?>">
                                            <i class="fas fa-bell mr-1"></i><?php echo strtoupper($log['alert_status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-sm text-gray-600">
                                    <?php echo date('M j, Y H:i', strtotime($log['log_time'])); ?>
                                </span>
                            </div>
                            <p class="text-gray-800 mb-2"><?php echo htmlspecialchars($log['message']); ?></p>
                            <?php if ($log['resolved_by']): ?>
                                <div class="text-sm text-green-700">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Resolved by <?php echo htmlspecialchars($log['resolver_name']); ?>
                                    on <?php echo date('M j, Y H:i', strtotime($log['resolved_at'])); ?>
                                    <?php if ($log['resolution_notes']): ?>
                                        <br><span class="ml-5"><?php echo htmlspecialchars($log['resolution_notes']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (count($logs) >= 20): ?>
                <div class="mt-4 text-center">
                    <a href="device_logs.php?device=<?php echo $device['d_id']; ?>" 
                       class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-external-link-alt mr-1"></i>View All Logs
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md">
            <h3 class="text-2xl font-bold text-red-600 mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>Delete Device?
            </h3>
            <p class="text-gray-700 mb-6">
                Are you sure you want to delete <strong><?php echo htmlspecialchars($device['d_name']); ?></strong>? 
                This will permanently delete:
            </p>
            <ul class="list-disc list-inside text-gray-700 mb-6 space-y-1">
                <li>The device record</li>
                <li><?php echo $stats['total_logs']; ?> log entries</li>
                <li><?php echo count($deployments); ?> deployments</li>
                <li>All associated alerts</li>
            </ul>
            <p class="text-red-600 font-bold mb-6">This action cannot be undone!</p>
            <div class="flex space-x-3">
                <button onclick="document.getElementById('delete-modal').classList.add('hidden')" 
                        class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition">
                    Cancel
                </button>
                <form method="POST" class="flex-1">
                    <input type="hidden" name="action" value="delete_device">
                    <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        document.getElementById('delete-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
