<?php
session_start();
require_once 'config/sql_features.php';

/**
 * SQL Features Documentation and Search Interface
 * This page provides comprehensive documentation of all SQL features used
 */

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';

$features = SQLFeatureTracker::getAllFeatures();
$searchResults = [];

if (!empty($searchTerm)) {
    $searchResults = SQLFeatureTracker::searchFeatures($searchTerm);
    $features = $searchResults;
}

if (!empty($selectedCategory) && isset($features[$selectedCategory])) {
    $features = [$selectedCategory => $features[$selectedCategory]];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Features Documentation - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
    <style>
        .feature-card {
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .code-block {
            background: #1f2937;
            color: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
        }
        
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .search-highlight {
            background-color: #fef3c7;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php include 'components/navbar.php'; ?>
    <?php endif; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                <i class="fas fa-database mr-3 text-blue-600"></i>
                SQL Features Documentation
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Comprehensive guide to all SQL features implemented in the IoT Device Manager system.
                This documentation demonstrates the usage of various MySQL/SQL operations throughout the application.
            </p>
        </div>
        
        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search mr-2"></i>Search SQL Features
                    </label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo htmlspecialchars($searchTerm); ?>"
                           placeholder="Search for SQL keywords, features, or descriptions..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="md:w-64">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-filter mr-2"></i>Filter by Category
                    </label>
                    <select id="category" 
                            name="category"
                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Categories</option>
                        <?php foreach (SQLFeatureTracker::getAllFeatures() as $cat => $feats): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo $selectedCategory === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </form>
            
            <?php if (!empty($searchTerm) || !empty($selectedCategory)): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php if (!empty($searchTerm)): ?>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                            Search: "<?php echo htmlspecialchars($searchTerm); ?>"
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($selectedCategory)): ?>
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                            Category: <?php echo htmlspecialchars($selectedCategory); ?>
                        </span>
                    <?php endif; ?>
                    
                    <a href="sql_features.php" class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm hover:bg-gray-200">
                        <i class="fas fa-times mr-1"></i>Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
                        <!-- Statistics -->
                <?php 
                $totalFeatures = 0;
                $totalFiles = [];
                foreach (SQLFeatureTracker::getAllFeatures() as $category => $features) {
                    $totalFeatures += count($features);
                    foreach ($features as $feature => $details) {
                        if (isset($details['files']) && is_array($details['files'])) {
                            $totalFiles = array_merge($totalFiles, $details['files']);
                        }
                    }
                }
                $uniqueFiles = count(array_unique($totalFiles));
                ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-layer-group text-3xl text-blue-600 mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo count(SQLFeatureTracker::getAllFeatures()); ?></h3>
                <p class="text-gray-600">Categories</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-code text-3xl text-green-600 mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?php 
                    $totalFeatures = 0;
                    foreach (SQLFeatureTracker::getAllFeatures() as $category => $features) {
                        $totalFeatures += count($features);
                    }
                    echo $totalFeatures;
                    ?>
                </h3>
                <p class="text-gray-600">SQL Features</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-file-code text-3xl text-purple-600 mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?php 
                    $totalFiles = [];
                    foreach (SQLFeatureTracker::getAllFeatures() as $category => $features) {
                        foreach ($features as $feature => $details) {
                            if (isset($details['files']) && is_array($details['files'])) {
                                foreach ($details['files'] as $file) {
                                    $totalFiles[$file] = true;
                                }
                            }
                        }
                    }
                    echo count($totalFiles);
                    ?>
                </h3>
                <p class="text-gray-600">Implementation Files</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-chart-line text-3xl text-orange-600 mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800">100%</h3>
                <p class="text-gray-600">Coverage</p>
            </div>
        </div>
        
        <!-- Features Documentation -->
        <?php if (empty($features)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <i class="fas fa-search text-3xl text-yellow-600 mb-4"></i>
                <h3 class="text-xl font-semibold text-yellow-800 mb-2">No Results Found</h3>
                <p class="text-yellow-700">Try adjusting your search terms or browse all categories.</p>
            </div>
        <?php else: ?>
            <?php foreach ($features as $categoryName => $categoryFeatures): ?>
                <div class="mb-12">
                    <div class="flex items-center mb-6">
                        <h2 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($categoryName); ?></h2>
                        <span class="ml-4 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                            <?php echo count($categoryFeatures); ?> features
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <?php foreach ($categoryFeatures as $featureName => $featureDetails): ?>
                            <div class="feature-card bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                                <div class="mb-4">
                                    <div class="category-badge"><?php echo htmlspecialchars($categoryName); ?></div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                                        <i class="fas fa-code mr-2 text-blue-600"></i>
                                        <?php echo htmlspecialchars($featureName); ?>
                                    </h3>
                                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars(isset($featureDetails['description']) ? $featureDetails['description'] : 'No description available'); ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-800 mb-2">
                                        <i class="fas fa-file-code mr-2"></i>Implementation Files:
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <?php if (isset($featureDetails['files']) && is_array($featureDetails['files'])): ?>
                                            <?php foreach ($featureDetails['files'] as $file): ?>
                                                <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-sm font-mono">
                                                    <?php echo htmlspecialchars($file); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-gray-200 text-gray-500 rounded text-sm">No files specified</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Example SQL Code (when available) -->
                                <?php if (isset($featureDetails['example'])): ?>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 mb-2">
                                            <i class="fas fa-terminal mr-2"></i>Example Usage:
                                        </h4>
                                        <div class="code-block">
                                            <pre><code class="language-sql"><?php echo htmlspecialchars($featureDetails['example']); ?></code></pre>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- SQL Examples Section -->
        <div class="mt-12 bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-lightbulb mr-3 text-yellow-500"></i>
                Common SQL Patterns Used
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Complex JOIN Operations</h3>
                    <div class="code-block">
                        <pre><code class="language-sql">SELECT d.d_name, dt.t_name, l.loc_name, u.f_name
FROM devices d
INNER JOIN device_types dt ON d.t_id = dt.t_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id
LEFT JOIN locations l ON dep.loc_id = l.loc_id
INNER JOIN users u ON d.user_id = u.user_id
WHERE d.status = 'active'</code></pre>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Aggregation with CASE</h3>
                    <div class="code-block">
                        <pre><code class="language-sql">SELECT dt.t_name,
    COUNT(d.d_id) as total_devices,
    SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN d.status = 'error' THEN 1 ELSE 0 END) as error_count
FROM device_types dt
LEFT JOIN devices d ON dt.t_id = d.t_id
GROUP BY dt.t_id, dt.t_name</code></pre>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Subquery with EXISTS</h3>
                    <div class="code-block">
                        <pre><code class="language-sql">SELECT d.d_name, d.status
FROM devices d
WHERE EXISTS (
    SELECT 1 FROM device_logs dl 
    WHERE dl.d_id = d.d_id 
    AND dl.log_type = 'error' 
    AND dl.resolved_by IS NULL
)</code></pre>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Window Functions</h3>
                    <div class="code-block">
                        <pre><code class="language-sql">SELECT d.d_name, dl.log_time, dl.message,
    ROW_NUMBER() OVER (PARTITION BY d.d_id ORDER BY dl.log_time DESC) as log_rank,
    COUNT(*) OVER (PARTITION BY d.d_id) as total_logs
FROM devices d
INNER JOIN device_logs dl ON d.d_id = dl.d_id</code></pre>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Navigation -->
        <div class="mt-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg shadow-md p-6 text-white">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-compass mr-2"></i>Quick Navigation
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="flex items-center p-3 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="devices.php" class="flex items-center p-3 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition">
                        <i class="fas fa-microchip mr-2"></i>Devices
                    </a>
                    <a href="analytics.php" class="flex items-center p-3 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition">
                        <i class="fas fa-chart-bar mr-2"></i>Analytics
                    </a>
                    <a href="device_logs.php" class="flex items-center p-3 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition">
                        <i class="fas fa-list-alt mr-2"></i>Logs
                    </a>
                <?php else: ?>
                    <a href="login.php" class="flex items-center p-3 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </a>
                    <a href="register.php" class="flex items-center p-3 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition">
                        <i class="fas fa-user-plus mr-2"></i>Register
                    </a>
                    <a href="index.php" class="flex items-center p-3 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition">
                        <i class="fas fa-home mr-2"></i>Home
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Highlight search terms
        function highlightSearchTerm(text, searchTerm) {
            if (!searchTerm) return text;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<span class="search-highlight">$1</span>');
        }
        
        // Auto-submit form on category change
        document.getElementById('category').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>