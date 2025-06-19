<?php
/**
 * Emergency Index - Minimal functionality to test the site
 * Use this temporarily to get the site running while we debug the main bootstrap
 */

// Basic error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Europe/London');

// Start session
session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painter Near Me - Emergency Mode</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .status {
            background: rgba(255, 193, 7, 0.2);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px solid #ffc107;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        .diagnostic-info {
            text-align: left;
            margin-top: 30px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🎨 Painter Near Me</div>
        
        <div class="status">
            <h2>⚠️ Maintenance Mode</h2>
            <p>We're currently experiencing technical difficulties and are working to resolve them.</p>
            <p>This emergency page confirms that PHP is working on the server.</p>
        </div>
        
        <div class="diagnostic-info">
            <h3>System Status:</h3>
            <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s T'); ?></p>
            <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
            <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
            <p><strong>Environment File:</strong> <?php echo file_exists('project.env') ? '✅ Found' : '❌ Missing'; ?></p>
            <p><strong>Bootstrap File:</strong> <?php echo file_exists('bootstrap.php') ? '✅ Found' : '❌ Missing'; ?></p>
            <p><strong>Core Directory:</strong> <?php echo is_dir('core') ? '✅ Found' : '❌ Missing'; ?></p>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="diagnostic.php" class="btn">🔧 Run Full Diagnostics</a>
            <a href="simple-test.php" class="btn">🧪 Simple PHP Test</a>
        </div>
        
        <p style="margin-top: 30px; opacity: 0.8;">
            If you're the site administrator, please check the diagnostic tools above to identify the issue.
        </p>
    </div>
</body>
</html> 