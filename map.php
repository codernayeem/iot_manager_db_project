<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: SELECT with JOINs, GROUP BY, Aggregate Functions (COUNT)
 * Interactive Map showing all locations with device counts
 */

$database = new Database();
$conn = $database->getConnection();

// Initialize default values
$locations = [];
$stats = [
    'total_locations' => 0,
    'total_devices' => 0,
    'active_devices' => 0
];

// Check if tables exist before querying
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'locations'");
    if ($checkTable->rowCount() > 0) {
        // Get all locations with device counts
        // SQL: SELECT with multiple LEFT JOINs, COUNT, CASE, and GROUP BY
        $locationsQuery = "
            SELECT 
                l.loc_id,
                l.loc_name,
                l.address,
                l.latitude,
                l.longitude,
                l.created_at,
                COUNT(DISTINCT CASE WHEN dep.is_active = 1 THEN d.d_id END) as total_devices,
                COUNT(DISTINCT CASE WHEN dep.is_active = 1 AND d.status = 'active' THEN d.d_id END) as active_devices,
                COUNT(DISTINCT CASE WHEN dep.is_active = 1 AND d.status = 'maintenance' THEN d.d_id END) as maintenance_devices,
                COUNT(DISTINCT CASE WHEN dep.is_active = 1 AND d.status = 'inactive' THEN d.d_id END) as inactive_devices
            FROM locations l
            LEFT JOIN deployments dep ON l.loc_id = dep.loc_id
            LEFT JOIN devices d ON dep.d_id = d.d_id
            GROUP BY l.loc_id, l.loc_name, l.address, l.latitude, l.longitude, l.created_at
            ORDER BY l.loc_name
        ";

        $stmt = $conn->prepare($locationsQuery);
        $stmt->execute();
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get overall statistics
        // SQL: SELECT with COUNT, DISTINCT, CASE, and multiple LEFT JOINs
        $statsQuery = "
            SELECT 
                COUNT(DISTINCT l.loc_id) as total_locations,
                COUNT(DISTINCT CASE WHEN dep.is_active = 1 THEN d.d_id END) as total_devices,
                COUNT(DISTINCT CASE WHEN dep.is_active = 1 AND d.status = 'active' THEN d.d_id END) as active_devices
            FROM locations l
            LEFT JOIN deployments dep ON l.loc_id = dep.loc_id
            LEFT JOIN devices d ON dep.d_id = d.d_id
        ";

        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Database not ready yet, use default values
}

// Convert to JSON for JavaScript
$locationsJson = json_encode($locations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Map - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            height: 600px;
            border-radius: 0.5rem;
        }
        .leaflet-popup-content-wrapper {
            border-radius: 0.5rem;
        }
        .location-card {
            transition: all 0.3s ease;
        }
        .location-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-map-marked-alt mr-3"></i>Location Map
            </h1>
            <p class="text-gray-600">View all locations and device distributions on the map</p>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-map-marker-alt text-red-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Locations</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_locations']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-microchip text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Devices</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_devices']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Devices</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['active_devices']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Container -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-globe mr-2"></i>Interactive Map
            </h2>
            <div id="map" class="shadow-inner"></div>
        </div>
        
        <!-- Locations List -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-list mr-2"></i>All Locations
            </h2>
            
            <?php if (empty($locations)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-map-marker-alt text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Locations Found</h3>
                    <p class="text-gray-500 mb-4">Add locations to see them on the map.</p>
                    <a href="locations.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition inline-block">
                        <i class="fas fa-plus mr-2"></i>Add Location
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($locations as $location): ?>
                        <div class="location-card border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer" 
                             onclick='zoomToLocation(<?php echo $location["latitude"]; ?>, <?php echo $location["longitude"]; ?>)'>
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-bold text-gray-800 mb-1">
                                        <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                                        <?php echo htmlspecialchars($location['loc_name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-map-pin text-gray-400 mr-1"></i>
                                        <?php echo htmlspecialchars($location['address']); ?>
                                    </p>
                                </div>
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-1 rounded">
                                    <?php echo $location['total_devices']; ?> devices
                                </span>
                            </div>
                            
                            <div class="border-t border-gray-200 pt-3 mt-3">
                                <div class="flex justify-between text-xs">
                                    <span class="text-green-600">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        <?php echo $location['active_devices']; ?> active
                                    </span>
                                    <span class="text-yellow-600">
                                        <i class="fas fa-wrench mr-1"></i>
                                        <?php echo $location['maintenance_devices']; ?> maintenance
                                    </span>
                                    <span class="text-red-600">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        <?php echo $location['inactive_devices']; ?> inactive
                                    </span>
                                </div>
                            </div>
                            
                            <div class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-globe mr-1"></i>
                                <?php echo number_format($location['latitude'], 6); ?>, 
                                <?php echo number_format($location['longitude'], 6); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Parse locations data
        const locations = <?php echo $locationsJson; ?>;
        
        // Initialize the map
        let map;
        let markers = [];
        
        function initMap() {
            // Default center (will be adjusted based on locations)
            const defaultCenter = [20, 0];
            const defaultZoom = 2;
            
            // Create map
            map = L.map('map').setView(defaultCenter, defaultZoom);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add markers for each location
            if (locations.length > 0) {
                const bounds = [];
                
                locations.forEach(location => {
                    if (location.latitude && location.longitude) {
                        const lat = parseFloat(location.latitude);
                        const lng = parseFloat(location.longitude);
                        
                        // Create custom icon based on device count
                        const deviceCount = parseInt(location.total_devices);
                        let iconColor = 'red';
                        if (deviceCount === 0) iconColor = 'gray';
                        else if (deviceCount < 5) iconColor = 'orange';
                        else if (deviceCount < 10) iconColor = 'blue';
                        else iconColor = 'green';
                        
                        // Create marker
                        const marker = L.marker([lat, lng], {
                            icon: L.divIcon({
                                html: `<div style="background-color: ${iconColor}; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">${deviceCount}</div>`,
                                className: '',
                                iconSize: [30, 30],
                                iconAnchor: [15, 15]
                            })
                        }).addTo(map);
                        
                        // Create popup content
                        const popupContent = `
                            <div style="min-width: 200px;">
                                <h3 style="font-weight: bold; font-size: 16px; margin-bottom: 8px;">
                                    <i class="fas fa-map-marker-alt" style="color: red;"></i> 
                                    ${escapeHtml(location.loc_name)}
                                </h3>
                                <p style="color: #666; font-size: 13px; margin-bottom: 10px;">
                                    <i class="fas fa-map-pin"></i> ${escapeHtml(location.address)}
                                </p>
                                <div style="border-top: 1px solid #e5e7eb; padding-top: 10px;">
                                    <div style="margin-bottom: 5px;">
                                        <strong>Total Devices:</strong> ${deviceCount}
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                                        <span style="color: #10b981;">
                                            <i class="fas fa-check-circle"></i> ${location.active_devices} active
                                        </span>
                                        <span style="color: #f59e0b;">
                                            <i class="fas fa-wrench"></i> ${location.maintenance_devices} maint.
                                        </span>
                                        <span style="color: #ef4444;">
                                            <i class="fas fa-times-circle"></i> ${location.inactive_devices} inactive
                                        </span>
                                    </div>
                                </div>
                                <div style="margin-top: 10px; text-align: center;">
                                    <a href="locations.php?id=${location.loc_id}" 
                                       style="background-color: #3b82f6; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        `;
                        
                        marker.bindPopup(popupContent);
                        markers.push({ marker, lat, lng });
                        bounds.push([lat, lng]);
                    }
                });
                
                // Fit map to show all markers
                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            }
        }
        
        function zoomToLocation(lat, lng) {
            map.setView([lat, lng], 15);
            
            // Find and open the marker popup
            markers.forEach(item => {
                if (item.lat === lat && item.lng === lng) {
                    item.marker.openPopup();
                }
            });
            
            // Scroll to map
            document.getElementById('map').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>
