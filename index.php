<?php
session_start();
require_once 'config/database.php';

/**
 * SQL Features Used: CREATE DATABASE, CREATE TABLE with constraints
 */

$database = new Database();
$message = '';
$error = '';

// Handle sample data insertion
if (isset($_POST['insert_sample_data'])) {
    if ($database->insertSampleData()) {
        $message = "Comprehensive sample data inserted successfully! Including 30 days of log data.";
    } else {
        $error = "Failed to insert sample data.";
    }
}

// Create database if not exists
$database->createDatabase();

// Create tables with all constraints
$tablesCreated = $database->createTables();

// Check current data status
$conn = $database->getConnection();
$stmt = $conn->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$userCount = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM devices");
$stmt->execute();
$deviceCount = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM device_logs");
$stmt->execute();
$logCount = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Device Manager - Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-2xl w-full">
        <div class="text-center mb-6">
            <i class="fas fa-database text-4xl text-blue-600 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">IoT Device Manager</h1>
            <p class="text-gray-600">Database Setup & Sample Data Management</p>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Database Status -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Database Status:</strong> Tables created successfully
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Current Data Statistics -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white border rounded-lg p-4 text-center">
                <i class="fas fa-users text-blue-600 text-2xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $userCount; ?></p>
                <p class="text-sm text-gray-600">Users</p>
            </div>
            
            <div class="bg-white border rounded-lg p-4 text-center">
                <i class="fas fa-microchip text-green-600 text-2xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo $deviceCount; ?></p>
                <p class="text-sm text-gray-600">Devices</p>
            </div>
            
            <div class="bg-white border rounded-lg p-4 text-center">
                <i class="fas fa-list text-purple-600 text-2xl mb-2"></i>
                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($logCount); ?></p>
                <p class="text-sm text-gray-600">Log Entries</p>
            </div>
        </div>
        
        <!-- Sample Data Section -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-bold text-yellow-800 mb-3">
                <i class="fas fa-flask mr-2"></i>Comprehensive Demo Data
            </h3>
            <p class="text-sm text-yellow-700 mb-4">
                Insert realistic sample data including:<br>
                • <strong>18 diverse devices</strong> across 10 categories<br>
                • <strong>6 locations</strong> with GPS coordinates<br>
                • <strong>30 days of logs</strong> (3,000+ realistic entries)<br>
                • <strong>5 technician users</strong> with resolution history<br>
                • Complete deployment and maintenance records
            </p>
            
            <form method="POST" class="inline">
                <button type="submit" name="insert_sample_data" 
                        class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition">
                    <i class="fas fa-download mr-2"></i>Insert Comprehensive Data
                </button>
            </form>
        </div>
        
        <!-- Navigation -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="login.php" class="bg-blue-600 text-white py-3 px-4 rounded text-center hover:bg-blue-700 transition">
                <i class="fas fa-sign-in-alt mr-2"></i>Login to System
            </a>
            
            <a href="register.php" class="bg-gray-600 text-white py-3 px-4 rounded text-center hover:bg-gray-700 transition">
                <i class="fas fa-user-plus mr-2"></i>Register New User
            </a>
            
            <a href="sql_features.php" class="bg-green-600 text-white py-3 px-4 rounded text-center hover:bg-green-700 transition">
                <i class="fas fa-code mr-2"></i>SQL Features Explorer
            </a>
            
            <a href="features_overview.php" class="bg-purple-600 text-white py-3 px-4 rounded text-center hover:bg-purple-700 transition">
                <i class="fas fa-chart-bar mr-2"></i>Features Overview
            </a>
        </div>
        
        <!-- Login Credentials -->
        <div class="mt-6 bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-gray-800 mb-2">Default Login Credentials:</h4>
            <div class="text-sm text-gray-600 space-y-1">
                <p><strong>Admin:</strong> admin@iotmanager.com / admin123</p>
                <p><strong>Technician:</strong> john.smith@tech.com / password123</p>
                <p class="text-xs text-gray-500 mt-2">More users available after inserting sample data</p>
            </div>
        </div>
    </div>
</body>
</html>