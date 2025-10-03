<?php
session_start();
require_once 'config/sql_features.php';

/**
 * Enhanced SQL Features Documentation with Advanced Search
 * SQL Features: Dynamic search, pattern matching, feature categorization
 */

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';

// Enhanced search with keyword mapping
$sqlKeywords = [
    'select' => ['SELECT statements', 'Basic queries', 'Column selection', 'Data retrieval'],
    'join' => ['JOIN operations', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'Table relationships'],
    'left join' => ['LEFT JOIN', 'Outer joins', 'Table relationships'],
    'inner join' => ['INNER JOIN', 'Table relationships'],
    'group' => ['GROUP BY', 'Aggregation', 'Grouping data'],
    'having' => ['HAVING clause', 'Group filtering', 'Aggregate conditions'],
    'order' => ['ORDER BY', 'Sorting', 'Data ordering'],
    'where' => ['WHERE clause', 'Filtering', 'Conditions'],
    'insert' => ['INSERT statements', 'Data insertion', 'Adding records'],
    'update' => ['UPDATE statements', 'Data modification', 'Changing records'],
    'delete' => ['DELETE statements', 'Data removal', 'Removing records'],
    'create' => ['CREATE statements', 'Table creation', 'Database objects'],
    'alter' => ['ALTER statements', 'Schema modification', 'Table changes'],
    'view' => ['Views', 'Virtual tables', 'Stored queries'],
    'procedure' => ['Stored procedures', 'Reusable code', 'Complex operations'],
    'function' => ['User-defined functions', 'Calculations', 'Reusable logic'],
    'trigger' => ['Triggers', 'Event handling', 'Automatic actions'],
    'index' => ['Indexes', 'Performance optimization', 'Query speed'],
    'constraint' => ['Constraints', 'Data integrity', 'Rules'],
    'foreign key' => ['Foreign keys', 'Referential integrity', 'Relationships'],
    'primary key' => ['Primary keys', 'Unique identification', 'Table identity'],
    'count' => ['COUNT function', 'Counting records', 'Aggregate functions'],
    'sum' => ['SUM function', 'Totaling values', 'Aggregate functions'],
    'avg' => ['AVG function', 'Average calculation', 'Aggregate functions'],
    'max' => ['MAX function', 'Maximum values', 'Aggregate functions'],
    'min' => ['MIN function', 'Minimum values', 'Aggregate functions'],
    'case' => ['CASE statements', 'Conditional logic', 'If-then logic'],
    'union' => ['UNION operations', 'Combining results', 'Set operations'],
    'subquery' => ['Subqueries', 'Nested queries', 'Query in query'],
    'cte' => ['Common Table Expressions', 'WITH clause', 'Temporary results'],
    'window' => ['Window functions', 'Analytical functions', 'Row operations'],
    'partition' => ['PARTITION BY', 'Window functions', 'Data grouping'],
    'rank' => ['RANK function', 'Ranking data', 'Window functions'],
    'row_number' => ['ROW_NUMBER function', 'Sequential numbering', 'Window functions']
];

$features = SQLFeatureTracker::getAllFeatures();
$searchResults = [];
$searchMatches = [];

if (!empty($searchTerm)) {
    $searchLower = strtolower($searchTerm);
    
    // Direct keyword search
    if (isset($sqlKeywords[$searchLower])) {
        $searchMatches = $sqlKeywords[$searchLower];
    }
    
    // Pattern search in all features
    $searchResults = SQLFeatureTracker::searchFeatures($searchTerm);
    $features = $searchResults;
}

if (!empty($selectedCategory) && isset($features[$selectedCategory])) {
    $features = [$selectedCategory => $features[$selectedCategory]];
}

// Get usage statistics
$totalFeatures = 0;
$totalExamples = 0;
foreach (SQLFeatureTracker::getAllFeatures() as $category => $categoryFeatures) {
    $totalFeatures += count($categoryFeatures);
    foreach ($categoryFeatures as $feature) {
        if (isset($feature['examples']) && is_array($feature['examples'])) {
            $totalExamples += count($feature['examples']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Features Explorer - IoT Device Manager</title>
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
        
        .search-highlight {
            background-color: #fef3c7;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .keyword-tag {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 2px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .keyword-tag:hover {
            background: #2563eb;
        }
        
        .quick-search-btn {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.875rem;
            margin: 2px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-search-btn:hover {
            background: #e5e7eb;
            border-color: #9ca3af;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                <i class="fas fa-code mr-3"></i>SQL Features Explorer
            </h1>
            <p class="text-xl text-gray-600 mb-4">
                Comprehensive documentation of SQL features used in IoT Device Manager
            </p>
            
            <!-- Statistics -->
            <div class="flex justify-center space-x-8 text-sm text-gray-500">
                <span><i class="fas fa-list mr-1"></i><?php echo $totalFeatures; ?> Features</span>
                <span><i class="fas fa-code mr-1"></i><?php echo $totalExamples; ?> Examples</span>
                <span><i class="fas fa-tags mr-1"></i><?php echo count($sqlKeywords); ?> Keywords</span>
            </div>
        </div>
        
        <!-- Enhanced Search Interface -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-search mr-2"></i>Advanced SQL Search
            </h2>
            
            <!-- Search Form -->
            <form method="GET" class="mb-6">
                <div class="flex gap-4 mb-4">
                    <div class="flex-1">
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($searchTerm); ?>"
                               placeholder="Search SQL keywords: select, join, group, having, order, etc."
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <select name="category" class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>
                            <?php foreach (array_keys(SQLFeatureTracker::getAllFeatures()) as $category): ?>
                                <option value="<?php echo $category; ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $category)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </form>
            
            <!-- Quick Search Keywords -->
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Quick Search Keywords:</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
                    <?php 
                    $popularKeywords = ['select', 'join', 'group', 'having', 'order', 'where', 'insert', 'update', 'view', 'procedure', 'function', 'index'];
                    foreach ($popularKeywords as $keyword): 
                    ?>
                        <button onclick="searchKeyword('<?php echo $keyword; ?>')" 
                                class="quick-search-btn text-left">
                            <i class="fas fa-search mr-1"></i><?php echo strtoupper($keyword); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Search Results Info -->
            <?php if (!empty($searchTerm)): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <h3 class="font-semibold text-blue-800 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>Search Results for: 
                        <span class="search-highlight"><?php echo htmlspecialchars($searchTerm); ?></span>
                    </h3>
                    
                    <?php if (!empty($searchMatches)): ?>
                        <div class="mb-3">
                            <strong>Related concepts:</strong>
                            <?php foreach ($searchMatches as $match): ?>
                                <span class="keyword-tag"><?php echo $match; ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-blue-700">
                        Found <?php echo array_sum(array_map('count', $features)); ?> matching features
                        <?php if (!empty($searchMatches)): ?>
                            related to <strong><?php echo htmlspecialchars($searchTerm); ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
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
        
        <!-- Features Display -->
        <?php if (!empty($features)): ?>
            <?php foreach ($features as $category => $categoryFeatures): ?>
                <div class="bg-white rounded-lg shadow-md mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-t-lg">
                        <h2 class="text-2xl font-bold">
                            <i class="fas fa-folder mr-2"></i>
                            <?php echo ucwords(str_replace('_', ' ', $category)); ?>
                        </h2>
                        <p class="text-blue-100 mt-2">
                            <?php echo count($categoryFeatures); ?> features in this category
                        </p>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($categoryFeatures as $featureName => $feature): ?>
                                <div class="feature-card bg-gray-50 rounded-lg p-6 border border-gray-200">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-xl font-bold text-gray-800">
                                            <?php echo htmlspecialchars($featureName); ?>
                                        </h3>
                                        <?php if (isset($feature['difficulty'])): ?>
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                                <?php 
                                                switch($feature['difficulty']) {
                                                    case 'Basic': echo 'bg-green-100 text-green-800'; break;
                                                    case 'Intermediate': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'Advanced': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo $feature['difficulty']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="text-gray-600 mb-4">
                                        <?php echo htmlspecialchars($feature['description']); ?>
                                    </p>
                                    
                                    <!-- Usage Information -->
                                    <?php if (isset($feature['usage']) && is_array($feature['usage'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-lightbulb mr-1"></i>Why & How Used:
                                            </h4>
                                            <ul class="text-sm text-gray-600 space-y-1">
                                                <?php foreach ($feature['usage'] as $use): ?>
                                                    <li class="flex items-start">
                                                        <i class="fas fa-arrow-right text-blue-500 mr-2 mt-1 text-xs"></i>
                                                        <?php echo htmlspecialchars($use); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Code Examples -->
                                    <?php if (isset($feature['examples']) && is_array($feature['examples'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-code mr-1"></i>Examples:
                                            </h4>
                                            <?php foreach ($feature['examples'] as $index => $example): ?>
                                                <div class="mb-3">
                                                    <?php if (isset($example['title'])): ?>
                                                        <p class="text-sm font-medium text-gray-700 mb-1">
                                                            <?php echo htmlspecialchars($example['title']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (isset($example['code'])): ?>
                                                        <pre class="bg-gray-800 text-gray-100 p-3 rounded text-sm overflow-x-auto"><code class="language-sql"><?php echo htmlspecialchars($example['code']); ?></code></pre>
                                                    <?php endif; ?>
                                                    <?php if (isset($example['explanation'])): ?>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            <?php echo htmlspecialchars($example['explanation']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Files Using This Feature -->
                                    <?php if (isset($feature['files']) && is_array($feature['files'])): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <h4 class="font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-file-code mr-1"></i>Used in Files:
                                            </h4>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($feature['files'] as $file): ?>
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                        <?php echo htmlspecialchars($file); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-600 mb-2">No Features Found</h3>
                <p class="text-gray-500 mb-4">
                    <?php if (!empty($searchTerm)): ?>
                        No SQL features found matching "<?php echo htmlspecialchars($searchTerm); ?>"
                    <?php else: ?>
                        No features available in the selected category.
                    <?php endif; ?>
                </p>
                <a href="sql_features.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>View All Features
                </a>
            </div>
        <?php endif; ?>
        
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
        // Search keyword function
        function searchKeyword(keyword) {
            const searchInput = document.querySelector('input[name="search"]');
            searchInput.value = keyword;
            searchInput.form.submit();
        }
        
        // Highlight search terms in results
        document.addEventListener('DOMContentLoaded', function() {
            const searchTerm = '<?php echo addslashes($searchTerm); ?>';
            if (searchTerm) {
                const elements = document.querySelectorAll('.feature-card h3, .feature-card p');
                elements.forEach(element => {
                    if (element.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                        const regex = new RegExp(`(${searchTerm})`, 'gi');
                        element.innerHTML = element.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
            }
        });
        
        // Auto-focus search input
        document.querySelector('input[name="search"]').focus();
    </script>
</body>
</html>