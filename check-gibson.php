<?php
/**
 * Quick Gibson AI Connection Checker
 * Command-line tool for checking Gibson AI database connectivity
 */

// Include bootstrap
require_once 'bootstrap.php';

function printStatus($message, $status = 'info') {
    $colors = [
        'success' => "\033[32m", // Green
        'error' => "\033[31m",   // Red
        'warning' => "\033[33m", // Yellow
        'info' => "\033[36m",    // Cyan
        'reset' => "\033[0m"     // Reset
    ];
    
    $color = $colors[$status] ?? $colors['info'];
    echo $color . $message . $colors['reset'] . "\n";
}

function printHeader($text) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo strtoupper($text) . "\n";
    echo str_repeat("=", 50) . "\n";
}

printHeader("Gibson AI Database Connection Check");

// Check environment variables
printStatus("Checking environment variables...", 'info');
$requiredVars = ['GIBSON_API_KEY', 'GIBSON_API_URL', 'GIBSON_DATABASE_ID'];
$allSet = true;

foreach ($requiredVars as $var) {
    $value = getenv($var);
    if (empty($value)) {
        printStatus("❌ $var: Not set", 'error');
        $allSet = false;
    } else {
        $displayValue = $var === 'GIBSON_API_KEY' ? substr($value, 0, 20) . '...' : $value;
        printStatus("✅ $var: $displayValue", 'success');
    }
}

if (!$allSet) {
    printStatus("Missing required environment variables. Please check your project.env file.", 'error');
    exit(1);
}

// Check cURL
printStatus("\nChecking cURL extension...", 'info');
if (!function_exists('curl_init')) {
    printStatus("❌ cURL extension not available", 'error');
    exit(1);
}
printStatus("✅ cURL extension available", 'success');

// Initialize Gibson AI Service
printStatus("\nInitializing Gibson AI Service...", 'info');
try {
    require_once 'core/GibsonAIService.php';
    $gibson = new GibsonAIService();
    printStatus("✅ Gibson AI Service initialized", 'success');
} catch (Exception $e) {
    printStatus("❌ Failed to initialize: " . $e->getMessage(), 'error');
    exit(1);
}

// Test API connection
printStatus("\nTesting API connection...", 'info');
try {
    $result = $gibson->makeApiCallPublic('/v1/-/role', null, 'GET');
    
    if ($result['success']) {
        printStatus("✅ API connection successful", 'success');
        printStatus("   HTTP Status: " . ($result['http_code'] ?? 'Unknown'), 'info');
        
        if (isset($result['data']) && is_array($result['data'])) {
            printStatus("   Found " . count($result['data']) . " role(s)", 'info');
        }
    } else {
        printStatus("❌ API connection failed", 'error');
        printStatus("   Error: " . ($result['error'] ?? 'Unknown'), 'error');
        printStatus("   HTTP Status: " . ($result['http_code'] ?? 'Unknown'), 'error');
    }
} catch (Exception $e) {
    printStatus("❌ Exception: " . $e->getMessage(), 'error');
}

// Test database access
printStatus("\nTesting database access...", 'info');
try {
    $result = $gibson->makeApiCallPublic('/v1/-/user', null, 'GET');
    
    if ($result['success']) {
        printStatus("✅ Database access successful", 'success');
        printStatus("   HTTP Status: " . ($result['http_code'] ?? 'Unknown'), 'info');
        
        if (isset($result['data']) && is_array($result['data'])) {
            printStatus("   Found " . count($result['data']) . " user record(s)", 'info');
        }
    } else {
        $httpCode = $result['http_code'] ?? 0;
        if ($httpCode === 400) {
            printStatus("⚠️  Database access returned HTTP 400", 'warning');
            printStatus("   This might be normal if 'user' entity doesn't exist yet", 'warning');
        } else {
            printStatus("❌ Database access failed", 'error');
            printStatus("   Error: " . ($result['error'] ?? 'Unknown'), 'error');
            printStatus("   HTTP Status: " . $httpCode, 'error');
        }
    }
} catch (Exception $e) {
    printStatus("❌ Exception: " . $e->getMessage(), 'error');
}

// Summary
printHeader("Summary");
$gibsonEnabled = getenv('GIBSON_ENABLED') !== 'false';
$hasCredentials = !empty(getenv('GIBSON_API_KEY')) && !empty(getenv('GIBSON_DATABASE_ID'));

if ($gibsonEnabled && $hasCredentials) {
    printStatus("✅ Gibson AI database connection is configured and working", 'success');
    printStatus("   Your application can use Gibson AI for data storage", 'info');
    printStatus("   Main app: http://localhost:8088", 'info');
    printStatus("   Connection test: http://localhost:8089", 'info');
} else {
    printStatus("⚠️  Gibson AI connection has configuration issues", 'warning');
    if (!$gibsonEnabled) {
        printStatus("   - Gibson AI is disabled", 'warning');
    }
    if (!$hasCredentials) {
        printStatus("   - Missing API credentials", 'warning');
    }
}

echo "\n";
?>