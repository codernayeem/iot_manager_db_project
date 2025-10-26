<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: DELETE with foreign key constraints handling
 * Delete user with proper cascade/restrict logic
 */

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id == 0 || $user_id == $_SESSION['user_id']) {
    $_SESSION['error_message'] = "Cannot delete this user!";
    header("Location: users.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Check if user has devices (foreign key constraint will prevent deletion)
    $checkDevicesQuery = "SELECT COUNT(*) as device_count FROM devices WHERE user_id = ?";
    $checkStmt = $conn->prepare($checkDevicesQuery);
    $checkStmt->execute([$user_id]);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['device_count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete user! User owns {$result['device_count']} device(s). Please reassign or delete devices first.";
        header("Location: users.php");
        exit;
    }
    
    // Check if user has resolved logs (will be set to NULL due to ON DELETE SET NULL)
    $checkLogsQuery = "SELECT COUNT(*) as log_count FROM device_logs WHERE resolved_by = ?";
    $checkLogsStmt = $conn->prepare($checkLogsQuery);
    $checkLogsStmt->execute([$user_id]);
    $logResult = $checkLogsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user owns devices that have deployments
    $checkDeploymentsQuery = "SELECT COUNT(*) as deployment_count FROM deployments dep
                               INNER JOIN devices d ON dep.d_id = d.d_id
                               WHERE d.user_id = ?";
    $checkDepStmt = $conn->prepare($checkDeploymentsQuery);
    $checkDepStmt->execute([$user_id]);
    $depResult = $checkDepStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($depResult['deployment_count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete user! User has {$depResult['deployment_count']} deployment(s). Please remove deployments first.";
        header("Location: users.php");
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update device_logs to set resolved_by to NULL (if any)
    if ($logResult['log_count'] > 0) {
        $updateLogsQuery = "UPDATE device_logs SET resolved_by = NULL WHERE resolved_by = ?";
        $updateLogsStmt = $conn->prepare($updateLogsQuery);
        $updateLogsStmt->execute([$user_id]);
    }
    
    // Delete the user
    $deleteQuery = "DELETE FROM users WHERE user_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->execute([$user_id]);
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "User deleted successfully!";
    if ($logResult['log_count'] > 0) {
        $_SESSION['success_message'] .= " {$logResult['log_count']} log resolution(s) were unlinked.";
    }
    
} catch (PDOException $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
}

header("Location: users.php");
exit;
?>
