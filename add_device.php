<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: INSERT with foreign keys, Transaction handling,
 * SELECT with JOINs for dropdowns, Data validation
 */

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';

if ($_POST) {
    $deviceName = trim($_POST['device_name']);
    $deviceType = (int)$_POST['device_type'];
    $serialNumber = trim($_POST['serial_number']);
    $status = $_POST['status'];
    $purchaseDate = $_POST['purchase_date'];
    $locations = isset($_POST['locations']) ? $_POST['locations'] : [];
    $deploymentNotes = trim($_POST['deployment_notes']);
    
    if (!empty($deviceName) && $deviceType > 0 && !empty($serialNumber)) {
        try {
            // SQL Feature: Transaction with error handling
            $conn->beginTransaction();
            
            // Check if serial number already exists
            $checkSql = "SELECT EXISTS(SELECT 1 FROM devices WHERE serial_number = ?) as exists";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$serialNumber]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists) {
                throw new Exception("Serial number already exists!");
            }
            
            // SQL Feature: INSERT with foreign key references
            // TRIGGER: trg_log_new_device will automatically fire AFTER INSERT to create a log entry
            $deviceSql = "INSERT INTO devices (d_name, t_id, user_id, serial_number, status, purchase_date) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            
            $deviceStmt = $conn->prepare($deviceSql);
            $deviceStmt->execute([
                $deviceName, 
                $deviceType, 
                $_SESSION['user_id'], 
                $serialNumber, 
                $status, 
                $purchaseDate ?: null
            ]);
            
            $deviceId = $conn->lastInsertId();
            
            // Note: Trigger 'trg_log_new_device' has automatically created a log entry for this new device
            
            // SQL Feature: Bulk INSERT for deployments if locations selected
            if (!empty($locations)) {
                $deploymentSql = "INSERT INTO deployments (d_id, loc_id, deployed_by, deployment_notes) VALUES (?, ?, ?, ?)";
                $deploymentStmt = $conn->prepare($deploymentSql);
                
                foreach ($locations as $locationId) {
                    $deploymentStmt->execute([$deviceId, (int)$locationId, $_SESSION['user_id'], $deploymentNotes]);
                }
            }
            
            // SQL Feature: INSERT initial log entry
            $logSql = "INSERT INTO device_logs (d_id, log_type, message, severity_level) 
                      VALUES (?, 'info', ?, 1)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->execute([$deviceId, "Device '$deviceName' registered successfully"]);
            
            // SQL Feature: COMMIT transaction
            $conn->commit();
            $success = "Device added successfully!";
            
        } catch (Exception $e) {
            // SQL Feature: ROLLBACK on error
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields!";
    }
}

// Get device types for dropdown
$typesQuery = "SELECT t_id, t_name FROM device_types ORDER BY t_name";
$typesStmt = $conn->prepare($typesQuery);
$typesStmt->execute();
$deviceTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get locations for multi-select
$locationsQuery = "SELECT loc_id, loc_name, address FROM locations ORDER BY loc_name";
$locationsStmt = $conn->prepare($locationsQuery);
$locationsStmt->execute();
$locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Device - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-plus-circle mr-3"></i>Add New Device
                </h1>
                <p class="text-gray-600">Register a new IoT device in the system</p>
            </div>
            
            <!-- SQL Feature Info -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-database text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>SQL Features:</strong> INSERT with foreign keys, Transaction handling (BEGIN/COMMIT/ROLLBACK), 
                            EXISTS for validation, Bulk INSERT for deployments, lastInsertId(), 
                            <span class="inline-block px-2 py-1 bg-orange-100 text-orange-800 rounded">1 Trigger (trg_log_new_device - Auto-creates log AFTER INSERT)</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                    <div class="mt-2">
                        <a href="devices.php" class="text-green-800 hover:text-green-900 font-semibold">
                            <i class="fas fa-arrow-right mr-1"></i>View All Devices
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Device Form -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Device Name -->
                        <div class="md:col-span-2">
                            <label for="device_name" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2"></i>Device Name *
                            </label>
                            <input type="text" 
                                   id="device_name" 
                                   name="device_name" 
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter device name (e.g., Security Drone Alpha)">
                        </div>
                        
                        <!-- Device Type -->
                        <div>
                            <label for="device_type" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-microchip mr-2"></i>Device Type *
                            </label>
                            <select id="device_type" 
                                    name="device_type" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select device type</option>
                                <?php foreach ($deviceTypes as $type): ?>
                                    <option value="<?php echo $type['t_id']; ?>" title="<?php echo htmlspecialchars($type['description']); ?>">
                                        <?php echo htmlspecialchars($type['t_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Serial Number -->
                        <div>
                            <label for="serial_number" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-barcode mr-2"></i>Serial Number *
                            </label>
                            <input type="text" 
                                   id="serial_number" 
                                   name="serial_number" 
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter unique serial number">
                        </div>
                        
                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-info-circle mr-2"></i>Initial Status
                            </label>
                            <select id="status" 
                                    name="status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="inactive">Inactive</option>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        
                        <!-- Purchase Date -->
                        <div>
                            <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2"></i>Purchase Date
                            </label>
                            <input type="date" 
                                   id="purchase_date" 
                                   name="purchase_date"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <!-- Locations (Multi-select) -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-2"></i>Initial Deployment Locations (Optional)
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3">
                                <?php foreach ($locations as $location): ?>
                                    <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                                        <input type="checkbox" 
                                               name="locations[]" 
                                               value="<?php echo $location['loc_id']; ?>"
                                               class="text-blue-600 focus:ring-blue-500">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($location['loc_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($location['address']); ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Select one or more locations where this device will be deployed</p>
                        </div>
                        
                        <!-- Deployment Notes -->
                        <div class="md:col-span-2">
                            <label for="deployment_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-2"></i>Deployment Notes
                            </label>
                            <textarea id="deployment_notes" 
                                      name="deployment_notes" 
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Optional notes about the device deployment..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                        <a href="devices.php" class="text-gray-600 hover:text-gray-800 transition">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Devices
                        </a>
                        
                        <div class="space-x-3">
                            <button type="reset" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </button>
                            
                            <div class="sql-tooltip inline-block">
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition">
                                    <i class="fas fa-plus mr-2"></i>Add Device
                                </button>
                                <span class="tooltip-text">
                                    SQL Transaction Process:<br><br>
                                    BEGIN TRANSACTION;<br><br>
                                    1. Check serial uniqueness:<br>
                                    SELECT EXISTS(SELECT 1 FROM devices WHERE serial_number = ?)<br><br>
                                    2. Insert device:<br>
                                    INSERT INTO devices (d_name, t_id, user_id, serial_number, status, purchase_date, warranty_expiry)<br>
                                    VALUES (?, ?, ?, ?, ?, ?)<br><br>
                                    3. Insert deployments:<br>
                                    INSERT INTO deployments (d_id, loc_id, deployed_by, deployment_notes)<br>
                                    VALUES (?, ?, ?, ?)<br><br>
                                    4. Insert initial log:<br>
                                    INSERT INTO device_logs (d_id, log_type, message, severity_level)<br>
                                    VALUES (?, 'info', ?, 1)<br><br>
                                    COMMIT;
                                </span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Help Section -->
            <div class="mt-8 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">
                    <i class="fas fa-question-circle mr-2 text-blue-600"></i>Device Registration Help
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Required Fields</h4>
                        <ul class="space-y-1">
                            <li><i class="fas fa-check text-green-600 mr-2"></i>Device Name: Unique identifier for the device</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>Device Type: Category of the IoT device</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>Serial Number: Must be unique across all devices</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Optional Information</h4>
                        <ul class="space-y-1">
                            <li><i class="fas fa-info text-blue-600 mr-2"></i>Purchase & warranty dates for tracking</li>
                            <li><i class="fas fa-info text-blue-600 mr-2"></i>Multiple deployment locations</li>
                            <li><i class="fas fa-info text-blue-600 mr-2"></i>Deployment notes for documentation</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Generate serial number suggestion
        document.getElementById('device_type').addEventListener('change', function() {
            const typeSelect = this;
            const typeName = typeSelect.options[typeSelect.selectedIndex].text;
            const serialInput = document.getElementById('serial_number');
            
            if (typeName && serialInput.value === '') {
                const prefix = typeName.substring(0, 3).toUpperCase();
                const timestamp = Date.now().toString().slice(-6);
                const year = new Date().getFullYear();
                serialInput.value = `${prefix}-${timestamp}-${year}`;
            }
        });
    </script>
</body>
</html>