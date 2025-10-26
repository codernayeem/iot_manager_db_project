<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * User Management Page
 * SQL Features Used: 
 * - LEFT JOIN (users with devices)
 * - GROUP BY with multiple columns
 * - COUNT with DISTINCT
 * - Aggregate functions (COUNT, SUM)
 * - CASE expressions for conditional counting
 * - DATE functions (DATE_SUB, NOW, INTERVAL)
 * - Dynamic WHERE clause construction
 * - LIKE pattern matching for search
 * - ORDER BY with dynamic sorting
 * - LIMIT and OFFSET for pagination
 * - Prepared statements with parameterized queries
 */

$database = new Database();
$conn = $database->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'u.created_at';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build WHERE clause
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.f_name LIKE ? OR u.l_name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// SQL Feature: Complex query with aggregation
$usersQuery = "
    SELECT 
        u.user_id,
        u.f_name,
        u.l_name,
        u.email,
        u.created_at,
        u.updated_at,
        COUNT(DISTINCT d.d_id) as device_count
    FROM users u
    LEFT JOIN devices d ON u.user_id = d.user_id
    $whereClause
    GROUP BY u.user_id, u.f_name, u.l_name, u.email, u.created_at, u.updated_at
    ORDER BY $sortBy $sortOrder
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($usersQuery);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total users for pagination
$countQuery = "
    SELECT COUNT(DISTINCT u.user_id) as total
    FROM users u
    $whereClause
";

$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Get overall statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30days
    FROM users u
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - IoT Device Manager</title>
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
        
        .user-card {
            transition: all 0.3s ease;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-users mr-3"></i>User Management
                </h1>
                <p class="text-gray-600">Manage system users and permissions</p>
            </div>
            <a href="register.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-user-plus mr-2"></i>Add New User
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
                        <strong>SQL Features Used:</strong> 
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">LEFT JOIN (users → devices)</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">GROUP BY with aggregation</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">COUNT DISTINCT</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">CASE expressions</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">DATE_SUB & INTERVAL</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">LIKE pattern matching</span>
                        <span class="inline-block px-2 py-1 bg-white rounded mr-2">Dynamic sorting & pagination</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_users']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(*) FROM users
                </span>
            </div>
            
            <div class="sql-tooltip">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-plus text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">New (30 days)</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['new_users_30days']; ?></p>
                        </div>
                    </div>
                </div>
                <span class="tooltip-text">
                    SQL Query:<br>
                    SELECT COUNT(*) FROM users<br>
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                </span>
            </div>
        </div>
        
        <!-- Search and Filter Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Name or email..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="w-48">
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select id="sort" name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="u.created_at" <?php echo $sortBy === 'u.created_at' ? 'selected' : ''; ?>>Date Joined</option>
                        <option value="u.f_name" <?php echo $sortBy === 'u.f_name' ? 'selected' : ''; ?>>First Name</option>
                        <option value="u.l_name" <?php echo $sortBy === 'u.l_name' ? 'selected' : ''; ?>>Last Name</option>
                        <option value="u.email" <?php echo $sortBy === 'u.email' ? 'selected' : ''; ?>>Email</option>
                    </select>
                    <input type="hidden" name="order" value="<?php echo $sortOrder; ?>">
                </div>
                
                <div class="flex items-end space-x-2">
                    <div class="sql-tooltip">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <span class="tooltip-text">
                            SQL Query with multiple subqueries:<br><br>
                            SELECT u.*, COUNT(DISTINCT d.d_id) as device_count,<br>
                            (SELECT COUNT(*) FROM device_logs dl<br>
                            WHERE dl.resolved_by = u.user_id) as logs_resolved,<br>
                            (SELECT COUNT(*) FROM deployments dep<br>
                            WHERE dep.d_id = d3.d_id AND d3.user_id = u.user_id) as deployments_made<br>
                            FROM users u<br>
                            LEFT JOIN devices d ON u.user_id = d.user_id<br>
                            GROUP BY u.user_id<br>
                            ORDER BY <?php echo $sortBy; ?> <?php echo $sortOrder; ?><br>
                            LIMIT <?php echo $limit; ?> OFFSET <?php echo $offset; ?>
                        </span>
                    </div>
                    
                    <a href="users.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Results Summary -->
        <div class="flex justify-between items-center mb-6">
            <div class="text-gray-600">
                Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> users
                <?php if (!empty($search)): ?>
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
        
        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Devices</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex items-center justify-center w-10 h-10 bg-blue-600 rounded-full">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['f_name'] . ' ' . $user['l_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                ID: <?php echo $user['user_id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800" title="Total device count">
                                            <i class="fas fa-microchip mr-1"></i><?php echo $user['device_count']; ?> Device<?php echo $user['device_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('g:i A', strtotime($user['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="user_details.php?id=<?php echo $user['user_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900"
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" 
                                           class="text-green-600 hover:text-green-900"
                                           title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="devices.php?owner=<?php echo $user['user_id']; ?>" 
                                           class="text-purple-600 hover:text-purple-900"
                                           title="View User's Devices">
                                            <i class="fas fa-microchip"></i>
                                        </a>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <a href="#" 
                                               onclick="confirmDelete(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['f_name'] . ' ' . $user['l_name']); ?>')"
                                               class="text-red-600 hover:text-red-900"
                                               title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (empty($users)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center mt-8">
                <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No users found</h3>
                <p class="text-gray-500 mb-4">Try adjusting your search criteria.</p>
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
    
    <script>
        function confirmDelete(userId, userName) {
            if (confirm('Are you sure you want to delete user "' + userName + '"? This action cannot be undone.')) {
                window.location.href = 'delete_user.php?id=' + userId;
            }
        }
    </script>
</body>
</html>
