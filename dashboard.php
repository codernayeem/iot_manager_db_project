<?php
session_start();
require_once 'config/database.php';

/**
 * SQL Operations Dashboard
 * Showcases various SQL operations, joins, subqueries, views, functions, and procedures
 */

// Check database connection
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    header('Location: index.php');
    exit();
}

// Load all dashboard modules
$modules = [
    // JOIN Operations
    'inner_join' => 'dashboard_modules/module_inner_join.php',
    'left_join' => 'dashboard_modules/module_left_join.php',
    'right_join' => 'dashboard_modules/module_right_join.php',
    'self_join' => 'dashboard_modules/module_self_join.php',
    'cross_join' => 'dashboard_modules/module_cross_join.php',
    
    // Subquery Operations
    'subquery_scalar' => 'dashboard_modules/module_subquery_scalar.php',
    'subquery_inline' => 'dashboard_modules/module_subquery_inline.php',
    'subquery_exists' => 'dashboard_modules/module_subquery_exists.php',
    
    // Set Operations
    'union' => 'dashboard_modules/module_union.php',
    'union_all' => 'dashboard_modules/module_union_all.php',
    'intersect' => 'dashboard_modules/module_intersect.php',
    'except' => 'dashboard_modules/module_except.php',
    
    // Saved Database Objects
    'view_deployment' => 'dashboard_modules/module_view_deployment.php',
    'view_critical_logs' => 'dashboard_modules/module_view_critical_logs.php',
    'function_alert_summary' => 'dashboard_modules/module_function_alert_summary.php',
    'function_health_score' => 'dashboard_modules/module_function_health_score.php',
    'procedure_device_report' => 'dashboard_modules/module_procedure_device_report.php',
    
    // Advanced SQL Features
    'aggregate_functions' => 'dashboard_modules/module_aggregate_functions.php',
    'window_functions' => 'dashboard_modules/module_window_functions.php',
    'case_when' => 'dashboard_modules/module_case_when.php',
    'having_clause' => 'dashboard_modules/module_having_clause.php',
    
    // SQL Operators
    'group_concat' => 'dashboard_modules/module_group_concat.php',
    'in_operator' => 'dashboard_modules/module_in_operator.php',
    'between_operator' => 'dashboard_modules/module_between_operator.php',
    'like_operator' => 'dashboard_modules/module_like_operator.php',
    'null_handling' => 'dashboard_modules/module_null_handling.php',
    'distinct' => 'dashboard_modules/module_distinct.php',
    
    // Built-in Functions
    'string_functions' => 'dashboard_modules/module_string_functions.php',
    'date_functions' => 'dashboard_modules/module_date_functions.php',
];

// Load module data - isolate each module to avoid function conflicts
$moduleData = [];
foreach ($modules as $key => $file) {
    if (file_exists($file)) {
        // Use a closure to isolate the module scope
        $moduleData[$key] = (function($modulePath) {
            // Capture the module's returned data
            $getData = function() use ($modulePath) {
                return include $modulePath;
            };
            return $getData();
        })($file);
    }
}

// Function to render a module card
function renderModuleCard($data) {
    if (!$data || !isset($data['data'])) return '';
    
    $title = htmlspecialchars($data['title'] ?? 'Untitled');
    $description = htmlspecialchars($data['description'] ?? '');
    $icon = $data['icon'] ?? 'fa-database';
    $sql = htmlspecialchars($data['sql'] ?? '');
    $results = $data['data'];
    
    $rowCount = count($results);
    $cardId = 'module-' . md5($title);
    
    ob_start();
    ?>
    <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden">
        <!-- Module Header -->
        <div class="bg-gradient-to-r from-gray-700 to-gray-800 text-white p-4">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h3 class="text-lg font-bold flex items-center mb-1">
                        <i class="fas <?php echo $icon; ?> mr-2"></i>
                        <?php echo $title; ?>
                    </h3>
                    <p class="text-sm text-gray-300"><?php echo $description; ?></p>
                </div>
                <button 
                    onclick="toggleSQL('<?php echo $cardId; ?>')"
                    class="ml-3 bg-white/20 hover:bg-white/30 px-3 py-1 rounded text-sm transition-colors"
                    title="View SQL Code">
                    <i class="fas fa-code"></i> SQL
                </button>
            </div>
        </div>
        
        <!-- SQL Code (Hidden by default) -->
        <div id="sql-<?php echo $cardId; ?>" class="hidden bg-gray-900 text-gray-100 p-4 overflow-x-auto">
            <pre class="text-xs font-mono whitespace-pre-wrap"><?php echo $sql; ?></pre>
        </div>
        
        <!-- Results Info -->
        <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
            <span class="text-sm text-gray-600">
                <i class="fas fa-table mr-1"></i>
                <strong><?php echo $rowCount; ?></strong> rows returned
            </span>
            <button 
                onclick="toggleTable('<?php echo $cardId; ?>')"
                class="text-sm text-blue-600 hover:text-blue-800 font-medium"
                id="toggle-btn-<?php echo $cardId; ?>">
                <i class="fas fa-chevron-down mr-1"></i>Show Results
            </button>
        </div>
        
        <!-- Results Table (Hidden by default) -->
        <div id="table-<?php echo $cardId; ?>" class="hidden overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <?php if ($rowCount > 0): ?>
                    <thead class="bg-gray-100">
                        <tr>
                            <?php foreach (array_keys($results[0]) as $column): ?>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <?php echo htmlspecialchars($column); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <?php foreach ($row as $cell): ?>
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        <?php 
                                        if ($cell === null) {
                                            echo '<span class="text-gray-400 italic">NULL</span>';
                                        } else {
                                            echo htmlspecialchars($cell);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php else: ?>
                    <tbody>
                        <tr>
                            <td class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-2"></i>
                                <p>No data available</p>
                            </td>
                        </tr>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Operations Dashboard - IoT Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-section {
            scroll-margin-top: 100px;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 20;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .module-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Dashboard Header -->
        <div class="sticky-header bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                        SQL Operations Dashboard
                    </h3>
                </div>
                <div class="flex gap-2">
                    <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                        <i class="fas fa-database mr-2"></i>Database Setup
                    </a>
                    <button onclick="expandAll()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                        <i class="fas fa-expand-alt mr-2"></i>Expand All
                    </button>
                    <button onclick="collapseAll()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                        <i class="fas fa-compress-alt mr-2"></i>Collapse All
                    </button>
                </div>
            </div>
            
            <!-- Quick Navigation -->
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="#joins" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm hover:bg-blue-200 transition">
                    <i class="fas fa-link mr-1"></i>JOINs
                </a>
                <a href="#subqueries" class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm hover:bg-purple-200 transition">
                    <i class="fas fa-layer-group mr-1"></i>Subqueries
                </a>
                <a href="#set-ops" class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm hover:bg-green-200 transition">
                    <i class="fas fa-object-group mr-1"></i>Set Operations
                </a>
                <a href="#db-objects" class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-sm hover:bg-orange-200 transition">
                    <i class="fas fa-database mr-1"></i>Views/Functions/Procedures
                </a>
                <a href="#advanced" class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm hover:bg-red-200 transition">
                    <i class="fas fa-star mr-1"></i>Advanced SQL
                </a>
                <a href="#operators" class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm hover:bg-indigo-200 transition">
                    <i class="fas fa-sliders-h mr-1"></i>Operators
                </a>
                <a href="#functions" class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm hover:bg-yellow-200 transition">
                    <i class="fas fa-function mr-1"></i>Built-in Functions
                </a>
            </div>
        </div>
        
        <!-- JOIN Operations -->
        <div id="joins" class="category-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-link text-blue-600 mr-3"></i>
                JOIN Operations
            </h2>
            <div class="module-grid">
                <?php
                echo renderModuleCard($moduleData['inner_join'] ?? []);
                echo renderModuleCard($moduleData['left_join'] ?? []);
                echo renderModuleCard($moduleData['right_join'] ?? []);
                echo renderModuleCard($moduleData['self_join'] ?? []);
                echo renderModuleCard($moduleData['cross_join'] ?? []);
                ?>
            </div>
        </div>
        
        <!-- Subqueries -->
        <div id="subqueries" class="category-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-layer-group text-purple-600 mr-3"></i>
                Subquery Operations
            </h2>
            <div class="module-grid">
                <?php
                echo renderModuleCard($moduleData['subquery_scalar'] ?? []);
                echo renderModuleCard($moduleData['subquery_inline'] ?? []);
                echo renderModuleCard($moduleData['subquery_exists'] ?? []);
                ?>
            </div>
        </div>
        
        <!-- Set Operations -->
        <div id="set-ops" class="category-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-object-group text-green-600 mr-3"></i>
                Set Operations
            </h2>
            <div class="module-grid">
                <?php
                echo renderModuleCard($moduleData['union'] ?? []);
                echo renderModuleCard($moduleData['union_all'] ?? []);
                echo renderModuleCard($moduleData['intersect'] ?? []);
                echo renderModuleCard($moduleData['except'] ?? []);
                ?>
            </div>
        </div>
        
        <!-- Database Objects (Views, Functions, Procedures) -->
        <div id="db-objects" class="category-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-database text-orange-600 mr-3"></i>
                Saved Database Objects (Views, Functions, Procedures)
            </h2>
            <div class="module-grid">
                <?php
                echo renderModuleCard($moduleData['view_deployment'] ?? []);
                echo renderModuleCard($moduleData['view_critical_logs'] ?? []);
                echo renderModuleCard($moduleData['function_alert_summary'] ?? []);
                echo renderModuleCard($moduleData['function_health_score'] ?? []);
                echo renderModuleCard($moduleData['procedure_device_report'] ?? []);
                ?>
            </div>
        </div>
        
        <!-- Advanced SQL -->
        <div id="advanced" class="category-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-star text-red-600 mr-3"></i>
                Advanced SQL Features
            </h2>
            <div class="module-grid">
                <?php
                echo renderModuleCard($moduleData['aggregate_functions'] ?? []);
                echo renderModuleCard($moduleData['window_functions'] ?? []);
                echo renderModuleCard($moduleData['case_when'] ?? []);
                echo renderModuleCard($moduleData['having_clause'] ?? []);
                echo renderModuleCard($moduleData['group_concat'] ?? []);
                echo renderModuleCard($moduleData['distinct'] ?? []);
                ?>
            </div>
        </div>
        
        <!-- SQL Operators -->
        <div id="operators" class="category-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-sliders-h text-indigo-600 mr-3"></i>
                SQL Operators & Conditions
            </h2>
            <div class="module-grid">
                <?php
                echo renderModuleCard($moduleData['in_operator'] ?? []);
                echo renderModuleCard($moduleData['between_operator'] ?? []);
                echo renderModuleCard($moduleData['like_operator'] ?? []);
                echo renderModuleCard($moduleData['null_handling'] ?? []);
                ?>
            </div>
        </div>
        
        <!-- Built-in Functions -->
        <div id="functions" class="category-section mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-function text-yellow-600 mr-3"></i>
                Built-in Functions
            </h2>
            <div class="module-grid">
                <?php
                echo renderModuleCard($moduleData['string_functions'] ?? []);
                echo renderModuleCard($moduleData['date_functions'] ?? []);
                ?>
            </div>
        </div>
        
        <!-- Footer Stats -->
        <div class="bg-white rounded-lg shadow-sm p-6 mt-8">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                <div>
                    <div class="text-3xl font-bold text-blue-600">29+</div>
                    <div class="text-sm text-gray-600">SQL Operations</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-green-600">5</div>
                    <div class="text-sm text-gray-600">JOIN Types</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-purple-600">4</div>
                    <div class="text-sm text-gray-600">Set Operations</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-orange-600">5</div>
                    <div class="text-sm text-gray-600">DB Objects</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-red-600">6+</div>
                    <div class="text-sm text-gray-600">Advanced Features</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSQL(cardId) {
            const sqlDiv = document.getElementById('sql-' + cardId);
            sqlDiv.classList.toggle('hidden');
        }
        
        function toggleTable(cardId) {
            const tableDiv = document.getElementById('table-' + cardId);
            const toggleBtn = document.getElementById('toggle-btn-' + cardId);
            
            if (tableDiv.classList.contains('hidden')) {
                tableDiv.classList.remove('hidden');
                toggleBtn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>Hide Results';
            } else {
                tableDiv.classList.add('hidden');
                toggleBtn.innerHTML = '<i class="fas fa-chevron-down mr-1"></i>Show Results';
            }
        }
        
        function expandAll() {
            document.querySelectorAll('[id^="table-"]').forEach(el => {
                el.classList.remove('hidden');
            });
            document.querySelectorAll('[id^="toggle-btn-"]').forEach(btn => {
                btn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>Hide Results';
            });
        }
        
        function collapseAll() {
            document.querySelectorAll('[id^="table-"]').forEach(el => {
                el.classList.add('hidden');
            });
            document.querySelectorAll('[id^="toggle-btn-"]').forEach(btn => {
                btn.innerHTML = '<i class="fas fa-chevron-down mr-1"></i>Show Results';
            });
        }
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
    
    </div> <!-- End main-content wrapper -->
</body>
</html>
