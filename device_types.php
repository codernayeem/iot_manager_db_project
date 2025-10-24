<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: SELECT, INSERT, UPDATE, DELETE, JOIN, GROUP BY, COUNT, CASE
 * Device Types Management with CRUD operations
 */

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';

// Get search and sort parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 't_name';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Validate sort column
$allowedSortColumns = ['t_id', 't_name', 'device_count', 'active_count', 'created_at'];
if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 't_name';
}

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $typeName = trim($_POST['t_name']);
        
        if (!empty($typeName)) {
            try {
                // SQL: INSERT - Add new device type
                $sql = "INSERT INTO device_types (t_name) VALUES (?)";
                $stmt = $conn->prepare($sql);
                if ($stmt->execute([$typeName])) {
                    $success = "Device type added successfully!";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Device type already exists!";
                } else {
                    $error = "Error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Type name is required!";
        }
    } elseif ($action === 'edit') {
        $typeId = (int)$_POST['t_id'];
        $typeName = trim($_POST['t_name']);
        
        if (!empty($typeName) && $typeId > 0) {
            try {
                // SQL: UPDATE - Modify existing device type
                $sql = "UPDATE device_types SET t_name = ? WHERE t_id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt->execute([$typeName, $typeId])) {
                    $success = "Device type updated successfully!";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Device type already exists!";
                } else {
                    $error = "Error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Invalid input!";
        }
    } elseif ($action === 'delete') {
        $typeId = (int)$_POST['t_id'];
        
        if ($typeId > 0) {
            try {
                // SQL: SELECT with COUNT - Check foreign key constraints
                $checkSql = "SELECT COUNT(*) FROM devices WHERE t_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$typeId]);
                $count = $checkStmt->fetchColumn();
                
                if ($count > 0) {
                    $error = "Cannot delete! {$count} device(s) are using this type.";
                } else {
                    // SQL: DELETE - Remove device type
                    $sql = "DELETE FROM device_types WHERE t_id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute([$typeId])) {
                        $success = "Device type deleted successfully!";
                    }
                }
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// SQL: SELECT with LEFT JOIN, GROUP BY, COUNT, and CASE for aggregation
// Get all device types with device count and search/sort functionality
$typesQuery = "
    SELECT 
        dt.t_id,
        dt.t_name,
        dt.created_at,
        COUNT(d.d_id) as device_count,
        COUNT(CASE WHEN d.status = 'active' THEN 1 END) as active_count,
        COUNT(CASE WHEN d.status = 'maintenance' THEN 1 END) as maintenance_count,
        COUNT(CASE WHEN d.status = 'inactive' THEN 1 END) as inactive_count
    FROM device_types dt
    LEFT JOIN devices d ON dt.t_id = d.t_id
";

// Add search condition if search term is provided
if (!empty($search)) {
    $typesQuery .= " WHERE dt.t_name LIKE :search";
}

$typesQuery .= "
    GROUP BY dt.t_id, dt.t_name, dt.created_at
    ORDER BY " . $sortBy . " " . $sortOrder;

$stmt = $conn->prepare($typesQuery);

if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
}

$stmt->execute();
$deviceTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL: SELECT with COUNT - Get total statistics
$statsQuery = "SELECT COUNT(*) as total_types FROM device_types";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// SQL Feature: Using STORED PROCEDURE sp_get_devices_by_type()
// Get sample devices for each type using stored procedure
$devicesByType = [];
try {
    foreach ($deviceTypes as $type) {
        $procStmt = $conn->prepare("CALL sp_get_devices_by_type(?)");
        $procStmt->execute([$type['t_id']]);
        $devicesByType[$type['t_id']] = $procStmt->fetchAll(PDO::FETCH_ASSOC);
        $procStmt->closeCursor(); // Important: close cursor after each call
    }
} catch (Exception $e) {
    // Procedure might not exist yet
    $devicesByType = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Types - IoT Device Manager</title>
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
                    <i class="fas fa-tags mr-3"></i>Device Types
                </h1>
                <p class="text-gray-600">Manage device categories</p>
            </div>
            <button onclick="showAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-plus mr-2"></i>Add Device Type
            </button>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-gradient-to-r from-purple-50 to-blue-50 border-l-4 border-purple-500 p-4 mb-6 rounded-r-lg shadow-sm">
            <div class="flex items-center">
                <i class="fas fa-database text-purple-600 text-2xl mr-3"></i>
                <div>
                    <h3 class="font-semibold text-gray-800 mb-1">SQL Features Demonstrated</h3>
                    <p class="text-sm text-gray-600">
                        <strong>SQL Features Used:</strong> 
                        <span class="inline-block px-2 py-1 bg-purple-100 text-purple-800 rounded mr-2">1 Stored Procedure (sp_get_devices_by_type with IF/ELSE, WHILE LOOP)</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">Complex JOINs</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">GROUP BY with COUNT</span>
                        <span class="inline-block px-2 py-1 bg-white rounded">ORDER BY with sorting</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Search and Filter Bar -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search device types by name..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-database mr-1"></i>
                        <strong>SQL:</strong> SELECT ... WHERE t_name LIKE '%search%'
                    </p>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="device_types.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tags text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Types</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_types']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-database mr-1"></i>SELECT COUNT(*)
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-microchip text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Devices</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php 
                            $totalDevices = array_sum(array_column($deviceTypes, 'device_count'));
                            echo $totalDevices;
                            ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-database mr-1"></i>COUNT + LEFT JOIN
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Devices</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php 
                            $totalActive = array_sum(array_column($deviceTypes, 'active_count'));
                            echo $totalActive;
                            ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-database mr-1"></i>COUNT with CASE
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Device Types Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-table mr-2"></i>Device Types
                        <?php if (!empty($search)): ?>
                            <span class="text-sm font-normal text-gray-600">- Search results for "<?php echo htmlspecialchars($search); ?>"</span>
                        <?php endif; ?>
                    </h2>
                    <span class="text-sm text-gray-600">
                        <?php echo count($deviceTypes); ?> result(s)
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    <i class="fas fa-database mr-1"></i>
                    <strong>SQL Query:</strong> SELECT dt.*, COUNT(d.d_id) FROM device_types dt LEFT JOIN devices d ON dt.t_id = d.t_id GROUP BY dt.t_id ORDER BY <?php echo $sortBy . ' ' . $sortOrder; ?>
                </p>
            </div>
            
            <?php if (empty($deviceTypes)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">
                        <?php echo !empty($search) ? 'No Results Found' : 'No Device Types Yet'; ?>
                    </h3>
                    <p class="text-gray-500 mb-4">
                        <?php echo !empty($search) ? 'Try a different search term.' : 'Start by adding your first device type.'; ?>
                    </p>
                    <?php if (empty($search)): ?>
                        <button onclick="showAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-plus mr-2"></i>Add Device Type
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=t_id&order=<?php echo ($sortBy === 't_id' && $sortOrder === 'ASC') ? 'desc' : 'asc'; ?>" 
                                       class="flex items-center text-xs font-medium text-gray-600 uppercase tracking-wider hover:text-blue-600">
                                        ID
                                        <?php if ($sortBy === 't_id'): ?>
                                            <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort ml-1 text-gray-400"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=t_name&order=<?php echo ($sortBy === 't_name' && $sortOrder === 'ASC') ? 'desc' : 'asc'; ?>" 
                                       class="flex items-center text-xs font-medium text-gray-600 uppercase tracking-wider hover:text-blue-600">
                                        Type Name
                                        <?php if ($sortBy === 't_name'): ?>
                                            <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort ml-1 text-gray-400"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=device_count&order=<?php echo ($sortBy === 'device_count' && $sortOrder === 'ASC') ? 'desc' : 'asc'; ?>" 
                                       class="flex items-center text-xs font-medium text-gray-600 uppercase tracking-wider hover:text-blue-600">
                                        Total Devices
                                        <?php if ($sortBy === 'device_count'): ?>
                                            <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort ml-1 text-gray-400"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=active_count&order=<?php echo ($sortBy === 'active_count' && $sortOrder === 'ASC') ? 'desc' : 'asc'; ?>" 
                                       class="flex items-center text-xs font-medium text-gray-600 uppercase tracking-wider hover:text-blue-600">
                                        Active
                                        <?php if ($sortBy === 'active_count'): ?>
                                            <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort ml-1 text-gray-400"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    Status Distribution
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="?search=<?php echo urlencode($search); ?>&sort=created_at&order=<?php echo ($sortBy === 'created_at' && $sortOrder === 'ASC') ? 'desc' : 'asc'; ?>" 
                                       class="flex items-center text-xs font-medium text-gray-600 uppercase tracking-wider hover:text-blue-600">
                                        Created At
                                        <?php if ($sortBy === 'created_at'): ?>
                                            <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort ml-1 text-gray-400"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($deviceTypes as $type): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-mono text-gray-900">#<?php echo $type['t_id']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-tag text-blue-600 mr-2"></i>
                                            <span class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($type['t_name']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold 
                                            <?php echo $type['device_count'] > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'; ?>" 
                                            title="Using PROCEDURE: sp_get_devices_by_type(<?php echo $type['t_id']; ?>) - Fetches devices with IF/ELSE and WHILE LOOP">
                                            <i class="fas fa-microchip mr-1"></i>
                                            <?php echo $type['device_count']; ?>
                                            <i class="fas fa-database ml-1 text-xs text-purple-600"></i>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold 
                                            <?php echo $type['active_count'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'; ?>">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            <?php echo $type['active_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2 text-xs">
                                            <span class="text-green-600" title="Active devices">
                                                <i class="fas fa-circle text-xs"></i> <?php echo $type['active_count']; ?>
                                            </span>
                                            <span class="text-yellow-600" title="Maintenance">
                                                <i class="fas fa-circle text-xs"></i> <?php echo $type['maintenance_count']; ?>
                                            </span>
                                            <span class="text-red-600" title="Inactive">
                                                <i class="fas fa-circle text-xs"></i> <?php echo $type['inactive_count']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo date('M j, Y', strtotime($type['created_at'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            <?php echo date('g:i A', strtotime($type['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick='editType(<?php echo json_encode($type); ?>)' 
                                                class="text-blue-600 hover:text-blue-900 mr-3" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick='deleteType(<?php echo $type["t_id"]; ?>, "<?php echo htmlspecialchars($type["t_name"], ENT_QUOTES); ?>", <?php echo $type["device_count"]; ?>)' 
                                                class="text-red-600 hover:text-red-900" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
    </div>
    
    <!-- Add/Edit Modal -->
    <div id="typeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="bg-blue-600 text-white p-4 rounded-t-lg">
                    <h3 class="text-lg font-bold" id="modalTitle">Add Device Type</h3>
                </div>
                <form id="typeForm" method="POST" class="p-6">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="t_id" id="typeId">
                    
                    <div class="mb-4">
                        <label for="typeName" class="block text-sm font-medium text-gray-700 mb-2">
                            Type Name *
                        </label>
                        <input type="text" 
                               id="typeName" 
                               name="t_name" 
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Temperature Sensor">
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" 
                                onclick="closeModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i>Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="bg-red-600 text-white p-4 rounded-t-lg">
                    <h3 class="text-lg font-bold">Confirm Delete</h3>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="t_id" id="deleteTypeId">
                    
                    <p class="text-gray-700 mb-4" id="deleteMessage"></p>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" 
                                onclick="closeDeleteModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">
                            Cancel
                        </button>
                        <button type="submit" 
                                id="confirmDeleteBtn"
                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Device Type';
            document.getElementById('formAction').value = 'add';
            document.getElementById('typeId').value = '';
            document.getElementById('typeName').value = '';
            document.getElementById('typeModal').classList.remove('hidden');
        }
        
        function editType(type) {
            document.getElementById('modalTitle').textContent = 'Edit Device Type';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('typeId').value = type.t_id;
            document.getElementById('typeName').value = type.t_name;
            document.getElementById('typeModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('typeModal').classList.add('hidden');
        }
        
        function deleteType(typeId, typeName, deviceCount) {
            document.getElementById('deleteTypeId').value = typeId;
            
            if (deviceCount > 0) {
                document.getElementById('deleteMessage').innerHTML = 
                    `<i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>` +
                    `Cannot delete "${typeName}" because ${deviceCount} device(s) are using this type. ` +
                    `Please reassign or delete those devices first.`;
                document.getElementById('confirmDeleteBtn').disabled = true;
                document.getElementById('confirmDeleteBtn').classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                document.getElementById('deleteMessage').innerHTML = 
                    `Are you sure you want to delete device type <strong>"${typeName}"</strong>? This action cannot be undone.`;
                document.getElementById('confirmDeleteBtn').disabled = false;
                document.getElementById('confirmDeleteBtn').classList.remove('opacity-50', 'cursor-not-allowed');
            }
            
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
