<?php
/**
 * Diagnostic Script for Painter Near Me
 * This script helps identify issues causing 503 errors on the live server
 * Upload this file and access it directly to see what's causing the problem
 */

// Set error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Painter Near Me - Server Diagnostic</h1>";
echo "<p>Running diagnostics at " . date('Y-m-d H:i:s T') . "</p>";

// Test 1: PHP Version and Extensions
echo "<h2>1. PHP Environment Check</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";

$requiredExtensions = ['mysqli', 'curl', 'json', 'mbstring', 'openssl'];
foreach ($requiredExtensions as $ext) {
    $status = extension_loaded($ext) ? '✅ Loaded' : '❌ Missing';
    echo "Extension {$ext}: {$status}<br>";
}

// Test 2: File Permissions and Directory Structure
echo "<h2>2. File System Check</h2>";
$paths = [
    __DIR__ => 'Root Directory',
    __DIR__ . '/config' => 'Config Directory',
    __DIR__ . '/core' => 'Core Directory',
    __DIR__ . '/logs' => 'Logs Directory',
    __DIR__ . '/uploads' => 'Uploads Directory',
    __DIR__ . '/project.env' => 'Environment File',
    __DIR__ . '/bootstrap.php' => 'Bootstrap File',
    __DIR__ . '/index.php' => 'Index File'
];

foreach ($paths as $path => $name) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $readable = is_readable($path) ? '✅' : '❌';
        $writable = is_writable($path) ? '✅' : '❌';
        echo "{$name}: Exists ({$perms}) Read:{$readable} Write:{$writable}<br>";
    } else {
        echo "{$name}: ❌ Missing<br>";
    }
}

// Test 3: Environment Configuration
echo "<h2>3. Environment Configuration</h2>";
try {
    if (file_exists(__DIR__ . '/project.env')) {
        echo "✅ project.env file found<br>";
        $envContent = file_get_contents(__DIR__ . '/project.env');
        echo "File size: " . strlen($envContent) . " bytes<br>";
        
        // Parse environment variables
        $lines = file(__DIR__ . '/project.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $envVars = [];
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $envVars[$key] = $value;
            }
        }
        
        $keyVars = ['APP_ENV', 'APP_DEBUG', 'GIBSON_API_KEY', 'GIBSON_DATABASE_ID'];
        foreach ($keyVars as $var) {
            $status = isset($envVars[$var]) ? '✅' : '❌';
            $value = isset($envVars[$var]) ? (strlen($envVars[$var]) > 20 ? substr($envVars[$var], 0, 20) . '...' : $envVars[$var]) : 'Not set';
            if ($var === 'GIBSON_API_KEY' && isset($envVars[$var])) {
                $value = 'Present (hidden)';
            }
            echo "{$var}: {$status} {$value}<br>";
        }
    } else {
        echo "❌ project.env file not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error reading environment: " . $e->getMessage() . "<br>";
}

// Test 4: Core Files Check
echo "<h2>4. Core Files Check</h2>";
try {
    if (file_exists(__DIR__ . '/bootstrap.php')) {
        echo "✅ bootstrap.php found<br>";
        ob_start();
        $bootstrapError = null;
        try {
            // Don't actually include bootstrap to avoid conflicts
            $content = file_get_contents(__DIR__ . '/bootstrap.php');
            echo "Bootstrap file size: " . strlen($content) . " bytes<br>";
            
            // Check for syntax errors
            $check = php_check_syntax(__DIR__ . '/bootstrap.php', $syntaxError);
            if ($check) {
                echo "✅ Bootstrap syntax OK<br>";
            } else {
                echo "❌ Bootstrap syntax error: " . $syntaxError . "<br>";
            }
        } catch (Exception $e) {
            $bootstrapError = $e->getMessage();
        }
        ob_get_clean();
        
        if ($bootstrapError) {
            echo "❌ Bootstrap error: " . $bootstrapError . "<br>";
        }
    } else {
        echo "❌ bootstrap.php not found<br>";
    }
    
    $coreFiles = ['/core/ErrorHandler.php', '/config/database.php'];
    foreach ($coreFiles as $file) {
        if (file_exists(__DIR__ . $file)) {
            echo "✅ {$file} found<br>";
        } else {
            echo "❌ {$file} missing<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error checking core files: " . $e->getMessage() . "<br>";
}

// Test 5: Gibson AI Connection Test
echo "<h2>5. Gibson AI Connection Test</h2>";
try {
    if (file_exists(__DIR__ . '/project.env')) {
        $envContent = file(__DIR__ . '/project.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $apiKey = null;
        $apiUrl = null;
        
        foreach ($envContent as $line) {
            if (strpos($line, 'GIBSON_API_KEY=') === 0) {
                $apiKey = trim(substr($line, 15));
            }
            if (strpos($line, 'GIBSON_API_URL=') === 0) {
                $apiUrl = trim(substr($line, 15));
            }
        }
        
        if ($apiKey && $apiUrl) {
            echo "API Key: Present<br>";
            echo "API URL: {$apiUrl}<br>";
            
            // Test basic connectivity
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                echo "❌ Connection error: {$error}<br>";
            } else {
                echo "✅ Gibson API reachable (HTTP {$httpCode})<br>";
            }
        } else {
            echo "❌ Gibson API credentials not found<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Gibson connection test error: " . $e->getMessage() . "<br>";
}

// Test 6: Memory and Resource Limits
echo "<h2>6. Resource Limits</h2>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";

// Test 7: Simple Bootstrap Test
echo "<h2>7. Bootstrap Loading Test</h2>";
try {
    // Create a minimal test
    if (file_exists(__DIR__ . '/project.env')) {
        echo "✅ Attempting to load environment...<br>";
        
        // Manually load environment without bootstrap
        $envFile = __DIR__ . '/project.env';
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
        
        echo "✅ Environment loaded successfully<br>";
        echo "APP_ENV: " . (getenv('APP_ENV') ?: 'Not set') . "<br>";
        echo "APP_DEBUG: " . (getenv('APP_DEBUG') ?: 'Not set') . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Bootstrap test error: " . $e->getMessage() . "<br>";
}

echo "<h2>Diagnostic Complete</h2>";
echo "<p>If you see this message, PHP is working. Check the results above for issues.</p>";
echo "<p><strong>Common Solutions:</strong></p>";
echo "<ul>";
echo "<li>If core files are missing: Re-upload the complete project</li>";
echo "<li>If permissions are wrong: Set directories to 755, files to 644</li>";
echo "<li>If PHP extensions are missing: Contact your hosting provider</li>";
echo "<li>If Gibson API is unreachable: Check firewall/network settings</li>";
echo "</ul>";
?> 