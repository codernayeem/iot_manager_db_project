<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: UPDATE with JOINs, Transaction handling, SELECT with JOINs for validation,
 * Conditional logic, Data validation, Foreign key constraints
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

// Get device information with owner details
$deviceQuery = "
    SELECT 
        d.*,
        dt.t_name as device_type,
        dt.t_id as type_id,
        CONCAT(u.f_name, ' ', u.l_name) as owner_name,
        u.user_id as owner_id
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

// Check if user has permission to edit
if ($_SESSION['user_id'] != $device['owner_id'] && $_SESSION['user_email'] != 'admin@iot.com') {
    header("Location: device_details.php?id=$deviceId&error=Access denied");
    exit;
}

// Handle form submission
if ($_POST) {
    $deviceName = trim($_POST['device_name']);
    $deviceType = (int)$_POST['device_type'];
    $status = $_POST['status'];
    $purchaseDate = $_POST['purchase_date'];
    
    if (!empty($deviceName) && $deviceType > 0) {
        try {
            // SQL Feature: Transaction with multiple operations
            $conn->beginTransaction();
            
            // Update device with all fields
            // TRIGGER: trg_device_updated_at will automatically fire BEFORE UPDATE to set updated_at = NOW()
            // Note: We're also manually setting updated_at here, but the trigger ensures it's always updated
            $updateQuery = "
                UPDATE devices 
                SET d_name = ?, 
                    t_id = ?, 
                    status = ?, 
                    purchase_date = ?, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE d_id = ?
            ";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([
                $deviceName, 
                $deviceType, 
                $status, 
                $purchaseDate ?: null,
                $deviceId
            ]);
            
            // Log the update
            $logQuery = "
                INSERT INTO device_logs (d_id, log_type, message, severity_level) 
                VALUES (?, 'info', ?, 1)
            ";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->execute([
                $deviceId, 
                "Device updated by " . $_SESSION['user_name'] . " - Name: $deviceName, Status: $status"
            ]);
            
            $conn->commit();
            header("Location: device_details.php?id=$deviceId&success=Device updated successfully");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating device: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Get device types for dropdown
$typesQuery = "SELECT t_id, t_name FROM device_types ORDER BY t_name";
$typesStmt = $conn->prepare($typesQuery);
$typesStmt->execute();
$deviceTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get locations for potential deployment updates
$locationsQuery = "SELECT loc_id, loc_name, address FROM locations ORDER BY loc_name";
$locationsStmt = $conn->prepare($locationsQuery);
$locationsStmt->execute();
$locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current deployments
$currentDeploymentsQuery = "
    SELECT dep.*, l.loc_name 
    FROM deployments dep
    INNER JOIN locations l ON dep.loc_id = l.loc_id
    WHERE dep.d_id = ? AND dep.is_active = 1
";
$currentStmt = $conn->prepare($currentDeploymentsQuery);
$currentStmt->execute([$deviceId]);
$currentDeployments = $currentStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo htmlspecialchars($device['d_name']); ?> - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-section {
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .status-preview {
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .status-active { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .status-error { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .status-maintenance { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .status-inactive { background: linear-gradient(135deg, #6b7280, #4b5563); color: white; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <div class="flex items-center mb-2">
                    <a href="device_details.php?id=<?php echo $deviceId; ?>" class="text-blue-600 hover:text-blue-800 mr-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-edit mr-3"></i>Edit Device
                    </h1>
                </div>
                <p class="text-gray-600">
                    Editing: <?php echo htmlspecialchars($device['d_name']); ?> 
                    (<?php echo htmlspecialchars($device['device_type']); ?>)
                </p>
            </div>
            
            <div class="flex space-x-3">
                <a href="device_details.php?id=<?php echo $deviceId; ?>" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-eye mr-2"></i>View Details
                </a>
                
                <a href="devices.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-list mr-2"></i>All Devices
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
                        <strong>SQL Features:</strong> UPDATE with multiple fields, Transaction handling (BEGIN/COMMIT/ROLLBACK), 
                        INSERT for logging, SELECT with JOINs for validation, Foreign key constraints, 
                        Conditional NULL handling, CURRENT_TIMESTAMP function, 
                        <span class="inline-block px-2 py-1 bg-orange-100 text-orange-800 rounded">1 Trigger (trg_device_updated_at - Auto-updates timestamp BEFORE UPDATE)</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Edit Form -->
        <form method="POST" class="space-y-8">
            <!-- Basic Information Section -->
            <div class="form-section bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>Basic Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="device_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Device Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="device_name" 
                               name="device_name" 
                               required
                               value="<?php echo htmlspecialchars($device['d_name']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label for="device_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Device Type <span class="text-red-500">*</span>
                        </label>
                        <select id="device_type" 
                                name="device_type" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($deviceTypes as $type): ?>
                                <option value="<?php echo $type['t_id']; ?>" 
                                        <?php echo $device['type_id'] == $type['t_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['t_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Device Status
                        </label>
                        <div class="flex items-center space-x-4">
                            <select id="status" 
                                    name="status" 
                                    onchange="updateStatusPreview()"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="active" <?php echo $device['status'] === 'active' ? 'selected' : ''; ?>>
                                    Active - Device is operational and working normally
                                </option>
                                <option value="inactive" <?php echo $device['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive - Device is turned off or not in use
                                </option>
                                <option value="maintenance" <?php echo $device['status'] === 'maintenance' ? 'selected' : ''; ?>>
                                    Maintenance - Device is undergoing maintenance or repairs
                                </option>
                                <option value="error" <?php echo $device['status'] === 'error' ? 'selected' : ''; ?>>
                                    Error - Device has errors or malfunctions
                                </option>
                            </select>
                            <div id="status-preview" class="status-preview status-<?php echo $device['status']; ?>">
                                <?php echo ucfirst($device['status']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dates and Warranty Section -->
            <div class="form-section bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-calendar mr-2 text-green-600"></i>Dates and Warranty
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Purchase Date
                        </label>
                        <input type="date" 
                               id="purchase_date" 
                               name="purchase_date"
                               value="<?php echo $device['purchase_date']; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">When the device was purchased</p>
                    </div>
                    
                    <div>
                        <label for="warranty_expiry" class="block text-sm font-medium text-gray-700 mb-2">
                            Warranty Expiry
                        </label>
                        <input type="date" 
                               id="purchase_date" 
                               name="purchase_date"
                               value="<?php echo $device['purchase_date']; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">When the device was purchased</p>
                    </div>
                </div>
                
                <!-- Warranty Status Display -->
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Current Status</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Serial Number:</span>
                            <div class="font-mono font-semibold"><?php echo htmlspecialchars($device['serial_number']); ?></div>
                        </div>
                        <div>
                            <span class="text-gray-600">Created:</span>
                            <div class="font-semibold"><?php echo date('M j, Y', strtotime($device['created_at'])); ?></div>
                        </div>
                        <div>
                            <span class="text-gray-600">Last Updated:</span>
                            <div class="font-semibold"><?php echo date('M j, Y H:i', strtotime($device['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Current Deployments Info -->
            <?php if (!empty($currentDeployments)): ?>
                <div class="form-section bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-map-marker-alt mr-2 text-orange-600"></i>Current Deployments
                    </h3>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-blue-800 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            This device is currently deployed. To change deployments, use the main deployment management system.
                        </p>
                        
                        <div class="space-y-2">
                            <?php foreach ($currentDeployments as $deployment): ?>
                                <div class="flex justify-between items-center bg-white p-3 rounded border">
                                    <div>
                                        <span class="font-semibold"><?php echo htmlspecialchars($deployment['loc_name']); ?></span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Since <?php echo date('M j, Y', strtotime($deployment['deployed_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Form Actions -->
            <div class="flex justify-between items-center bg-white rounded-lg shadow-md p-6">
                <div>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-save mr-2"></i>
                        Changes will be logged and the device's updated timestamp will be modified.
                    </p>
                </div>
                
                <div class="flex space-x-3">
                    <a href="device_details.php?id=<?php echo $deviceId; ?>" 
                       class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        <i class="fas fa-save mr-2"></i>Update Device
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Help Section -->
        <div class="mt-8 bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">
                <i class="fas fa-question-circle mr-2 text-blue-600"></i>Editing Help
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
                <div>
                    <h4 class="font-semibold text-gray-800 mb-2">Device Status Guidelines</h4>
                    <ul class="space-y-1">
                        <li><strong>Active:</strong> Device is working normally</li>
                        <li><strong>Inactive:</strong> Device is powered off or unused</li>
                        <li><strong>Maintenance:</strong> Device is being serviced</li>
                        <li><strong>Error:</strong> Device has malfunctions</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-800 mb-2">Important Notes</h4>
                    <ul class="space-y-1">
                        <li>• Device name and type are required fields</li>
                        <li>• Dates are optional but recommended for tracking</li>
                        <li>• Status changes are automatically logged</li>
                        <li>• Serial number cannot be changed</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function updateStatusPreview() {
            const statusSelect = document.getElementById('status');
            const statusPreview = document.getElementById('status-preview');
            const selectedValue = statusSelect.value;
            
            // Remove all status classes
            statusPreview.className = 'status-preview status-' + selectedValue;
            statusPreview.textContent = selectedValue.charAt(0).toUpperCase() + selectedValue.slice(1);
        }
        
        // Set warranty reminder based on expiry date
        document.getElementById('warranty_expiry').addEventListener('change', function() {
            const warrantyDate = new Date(this.value);
            const today = new Date();
            const diffTime = warrantyDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            // Remove any existing warnings
            const existingWarning = document.getElementById('warranty-warning');
            if (existingWarning) {
                existingWarning.remove();
            }
    </script>
    
</div>
</body>
</html>