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
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-connected { background-color: #28a745; }
        .status-disconnected { background-color: #dc3545; }
        .config-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        .compact-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .status-row:last-child {
            border-bottom: none;
        }
        .compact-form .mb-3 {
            margin-bottom: 1rem !important;
        }
        .info-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: #495057;
        }
    </style>
</head>
<body>
    
    <div class="container-fluid mt-3">
        <div class="compact-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-database"></i> Database Configuration</h2>
                    <small class="text-muted">Manage database connection settings and test connectivity</small>
                </div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Current Status -->
            <div class="col-lg-5">
                <div class="info-card">
                    <div class="section-title"><i class="fas fa-info-circle"></i> Connection Status</div>
                    
                    <div class="status-row">
                        <span><strong>Connection:</strong></span>
                        <span>
                            <span class="status-indicator <?php echo $dbStatus['connected'] ? 'status-connected' : 'status-disconnected'; ?>"></span>
                            <?php echo $dbStatus['connected'] ? 'Connected' : 'Disconnected'; ?>
                        </span>
                    </div>
                    
                    <div class="status-row">
                        <span><strong>Database:</strong></span>
                        <span>
                            <span class="status-indicator <?php echo $dbStatus['database_exists'] ? 'status-connected' : 'status-disconnected'; ?>"></span>
                            <?php echo $dbStatus['database_exists'] ? 'Exists' : 'Missing'; ?>
                        </span>
                    </div>
                    
                    <div class="status-row">
                        <span><strong>DB Name:</strong></span>
                        <code class="small"><?php echo htmlspecialchars($config['db_name']); ?></code>
                    </div>
                    
                    <div class="status-row">
                        <span><strong>Host:Port:</strong></span>
                        <code class="small"><?php echo htmlspecialchars($config['host']) . ':' . htmlspecialchars($config['port']); ?></code>
                    </div>
                    
                    <?php if ($dbStatus['database_exists']): ?>
                        <div class="status-row">
                            <span><strong>Objects:</strong></span>
                            <small>T:<?php echo count(array_diff($dbStatus['tables'], $dbStatus['views'])); ?> | V:<?php echo count($dbStatus['views']); ?> | P:<?php echo count($dbStatus['procedures']); ?> | F:<?php echo count($dbStatus['functions']); ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="test_connection">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-plug"></i> Test Current Connection
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Configuration Form -->
            <div class="col-lg-7">
                <div class="config-form compact-form">
                    <div class="section-title"><i class="fas fa-cog"></i> Database Settings</div>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="update_config">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="host" class="form-label">Host</label>
                                <input type="text" class="form-control form-control-sm" id="host" name="host" 
                                       value="<?php echo htmlspecialchars($config['host']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="port" class="form-label">Port</label>
                                <input type="number" class="form-control form-control-sm" id="port" name="port" 
                                       value="<?php echo htmlspecialchars($config['port']); ?>" 
                                       min="1" max="65535" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_name" class="form-label">Database Name</label>
                            <input type="text" class="form-control form-control-sm" id="db_name" name="db_name" 
                                   value="<?php echo htmlspecialchars($config['db_name']); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control form-control-sm" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($config['username']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control form-control-sm" id="password" name="password" 
                                       value="<?php echo htmlspecialchars($config['password']); ?>">
                                <div class="form-text small">Leave blank if not required</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="charset" class="form-label">Character Set</label>
                                <select class="form-select form-select-sm" id="charset" name="charset">
                                    <option value="utf8" <?php echo $config['charset'] === 'utf8' ? 'selected' : ''; ?>>UTF-8</option>
                                    <option value="utf8mb4" <?php echo $config['charset'] === 'utf8mb4' ? 'selected' : ''; ?>>UTF-8 MB4</option>
                                    <option value="latin1" <?php echo $config['charset'] === 'latin1' ? 'selected' : ''; ?>>Latin1</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-sm d-block w-100">
                                    <i class="fas fa-save"></i> Save Configuration
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Quick Connection Test -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="info-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="section-title mb-0"><i class="fas fa-vial"></i> Quick Connection Test</div>
                        <small class="text-muted">Test without saving changes</small>
                    </div>
                    
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="action" value="test_custom_connection">
                        
                        <div class="col-md-3">
                            <label for="test_host" class="form-label small">Host</label>
                            <input type="text" class="form-control form-control-sm" id="test_host" name="test_host" 
                                   value="<?php echo htmlspecialchars($config['host']); ?>" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="test_port" class="form-label small">Port</label>
                            <input type="number" class="form-control form-control-sm" id="test_port" name="test_port" 
                                   value="<?php echo htmlspecialchars($config['port']); ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="test_username" class="form-label small">Username</label>
                            <input type="text" class="form-control form-control-sm" id="test_username" name="test_username" 
                                   value="<?php echo htmlspecialchars($config['username']); ?>" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="test_password" class="form-label small">Password</label>
                            <input type="password" class="form-control form-control-sm" id="test_password" name="test_password">
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-info btn-sm w-100">
                                <i class="fas fa-flask"></i> Test
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Additional Actions -->
        <?php if ($dbStatus['connected'] && !$dbStatus['database_exists']): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-warning d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-exclamation-triangle"></i> Database not found. Setup required.</span>
                    <a href="index.php?setup_db=1" class="btn btn-warning btn-sm">
                        <i class="fas fa-database"></i> Setup Database
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>