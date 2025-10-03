<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/database.php';

/**
 * SQL Features Used: CREATE VIEW, Stored Procedures, User-Defined Functions, 
 * Triggers, Advanced Indexing
 */

$database = new Database();
$conn = $database->getConnection();

$message = '';
$error = '';

// Handle form submissions for creating SQL objects
if ($_POST) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'create_views':
                createDatabaseViews($conn);
                $message = "Database views created successfully!";
                break;
                
            case 'create_procedures':
                createStoredProcedures($conn);
                $message = "Stored procedures created successfully!";
                break;
                
            case 'create_functions':
                createUserFunctions($conn);
                $message = "User-defined functions created successfully!";
                break;
                
            case 'create_triggers':
                createTriggers($conn);
                $message = "Database triggers created successfully!";
                break;
                
            case 'optimize_indexes':
                optimizeIndexes($conn);
                $message = "Database indexes optimized successfully!";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// SQL Feature: CREATE VIEW for device summary
function createDatabaseViews($conn) {
    // View 1: Device Summary View
    $view1 = "
        CREATE OR REPLACE VIEW device_summary_view AS
        SELECT 
            d.d_id,
            d.d_name,
            d.serial_number,
            d.status,
            dt.t_name as device_type,
            CONCAT(u.f_name, ' ', u.l_name) as owner_name,
            COUNT(DISTINCT dl.log_id) as total_logs,
            COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) as error_count,
            COUNT(DISTINCT CASE WHEN dl.resolved_by IS NULL AND dl.log_type = 'error' THEN dl.log_id END) as unresolved_errors,
            MAX(dl.log_time) as last_activity,
            COUNT(DISTINCT dep.loc_id) as deployment_locations,
            DATEDIFF(COALESCE(d.warranty_expiry, CURDATE()), CURDATE()) as warranty_days_remaining,
            CASE 
                WHEN COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) = 0 THEN 'Excellent'
                WHEN COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) <= 2 THEN 'Good'
                WHEN COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) <= 5 THEN 'Fair'
                ELSE 'Poor'
            END as health_status
        FROM devices d
        INNER JOIN device_types dt ON d.t_id = dt.t_id
        INNER JOIN users u ON d.user_id = u.user_id
        LEFT JOIN device_logs dl ON d.d_id = dl.d_id
        LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
        GROUP BY d.d_id, d.d_name, d.serial_number, d.status, dt.t_name, u.f_name, u.l_name, d.warranty_expiry
    ";
    
    // View 2: Location Analytics View
    $view2 = "
        CREATE OR REPLACE VIEW location_analytics_view AS
        SELECT 
            l.loc_id,
            l.loc_name,
            l.address,
            COUNT(DISTINCT dep.d_id) as total_devices,
            COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) as active_devices,
            COUNT(DISTINCT CASE WHEN d.status = 'error' THEN dep.d_id END) as error_devices,
            COUNT(DISTINCT dt.t_id) as device_type_diversity,
            COUNT(DISTINCT dl.log_id) as total_logs,
            COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) as total_errors,
            AVG(CASE WHEN dl.log_type = 'error' THEN dl.severity_level END) as avg_error_severity,
            MAX(dl.log_time) as last_activity,
            ROUND(
                COUNT(DISTINCT CASE WHEN d.status = 'active' THEN dep.d_id END) * 100.0 / 
                NULLIF(COUNT(DISTINCT dep.d_id), 0), 2
            ) as uptime_percentage
        FROM locations l
        LEFT JOIN deployments dep ON l.loc_id = dep.loc_id AND dep.is_active = 1
        LEFT JOIN devices d ON dep.d_id = d.d_id
        LEFT JOIN device_types dt ON d.t_id = dt.t_id
        LEFT JOIN device_logs dl ON d.d_id = dl.d_id
        GROUP BY l.loc_id, l.loc_name, l.address
    ";
    
    // View 3: User Activity View
    $view3 = "
        CREATE OR REPLACE VIEW user_activity_view AS
        SELECT 
            u.user_id,
            CONCAT(u.f_name, ' ', u.l_name) as full_name,
            u.email,
            COUNT(DISTINCT d.d_id) as owned_devices,
            COUNT(DISTINCT dep.deployment_id) as deployments_made,
            COUNT(DISTINCT dl_resolved.log_id) as issues_resolved,
            COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.d_id END) as active_devices,
            MAX(d.created_at) as last_device_added,
            MAX(dl_resolved.resolved_at) as last_issue_resolved,
            CASE 
                WHEN COUNT(DISTINCT d.d_id) >= 5 THEN 'Power User'
                WHEN COUNT(DISTINCT d.d_id) >= 2 THEN 'Regular User'
                ELSE 'New User'
            END as user_category
        FROM users u
        LEFT JOIN devices d ON u.user_id = d.user_id
        LEFT JOIN deployments dep ON u.user_id = dep.deployed_by
        LEFT JOIN device_logs dl_resolved ON u.user_id = dl_resolved.resolved_by
        GROUP BY u.user_id, u.f_name, u.l_name, u.email
    ";
    
    $conn->exec($view1);
    $conn->exec($view2);
    $conn->exec($view3);
}

// SQL Feature: Stored Procedures with parameters and control flow
function createStoredProcedures($conn) {
    // Procedure 1: Device Health Check
    $proc1 = "
        DROP PROCEDURE IF EXISTS DeviceHealthCheck;
        CREATE PROCEDURE DeviceHealthCheck(IN device_id INT, OUT health_score INT, OUT status_message VARCHAR(255))
        BEGIN
            DECLARE error_count INT DEFAULT 0;
            DECLARE warning_count INT DEFAULT 0;
            DECLARE last_activity DATETIME;
            DECLARE days_since_activity INT DEFAULT 0;
            
            -- Get error and warning counts
            SELECT 
                COUNT(CASE WHEN log_type = 'error' THEN 1 END),
                COUNT(CASE WHEN log_type = 'warning' THEN 1 END),
                MAX(log_time)
            INTO error_count, warning_count, last_activity
            FROM device_logs 
            WHERE d_id = device_id AND log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY);
            
            -- Calculate days since last activity
            IF last_activity IS NOT NULL THEN
                SET days_since_activity = DATEDIFF(NOW(), last_activity);
            ELSE
                SET days_since_activity = 999;
            END IF;
            
            -- Calculate health score (0-100)
            SET health_score = 100;
            SET health_score = health_score - (error_count * 10);
            SET health_score = health_score - (warning_count * 5);
            SET health_score = health_score - (days_since_activity * 2);
            
            IF health_score < 0 THEN
                SET health_score = 0;
            END IF;
            
            -- Generate status message
            IF health_score >= 80 THEN
                SET status_message = 'Device is operating excellently';
            ELSEIF health_score >= 60 THEN
                SET status_message = 'Device is performing well with minor issues';
            ELSEIF health_score >= 40 THEN
                SET status_message = 'Device needs attention - moderate issues detected';
            ELSEIF health_score >= 20 THEN
                SET status_message = 'Device requires immediate attention - multiple issues';
            ELSE
                SET status_message = 'Device is in critical condition - urgent maintenance required';
            END IF;
        END
    ";
    
    // Procedure 2: Bulk Log Cleanup
    $proc2 = "
        DROP PROCEDURE IF EXISTS CleanupOldLogs;
        CREATE PROCEDURE CleanupOldLogs(IN days_to_keep INT, OUT deleted_count INT)
        BEGIN
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                SET deleted_count = -1;
            END;
            
            START TRANSACTION;
            
            -- Delete old resolved logs
            DELETE FROM device_logs 
            WHERE log_time < DATE_SUB(NOW(), INTERVAL days_to_keep DAY) 
            AND resolved_by IS NOT NULL 
            AND log_type IN ('info', 'debug');
            
            SET deleted_count = ROW_COUNT();
            
            COMMIT;
        END
    ";
    
    // Procedure 3: Device Deployment with validation
    $proc3 = "
        DROP PROCEDURE IF EXISTS DeployDevice;
        CREATE PROCEDURE DeployDevice(
            IN p_device_id INT, 
            IN p_location_id INT, 
            IN p_deployed_by INT, 
            IN p_notes TEXT,
            OUT result_code INT,
            OUT result_message VARCHAR(255)
        )
        BEGIN
            DECLARE device_exists INT DEFAULT 0;
            DECLARE location_exists INT DEFAULT 0;
            DECLARE already_deployed INT DEFAULT 0;
            
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                SET result_code = -1;
                SET result_message = 'Database error occurred during deployment';
            END;
            
            START TRANSACTION;
            
            -- Check if device exists
            SELECT COUNT(*) INTO device_exists FROM devices WHERE d_id = p_device_id;
            IF device_exists = 0 THEN
                SET result_code = 1;
                SET result_message = 'Device not found';
                ROLLBACK;
            ELSE
                -- Check if location exists
                SELECT COUNT(*) INTO location_exists FROM locations WHERE loc_id = p_location_id;
                IF location_exists = 0 THEN
                    SET result_code = 2;
                    SET result_message = 'Location not found';
                    ROLLBACK;
                ELSE
                    -- Check if already deployed at this location
                    SELECT COUNT(*) INTO already_deployed 
                    FROM deployments 
                    WHERE d_id = p_device_id AND loc_id = p_location_id AND is_active = 1;
                    
                    IF already_deployed > 0 THEN
                        SET result_code = 3;
                        SET result_message = 'Device already deployed at this location';
                        ROLLBACK;
                    ELSE
                        -- Insert deployment
                        INSERT INTO deployments (d_id, loc_id, deployed_by, deployment_notes)
                        VALUES (p_device_id, p_location_id, p_deployed_by, p_notes);
                        
                        -- Log the deployment
                        INSERT INTO device_logs (d_id, log_type, message, severity_level)
                        VALUES (p_device_id, 'info', CONCAT('Device deployed to location ID: ', p_location_id), 1);
                        
                        SET result_code = 0;
                        SET result_message = 'Device deployed successfully';
                        COMMIT;
                    END IF;
                END IF;
            END IF;
        END
    ";
    
    $conn->exec($proc1);
    $conn->exec($proc2);
    $conn->exec($proc3);
}

// SQL Feature: User-Defined Functions
function createUserFunctions($conn) {
    // Function 1: Calculate device uptime percentage
    $func1 = "
        DROP FUNCTION IF EXISTS CalculateDeviceUptime;
        CREATE FUNCTION CalculateDeviceUptime(device_id INT, days_period INT) 
        RETURNS DECIMAL(5,2)
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            DECLARE total_time INT DEFAULT 0;
            DECLARE error_time INT DEFAULT 0;
            DECLARE uptime_percentage DECIMAL(5,2) DEFAULT 100.00;
            
            SET total_time = days_period * 24; -- Total hours in period
            
            -- Calculate estimated downtime from error logs
            SELECT COALESCE(SUM(
                CASE 
                    WHEN resolved_at IS NOT NULL THEN 
                        TIMESTAMPDIFF(HOUR, log_time, resolved_at)
                    ELSE 
                        TIMESTAMPDIFF(HOUR, log_time, NOW())
                END
            ), 0) INTO error_time
            FROM device_logs 
            WHERE d_id = device_id 
            AND log_type = 'error' 
            AND log_time >= DATE_SUB(NOW(), INTERVAL days_period DAY);
            
            -- Calculate uptime percentage
            IF total_time > 0 THEN
                SET uptime_percentage = ((total_time - error_time) / total_time) * 100;
                IF uptime_percentage < 0 THEN
                    SET uptime_percentage = 0;
                END IF;
            END IF;
            
            RETURN uptime_percentage;
        END
    ";
    
    // Function 2: Get device risk score
    $func2 = "
        DROP FUNCTION IF EXISTS GetDeviceRiskScore;
        CREATE FUNCTION GetDeviceRiskScore(device_id INT) 
        RETURNS INT
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            DECLARE risk_score INT DEFAULT 0;
            DECLARE error_count INT DEFAULT 0;
            DECLARE warning_count INT DEFAULT 0;
            DECLARE days_since_maintenance INT DEFAULT 0;
            DECLARE warranty_days INT DEFAULT 0;
            
            -- Get error and warning counts (last 30 days)
            SELECT 
                COUNT(CASE WHEN log_type = 'error' THEN 1 END),
                COUNT(CASE WHEN log_type = 'warning' THEN 1 END)
            INTO error_count, warning_count
            FROM device_logs 
            WHERE d_id = device_id AND log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY);
            
            -- Get days since last maintenance
            SELECT COALESCE(DATEDIFF(NOW(), last_maintenance), 365) INTO days_since_maintenance
            FROM devices WHERE d_id = device_id;
            
            -- Get warranty status
            SELECT COALESCE(DATEDIFF(warranty_expiry, NOW()), 0) INTO warranty_days
            FROM devices WHERE d_id = device_id;
            
            -- Calculate risk score (0-100, higher = more risky)
            SET risk_score = 0;
            SET risk_score = risk_score + (error_count * 15);
            SET risk_score = risk_score + (warning_count * 5);
            
            IF days_since_maintenance > 180 THEN
                SET risk_score = risk_score + 20;
            ELSEIF days_since_maintenance > 90 THEN
                SET risk_score = risk_score + 10;
            END IF;
            
            IF warranty_days < 0 THEN
                SET risk_score = risk_score + 15;
            ELSEIF warranty_days < 30 THEN
                SET risk_score = risk_score + 5;
            END IF;
            
            IF risk_score > 100 THEN
                SET risk_score = 100;
            END IF;
            
            RETURN risk_score;
        END
    ";
    
    $conn->exec($func1);
    $conn->exec($func2);
}

// SQL Feature: Triggers for automated actions
function createTriggers($conn) {
    // Trigger 1: Auto-log device status changes
    $trigger1 = "
        DROP TRIGGER IF EXISTS device_status_change_log;
        CREATE TRIGGER device_status_change_log
        AFTER UPDATE ON devices
        FOR EACH ROW
        BEGIN
            IF OLD.status != NEW.status THEN
                INSERT INTO device_logs (d_id, log_type, message, severity_level)
                VALUES (
                    NEW.d_id, 
                    'info', 
                    CONCAT('Device status changed from ', OLD.status, ' to ', NEW.status),
                    CASE 
                        WHEN NEW.status = 'error' THEN 3
                        WHEN NEW.status = 'maintenance' THEN 2
                        ELSE 1
                    END
                );
            END IF;
        END
    ";
    
    // Trigger 2: Update device timestamp on any change
    $trigger2 = "
        DROP TRIGGER IF EXISTS device_update_timestamp;
        CREATE TRIGGER device_update_timestamp
        BEFORE UPDATE ON devices
        FOR EACH ROW
        SET NEW.updated_at = NOW()
    ";
    
    // Trigger 3: Auto-resolve old logs when device status improves
    $trigger3 = "
        DROP TRIGGER IF EXISTS auto_resolve_logs;
        CREATE TRIGGER auto_resolve_logs
        AFTER UPDATE ON devices
        FOR EACH ROW
        BEGIN
            IF OLD.status IN ('error', 'maintenance') AND NEW.status = 'active' THEN
                UPDATE device_logs 
                SET resolved_by = 1, 
                    resolved_at = NOW(), 
                    resolution_notes = 'Auto-resolved: Device status changed to active'
                WHERE d_id = NEW.d_id 
                AND log_type IN ('error', 'warning') 
                AND resolved_by IS NULL;
            END IF;
        END
    ";
    
    $conn->exec($trigger1);
    $conn->exec($trigger2);
    $conn->exec($trigger3);
}

// SQL Feature: Advanced Indexing for performance
function optimizeIndexes($conn) {
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_device_logs_composite ON device_logs(d_id, log_time, log_type)",
        "CREATE INDEX IF NOT EXISTS idx_device_logs_resolution ON device_logs(resolved_by, resolved_at)",
        "CREATE INDEX IF NOT EXISTS idx_devices_status_type ON devices(status, t_id)",
        "CREATE INDEX IF NOT EXISTS idx_deployments_active ON deployments(is_active, deployed_at)",
        "CREATE INDEX IF NOT EXISTS idx_locations_coordinates ON locations(latitude, longitude)",
        "CREATE INDEX IF NOT EXISTS idx_users_email_hash ON users(email(10))", // Prefix index
    ];
    
    foreach ($indexes as $index) {
        $conn->exec($index);
    }
}

// Get existing views, procedures, functions info
$viewsQuery = "SHOW FULL TABLES WHERE Table_type = 'VIEW'";
$views = $conn->query($viewsQuery)->fetchAll(PDO::FETCH_COLUMN);

$proceduresQuery = "SHOW PROCEDURE STATUS WHERE Db = DATABASE()";
$procedures = $conn->query($proceduresQuery)->fetchAll(PDO::FETCH_ASSOC);

$functionsQuery = "SHOW FUNCTION STATUS WHERE Db = DATABASE()";
$functions = $conn->query($functionsQuery)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced SQL Features - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .feature-card {
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .code-block {
            background: #1f2937;
            color: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-cogs mr-3"></i>Advanced SQL Features
            </h1>
            <p class="text-gray-600">Views, Stored Procedures, Functions, Triggers, and Performance Optimization</p>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Feature Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Database Views -->
            <div class="feature-card bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-eye mr-2 text-blue-600"></i>Database Views
                    </h2>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="create_views">
                        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                            Create Views
                        </button>
                    </form>
                </div>
                
                <p class="text-gray-600 mb-4">Simplified data access through virtual tables</p>
                
                <div class="space-y-3">
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">device_summary_view</h4>
                        <p class="text-sm text-gray-600">Comprehensive device information with health metrics</p>
                    </div>
                    
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">location_analytics_view</h4>
                        <p class="text-sm text-gray-600">Location-based analytics and performance metrics</p>
                    </div>
                    
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">user_activity_view</h4>
                        <p class="text-sm text-gray-600">User engagement and activity summary</p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h4 class="font-semibold text-gray-800 mb-2">Existing Views:</h4>
                    <div class="text-sm text-gray-600">
                        <?php if (empty($views)): ?>
                            <span class="text-gray-400">No views created yet</span>
                        <?php else: ?>
                            <?php foreach ($views as $view): ?>
                                <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded mr-2 mb-1"><?php echo $view; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <details class="mt-4">
                    <summary class="cursor-pointer text-blue-600 hover:text-blue-800">View SQL Example</summary>
                    <div class="code-block mt-2">
CREATE VIEW device_summary_view AS
SELECT 
    d.d_id,
    d.d_name,
    d.status,
    dt.t_name as device_type,
    COUNT(DISTINCT dl.log_id) as total_logs,
    COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) as error_count,
    CASE 
        WHEN COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) = 0 THEN 'Excellent'
        WHEN COUNT(DISTINCT CASE WHEN dl.log_type = 'error' THEN dl.log_id END) <= 2 THEN 'Good'
        ELSE 'Poor'
    END as health_status
FROM devices d
INNER JOIN device_types dt ON d.t_id = dt.t_id
LEFT JOIN device_logs dl ON d.d_id = dl.d_id
GROUP BY d.d_id, d.d_name, d.status, dt.t_name;
                    </div>
                </details>
            </div>
            
            <!-- Stored Procedures -->
            <div class="feature-card bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-code mr-2 text-green-600"></i>Stored Procedures
                    </h2>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="create_procedures">
                        <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                            Create Procedures
                        </button>
                    </form>
                </div>
                
                <p class="text-gray-600 mb-4">Reusable code blocks with parameters and control flow</p>
                
                <div class="space-y-3">
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">DeviceHealthCheck</h4>
                        <p class="text-sm text-gray-600">Calculates device health score and status message</p>
                    </div>
                    
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">CleanupOldLogs</h4>
                        <p class="text-sm text-gray-600">Removes old resolved logs for maintenance</p>
                    </div>
                    
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">DeployDevice</h4>
                        <p class="text-sm text-gray-600">Validates and deploys device with error handling</p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h4 class="font-semibold text-gray-800 mb-2">Existing Procedures:</h4>
                    <div class="text-sm text-gray-600">
                        <?php if (empty($procedures)): ?>
                            <span class="text-gray-400">No procedures created yet</span>
                        <?php else: ?>
                            <?php foreach ($procedures as $proc): ?>
                                <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded mr-2 mb-1"><?php echo $proc['Name']; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <details class="mt-4">
                    <summary class="cursor-pointer text-blue-600 hover:text-blue-800">Procedure SQL Example</summary>
                    <div class="code-block mt-2">
CREATE PROCEDURE DeviceHealthCheck(
    IN device_id INT, 
    OUT health_score INT, 
    OUT status_message VARCHAR(255)
)
BEGIN
    DECLARE error_count INT DEFAULT 0;
    DECLARE warning_count INT DEFAULT 0;
    
    SELECT 
        COUNT(CASE WHEN log_type = 'error' THEN 1 END),
        COUNT(CASE WHEN log_type = 'warning' THEN 1 END)
    INTO error_count, warning_count
    FROM device_logs 
    WHERE d_id = device_id 
    AND log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    SET health_score = 100 - (error_count * 10) - (warning_count * 5);
    
    IF health_score >= 80 THEN
        SET status_message = 'Device is operating excellently';
    ELSEIF health_score >= 60 THEN
        SET status_message = 'Device is performing well';
    ELSE
        SET status_message = 'Device needs attention';
    END IF;
END
                    </div>
                </details>
            </div>
            
            <!-- User-Defined Functions -->
            <div class="feature-card bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-function mr-2 text-purple-600"></i>User Functions
                    </h2>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="create_functions">
                        <button type="submit" class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700">
                            Create Functions
                        </button>
                    </form>
                </div>
                
                <p class="text-gray-600 mb-4">Custom functions for calculations and data processing</p>
                
                <div class="space-y-3">
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">CalculateDeviceUptime</h4>
                        <p class="text-sm text-gray-600">Returns device uptime percentage for a given period</p>
                    </div>
                    
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">GetDeviceRiskScore</h4>
                        <p class="text-sm text-gray-600">Calculates risk score based on multiple factors</p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h4 class="font-semibold text-gray-800 mb-2">Existing Functions:</h4>
                    <div class="text-sm text-gray-600">
                        <?php if (empty($functions)): ?>
                            <span class="text-gray-400">No functions created yet</span>
                        <?php else: ?>
                            <?php foreach ($functions as $func): ?>
                                <span class="inline-block bg-purple-100 text-purple-800 px-2 py-1 rounded mr-2 mb-1"><?php echo $func['Name']; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <details class="mt-4">
                    <summary class="cursor-pointer text-blue-600 hover:text-blue-800">Function SQL Example</summary>
                    <div class="code-block mt-2">
CREATE FUNCTION CalculateDeviceUptime(device_id INT, days_period INT) 
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_time INT DEFAULT 0;
    DECLARE error_time INT DEFAULT 0;
    DECLARE uptime_percentage DECIMAL(5,2) DEFAULT 100.00;
    
    SET total_time = days_period * 24;
    
    SELECT COALESCE(SUM(
        CASE 
            WHEN resolved_at IS NOT NULL THEN 
                TIMESTAMPDIFF(HOUR, log_time, resolved_at)
            ELSE 
                TIMESTAMPDIFF(HOUR, log_time, NOW())
        END
    ), 0) INTO error_time
    FROM device_logs 
    WHERE d_id = device_id 
    AND log_type = 'error' 
    AND log_time >= DATE_SUB(NOW(), INTERVAL days_period DAY);
    
    IF total_time > 0 THEN
        SET uptime_percentage = ((total_time - error_time) / total_time) * 100;
    END IF;
    
    RETURN uptime_percentage;
END
                    </div>
                </details>
            </div>
            
            <!-- Database Triggers -->
            <div class="feature-card bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-bolt mr-2 text-yellow-600"></i>Database Triggers
                    </h2>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="create_triggers">
                        <button type="submit" class="bg-yellow-600 text-white px-3 py-1 rounded text-sm hover:bg-yellow-700">
                            Create Triggers
                        </button>
                    </form>
                </div>
                
                <p class="text-gray-600 mb-4">Automatic actions triggered by database events</p>
                
                <div class="space-y-3">
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">device_status_change_log</h4>
                        <p class="text-sm text-gray-600">Automatically logs when device status changes</p>
                    </div>
                    
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">device_update_timestamp</h4>
                        <p class="text-sm text-gray-600">Updates timestamp on any device modification</p>
                    </div>
                    
                    <div class="p-3 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-800">auto_resolve_logs</h4>
                        <p class="text-sm text-gray-600">Auto-resolves logs when device status improves</p>
                    </div>
                </div>
                
                <details class="mt-4">
                    <summary class="cursor-pointer text-blue-600 hover:text-blue-800">Trigger SQL Example</summary>
                    <div class="code-block mt-2">
CREATE TRIGGER device_status_change_log
AFTER UPDATE ON devices
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO device_logs (d_id, log_type, message, severity_level)
        VALUES (
            NEW.d_id, 
            'info', 
            CONCAT('Device status changed from ', OLD.status, ' to ', NEW.status),
            CASE 
                WHEN NEW.status = 'error' THEN 3
                WHEN NEW.status = 'maintenance' THEN 2
                ELSE 1
            END
        );
    END IF;
END
                    </div>
                </details>
            </div>
        </div>
        
        <!-- Performance Optimization -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-tachometer-alt mr-2 text-red-600"></i>Performance Optimization
                </h2>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="optimize_indexes">
                    <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                        Optimize Indexes
                    </button>
                </form>
            </div>
            
            <p class="text-gray-600 mb-4">Advanced indexing strategies for query performance</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="p-3 bg-gray-50 rounded">
                    <h4 class="font-semibold text-gray-800">Composite Indexes</h4>
                    <p class="text-sm text-gray-600">Multi-column indexes for complex queries</p>
                    <code class="text-xs text-blue-600">idx_device_logs_composite</code>
                </div>
                
                <div class="p-3 bg-gray-50 rounded">
                    <h4 class="font-semibold text-gray-800">Covering Indexes</h4>
                    <p class="text-sm text-gray-600">Indexes that include all required columns</p>
                    <code class="text-xs text-blue-600">idx_devices_status_type</code>
                </div>
                
                <div class="p-3 bg-gray-50 rounded">
                    <h4 class="font-semibold text-gray-800">Prefix Indexes</h4>
                    <p class="text-sm text-gray-600">Partial column indexing for large strings</p>
                    <code class="text-xs text-blue-600">idx_users_email_hash</code>
                </div>
                
                <div class="p-3 bg-gray-50 rounded">
                    <h4 class="font-semibold text-gray-800">Spatial Indexes</h4>
                    <p class="text-sm text-gray-600">Coordinate-based location indexing</p>
                    <code class="text-xs text-blue-600">idx_locations_coordinates</code>
                </div>
                
                <div class="p-3 bg-gray-50 rounded">
                    <h4 class="font-semibold text-gray-800">Filtered Indexes</h4>
                    <p class="text-sm text-gray-600">Conditional indexing for specific data</p>
                    <code class="text-xs text-blue-600">idx_deployments_active</code>
                </div>
                
                <div class="p-3 bg-gray-50 rounded">
                    <h4 class="font-semibold text-gray-800">Resolution Indexes</h4>
                    <p class="text-sm text-gray-600">Optimized for log resolution queries</p>
                    <code class="text-xs text-blue-600">idx_device_logs_resolution</code>
                </div>
            </div>
            
            <details class="mt-4">
                <summary class="cursor-pointer text-blue-600 hover:text-blue-800">Index Creation Examples</summary>
                <div class="code-block mt-2">
-- Composite index for log queries
CREATE INDEX idx_device_logs_composite ON device_logs(d_id, log_time, log_type);

-- Covering index for device filtering
CREATE INDEX idx_devices_status_type ON devices(status, t_id);

-- Prefix index for email searches
CREATE INDEX idx_users_email_hash ON users(email(10));

-- Spatial index for location queries
CREATE INDEX idx_locations_coordinates ON locations(latitude, longitude);

-- Filtered index for active deployments
CREATE INDEX idx_deployments_active ON deployments(is_active, deployed_at);
                </div>
            </details>
        </div>
        
        <!-- Usage Examples -->
        <div class="mt-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg shadow-md p-6 text-white">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-lightbulb mr-2"></i>Usage Examples
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold mb-2">Using Views</h4>
                    <div class="bg-black bg-opacity-30 rounded p-3 text-sm font-mono">
                        SELECT * FROM device_summary_view<br>
                        WHERE health_status = 'Poor';
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-2">Calling Procedures</h4>
                    <div class="bg-black bg-opacity-30 rounded p-3 text-sm font-mono">
                        CALL DeviceHealthCheck(1, @score, @msg);<br>
                        SELECT @score, @msg;
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-2">Using Functions</h4>
                    <div class="bg-black bg-opacity-30 rounded p-3 text-sm font-mono">
                        SELECT d_name, CalculateDeviceUptime(d_id, 30)<br>
                        FROM devices;
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-2">Query Performance</h4>
                    <div class="bg-black bg-opacity-30 rounded p-3 text-sm font-mono">
                        EXPLAIN SELECT * FROM devices<br>
                        WHERE status = 'active';
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>