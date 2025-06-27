<?php
/**
 * Enhanced System Status Dashboard
 * Real-time monitoring for Painter Near Me platform
 */

require_once 'core/DatabaseManager.php';

// Security check
session_start();
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isAdmin && !isset($_GET['public'])) {
    header('Location: admin-login.php');
    exit;
}

$db = DatabaseManager::getInstance();
$startTime = microtime(true);

// Get system information
function getSystemInfo() {
    return [
        'php_version' => phpversion(),
        'memory_usage' => memory_get_usage(true),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'disk_free_space' => disk_free_space('.'),
        'disk_total_space' => disk_total_space('.')
    ];
}

// Test database connectivity
function testDatabase($db) {
    try {
        $start = microtime(true);
        $result = $db->query("SELECT 1 as test");
        $responseTime = (microtime(true) - $start) * 1000;
        
        return [
            'status' => 'connected',
            'response_time' => round($responseTime, 2),
            'driver' => $db->getCurrentDriver(),
            'info' => $db->getDatabaseInfo()
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage(),
            'driver' => $db->getCurrentDriver()
        ];
    }
}

// Test file permissions
function testFilePermissions() {
    $paths = [
        'uploads' => './uploads',
        'logs' => './logs',
        'database' => './database',
        'assets' => './assets'
    ];
    
    $results = [];
    foreach ($paths as $name => $path) {
        $results[$name] = [
            'exists' => file_exists($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'path' => $path
        ];
    }
    
    return $results;
}

// Test email configuration
function testEmailConfig() {
    $config = [];
    
    if (file_exists('config/email.php')) {
        $emailConfig = include 'config/email.php';
        $config = [
            'configured' => !empty($emailConfig['host']),
            'host' => $emailConfig['host'] ?? 'Not configured',
            'port' => $emailConfig['port'] ?? 'Not configured',
            'encryption' => $emailConfig['encryption'] ?? 'Not configured'
        ];
    } else {
        $config = ['configured' => false, 'error' => 'Email config file not found'];
    }
    
    return $config;
}

// Test API endpoints
function testAPIEndpoints() {
    $endpoints = [
        'payment-api' => 'api/payment-api.php/config',
        'stripe-webhook' => 'api/stripe-webhook.php'
    ];
    
    $results = [];
    foreach ($endpoints as $name => $endpoint) {
        $results[$name] = [
            'exists' => file_exists($endpoint),
            'accessible' => file_exists($endpoint) && is_readable($endpoint)
        ];
    }
    
    return $results;
}

// Get performance metrics
function getPerformanceMetrics($startTime) {
    return [
        'page_load_time' => round((microtime(true) - $startTime) * 1000, 2),
        'memory_peak' => memory_get_peak_usage(true),
        'included_files' => count(get_included_files())
    ];
}

// Collect all status information
$status = [
    'timestamp' => date('Y-m-d H:i:s'),
    'system' => getSystemInfo(),
    'database' => testDatabase($db),
    'file_permissions' => testFilePermissions(),
    'email_config' => testEmailConfig(),
    'api_endpoints' => testAPIEndpoints(),
    'stats' => $db->getStats(),
    'performance' => getPerformanceMetrics($startTime)
];

// JSON API response
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode($status, JSON_PRETTY_PRINT);
    exit;
}

// Format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get status color
function getStatusColor($condition) {
    return $condition ? '#00b050' : '#dc3545';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - Painter Near Me</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #00b050;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .timestamp {
            color: #666;
            font-size: 1rem;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-left: 5px solid #00b050;
        }
        
        .status-card.error {
            border-left-color: #dc3545;
        }
        
        .status-card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .status-table th,
        .status-table td {
            text-align: left;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .status-table th {
            font-weight: 600;
            color: #333;
            width: 40%;
        }
        
        .status-table td {
            color: #666;
        }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .metric-label {
            font-weight: 500;
            color: #333;
        }
        
        .metric-value {
            color: #666;
            font-family: monospace;
        }
        
        .refresh-btn {
            background: #00b050;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .refresh-btn:hover {
            background: #009140;
        }
        
        .auto-refresh {
            text-align: center;
            margin-top: 20px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00b050, #009140);
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .status-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 System Status Dashboard</h1>
            <div class="timestamp">Last updated: <?= $status['timestamp'] ?></div>
        </div>
        
        <div class="status-grid">
            <!-- Database Status -->
            <div class="status-card <?= $status['database']['status'] === 'connected' ? '' : 'error' ?>">
                <h3>
                    <span class="status-indicator" style="background-color: <?= getStatusColor($status['database']['status'] === 'connected') ?>"></span>
                    Database Status
                </h3>
                <table class="status-table">
                    <tr>
                        <th>Status</th>
                        <td><?= ucfirst($status['database']['status']) ?></td>
                    </tr>
                    <tr>
                        <th>Driver</th>
                        <td><?= ucfirst($status['database']['driver']) ?></td>
                    </tr>
                    <?php if (isset($status['database']['response_time'])): ?>
                    <tr>
                        <th>Response Time</th>
                        <td><?= $status['database']['response_time'] ?>ms</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($status['database']['info']['version'])): ?>
                    <tr>
                        <th>Version</th>
                        <td><?= $status['database']['info']['version'] ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- System Information -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator" style="background-color: #00b050"></span>
                    System Information
                </h3>
                <table class="status-table">
                    <tr>
                        <th>PHP Version</th>
                        <td><?= $status['system']['php_version'] ?></td>
                    </tr>
                    <tr>
                        <th>Memory Usage</th>
                        <td><?= formatBytes($status['system']['memory_usage']) ?></td>
                    </tr>
                    <tr>
                        <th>Memory Limit</th>
                        <td><?= $status['system']['memory_limit'] ?></td>
                    </tr>
                    <tr>
                        <th>Free Disk Space</th>
                        <td><?= formatBytes($status['system']['disk_free_space']) ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Platform Statistics -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator" style="background-color: #00b050"></span>
                    Platform Statistics
                </h3>
                <table class="status-table">
                    <tr>
                        <th>Painters</th>
                        <td><?= number_format($status['stats']['painters']) ?></td>
                    </tr>
                    <tr>
                        <th>Leads</th>
                        <td><?= number_format($status['stats']['leads']) ?></td>
                    </tr>
                    <tr>
                        <th>Payments</th>
                        <td><?= number_format($status['stats']['payments']) ?></td>
                    </tr>
                    <tr>
                        <th>Total Revenue</th>
                        <td>£<?= number_format($status['stats']['total_revenue'], 2) ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Performance Metrics -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator" style="background-color: #00b050"></span>
                    Performance Metrics
                </h3>
                <table class="status-table">
                    <tr>
                        <th>Page Load Time</th>
                        <td><?= $status['performance']['page_load_time'] ?>ms</td>
                    </tr>
                    <tr>
                        <th>Peak Memory</th>
                        <td><?= formatBytes($status['performance']['memory_peak']) ?></td>
                    </tr>
                    <tr>
                        <th>Included Files</th>
                        <td><?= $status['performance']['included_files'] ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- File Permissions -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator" style="background-color: #00b050"></span>
                    File Permissions
                </h3>
                <?php foreach ($status['file_permissions'] as $name => $perm): ?>
                <div class="metric-row">
                    <span class="metric-label"><?= ucfirst($name) ?></span>
                    <span class="metric-value" style="color: <?= getStatusColor($perm['writable']) ?>">
                        <?= $perm['exists'] ? ($perm['writable'] ? 'Writable' : 'Read-only') : 'Missing' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- API Endpoints -->
            <div class="status-card">
                <h3>
                    <span class="status-indicator" style="background-color: #00b050"></span>
                    API Endpoints
                </h3>
                <?php foreach ($status['api_endpoints'] as $name => $endpoint): ?>
                <div class="metric-row">
                    <span class="metric-label"><?= ucfirst(str_replace('-', ' ', $name)) ?></span>
                    <span class="metric-value" style="color: <?= getStatusColor($endpoint['accessible']) ?>">
                        <?= $endpoint['accessible'] ? 'Available' : 'Unavailable' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="auto-refresh">
            <button class="refresh-btn" onclick="location.reload()">🔄 Refresh Status</button>
            <p style="margin-top: 10px; color: #666;">
                Auto-refresh in <span id="countdown">30</span> seconds
            </p>
        </div>
    </div>
    
    <script>
        // Auto-refresh countdown
        let countdown = 30;
        const countdownElement = document.getElementById('countdown');
        
        setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);
        
        // Add some interactivity
        document.querySelectorAll('.status-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
                card.style.boxShadow = '0 8px 30px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
                card.style.boxShadow = '0 4px 20px rgba(0,0,0,0.1)';
            });
        });
    </script>
</body>
</html>