<?php
session_start();
require_once 'config/database.php';

/**
 * SQL Features Used: SELECT with WHERE, password verification
 * Login authentication system
 */

$error = '';
$success = '';

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (!empty($email) && !empty($password)) {
        $database = new Database();
        $conn = $database->getConnection();
        
        // SQL Feature: SELECT with WHERE clause and LIMIT
        $sql = "SELECT user_id, f_name, l_name, email, password 
                FROM users 
                WHERE email = ? 
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['f_name'] . ' ' . $user['l_name'];
            $_SESSION['user_email'] = $user['email'];
            
            // SQL Feature: UPDATE with timestamp
            $updateSql = "UPDATE users SET updated_at = NOW() WHERE user_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$user['user_id']]);
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password!";
        }
    } else {
        $error = "Please fill in all fields!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sql-tooltip {
            position: relative;
            display: inline-block;
        }
        
        .sql-tooltip .tooltip-text {
            visibility: hidden;
            width: 300px;
            background-color: #1f2937;
            color: #fff;
            text-center;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -150px;
            opacity: 0;
            transition: opacity 0.3s;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            text-align: left;
        }
        
        .sql-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-600 to-purple-700 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full">
        <div class="text-center mb-8">
            <i class="fas fa-microchip text-4xl text-blue-600 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800">IoT Device Manager</h1>
            <p class="text-gray-600 mt-2">Login to access your devices</p>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>SQL Features on this page:</strong> SELECT with WHERE clause, Password verification, UPDATE with timestamp
                    </p>
                </div>
            </div>
        </div>
        
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
        
        <form method="POST" action="">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-envelope mr-2"></i>Email Address
                </label>
                <input type="email" id="email" name="email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter your email">
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter your password">
            </div>
            
            <div class="sql-tooltip w-full">
                <button type="submit" 
                        class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
                <span class="tooltip-text">
                    SQL Query on Login:<br>
                    SELECT user_id, f_name, l_name, email, password<br>
                    FROM users<br>
                    WHERE email = ?<br>
                    LIMIT 1;
                </span>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Don't have an account? 
                <a href="register.php" class="text-blue-600 hover:text-blue-800 font-semibold">Register here</a>
            </p>
        </div>
        
        <div class="mt-4 text-center">
            <a href="sql_features.php" class="text-green-600 hover:text-green-800 font-semibold">
                <i class="fas fa-code mr-1"></i>View SQL Features Used
            </a>
        </div>
        
        <div class="mt-6 bg-gray-100 p-4 rounded-md">
            <h3 class="font-semibold text-gray-700 mb-2">Demo Accounts:</h3>
            <div class="text-sm text-gray-600 space-y-1">
                <p><strong>Admin:</strong> admin@iot.com / 123456</p>
                <p><strong>User:</strong> john.doe@email.com / 123456</p>
            </div>
        </div>
    </div>
</body>
</html>