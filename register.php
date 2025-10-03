<?php
session_start();
require_once 'config/database.php';

/**
 * SQL Features Used: INSERT, UNIQUE constraints, password hashing
 * User registration system
 */

$error = '';
$success = '';

if ($_POST) {
    $f_name = trim($_POST['f_name']);
    $l_name = trim($_POST['l_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!empty($f_name) && !empty($l_name) && !empty($email) && !empty($password)) {
        if ($password === $confirm_password) {
            $database = new Database();
            $conn = $database->getConnection();
            
            // SQL Feature: SELECT with EXISTS to check email uniqueness
            $checkSql = "SELECT EXISTS(SELECT 1 FROM users WHERE email = ?) as email_exists";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$email]);
            $emailExists = $checkStmt->fetchColumn();
            
            if (!$emailExists) {
                // SQL Feature: INSERT with password hashing
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (f_name, l_name, email, password) VALUES (?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt->execute([$f_name, $l_name, $email, $hashedPassword])) {
                    $success = "Registration successful! You can now login.";
                } else {
                    $error = "Registration failed. Please try again.";
                }
            } else {
                $error = "Email address already exists!";
            }
        } else {
            $error = "Passwords do not match!";
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
    <title>Register - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sql-tooltip {
            position: relative;
            display: inline-block;
        }
        
        .sql-tooltip .tooltip-text {
            visibility: hidden;
            width: 320px;
            background-color: #1f2937;
            color: #fff;
            text-center;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -160px;
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
<body class="bg-gradient-to-br from-green-600 to-blue-700 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full">
        <div class="text-center mb-8">
            <i class="fas fa-user-plus text-4xl text-green-600 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800">Create Account</h1>
            <p class="text-gray-600 mt-2">Join the IoT Device Manager</p>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        <strong>SQL Features:</strong> INSERT statement, UNIQUE constraint validation, EXISTS clause, Password hashing
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
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="f_name" class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-user mr-2"></i>First Name
                    </label>
                    <input type="text" id="f_name" name="f_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                           placeholder="First name">
                </div>
                
                <div>
                    <label for="l_name" class="block text-gray-700 text-sm font-bold mb-2">
                        Last Name
                    </label>
                    <input type="text" id="l_name" name="l_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                           placeholder="Last name">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-envelope mr-2"></i>Email Address
                </label>
                <input type="email" id="email" name="email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                       placeholder="Enter your email">
            </div>
            
            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <input type="password" id="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                       placeholder="Enter your password">
            </div>
            
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-lock mr-2"></i>Confirm Password
                </label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                       placeholder="Confirm your password">
            </div>
            
            <div class="sql-tooltip w-full">
                <button type="submit" 
                        class="w-full bg-green-600 text-white font-bold py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-200">
                    <i class="fas fa-user-plus mr-2"></i>Create Account
                </button>
                <span class="tooltip-text">
                    SQL Queries on Registration:<br><br>
                    1. Check email uniqueness:<br>
                    SELECT EXISTS(SELECT 1 FROM users WHERE email = ?) as email_exists<br><br>
                    2. Insert new user:<br>
                    INSERT INTO users (f_name, l_name, email, password)<br>
                    VALUES (?, ?, ?, ?)
                </span>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Already have an account? 
                <a href="login.php" class="text-green-600 hover:text-green-800 font-semibold">Login here</a>
            </p>
        </div>
        
        <div class="mt-4 text-center">
            <a href="sql_features.php" class="text-blue-600 hover:text-blue-800 font-semibold">
                <i class="fas fa-code mr-1"></i>View SQL Features Used
            </a>
        </div>
    </div>
</body>
</html>