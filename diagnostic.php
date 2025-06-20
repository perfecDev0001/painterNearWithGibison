<?php
/**
 * Diagnostic Script for Painter Near Me
 * This script helps identify server configuration issues
 */

// Enable error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Painter Near Me - Server Diagnostic</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Check PHP version
echo "<h2>PHP Configuration</h2>";
echo "<p>PHP Version: <span class='ok'>" . PHP_VERSION . "</span></p>";

// Check required PHP extensions
$requiredExtensions = ['mysqli', 'pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'openssl'];
echo "<h3>Required PHP Extensions:</h3>";
foreach ($requiredExtensions as $ext) {
    $status = extension_loaded($ext) ? "<span class='ok'>✓ Loaded</span>" : "<span class='error'>✗ Missing</span>";
    echo "<p>$ext: $status</p>";
}

// Check file permissions
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
    }
}

// Check environment files
echo "<h2>Environment Configuration</h2>";
$envFiles = ['project.env', '.env', '.gibson-env'];
foreach ($envFiles as $file) {
    if (file_exists($file)) {
        echo "<p>$file: <span class='ok'>Found</span></p>";
    } else {
        echo "<p>$file: <span class='warning'>Not found</span></p>";
    }
}

// Test basic file includes
echo "<h2>Core Files</h2>";
$coreFiles = [
    'bootstrap.php' => 'Bootstrap file',
    'core/ErrorHandler.php' => 'Error handler',
    'core/Wizard.php' => 'Quote wizard',
    'config/database.php' => 'Database config'
];

foreach ($coreFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<p>$description ($file): <span class='ok'>Found</span></p>";
    } else {
        echo "<p>$description ($file): <span class='error'>Missing</span></p>";
    }
}

// Test environment loading
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
            }
        }
        
        echo "<p>Environment variables loaded: <span class='ok'>" . count($envVars) . " variables</span></p>";
        
        // Check critical variables
        $criticalVars = ['APP_ENV', 'GIBSON_DEVELOPMENT_MODE', 'DB_HOST', 'DB_DATABASE'];
        foreach ($criticalVars as $var) {
            if (isset($envVars[$var])) {
                echo "<p>$var: <span class='ok'>" . htmlspecialchars($envVars[$var]) . "</span></p>";
            } else {
                echo "<p>$var: <span class='warning'>Not set</span></p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p>Environment loading error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}

// Test database connection
echo "<h2>Database Connection</h2>";
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
            $connection->close();
        }
    }
} catch (Exception $e) {
    echo "<p>Database test error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}

// Test bootstrap loading
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

// Test Wizard class loading
echo "<h2>Wizard Class Test</h2>";
try {
    if (class_exists('Wizard')) {
        echo "<p>Wizard class: <span class='ok'>Available</span></p>";
        
        // Try to instantiate
        $wizard = new Wizard();
        echo "<p>Wizard instantiation: <span class='ok'>Success</span></p>";
    } else {
        echo "<p>Wizard class: <span class='error'>Not found</span></p>";
    }
} catch (Exception $e) {
    echo "<p>Wizard test error: <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}

// Server information
echo "<h2>Server Information</h2>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";
echo "<p>Current Working Directory: " . getcwd() . "</p>";

// Memory and limits
echo "<h2>PHP Limits</h2>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . " seconds</p>";
echo "<p>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";

// Check for common issues
echo "<h2>Common Issues Check</h2>";

// Check if mod_rewrite is available
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p>mod_rewrite: <span class='ok'>Available</span></p>";
    } else {
        echo "<p>mod_rewrite: <span class='error'>Not available</span></p>";
    }
} else {
    echo "<p>mod_rewrite: <span class='warning'>Cannot detect (function not available)</span></p>";
}

// Check .htaccess
if (file_exists('.htaccess')) {
    echo "<p>.htaccess file: <span class='ok'>Found</span></p>";
    $htaccessSize = filesize('.htaccess');
    echo "<p>.htaccess size: {$htaccessSize} bytes</p>";
} else {
    echo "<p>.htaccess file: <span class='error'>Missing</span></p>";
}

echo "<hr>";
echo "<p><strong>Diagnostic completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>If you see any red errors above, those need to be fixed first</li>";
echo "<li>Check your hosting provider's error logs for more details</li>";
echo "<li>Ensure all required PHP extensions are installed</li>";
echo "<li>Verify database credentials are correct</li>";
echo "<li>Make sure file permissions allow PHP to read/write necessary directories</li>";
echo "</ul>";
?>