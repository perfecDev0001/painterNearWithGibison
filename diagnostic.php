<?php
/**
 * Enhanced Diagnostic Script for Painter Near Me
 * This script helps identify server configuration issues and Gibson AI connectivity
 */

// Enable error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Painter Near Me - Enhanced Server Diagnostic</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;} .section{margin:20px 0;padding:15px;border:1px solid #ddd;border-radius:5px;}</style>";

// Check PHP version
echo "<div class='section'>";
echo "<h2>PHP Configuration</h2>";
echo "<p>PHP Version: <span class='ok'>" . PHP_VERSION . "</span></p>";
echo "<p>Server API: " . php_sapi_name() . "</p>";

// Check required PHP extensions
$requiredExtensions = ['mysqli', 'pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl', 'session'];
echo "<h3>Required PHP Extensions:</h3>";
foreach ($requiredExtensions as $ext) {
    $status = extension_loaded($ext) ? "<span class='ok'>✓ Loaded</span>" : "<span class='error'>✗ Missing</span>";
    echo "<p>$ext: $status</p>";
}

// Check cURL specifically
if (extension_loaded('curl')) {
    $curlVersion = curl_version();
    echo "<p>cURL Version: <span class='ok'>" . $curlVersion['version'] . "</span></p>";
    echo "<p>SSL Version: <span class='ok'>" . $curlVersion['ssl_version'] . "</span></p>";
}
echo "</div>";

// Check file permissions
echo "<div class='section'>";
echo "<h2>File System</h2>";
$checkPaths = [
    '.' => 'Root directory',
    './logs' => 'Logs directory',
    './uploads' => 'Uploads directory',
    './config' => 'Config directory',
    './core' => 'Core directory'
];

foreach ($checkPaths as $path => $description) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? "<span class='ok'>Writable</span>" : "<span class='warning'>Read-only</span>";
        echo "<p>$description ($path): Permissions $perms - $writable</p>";
    } else {
        echo "<p>$description ($path): <span class='error'>Not found</span></p>";
        // Try to create directory
        if (strpos($path, 'logs') !== false || strpos($path, 'uploads') !== false) {
            if (@mkdir($path, 0755, true)) {
                echo "<p>→ <span class='ok'>Created successfully</span></p>";
            } else {
                echo "<p>→ <span class='error'>Failed to create</span></p>";
            }
        }
    }
}
echo "</div>";

// Check environment files
echo "<div class='section'>";
echo "<h2>Environment Configuration</h2>";
$envFiles = ['project.env', '.env', '.gibson-env'];
foreach ($envFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "<p>$file: <span class='ok'>Found ($size bytes)</span></p>";
    } else {
        echo "<p>$file: <span class='warning'>Not found</span></p>";
    }
}
echo "</div>";

// Test environment loading
echo "<div class='section'>";
echo "<h2>Environment Variables</h2>";
try {
    // Load environment manually
    if (file_exists('project.env')) {
        $lines = file('project.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $envVars = [];
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $envVars[$key] = $value;
                putenv("$key=$value"); // Set for this script
            }
        }
        
        echo "<p>Environment variables loaded: <span class='ok'>" . count($envVars) . " variables</span></p>";
        
        // Check critical variables
        $criticalVars = ['APP_ENV', 'GIBSON_API_KEY', 'GIBSON_DATABASE_ID', 'GIBSON_API_URL', 'DB_HOST', 'DB_DATABASE'];
        foreach ($criticalVars as $var) {
            if (isset($envVars[$var])) {
                $displayValue = $var === 'GIBSON_API_KEY' ? substr($envVars[$var], 0, 20) . '...' : $envVars[$var];
                echo "<p>$var: <span class='ok'>" . htmlspecialchars($displayValue) . "</span></p>";
            } else {
                echo "<p>$var: <span class='warning'>Not set</span></p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p>Environment loading error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}
echo "</div>";

// Test bootstrap loading
echo "<div class='section'>";
echo "<h2>Bootstrap Test</h2>";
try {
    // Test if we can load bootstrap without errors
    ob_start();
    require_once 'bootstrap.php';
    $bootstrapOutput = ob_get_clean();
    
    echo "<p>Bootstrap loading: <span class='ok'>Success</span></p>";
    if (!empty($bootstrapOutput)) {
        echo "<p>Bootstrap output:</p><pre>" . htmlspecialchars($bootstrapOutput) . "</pre>";
    }
    
    // Check if constants are defined
    $constants = ['ROOT_PATH', 'ENVIRONMENT', 'BOOTSTRAP_LOADED'];
    foreach ($constants as $const) {
        if (defined($const)) {
            echo "<p>$const: <span class='ok'>" . htmlspecialchars(constant($const)) . "</span></p>";
        } else {
            echo "<p>$const: <span class='error'>Not defined</span></p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>Bootstrap test error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}
echo "</div>";

// Test Gibson AI Configuration
echo "<div class='section'>";
echo "<h2>Gibson AI Configuration Test</h2>";
try {
    if (file_exists('config/gibson.php')) {
        $gibsonConfig = require 'config/gibson.php';
        echo "<p>Gibson config loaded: <span class='ok'>Success</span></p>";
        
        $apiKey = $gibsonConfig['api_key'] ?? '';
        $apiUrl = $gibsonConfig['api_url'] ?? '';
        $databaseId = $gibsonConfig['database_id'] ?? '';
        
        echo "<p>API URL: <span class='ok'>" . htmlspecialchars($apiUrl) . "</span></p>";
        echo "<p>Database ID: <span class='ok'>" . htmlspecialchars($databaseId) . "</span></p>";
        echo "<p>API Key: " . (strlen($apiKey) > 0 ? "<span class='ok'>Present (" . strlen($apiKey) . " chars)</span>" : "<span class='error'>Missing</span>") . "</p>";
        
    } else {
        echo "<p>Gibson config: <span class='error'>File not found</span></p>";
    }
} catch (Exception $e) {
    echo "<p>Gibson config error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}
echo "</div>";

// Test Gibson AI Service
echo "<div class='section'>";
echo "<h2>Gibson AI Service Test</h2>";
try {
    require_once 'core/GibsonAIService.php';
    $gibson = new GibsonAIService();
    echo "<p>Gibson AI Service: <span class='ok'>Instantiated successfully</span></p>";
    
    // Test API connectivity with timeout
    echo "<p>Testing API connectivity...</p>";
    
    // Set short timeout for test
    $originalTimeout = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', 10);
    
    $startTime = microtime(true);
    $testResult = $gibson->makeApiCallPublic('/v1/-/role', null, 'GET');
    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    // Restore timeout
    ini_set('default_socket_timeout', $originalTimeout);
    
    echo "<p>Response time: {$responseTime}ms</p>";
    echo "<p>HTTP Code: " . ($testResult['http_code'] ?? 'N/A') . "</p>";
    
    if ($testResult['success']) {
        echo "<p>API Connection: <span class='ok'>✓ Success</span></p>";
        if (isset($testResult['data']) && is_array($testResult['data'])) {
            echo "<p>Response data: <span class='ok'>Valid JSON (" . count($testResult['data']) . " items)</span></p>";
        }
    } else {
        echo "<p>API Connection: <span class='error'>✗ Failed</span></p>";
        echo "<p>Error: " . htmlspecialchars($testResult['error'] ?? 'Unknown error') . "</p>";
        
        // Additional debugging
        if (isset($testResult['data'])) {
            echo "<p>Raw response:</p><pre>" . htmlspecialchars(json_encode($testResult['data'], JSON_PRETTY_PRINT)) . "</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>Gibson AI Service error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
    echo "<p>Stack trace:</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
echo "</div>";

// Test database connection
echo "<div class='section'>";
echo "<h2>Database Connection Test</h2>";
try {
    if (file_exists('config/database.php')) {
        $config = require 'config/database.php';
        
        echo "<p>Database config loaded: <span class='ok'>Success</span></p>";
        echo "<p>Host: " . htmlspecialchars($config['host']) . "</p>";
        echo "<p>Database: " . htmlspecialchars($config['database']) . "</p>";
        echo "<p>Port: " . htmlspecialchars($config['port']) . "</p>";
        
        // Test connection
        $connection = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );
        
        if ($connection->connect_error) {
            echo "<p>Database connection: <span class='error'>Failed - " . htmlspecialchars($connection->connect_error) . "</span></p>";
        } else {
            echo "<p>Database connection: <span class='ok'>Success</span></p>";
            echo "<p>MySQL Version: <span class='ok'>" . $connection->server_info . "</span></p>";
            $connection->close();
        }
    }
} catch (Exception $e) {
    echo "<p>Database test error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}
echo "</div>";

// Network connectivity test
echo "<div class='section'>";
echo "<h2>Network Connectivity Test</h2>";

$testUrls = [
    'https://api.gibsonai.com' => 'Gibson AI API',
    'https://www.google.com' => 'Google (general connectivity)',
    'https://httpbin.org/get' => 'HTTP test service'
];

foreach ($testUrls as $url => $description) {
    echo "<p>Testing $description ($url):</p>";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_NOBODY => true, // HEAD request only
        CURLOPT_USERAGENT => 'PainterNearMe-Diagnostic/1.0'
    ]);
    
    $startTime = microtime(true);
    $result = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    curl_close($ch);
    
    if ($error) {
        echo "<p>→ <span class='error'>Failed: $error</span></p>";
    } else {
        $status = ($httpCode >= 200 && $httpCode < 400) ? 'ok' : 'warning';
        echo "<p>→ <span class='$status'>HTTP $httpCode ({$responseTime}ms)</span></p>";
    }
}
echo "</div>";

// Server information
echo "<div class='section'>";
echo "<h2>Server Information</h2>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";
echo "<p>Current Working Directory: " . getcwd() . "</p>";
echo "<p>Server Time: " . date('Y-m-d H:i:s T') . "</p>";
echo "<p>PHP Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>PHP Max Execution Time: " . ini_get('max_execution_time') . " seconds</p>";
echo "</div>";

// Recommendations
echo "<div class='section'>";
echo "<h2>Recommendations</h2>";
echo "<div style='background:#f0f8ff;padding:15px;border-radius:5px;'>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>If Gibson AI connection failed:</strong> Check your API key and network connectivity</li>";
echo "<li><strong>If database connection failed:</strong> Verify database credentials in project.env</li>";
echo "<li><strong>If file permissions are wrong:</strong> Set directories to 755 and files to 644</li>";
echo "<li><strong>If PHP extensions are missing:</strong> Contact your hosting provider</li>";
echo "<li><strong>Check error logs:</strong> Look in logs/error.log for detailed error messages</li>";
echo "</ol>";

echo "<h3>Common Solutions:</h3>";
echo "<ul>";
echo "<li>Ensure all files are uploaded correctly via FTP</li>";
echo "<li>Check that project.env contains correct Gibson AI credentials</li>";
echo "<li>Verify your hosting provider allows outbound HTTPS connections</li>";
echo "<li>Make sure logs and uploads directories are writable</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Diagnostic completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><a href='/' style='color:#00b050;'>← Back to Homepage</a> | <a href='contact.php' style='color:#00b050;'>Contact Support</a></p>";
?>