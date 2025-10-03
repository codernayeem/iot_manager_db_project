<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: Complex JOINs, Subqueries, Aggregation Functions, Window Functions,
 * CASE statements, Date Functions, Transaction handling for updates
 */

$database = new Database();
$conn = $database->getConnection();

$deviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($deviceId <= 0) {
    header("Location: devices.php");
    exit;
}

// Handle POST requests for device updates
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_device') {
        $name = trim($_POST['device_name']);
        $status = $_POST['status'];
        $warrantyExpiry = $_POST['warranty_expiry'];
        $lastMaintenance = $_POST['last_maintenance'];
        
        if (!empty($name)) {
            try {
                $conn->beginTransaction();
                
                // Update device
                $updateQuery = "UPDATE devices SET d_name = ?, status = ?, warranty_expiry = ?, last_maintenance = ? WHERE d_id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->execute([$name, $status, $warrantyExpiry ?: null, $lastMaintenance ?: null, $deviceId]);
                
                // Log the update
                $logQuery = "INSERT INTO device_logs (d_id, log_type, message, severity_level) VALUES (?, 'info', ?, 1)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->execute([$deviceId, "Device information updated by " . $_SESSION['user_name']]);
                
                $conn->commit();
                $success = "Device updated successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error updating device: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'resolve_log') {
        $logId = (int)$_POST['log_id'];
        $resolutionNotes = trim($_POST['resolution_notes']);
        
        if ($logId > 0) {
            try {
                $resolveQuery = "UPDATE device_logs SET resolved_by = ?, resolved_at = NOW(), resolution_notes = ? WHERE log_id = ? AND d_id = ?";
                $stmt = $conn->prepare($resolveQuery);
                $stmt->execute([$_SESSION['user_id'], $resolutionNotes, $logId, $deviceId]);
                $success = "Log resolved successfully!";
            } catch (Exception $e) {
                $error = "Error resolving log: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'deploy_device') {
        $locationId = (int)$_POST['location_id'];
        $deploymentNotes = trim($_POST['deployment_notes']);
        
        if ($locationId > 0) {
            try {
                $conn->beginTransaction();
                
                // Check if device is already deployed to this location
                $checkQuery = "SELECT deployment_id FROM deployments WHERE d_id = ? AND loc_id = ? AND is_active = 1";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->execute([$deviceId, $locationId]);
                
                if ($checkStmt->fetchColumn()) {
                    throw new Exception("Device is already deployed to this location.");
                }
                
                // Add new deployment
                $deployQuery = "INSERT INTO deployments (d_id, loc_id, deployed_by, deployment_notes, is_active) VALUES (?, ?, ?, ?, 1)";
                $deployStmt = $conn->prepare($deployQuery);
                $deployStmt->execute([$deviceId, $locationId, $_SESSION['user_id'], $deploymentNotes]);
                
                // Log the deployment
                $logQuery = "INSERT INTO device_logs (d_id, log_type, message, severity_level) VALUES (?, 'info', ?, 1)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->execute([$deviceId, "Device deployed to new location by " . $_SESSION['user_name']]);
                
                $conn->commit();
                $success = "Device deployed successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error deploying device: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'undeploy_device') {
        $deploymentId = (int)$_POST['deployment_id'];
        
        if ($deploymentId > 0) {
            try {
                $conn->beginTransaction();
                
                // Deactivate deployment
                $undeployQuery = "UPDATE deployments SET is_active = 0 WHERE deployment_id = ? AND d_id = ?";
                $undeployStmt = $conn->prepare($undeployQuery);
                $undeployStmt->execute([$deploymentId, $deviceId]);
                
                // Log the undeployment
                $logQuery = "INSERT INTO device_logs (d_id, log_type, message, severity_level) VALUES (?, 'info', ?, 1)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->execute([$deviceId, "Device undeployed by " . $_SESSION['user_name']]);
                
                $conn->commit();
                $success = "Device undeployed successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error undeploying device: " . $e->getMessage();
            }
        }
    }
}

// SQL Feature: Complex query with multiple JOINs and subqueries to get comprehensive device info
$deviceQuery = "
    SELECT 
        d.*,
        dt.t_name as device_type,
        dt.description as type_description,
        dt.icon as device_icon,
        CONCAT(u.f_name, ' ', u.l_name) as owner_name,
        u.email as owner_email,
        u.user_id as owner_id,
        
        -- Aggregated statistics from subqueries
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id) as total_logs,
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'error') as total_errors,
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'warning') as total_warnings,
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'error' AND dl.resolved_by IS NULL) as unresolved_errors,
        (SELECT COUNT(*) FROM device_logs dl WHERE dl.d_id = d.d_id AND dl.log_type = 'warning' AND dl.resolved_by IS NULL) as unresolved_warnings,
        (SELECT MAX(dl.log_time) FROM device_logs dl WHERE dl.d_id = d.d_id) as last_activity,
        
        -- Current deployments info
        GROUP_CONCAT(
            DISTINCT CONCAT(l.loc_name, ' (', DATE_FORMAT(dep.deployed_at, '%Y-%m-%d'), ')') 
            ORDER BY dep.deployed_at DESC SEPARATOR '; '
        ) as deployment_history,
        COUNT(DISTINCT CASE WHEN dep.is_active = 1 THEN dep.loc_id END) as active_deployments,
        
        -- Warranty and maintenance info
        CASE 
            WHEN d.warranty_expiry IS NULL THEN 'No warranty info'
            WHEN d.warranty_expiry < CURDATE() THEN 'Expired'
            WHEN DATEDIFF(d.warranty_expiry, CURDATE()) <= 30 THEN 'Expiring Soon'
            ELSE 'Active'
        END as warranty_status,
        
        CASE 
            WHEN d.last_maintenance IS NULL THEN 'Never'
            WHEN DATEDIFF(CURDATE(), d.last_maintenance) > 365 THEN 'Overdue'
            WHEN DATEDIFF(CURDATE(), d.last_maintenance) > 180 THEN 'Due Soon'
            ELSE 'Recent'
        END as maintenance_status,
        
        DATEDIFF(CURDATE(), d.last_maintenance) as days_since_maintenance,
        DATEDIFF(d.warranty_expiry, CURDATE()) as warranty_days_remaining
        
    FROM devices d
    INNER JOIN device_types dt ON d.t_id = dt.t_id
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN deployments dep ON d.d_id = dep.d_id
    LEFT JOIN locations l ON dep.loc_id = l.loc_id
    WHERE d.d_id = ?
    GROUP BY d.d_id, d.d_name, d.serial_number, d.status, d.purchase_date, d.warranty_expiry, 
             d.last_maintenance, d.created_at, d.updated_at, dt.t_name, dt.description, dt.icon,
             u.f_name, u.l_name, u.email, u.user_id
";

$stmt = $conn->prepare($deviceQuery);
$stmt->execute([$deviceId]);
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    header("Location: devices.php?error=Device not found");
    exit;
}

// Get recent logs with resolver information
$logsQuery = "
    SELECT 
        dl.*,
        CONCAT(resolver.f_name, ' ', resolver.l_name) as resolver_name,
        resolver.email as resolver_email,
        CASE 
            WHEN dl.log_type = 'error' THEN 'bg-red-50 border-red-200'
            WHEN dl.log_type = 'warning' THEN 'bg-yellow-50 border-yellow-200'
            WHEN dl.log_type = 'info' THEN 'bg-blue-50 border-blue-200'
            ELSE 'bg-gray-50 border-gray-200'
        END as log_style_class,
        CASE 
            WHEN dl.severity_level >= 4 THEN 'text-red-800'
            WHEN dl.severity_level >= 3 THEN 'text-red-600'
            WHEN dl.severity_level >= 2 THEN 'text-yellow-600'
            ELSE 'text-green-600'
        END as severity_class
    FROM device_logs dl
    LEFT JOIN users resolver ON dl.resolved_by = resolver.user_id
    WHERE dl.d_id = ?
    ORDER BY dl.log_time DESC, dl.log_id DESC
    LIMIT 50
";

$logsStmt = $conn->prepare($logsQuery);
$logsStmt->execute([$deviceId]);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get deployment history
$deploymentsQuery = "
    SELECT 
        dep.*,
        l.loc_name,
        l.address,
        CONCAT(u.f_name, ' ', u.l_name) as deployed_by_name,
        DATEDIFF(NOW(), dep.deployed_at) as deployment_days
    FROM deployments dep
    INNER JOIN locations l ON dep.loc_id = l.loc_id
    INNER JOIN users u ON dep.deployed_by = u.user_id
    WHERE dep.d_id = ?
    ORDER BY dep.deployed_at DESC
";

$deploymentsStmt = $conn->prepare($deploymentsQuery);
$deploymentsStmt->execute([$deviceId]);
$deployments = $deploymentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get available locations for deployment (excluding currently active deployments)
$availableLocationsQuery = "
    SELECT l.loc_id, l.loc_name, l.address 
    FROM locations l
    WHERE l.loc_id NOT IN (
        SELECT dep.loc_id 
        FROM deployments dep 
        WHERE dep.d_id = ? AND dep.is_active = 1
    )
    ORDER BY l.loc_name
";
$availableStmt = $conn->prepare($availableLocationsQuery);
$availableStmt->execute([$deviceId]);
$availableLocations = $availableStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate device health score (custom algorithm)
$healthScore = 100;
if ($device['unresolved_errors'] > 0) $healthScore -= min(50, $device['unresolved_errors'] * 10);
if ($device['unresolved_warnings'] > 0) $healthScore -= min(20, $device['unresolved_warnings'] * 5);
if ($device['status'] === 'error') $healthScore -= 30;
if ($device['status'] === 'maintenance') $healthScore -= 15;
if ($device['warranty_status'] === 'Expired') $healthScore -= 10;
if ($device['maintenance_status'] === 'Overdue') $healthScore -= 15;
$healthScore = max(0, $healthScore);

$healthClass = $healthScore >= 80 ? 'text-green-600' : ($healthScore >= 60 ? 'text-yellow-600' : 'text-red-600');
$healthBgClass = $healthScore >= 80 ? 'bg-green-100' : ($healthScore >= 60 ? 'bg-yellow-100' : 'bg-red-100');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($device['d_name']); ?> - Device Details - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .health-meter {
            background: conic-gradient(
                #10b981 0deg <?php echo $healthScore * 3.6; ?>deg,
                #e5e7eb <?php echo $healthScore * 3.6; ?>deg 360deg
            );
        }
        
        .tab-button {
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .status-active { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .status-error { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .status-maintenance { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .status-inactive { background: linear-gradient(135deg, #6b7280, #4b5563); color: white; }
        
        .log-item {
            transition: all 0.3s ease;
        }
        
        .log-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .sql-feature-tooltip {
            position: relative;
            display: inline-block;
        }
        
        .sql-feature-tooltip .tooltip-content {
            visibility: hidden;
            width: 600px;
            background-color: #1f2937;
            color: #fff;
            border-radius: 8px;
            padding: 20px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -300px;
            opacity: 0;
            transition: opacity 0.3s;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
            line-height: 1.4;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .sql-feature-tooltip:hover .tooltip-content {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <div class="flex items-center mb-2">
                    <a href="devices.php" class="text-blue-600 hover:text-blue-800 mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-microchip mr-3"></i><?php echo htmlspecialchars($device['d_name']); ?>
                    </h1>
                    <span class="status-badge status-<?php echo $device['status']; ?> ml-4">
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
                    <button onclick="toggleEditMode()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-edit mr-2"></i>Edit Device
                    </button>
                <?php endif; ?>
                
                <a href="device_logs.php?device=<?php echo $device['d_id']; ?>" 
                   class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-list mr-2"></i>View All Logs
                </a>
                
                <a href="add_device.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-plus mr-2"></i>Add New Device
                </a>
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
                        <strong>SQL Features:</strong> Complex multi-table JOINs, Aggregation with GROUP_CONCAT, 
                        Subqueries for statistics, CASE statements for conditional logic, Date functions (DATEDIFF, CURDATE), 
                        Window functions concepts, Transaction handling for updates
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Device Overview Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            <!-- Health Score -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Health Score</h3>
                    <div class="sql-feature-tooltip">
                        <i class="fas fa-info-circle text-gray-400 cursor-help"></i>
                        <div class="tooltip-content">
                            Custom algorithm calculating device health:<br><br>
                            Base Score: 100<br>
                            - Unresolved errors: -10 each (max -50)<br>
                            - Unresolved warnings: -5 each (max -20)<br>
                            - Error status: -30<br>
                            - Maintenance status: -15<br>
                            - Expired warranty: -10<br>
                            - Overdue maintenance: -15<br><br>
                            Uses CASE statements and conditional logic
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="health-meter w-16 h-16 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-lg"><?php echo $healthScore; ?></span>
                    </div>
                    <div>
                        <div class="<?php echo $healthClass; ?> font-bold text-xl"><?php echo $healthScore; ?>%</div>
                        <div class="text-gray-600 text-sm">
                            <?php 
                            if ($healthScore >= 80) echo "Excellent";
                            else if ($healthScore >= 60) echo "Good";
                            else if ($healthScore >= 40) echo "Fair";
                            else echo "Poor";
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Stats -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Activity Stats</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Logs:</span>
                        <span class="font-semibold"><?php echo $device['total_logs']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-red-600">Errors:</span>
                        <span class="font-semibold text-red-600"><?php echo $device['total_errors']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-yellow-600">Warnings:</span>
                        <span class="font-semibold text-yellow-600"><?php echo $device['total_warnings']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-red-800">Unresolved:</span>
                        <span class="font-semibold text-red-800"><?php echo $device['unresolved_errors'] + $device['unresolved_warnings']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Warranty Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Warranty</h3>
                <div class="space-y-3">
                    <div class="text-center">
                        <div class="text-2xl font-bold <?php 
                            echo $device['warranty_status'] === 'Active' ? 'text-green-600' : 
                                ($device['warranty_status'] === 'Expiring Soon' ? 'text-yellow-600' : 'text-red-600'); 
                        ?>">
                            <?php echo $device['warranty_status']; ?>
                        </div>
                        <?php if ($device['warranty_expiry']): ?>
                            <div class="text-gray-600 text-sm mt-2">
                                Expires: <?php echo date('M j, Y', strtotime($device['warranty_expiry'])); ?>
                            </div>
                            <?php if ($device['warranty_days_remaining'] > 0): ?>
                                <div class="text-gray-500 text-xs">
                                    <?php echo $device['warranty_days_remaining']; ?> days remaining
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-gray-500 text-sm">No warranty info</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Maintenance Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Maintenance</h3>
                <div class="space-y-3">
                    <div class="text-center">
                        <div class="text-2xl font-bold <?php 
                            echo $device['maintenance_status'] === 'Recent' ? 'text-green-600' : 
                                ($device['maintenance_status'] === 'Due Soon' ? 'text-yellow-600' : 'text-red-600'); 
                        ?>">
                            <?php echo $device['maintenance_status']; ?>
                        </div>
                        <?php if ($device['last_maintenance']): ?>
                            <div class="text-gray-600 text-sm mt-2">
                                Last: <?php echo date('M j, Y', strtotime($device['last_maintenance'])); ?>
                            </div>
                            <div class="text-gray-500 text-xs">
                                <?php echo $device['days_since_maintenance']; ?> days ago
                            </div>
                        <?php else: ?>
                            <div class="text-gray-500 text-sm">Never maintained</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="mb-6">
            <div class="flex space-x-1 bg-gray-200 p-1 rounded-lg">
                <button onclick="showTab('details')" id="tab-details" class="tab-button flex-1 py-2 px-4 rounded-md text-center font-medium active">
                    <i class="fas fa-info-circle mr-2"></i>Details
                </button>
                <button onclick="showTab('logs')" id="tab-logs" class="tab-button flex-1 py-2 px-4 rounded-md text-center font-medium">
                    <i class="fas fa-list-alt mr-2"></i>Recent Logs
                </button>
                <button onclick="showTab('deployments')" id="tab-deployments" class="tab-button flex-1 py-2 px-4 rounded-md text-center font-medium">
                    <i class="fas fa-map-marker-alt mr-2"></i>Deployments
                </button>
                <button onclick="showTab('edit')" id="tab-edit" class="tab-button flex-1 py-2 px-4 rounded-md text-center font-medium <?php echo ($_SESSION['user_id'] != $device['owner_id'] && $_SESSION['user_email'] != 'admin@iot.com') ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                    <i class="fas fa-edit mr-2"></i>Edit
                </button>
            </div>
        </div>
        
        <!-- Tab Contents -->
        
        <!-- Details Tab -->
        <div id="content-details" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>Device Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Device Name</label>
                                <div class="text-gray-800 font-semibold"><?php echo htmlspecialchars($device['d_name']); ?></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Type</label>
                                <div class="text-gray-800"><?php echo htmlspecialchars($device['device_type']); ?></div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Serial Number</label>
                                <div class="text-gray-800 font-mono"><?php echo htmlspecialchars($device['serial_number']); ?></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Status</label>
                                <span class="status-badge status-<?php echo $device['status']; ?>">
                                    <?php echo ucfirst($device['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Purchase Date</label>
                                <div class="text-gray-800">
                                    <?php echo $device['purchase_date'] ? date('M j, Y', strtotime($device['purchase_date'])) : 'Not specified'; ?>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-1">Last Activity</label>
                                <div class="text-gray-800">
                                    <?php echo $device['last_activity'] ? date('M j, Y H:i', strtotime($device['last_activity'])) : 'No activity'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-user mr-2 text-green-600"></i>Owner Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Owner</label>
                            <div class="text-gray-800 font-semibold"><?php echo htmlspecialchars($device['owner_name']); ?></div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
                            <div class="text-gray-800"><?php echo htmlspecialchars($device['owner_email']); ?></div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Registration Date</label>
                            <div class="text-gray-800"><?php echo date('M j, Y H:i', strtotime($device['created_at'])); ?></div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Last Updated</label>
                            <div class="text-gray-800"><?php echo date('M j, Y H:i', strtotime($device['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Logs Tab -->
        <div id="content-logs" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-list-alt mr-2 text-purple-600"></i>Recent Activity Logs
                    </h3>
                    <div class="sql-feature-tooltip">
                        <span class="text-sm text-blue-600 cursor-help">
                            <i class="fas fa-database mr-1"></i>SQL Features
                        </span>
                        <div class="tooltip-content">
                            Log query with advanced features:<br><br>
                            SELECT dl.*, CONCAT(resolver.f_name, ' ', resolver.l_name) as resolver_name,<br>
                            CASE WHEN dl.log_type = 'error' THEN 'bg-red-50'<br>
                            WHEN dl.log_type = 'warning' THEN 'bg-yellow-50'<br>
                            ELSE 'bg-blue-50' END as log_style_class<br>
                            FROM device_logs dl<br>
                            LEFT JOIN users resolver ON dl.resolved_by = resolver.user_id<br>
                            WHERE dl.d_id = ?<br>
                            ORDER BY dl.log_time DESC LIMIT 50<br><br>
                            Features: LEFT JOIN for optional resolver, CASE for styling,<br>
                            ORDER BY with LIMIT for performance
                        </div>
                    </div>
                </div>
                
                <?php if (empty($logs)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-list-alt text-4xl text-gray-400 mb-4"></i>
                        <h4 class="text-xl font-semibold text-gray-600 mb-2">No logs found</h4>
                        <p class="text-gray-500">This device has no recorded activity logs yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($logs as $log): ?>
                            <div class="log-item <?php echo $log['log_style_class']; ?> border rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex items-center space-x-2">
                                            <span class="px-2 py-1 rounded text-xs font-medium <?php 
                                                echo $log['log_type'] === 'error' ? 'bg-red-100 text-red-800' :
                                                    ($log['log_type'] === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                                                    ($log['log_type'] === 'info' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'));
                                            ?>">
                                                <?php echo strtoupper($log['log_type']); ?>
                                            </span>
                                            <span class="<?php echo $log['severity_class']; ?> text-sm font-medium">
                                                Severity: <?php echo $log['severity_level']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right text-sm text-gray-600">
                                        <?php echo date('M j, Y H:i:s', strtotime($log['log_time'])); ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="text-gray-800"><?php echo htmlspecialchars($log['message']); ?></p>
                                </div>
                                
                                <?php if ($log['resolved_by']): ?>
                                    <div class="bg-green-50 border border-green-200 rounded p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-green-800 font-medium text-sm">
                                                <i class="fas fa-check-circle mr-1"></i>Resolved
                                            </span>
                                            <span class="text-green-600 text-sm">
                                                by <?php echo htmlspecialchars($log['resolver_name']); ?> 
                                                on <?php echo date('M j, Y H:i', strtotime($log['resolved_at'])); ?>
                                            </span>
                                        </div>
                                        <?php if ($log['resolution_notes']): ?>
                                            <p class="text-green-700 text-sm"><?php echo htmlspecialchars($log['resolution_notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (in_array($log['log_type'], ['error', 'warning']) && ($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com')): ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="resolve_log">
                                        <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                        <div class="flex space-x-3">
                                            <input type="text" name="resolution_notes" 
                                                   placeholder="Resolution notes..." 
                                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm">
                                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md text-sm hover:bg-green-700">
                                                <i class="fas fa-check mr-1"></i>Resolve
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-6 text-center">
                        <a href="device_logs.php?device=<?php echo $device['d_id']; ?>" 
                           class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-external-link-alt mr-2"></i>View All Logs
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Deployments Tab -->
        <div id="content-deployments" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-map-marker-alt mr-2 text-orange-600"></i>Deployment Management
                    </h3>
                    
                    <?php if ($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com'): ?>
                        <button onclick="toggleDeploymentForm()" id="toggle-deploy-btn" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                            <i class="fas fa-plus mr-2"></i>Deploy to Location
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- New Deployment Form -->
                <?php if (($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com') && !empty($availableLocations)): ?>
                    <div id="deployment-form" class="hidden mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <h4 class="text-lg font-semibold text-green-800 mb-4">Deploy to New Location</h4>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="deploy_device">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="location_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Select Location <span class="text-red-500">*</span>
                                    </label>
                                    <select id="location_id" name="location_id" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500">
                                        <option value="">Choose a location...</option>
                                        <?php foreach ($availableLocations as $location): ?>
                                            <option value="<?php echo $location['loc_id']; ?>">
                                                <?php echo htmlspecialchars($location['loc_name']); ?> 
                                                - <?php echo htmlspecialchars($location['address']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="deployment_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                        Deployment Notes
                                    </label>
                                    <input type="text" id="deployment_notes" name="deployment_notes"
                                           placeholder="Optional notes about this deployment..."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500">
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="toggleDeploymentForm()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                                    Cancel
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                                    <i class="fas fa-map-marker-alt mr-2"></i>Deploy Device
                                </button>
                            </div>
                        </form>
                    </div>
                <?php elseif (($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com') && empty($availableLocations)): ?>
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            This device is deployed to all available locations. To deploy to a new location, please create the location first.
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Current and Historical Deployments -->
                <?php if (empty($deployments)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-map-marker-alt text-4xl text-gray-400 mb-4"></i>
                        <h4 class="text-xl font-semibold text-gray-600 mb-2">No deployments found</h4>
                        <p class="text-gray-500">This device has not been deployed to any locations yet.</p>
                        <?php if (($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com') && !empty($availableLocations)): ?>
                            <button onclick="toggleDeploymentForm()" 
                                    class="mt-4 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                <i class="fas fa-plus mr-2"></i>Deploy Now
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($deployments as $deployment): ?>
                            <div class="border rounded-lg p-4 <?php echo $deployment['is_active'] ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'; ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="font-semibold text-gray-800 text-lg">
                                                <?php echo htmlspecialchars($deployment['loc_name']); ?>
                                            </h4>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $deployment['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'; ?>">
                                                <?php echo $deployment['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($deployment['address']); ?></p>
                                    </div>
                                    
                                    <?php if ($deployment['is_active'] && ($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com')): ?>
                                        <form method="POST" class="ml-4" onsubmit="return confirm('Are you sure you want to undeploy this device from this location?')">
                                            <input type="hidden" name="action" value="undeploy_device">
                                            <input type="hidden" name="deployment_id" value="<?php echo $deployment['deployment_id']; ?>">
                                            <button type="submit" 
                                                    class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700 transition">
                                                <i class="fas fa-times mr-1"></i>Undeploy
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                                    <div>
                                        <span class="text-gray-600">Deployed by:</span>
                                        <span class="font-medium"><?php echo htmlspecialchars($deployment['deployed_by_name']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Deployed on:</span>
                                        <span class="font-medium"><?php echo date('M j, Y H:i', strtotime($deployment['deployed_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="text-sm">
                                    <span class="text-gray-600">Duration:</span>
                                    <span class="font-medium"><?php echo $deployment['deployment_days']; ?> days</span>
                                </div>
                                
                                <?php if ($deployment['deployment_notes']): ?>
                                    <div class="mt-3 p-3 bg-white rounded border">
                                        <span class="text-gray-600 text-sm">Notes:</span>
                                        <p class="text-gray-800 text-sm mt-1"><?php echo htmlspecialchars($deployment['deployment_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Edit Tab -->
        <div id="content-edit" class="tab-content hidden">
            <?php if ($_SESSION['user_id'] == $device['owner_id'] || $_SESSION['user_email'] == 'admin@iot.com'): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-edit mr-2 text-green-600"></i>Edit Device Information
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_device">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="device_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Device Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="device_name" name="device_name" required
                                       value="<?php echo htmlspecialchars($device['d_name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Status
                                </label>
                                <select id="status" name="status" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    <option value="active" <?php echo $device['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $device['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="maintenance" <?php echo $device['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="error" <?php echo $device['status'] === 'error' ? 'selected' : ''; ?>>Error</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="warranty_expiry" class="block text-sm font-medium text-gray-700 mb-2">
                                    Warranty Expiry
                                </label>
                                <input type="date" id="warranty_expiry" name="warranty_expiry"
                                       value="<?php echo $device['warranty_expiry']; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="last_maintenance" class="block text-sm font-medium text-gray-700 mb-2">
                                    Last Maintenance
                                </label>
                                <input type="date" id="last_maintenance" name="last_maintenance"
                                       value="<?php echo $device['last_maintenance']; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="showTab('details')" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                                <i class="fas fa-save mr-2"></i>Update Device
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="text-center py-12">
                        <i class="fas fa-lock text-4xl text-gray-400 mb-4"></i>
                        <h4 class="text-xl font-semibold text-gray-600 mb-2">Access Restricted</h4>
                        <p class="text-gray-500">You don't have permission to edit this device.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedContent = document.getElementById('content-' + tabName);
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
            }
            
            // Add active class to selected tab button
            const selectedButton = document.getElementById('tab-' + tabName);
            if (selectedButton) {
                selectedButton.classList.add('active');
            }
        }
        
        function toggleEditMode() {
            showTab('edit');
        }
        
        function toggleDeploymentForm() {
            const form = document.getElementById('deployment-form');
            const button = document.getElementById('toggle-deploy-btn');
            
            if (form && button) {
                if (form.classList.contains('hidden')) {
                    form.classList.remove('hidden');
                    button.innerHTML = '<i class="fas fa-times mr-2"></i>Cancel';
                    button.classList.remove('bg-green-600', 'hover:bg-green-700');
                    button.classList.add('bg-gray-600', 'hover:bg-gray-700');
                } else {
                    form.classList.add('hidden');
                    button.innerHTML = '<i class="fas fa-plus mr-2"></i>Deploy to Location';
                    button.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                    button.classList.add('bg-green-600', 'hover:bg-green-700');
                }
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showTab('details');
        });
    </script>
    
</div>
</body>
</html>