<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: Geospatial queries, Distance calculations, 
 * Aggregation with HAVING, Subqueries with EXISTS
 */

$database = new Database();
$conn = $database->getConnection();

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['add_location'])) {
        $locName = trim($_POST['loc_name']);
        $address = trim($_POST['address']);
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        
        if (!empty($locName) && !empty($address)) {
            // SQL Feature: INSERT with geospatial data
            $sql = "INSERT INTO locations (loc_name, address, latitude, longitude) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$locName, $address, $latitude, $longitude])) {
                $message = "Location added successfully!";
            } else {
                $error = "Failed to add location.";
            }
        } else {
            $error = "Please fill in required fields.";
        }
    }
    
    // Handle edit location
    if (isset($_POST['edit_location'])) {
        $locId = (int)$_POST['loc_id'];
        $locName = trim($_POST['edit_loc_name']);
        $address = trim($_POST['edit_address']);
        $latitude = !empty($_POST['edit_latitude']) ? (float)$_POST['edit_latitude'] : null;
        $longitude = !empty($_POST['edit_longitude']) ? (float)$_POST['edit_longitude'] : null;
        
        if (!empty($locName) && !empty($address) && $locId > 0) {
            // SQL Feature: UPDATE with geospatial data
            $sql = "UPDATE locations SET loc_name = ?, address = ?, latitude = ?, longitude = ?, updated_at = CURRENT_TIMESTAMP WHERE loc_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$locName, $address, $latitude, $longitude, $locId])) {
                $message = "Location updated successfully!";
            } else {
                $error = "Failed to update location.";
            }
        } else {
            $error = "Please fill in required fields.";
        }
    }
    
    // Handle delete location (admin only)
    if (isset($_POST['delete_location']) && $_SESSION['user_email'] == 'admin@iot.com') {
        $locId = (int)$_POST['delete_loc_id'];
        
        if ($locId > 0) {
            try {
                // SQL Feature: Transaction with multiple operations
                $conn->beginTransaction();
                
                // First, deactivate all deployments for this location
                $deactivateDeployments = "UPDATE deployments SET is_active = FALSE WHERE loc_id = ?";
                $stmt = $conn->prepare($deactivateDeployments);
                $stmt->execute([$locId]);
                
                // Then delete the location
                $deleteLocation = "DELETE FROM locations WHERE loc_id = ?";
                $stmt = $conn->prepare($deleteLocation);
                $stmt->execute([$locId]);
                
                $conn->commit();
                $message = "Location deleted successfully! All devices have been unlinked from this location.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to delete location: " . $e->getMessage();
            }
        } else {
            $error = "Invalid location ID.";
        }
    } elseif (isset($_POST['delete_location']) && $_SESSION['user_email'] != 'admin@iot.com') {
        $error = "Access denied. Only admin can delete locations.";
    }
}

// SQL Feature: Complex query with geospatial calculations and multiple aggregations
$locationsQuery = "
    SELECT 
        l.loc_id,
        l.loc_name,
        l.address,
        l.latitude,
        l.longitude,
        l.created_at,
        COUNT(DISTINCT dep.d_id) as total_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) as active_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'error' THEN dep.d_id END) as error_devices,
        COUNT(DISTINCT CASE WHEN d.status = 'maintenance' THEN dep.d_id END) as maintenance_devices,
        COUNT(DISTINCT dt.t_id) as device_type_count,
        GROUP_CONCAT(DISTINCT dt.t_name ORDER BY dt.t_name SEPARATOR ', ') as device_types,
        COUNT(DISTINCT dl.log_id) as total_logs,
        COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) as error_logs,
        COUNT(DISTINCT CASE WHEN dl.log_type = 'error' AND dl.resolved_by IS NULL THEN dl.log_id END) as unresolved_errors,
        MAX(dl.log_time) as last_activity,
        AVG(CASE WHEN dl.log_type = 'error' THEN dl.severity_level END) as avg_error_severity,
        COUNT(DISTINCT dep.deployed_by) as deploying_users,
        ROUND(
            COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) * 100.0 / 
            NULLIF(COUNT(DISTINCT dep.d_id), 0), 2
        ) as uptime_percentage,
        CASE 
            WHEN COUNT(DISTINCT dep.d_id) = 0 THEN 'Empty'
            WHEN COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) / COUNT(DISTINCT dep.d_id) >= 0.9 THEN 'Excellent'
            WHEN COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) / COUNT(DISTINCT dep.d_id) >= 0.7 THEN 'Good'
            WHEN COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) / COUNT(DISTINCT dep.d_id) >= 0.5 THEN 'Fair'
            ELSE 'Poor'
        END as location_health
    FROM locations l
    LEFT JOIN deployments dep ON l.loc_id = dep.loc_id AND dep.is_active = 1
    LEFT JOIN devices d ON dep.d_id = d.d_id
    LEFT JOIN device_types dt ON d.t_id = dt.t_id
    LEFT JOIN device_logs dl ON d.d_id = dl.d_id AND dl.log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY l.loc_id, l.loc_name, l.address, l.latitude, l.longitude, l.created_at
    ORDER BY total_devices DESC, active_devices DESC, l.loc_name
";

$stmt = $conn->prepare($locationsQuery);
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL Feature: Geospatial distance calculations (if coordinates available)
if (!empty($locations)) {
    $centerLat = array_sum(array_filter(array_column($locations, 'latitude'))) / count(array_filter(array_column($locations, 'latitude')));
    $centerLng = array_sum(array_filter(array_column($locations, 'longitude'))) / count(array_filter(array_column($locations, 'longitude')));
    
    // Calculate distances from center point
    foreach ($locations as &$location) {
        if ($location['latitude'] && $location['longitude']) {
            $location['distance_from_center'] = calculateDistance(
                $centerLat, $centerLng, 
                $location['latitude'], $location['longitude']
            );
        } else {
            $location['distance_from_center'] = null;
        }
    }
}

// SQL Feature: Location statistics with HAVING clause
$statsQuery = "
    SELECT 
        COUNT(*) as total_locations,
        COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as geocoded_locations,
        COUNT(CASE WHEN EXISTS(SELECT 1 FROM deployments WHERE loc_id = locations.loc_id AND is_active = 1) THEN 1 END) as active_locations,
        AVG(CASE WHEN latitude IS NOT NULL THEN latitude END) as avg_latitude,
        AVG(CASE WHEN longitude IS NOT NULL THEN longitude END) as avg_longitude
    FROM locations
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Helper function for distance calculation
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return round($earthRadius * $c, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locations - IoT Device Manager</title>
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
        
        .location-card {
            transition: all 0.3s ease;
        }
        
        .location-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .health-excellent { border-left: 4px solid #059669; background-color: #f0fdf4; }
        .health-good { border-left: 4px solid #0284c7; background-color: #f0f9ff; }
        .health-fair { border-left: 4px solid #d97706; background-color: #fffbeb; }
        .health-poor { border-left: 4px solid #dc2626; background-color: #fef2f2; }
        .health-empty { border-left: 4px solid #6b7280; background-color: #f9fafb; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-map-marker-alt mr-3"></i>Location Management
                </h1>
                <p class="text-gray-600">Manage deployment locations and analyze performance</p>
            </div>
            <button onclick="toggleAddForm()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-plus mr-2"></i>Add Location
            </button>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>SQL Features:</strong> Geospatial queries, Distance calculations, Complex aggregations with HAVING, 
                        Conditional aggregation with CASE, GROUP_CONCAT, EXISTS subqueries, Percentage calculations
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-map-marked-alt text-blue-600 text-2xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_locations']; ?></p>
                <p class="text-sm text-gray-600">Total Locations</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-satellite text-green-600 text-2xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['geocoded_locations']; ?></p>
                <p class="text-sm text-gray-600">Geocoded</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-check-circle text-purple-600 text-2xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_locations']; ?></p>
                <p class="text-sm text-gray-600">Active</p>
            </div>
        </div>
        
        <!-- Add Location Form -->
        <div id="addLocationForm" class="hidden bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-plus-circle mr-2"></i>Add New Location
            </h2>
            
            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="loc_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Location Name *
                        </label>
                        <input type="text" id="loc_name" name="loc_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter location name">
                    </div>
                    
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                            Address *
                        </label>
                        <input type="text" id="address" name="address" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter full address">
                    </div>
                    
                    <div>
                        <label for="latitude" class="block text-sm font-medium text-gray-700 mb-2">
                            Latitude (Optional)
                        </label>
                        <input type="number" id="latitude" name="latitude" step="0.000001" min="-90" max="90"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., 37.4419">
                    </div>
                    
                    <div>
                        <label for="longitude" class="block text-sm font-medium text-gray-700 mb-2">
                            Longitude (Optional)
                        </label>
                        <input type="number" id="longitude" name="longitude" step="0.000001" min="-180" max="180"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., -122.1430">
                    </div>
                </div>
                
                <div class="flex justify-between items-center mt-6">
                    <button type="button" onclick="toggleAddForm()" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    
                    <div class="sql-tooltip">
                        <button type="submit" name="add_location" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-plus mr-2"></i>Add Location
                        </button>
                        <span class="tooltip-text">
                            SQL INSERT with geospatial data:<br><br>
                            INSERT INTO locations (loc_name, address, latitude, longitude)<br>
                            VALUES (?, ?, ?, ?)
                        </span>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Locations Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($locations as $location): ?>
                <div class="location-card health-<?php echo strtolower($location['location_health']); ?> bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1">
                                <?php echo htmlspecialchars($location['loc_name']); ?>
                            </h3>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($location['address']); ?></p>
                            
                            <?php if ($location['latitude'] && $location['longitude']): ?>
                                <div class="text-xs text-gray-500 mb-2">
                                    <i class="fas fa-map-pin mr-1"></i>
                                    <?php echo number_format($location['latitude'], 4); ?>, <?php echo number_format($location['longitude'], 4); ?>
                                    <?php if ($location['distance_from_center']): ?>
                                        <br><i class="fas fa-route mr-1"></i>
                                        <?php echo $location['distance_from_center']; ?> km from center
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-right">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                <?php echo $location['location_health'] === 'Excellent' ? 'bg-green-100 text-green-800' : 
                                           ($location['location_health'] === 'Good' ? 'bg-blue-100 text-blue-800' : 
                                            ($location['location_health'] === 'Fair' ? 'bg-yellow-100 text-yellow-800' : 
                                             ($location['location_health'] === 'Poor' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'))); ?>">
                                <?php echo $location['location_health']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Device Statistics -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="text-center p-2 bg-gray-50 rounded">
                            <p class="text-lg font-bold text-blue-600"><?php echo $location['total_devices']; ?></p>
                            <p class="text-xs text-gray-600">Total Devices</p>
                        </div>
                        
                        <div class="text-center p-2 bg-gray-50 rounded">
                            <p class="text-lg font-bold text-green-600"><?php echo $location['active_devices']; ?></p>
                            <p class="text-xs text-gray-600">Active</p>
                        </div>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <?php if ($location['total_devices'] > 0): ?>
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Uptime:</span>
                                <span class="font-semibold text-green-600"><?php echo $location['uptime_percentage']; ?>%</span>
                            </div>
                            
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Device Types:</span>
                                <span class="font-semibold"><?php echo $location['device_type_count']; ?></span>
                            </div>
                            
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Total Logs:</span>
                                <span class="font-semibold"><?php echo number_format($location['total_logs']); ?></span>
                            </div>
                            
                            <?php if ($location['unresolved_errors'] > 0): ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Unresolved:</span>
                                    <span class="font-semibold text-red-600"><?php echo $location['unresolved_errors']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($location['avg_error_severity']): ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Avg Severity:</span>
                                    <span class="font-semibold"><?php echo number_format($location['avg_error_severity'], 1); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Device Types List -->
                        <?php if ($location['device_types']): ?>
                            <div class="mb-4">
                                <h4 class="text-xs font-semibold text-gray-700 mb-1">Device Types:</h4>
                                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($location['device_types']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Last Activity -->
                        <?php if ($location['last_activity']): ?>
                            <div class="text-xs text-gray-500 mb-4">
                                <i class="fas fa-clock mr-1"></i>
                                Last activity: <?php echo date('M j, Y H:i', strtotime($location['last_activity'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                <span>Device Status</span>
                                <span><?php echo $location['active_devices']; ?>/<?php echo $location['total_devices']; ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" 
                                     style="width: <?php echo $location['uptime_percentage']; ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex justify-between items-center">
                            <div class="flex space-x-2">
                                <a href="devices.php?location=<?php echo $location['loc_id']; ?>" 
                                   class="bg-blue-100 text-blue-600 px-3 py-2 rounded text-sm hover:bg-blue-200 transition"
                                   title="View Devices">
                                    <i class="fas fa-microchip"></i>
                                </a>
                                
                                <a href="device_logs.php?location=<?php echo $location['loc_id']; ?>" 
                                   class="bg-purple-100 text-purple-600 px-3 py-2 rounded text-sm hover:bg-purple-200 transition"
                                   title="View Logs">
                                    <i class="fas fa-list"></i>
                                </a>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($location)); ?>)" 
                                        class="bg-gray-100 text-gray-600 px-3 py-2 rounded text-sm hover:bg-gray-200 transition"
                                        title="Edit Location">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($_SESSION['user_email'] == 'admin@iot.com'): ?>
                                <button onclick="confirmDelete(<?php echo $location['loc_id']; ?>, '<?php echo htmlspecialchars($location['loc_name']); ?>')" 
                                        class="bg-red-100 text-red-600 px-3 py-2 rounded text-sm hover:bg-red-200 transition"
                                        title="Delete Location">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-500">No devices deployed</p>
                            <a href="add_device.php" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-plus mr-1"></i>Deploy Device
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($locations)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-map-marker-alt text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No locations found</h3>
                <p class="text-gray-500 mb-4">Get started by adding your first deployment location.</p>
                <button onclick="toggleAddForm()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>Add First Location
                </button>
            </div>
        <?php endif; ?>
        
        <!-- SQL Query Display -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <div class="sql-tooltip inline-block">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-code mr-2 text-blue-600"></i>Complex Location Analytics Query
                </h3>
                <span class="tooltip-text">
                    This query demonstrates multiple advanced SQL features:<br>
                    - Complex JOINs across multiple tables<br>
                    - Conditional aggregation with CASE statements<br>
                    - GROUP_CONCAT for string aggregation<br>
                    - Percentage calculations<br>
                    - Date filtering with DATE_SUB<br>
                    - Geospatial data handling<br>
                    - Performance categorization logic
                </span>
            </div>
            
            <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
                <pre>SELECT 
    l.loc_name, l.address, l.latitude, l.longitude,
    COUNT(DISTINCT dep.d_id) as total_devices,
    COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) as active_devices,
    GROUP_CONCAT(DISTINCT dt.t_name ORDER BY dt.t_name) as device_types,
    COUNT(DISTINCT dl.log_id) as total_logs,
    ROUND(COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) * 100.0 / 
          NULLIF(COUNT(DISTINCT dep.d_id), 0), 2) as uptime_percentage,
    CASE 
        WHEN COUNT(DISTINCT dep.d_id) = 0 THEN 'Empty'
        WHEN COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) / 
             COUNT(DISTINCT dep.d_id) >= 0.9 THEN 'Excellent'
        WHEN COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) / 
             COUNT(DISTINCT dep.d_id) >= 0.7 THEN 'Good'
        ELSE 'Poor'
    END as location_health
FROM locations l
LEFT JOIN deployments dep ON l.loc_id = dep.loc_id AND dep.is_active = 1
LEFT JOIN devices d ON dep.d_id = d.d_id
LEFT JOIN device_types dt ON d.t_id = dt.t_id
LEFT JOIN device_logs dl ON d.d_id = dl.d_id AND dl.log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY l.loc_id, l.loc_name, l.address, l.latitude, l.longitude
ORDER BY total_devices DESC, active_devices DESC;</pre>
            </div>
        </div>
    </div>
    
    <!-- Edit Location Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-edit mr-2"></i>Edit Location
                </h3>
                
                <form method="POST" id="editForm">
                    <input type="hidden" name="loc_id" id="edit_loc_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="edit_loc_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Location Name *
                            </label>
                            <input type="text" id="edit_loc_name" name="edit_loc_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="edit_address" class="block text-sm font-medium text-gray-700 mb-2">
                                Address *
                            </label>
                            <input type="text" id="edit_address" name="edit_address" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="edit_latitude" class="block text-sm font-medium text-gray-700 mb-2">
                                Latitude (Optional)
                            </label>
                            <input type="number" id="edit_latitude" name="edit_latitude" step="0.000001" min="-90" max="90"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="edit_longitude" class="block text-sm font-medium text-gray-700 mb-2">
                                Longitude (Optional)
                            </label>
                            <input type="number" id="edit_longitude" name="edit_longitude" step="0.000001" min="-180" max="180"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mt-6">
                        <button type="button" onclick="closeEditModal()" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        
                        <button type="submit" name="edit_location" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i>Update Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-red-800 mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Delete
                </h3>
                
                <p class="text-gray-600 mb-6">
                    Are you sure you want to delete <strong id="delete_location_name"></strong>? 
                    This will unlink all devices from this location and cannot be undone.
                </p>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Warning:</strong> All devices deployed at this location will be unlinked but not deleted.
                            </p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="delete_loc_id" id="delete_loc_id">
                    
                    <div class="flex justify-between items-center">
                        <button type="button" onclick="closeDeleteModal()" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        
                        <button type="submit" name="delete_location" class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700 transition">
                            <i class="fas fa-trash mr-2"></i>Delete Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function toggleAddForm() {
            const form = document.getElementById('addLocationForm');
            form.classList.toggle('hidden');
        }
        
        function openEditModal(location) {
            document.getElementById('edit_loc_id').value = location.loc_id;
            document.getElementById('edit_loc_name').value = location.loc_name;
            document.getElementById('edit_address').value = location.address;
            document.getElementById('edit_latitude').value = location.latitude || '';
            document.getElementById('edit_longitude').value = location.longitude || '';
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        function confirmDelete(locId, locName) {
            document.getElementById('delete_loc_id').value = locId;
            document.getElementById('delete_location_name').textContent = locName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Auto-fill coordinates based on address (placeholder for geolocation API)
        document.getElementById('address').addEventListener('blur', function() {
            // This would typically connect to a geocoding service
            // For demo purposes, we'll show the concept
            console.log('Address entered:', this.value);
            // geocodeAddress(this.value);
        });
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id === 'editModal') {
                closeEditModal();
            }
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>