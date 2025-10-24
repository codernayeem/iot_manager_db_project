<?php
/**
 * Debug page to check database status
 * Access this page to see what's being returned by the API
 */
session_start();
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Database Status</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        h1 {
            color: #4ec9b0;
        }
        h2 {
            color: #569cd6;
            margin-top: 20px;
        }
        pre {
            background: #2d2d2d;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .success {
            color: #4ec9b0;
        }
        .error {
            color: #f48771;
        }
        .info {
            color: #dcdcaa;
        }
    </style>
</head>
<body>
    <h1>🔍 Database Status Debug</h1>
    
    <?php
    try {
        $database = new Database();
        $status = $database->getDatabaseStatus();
        
        echo "<h2>✅ Status Retrieved Successfully</h2>";
        
        echo "<h2>Connection</h2>";
        echo "<pre>";
        echo "Connected: " . ($status['connection'] ? '<span class="success">YES</span>' : '<span class="error">NO</span>') . "\n";
        echo "Database Exists: " . ($status['database_exists'] ? '<span class="success">YES</span>' : '<span class="error">NO</span>') . "\n";
        echo "</pre>";
        
        echo "<h2>Tables (" . count($status['tables']) . "/6)</h2>";
        echo "<pre>";
        print_r($status['tables']);
        echo "</pre>";
        
        echo "<h2>Views (" . count($status['views']) . "/2)</h2>";
        echo "<pre>";
        print_r($status['views']);
        echo "</pre>";
        
        echo "<h2>Procedures (" . count($status['procedures']) . "/2)</h2>";
        echo "<pre>";
        print_r($status['procedures']);
        echo "</pre>";
        
        echo "<h2>Functions (" . count($status['functions']) . "/2)</h2>";
        echo "<pre>";
        print_r($status['functions']);
        echo "</pre>";
        
        echo "<h2>Triggers (" . count($status['triggers']) . "/2)</h2>";
        echo "<pre>";
        print_r($status['triggers']);
        echo "</pre>";
        
        echo "<h2>Full JSON Response</h2>";
        echo "<pre>";
        echo json_encode($status, JSON_PRETTY_PRINT);
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "<h2 class='error'>❌ Error</h2>";
        echo "<pre class='error'>";
        echo $e->getMessage();
        echo "</pre>";
    }
    ?>
    
    <p style="margin-top: 30px; color: #858585;">
        <a href="index.php" style="color: #569cd6;">← Back to Database Setup</a>
    </p>
</body>
</html>
