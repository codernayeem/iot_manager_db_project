<?php
/**
 * Database Configuration Management Page
 * Allows users to view and modify database connection settings
 */

session_start();
require_once 'config/database.php';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_config':
                    $newConfig = [
                        'host' => $_POST['host'] ?? '',
                        'db_name' => $_POST['db_name'] ?? '',
                        'username' => $_POST['username'] ?? '',
                        'password' => $_POST['password'] ?? '',
                        'port' => (int)($_POST['port'] ?? 3306),
                        'charset' => $_POST['charset'] ?? 'utf8'
                    ];
                    
                    $database->updateConfig($newConfig);
                    $message = 'Configuration updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'test_connection':
                    $result = $database->testConnection();
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                    break;
                    
                case 'test_custom_connection':
                    $configManager = new ConfigManager();
                    $result = $configManager->testConnectionWith(
                        $_POST['test_host'] ?? '',
                        $_POST['test_username'] ?? '',
                        $_POST['test_password'] ?? '',
                        (int)($_POST['test_port'] ?? 3306)
                    );
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current configuration
$database = new Database();
$config = $database->getConfig();
$dbStatus = $database->getDatabaseStatus();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Configuration - IoT Device Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .config-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-connected { background-color: #28a745; }
        .status-disconnected { background-color: #dc3545; }
        .config-form {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-database"></i> Database Configuration</h1>
                <p class="text-muted">Manage database connection settings and test connectivity</p>
                
                <!-- Message Display -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <!-- Current Status -->
            <div class="col-md-6">
                <div class="config-section">
                    <h3><i class="fas fa-info-circle"></i> Connection Status</h3>
                    
                    <div class="mb-3">
                        <strong>Database Connection:</strong>
                        <span class="status-indicator <?php echo $dbStatus['connected'] ? 'status-connected' : 'status-disconnected'; ?>"></span>
                        <?php echo $dbStatus['connected'] ? 'Connected' : 'Disconnected'; ?>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Database Exists:</strong>
                        <span class="status-indicator <?php echo $dbStatus['database_exists'] ? 'status-connected' : 'status-disconnected'; ?>"></span>
                        <?php echo $dbStatus['database_exists'] ? 'Yes' : 'No'; ?>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Current Database:</strong> 
                        <code><?php echo htmlspecialchars($config['db_name']); ?></code>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Connection String:</strong><br>
                        <small><code><?php 
                            echo "mysql:host=" . htmlspecialchars($config['host']) . 
                                 ";port=" . htmlspecialchars($config['port']) . 
                                 ";dbname=" . htmlspecialchars($config['db_name']) . 
                                 ";charset=" . htmlspecialchars($config['charset']);
                        ?></code></small>
                    </div>
                    
                    <?php if ($dbStatus['database_exists']): ?>
                        <div class="mb-3">
                            <strong>Tables:</strong> <?php echo count(array_diff($dbStatus['tables'], $dbStatus['views'])); ?><br>
                            <strong>Views:</strong> <?php echo count($dbStatus['views']); ?><br>
                            <strong>Procedures:</strong> <?php echo count($dbStatus['procedures']); ?><br>
                            <strong>Functions:</strong> <?php echo count($dbStatus['functions']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Test Current Connection -->
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="test_connection">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plug"></i> Test Current Connection
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Configuration Form -->
            <div class="col-md-6">
                <div class="config-form">
                    <h3><i class="fas fa-cog"></i> Database Settings</h3>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="update_config">
                        
                        <div class="mb-3">
                            <label for="host" class="form-label">Host</label>
                            <input type="text" class="form-control" id="host" name="host" 
                                   value="<?php echo htmlspecialchars($config['host']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="port" name="port" 
                                   value="<?php echo htmlspecialchars($config['port']); ?>" 
                                   min="1" max="65535" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_name" class="form-label">Database Name</label>
                            <input type="text" class="form-control" id="db_name" name="db_name" 
                                   value="<?php echo htmlspecialchars($config['db_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($config['username']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   value="<?php echo htmlspecialchars($config['password']); ?>">
                            <div class="form-text">Leave blank if no password is required</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="charset" class="form-label">Character Set</label>
                            <select class="form-select" id="charset" name="charset">
                                <option value="utf8" <?php echo $config['charset'] === 'utf8' ? 'selected' : ''; ?>>UTF-8</option>
                                <option value="utf8mb4" <?php echo $config['charset'] === 'utf8mb4' ? 'selected' : ''; ?>>UTF-8 MB4</option>
                                <option value="latin1" <?php echo $config['charset'] === 'latin1' ? 'selected' : ''; ?>>Latin1</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Quick Connection Test -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="config-section">
                    <h3><i class="fas fa-vial"></i> Test Connection</h3>
                    <p class="text-muted">Test database connectivity with custom parameters without saving</p>
                    
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="test_custom_connection">
                        
                        <div class="col-md-3">
                            <label for="test_host" class="form-label">Host</label>
                            <input type="text" class="form-control" id="test_host" name="test_host" 
                                   value="<?php echo htmlspecialchars($config['host']); ?>" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="test_port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="test_port" name="test_port" 
                                   value="<?php echo htmlspecialchars($config['port']); ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="test_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="test_username" name="test_username" 
                                   value="<?php echo htmlspecialchars($config['username']); ?>" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="test_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="test_password" name="test_password">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-outline-secondary d-block">
                                <i class="fas fa-flask"></i> Test
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    
                    <?php if ($dbStatus['connected'] && !$dbStatus['database_exists']): ?>
                        <a href="index.php?setup_db=1" class="btn btn-primary">
                            <i class="fas fa-database"></i> Setup Database
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>