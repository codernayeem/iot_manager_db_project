<?php
session_start();
require_once 'config/database.php';

/**
 * Database Management Dashboard
 * SQL Features: Database creation, Table management, Advanced SQL objects
 * Now using API-based architecture for real-time updates
 */

// Display session messages if any
$message = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get current configuration
$database = new Database();
$currentConfig = $database->getConfig();

// Actions are now handled via API
// Status will be loaded via JavaScript API calls


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Device Manager - Database Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="js/database-manager.js" defer></script>
    <style>
        .status-good { color: #10b981; }
        .status-missing { color: #ef4444; }
        .status-partial { color: #f59e0b; }
        
        .log-entry {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include 'components/navbar.php'; ?>
    
        <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-3">
                <i class="fas fa-database text-2xl text-blue-600"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">IoT Device Manager</h1>
                    <p class="text-sm text-gray-600">Database Management Dashboard</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-600"><strong>Md. Nayeem</strong> (2107050)</p>
                <a href="https://github.com/codernayeem" class="text-blue-600 hover:underline">@codernayeem</a>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded mb-3 text-sm">
                <i class="fas fa-check-circle mr-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded mb-3 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        

        
        <!-- Database Status Overview -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-heartbeat mr-2"></i>Database Status
                </h2>
                <a href="db_config.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm transition-colors">
                    <i class="fas fa-cog"></i> Database Config
                </a>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <!-- Database Status -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Database</h3>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span>Connection:</span>
                            <span id="connection-status" class="connection-status status-missing">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Database:</span>
                            <span id="database-status" class="database-status status-missing cursor-pointer" 
                                  data-sql-type="create_database" 
                                  title="Click to see SQL commands">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </div>
                    </div>
                    </br>
                    <div class="text-xs text-gray-600 mb-1 font-mono">
                        <?php echo htmlspecialchars($currentConfig['host'] . ':' . ($currentConfig['port'] ?? 3306)); ?>
                    </div>
                    <div class="text-xs text-blue-600 font-semibold">
                        <?php echo htmlspecialchars($currentConfig['db_name']); ?>
                    </div>
                </div>
                
                <!-- Tables Status -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Tables <span id="tables-status" class="table-count">(0/6)</span></h3>
                    <div class="space-y-1 text-xs">
                        <?php foreach (['users', 'device_types', 'devices', 'locations', 'deployments', 'device_logs'] as $table): ?>
                            <div class="flex justify-between">
                                <span data-table="<?php echo $table; ?>" 
                                      class="cursor-pointer underline"
                                      title="Click for table details">
                                    <?php echo $table; ?>:
                                </span>
                                <span data-table-status="<?php echo $table; ?>"
                                      data-sql-type="table"
                                      data-sql-name="<?php echo $table; ?>"
                                      class="status-missing cursor-pointer"
                                      title="Click to see CREATE TABLE SQL">
                                    <i class="fas fa-times"></i>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Views Status -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Views <span id="views-status" class="view-count">(0/3)</span></h3>
                    <div class="space-y-1 text-xs">
                        <?php foreach (['v_device_summary', 'v_log_analysis', 'v_resolver_performance'] as $view): ?>
                            <div class="flex justify-between">
                                <span data-sql-type="view" 
                                      data-sql-name="<?php echo $view; ?>"
                                      class="cursor-pointer underline"
                                      title="Click to see SQL">
                                    <?php echo str_replace('v_', '', $view); ?>:
                                </span>
                                <span data-view-status="<?php echo $view; ?>"
                                      class="status-missing"
                                      title="CREATE VIEW <?php echo $view; ?> AS ...">
                                    <i class="fas fa-times"></i>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Procedures & Functions -->
                <div class="bg-gray-50 rounded p-3 text-sm">
                    <h3 class="font-semibold text-gray-700 mb-2">Procedures & Functions</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span data-sql-type="procedures" 
                                  class="cursor-pointer underline"
                                  title="Click to see SQL">
                                Procedures:
                            </span>
                            <span id="procedure-count" class="procedure-count status-missing"
                                  title="CREATE PROCEDURE sp_...">
                                0/4
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span data-sql-type="functions" 
                                  class="cursor-pointer underline"
                                  title="Click to see SQL">
                                Functions:
                            </span>
                            <span id="function-count" class="function-count status-missing"
                                  title="CREATE FUNCTION fn_...">
                                0/3
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Table Overview Section (Managed by JavaScript) -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4" id="tableOverview" style="display: none;">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-table mr-2"></i>Table Overview
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="tables-overview">
                <!-- Tables will be populated by JavaScript -->
            </div>
        </div>
        
        <!-- Management Actions -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <h2 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-cogs mr-2"></i>Database Management & Application Access
            </h2>
            
            <!-- Do All Button -->
            <div class="mb-4">
                <div class="text-center">
                    <button data-action="setup_all" 
                            class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition text-sm font-medium"
                            title="CREATE DATABASE + CREATE TABLES + INSERT DATA">
                        <i class="fas fa-magic mr-2"></i>Setup Complete Database
                    </button>
                    <p class="text-xs text-gray-500 mt-1">Creates database, tables, views, procedures, functions & inserts sample data</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Database Actions -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3">Database Operations</h3>
                <div class="space-y-3">
                <!-- Create Database -->
                <button data-action="create_database" 
                        class="w-full bg-blue-600 text-white py-2 px-3 rounded hover:bg-blue-700 transition text-sm"
                        title="CREATE DATABASE iot_device_manager">
                    <i class="fas fa-database mr-1"></i>
                    Create Database
                </button>
                
                <!-- Create Tables -->
                <button data-action="create_tables" 
                        class="w-full bg-green-600 text-white py-2 px-3 rounded hover:bg-green-700 transition text-sm"
                        title="CREATE TABLES + VIEWS + PROCEDURES + FUNCTIONS">
                    <i class="fas fa-table mr-1"></i>
                    Create Tables
                </button>
                
                <!-- Insert Sample Data -->
                <button data-action="insert_sample_data" 
                        class="w-full bg-orange-600 text-white py-2 px-3 rounded hover:bg-orange-700 transition text-sm"
                        title="INSERT INTO tables VALUES (...)">
                    <i class="fas fa-download mr-1"></i>
                    Insert Sample Data
                </button>
                
                <!-- Reset Database -->
                <button data-action="reset_database" 
                        class="w-full bg-red-600 text-white py-2 px-3 rounded hover:bg-red-700 transition text-sm"
                        title="DROP DATABASE iot_device_manager">
                    <i class="fas fa-trash mr-1"></i>
                    Reset Database
                </button>
                </div>
            </div>
            
            <!-- Application Access -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3">Application Access</h3>
                <div class="space-y-3">
                    <a href="login.php" class="block bg-blue-600 text-white py-2 px-3 rounded text-center hover:bg-blue-700 transition text-sm">
                        <i class="fas fa-sign-in-alt mr-1"></i>Login
                    </a>
                    
                    <a href="register.php" class="block bg-gray-600 text-white py-2 px-3 rounded text-center hover:bg-gray-700 transition text-sm">
                        <i class="fas fa-user-plus mr-1"></i>Register
                    </a>
                    
                    <a href="dashboard.php" class="block bg-purple-600 text-white py-2 px-3 rounded text-center hover:bg-purple-700 transition text-sm">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Additional Tools -->
            <div>
                <h3 class="text-md font-semibold text-gray-700 mb-3">Tools & Features</h3>
                <div class="space-y-3">
                    <a href="sql_features.php" class="block bg-green-600 text-white py-2 px-3 rounded text-center hover:bg-green-700 transition text-sm">
                        <i class="fas fa-code mr-1"></i>SQL Features
                    </a>
                    
                    <a href="analytics.php" class="block bg-indigo-600 text-white py-2 px-3 rounded text-center hover:bg-indigo-700 transition text-sm">
                        <i class="fas fa-chart-line mr-1"></i>Analytics
                    </a>
                    
                    <a href="api-test.html" class="block bg-yellow-600 text-white py-2 px-3 rounded text-center hover:bg-yellow-700 transition text-sm">
                        <i class="fas fa-flask mr-1"></i>API Test
                    </a>
                    
                    <button onclick="window.dbManager?.refreshStatus()" class="w-full bg-gray-600 text-white py-2 px-3 rounded hover:bg-gray-700 transition text-sm">
                        <i class="fas fa-sync mr-1"></i>Refresh Status
                    </button>
                </div>
            </div>
            </div>
        </div>
    </div>
    
    <!-- SQL Modal -->
    <div id="sqlModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="modal-overlay flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-[80vh] overflow-y-auto">
                <div class="bg-blue-600 text-white p-4 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="sqlModalTitle">SQL Commands</h3>
                        <button class="modal-close text-white hover:text-gray-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6" id="sqlModalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Table Details Modal -->
    <div id="tableModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="modal-overlay flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-5xl w-full max-h-[80vh] overflow-y-auto">
                <div class="bg-green-600 text-white p-4 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="tableModalTitle">Table Structure</h3>
                        <button class="modal-close text-white hover:text-gray-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6" id="tableModalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-40 hidden">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i>
            <span class="text-gray-700 font-medium">Processing...</span>
        </div>
    </div>
    
    <!-- Tooltip -->
    <div id="tooltip" class="fixed bg-gray-800 text-white p-2 rounded text-xs z-50 hidden max-w-xs"></div>
    
    <script>
        // Simple tooltip for backward compatibility
        function showTooltip(element, text) {
            const tooltip = document.getElementById('tooltip');
            if (tooltip) {
                tooltip.textContent = text;
                tooltip.classList.remove('hidden');
                
                const rect = element.getBoundingClientRect();
                tooltip.style.left = (rect.left + window.scrollX) + 'px';
                tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 5) + 'px';
            }
        }
        
        function hideTooltip() {
            const tooltip = document.getElementById('tooltip');
            if (tooltip) {
                tooltip.classList.add('hidden');
            }
        }
    </script>
    
        </div> <!-- End container -->
    </div> <!-- End main-content wrapper -->
</body>
</html>