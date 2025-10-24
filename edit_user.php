<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: SELECT, UPDATE with prepared statements
 * Edit user information
 */

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($user_id == 0) {
    header("Location: users.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Get user details
$userQuery = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error_message'] = "User not found!";
    header("Location: users.php");
    exit;
}

// Handle form submission
if ($_POST) {
    $f_name = trim($_POST['f_name']);
    $l_name = trim($_POST['l_name']);
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    
    if (empty($f_name) || empty($l_name) || empty($email)) {
        $error = "Please fill in all required fields!";
    } else {
        // Check if email is already taken by another user
        $checkEmailQuery = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $checkStmt = $conn->prepare($checkEmailQuery);
        $checkStmt->execute([$email, $user_id]);
        
        if ($checkStmt->fetch()) {
            $error = "Email is already taken by another user!";
        } else {
            try {
                // Update user information
                if (!empty($new_password)) {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE users SET f_name = ?, l_name = ?, email = ?, password = ?, updated_at = NOW() WHERE user_id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->execute([$f_name, $l_name, $email, $hashed_password, $user_id]);
                } else {
                    // Update without changing password
                    $updateQuery = "UPDATE users SET f_name = ?, l_name = ?, email = ?, updated_at = NOW() WHERE user_id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->execute([$f_name, $l_name, $email, $user_id]);
                }
                
                $_SESSION['success_message'] = "User updated successfully!";
                header("Location: user_details.php?id=" . $user_id);
                exit;
            } catch (PDOException $e) {
                $error = "Error updating user: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - IoT Device Manager</title>
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
                    <i class="fas fa-user-edit mr-3"></i>Edit User
                </h1>
                <p class="text-gray-600">Update user information</p>
            </div>
            <div class="flex space-x-2">
                <a href="user_details.php?id=<?php echo $user_id; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
        
        <!-- SQL Feature Info -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-database text-blue-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>SQL Features:</strong> UPDATE with prepared statements, UNIQUE constraint validation, Password hashing, NOW() timestamp function
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
        
        <!-- Edit Form -->
        <div class="bg-white rounded-lg shadow-md p-8 max-w-2xl mx-auto">
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="f_name" class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-user mr-2"></i>First Name *
                        </label>
                        <input type="text" 
                               id="f_name" 
                               name="f_name" 
                               value="<?php echo htmlspecialchars($user['f_name']); ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter first name">
                    </div>
                    
                    <div>
                        <label for="l_name" class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-user mr-2"></i>Last Name *
                        </label>
                        <input type="text" 
                               id="l_name" 
                               name="l_name" 
                               value="<?php echo htmlspecialchars($user['l_name']); ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter last name">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email Address *
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter email address">
                </div>
                
                <div class="mb-6">
                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-lock mr-2"></i>New Password
                    </label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Leave blank to keep current password">
                    <p class="text-xs text-gray-500 mt-1">Only fill this if you want to change the password</p>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-md mb-6">
                    <h3 class="font-semibold text-gray-700 mb-2">User Information</h3>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>User ID:</strong> <?php echo $user['user_id']; ?></p>
                        <p><strong>Created:</strong> <?php echo date('M j, Y H:i', strtotime($user['created_at'])); ?></p>
                        <p><strong>Last Updated:</strong> <?php echo date('M j, Y H:i', strtotime($user['updated_at'])); ?></p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <a href="user_details.php?id=<?php echo $user_id; ?>" 
                       class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
