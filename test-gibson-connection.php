<?php
/**
 * Gibson AI Database Connection Test
 * This script tests the connection to Gibson AI database and displays connection status
 */

// Include bootstrap to load environment and error handling
require_once 'bootstrap.php';

echo "<h1>Gibson AI Database Connection Test</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #ffe8e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #e8f0ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .warning { color: orange; background: #fff8e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>\n";

// Test 1: Check Environment Variables
echo "<h2>1. Environment Variables Check</h2>\n";
$envVars = [
    'GIBSON_API_KEY' => getenv('GIBSON_API_KEY'),
    'GIBSON_API_URL' => getenv('GIBSON_API_URL'),
    'GIBSON_DATABASE_ID' => getenv('GIBSON_DATABASE_ID'),
    'GIBSON_PROJECT_ID' => getenv('GIBSON_PROJECT_ID'),
    'GIBSON_ENABLED' => getenv('GIBSON_ENABLED'),
    'GIBSON_DEVELOPMENT_MODE' => getenv('GIBSON_DEVELOPMENT_MODE')
];

echo "<table>\n";
echo "<tr><th>Variable</th><th>Value</th><th>Status</th></tr>\n";
foreach ($envVars as $key => $value) {
    $status = empty($value) ? '<span style="color: red;">❌ Missing</span>' : '<span style="color: green;">✅ Set</span>';
    $displayValue = $key === 'GIBSON_API_KEY' ? (empty($value) ? 'Not set' : substr($value, 0, 20) . '...') : ($value ?: 'Not set');
    echo "<tr><td>$key</td><td>$displayValue</td><td>$status</td></tr>\n";
}
echo "</table>\n";

// Test 2: Check cURL Extension
echo "<h2>2. cURL Extension Check</h2>\n";
if (function_exists('curl_init')) {
    echo "<div class='success'>✅ cURL extension is available</div>\n";
    $curlVersion = curl_version();
    echo "<div class='info'>cURL Version: " . $curlVersion['version'] . "</div>\n";
} else {
    echo "<div class='error'>❌ cURL extension is not available</div>\n";
    exit;
}

// Test 3: Initialize Gibson AI Service
echo "<h2>3. Gibson AI Service Initialization</h2>\n";
try {
    require_once 'core/GibsonAIService.php';
    $gibson = new GibsonAIService();
    echo "<div class='success'>✅ Gibson AI Service initialized successfully</div>\n";
} catch (Exception $e) {
    echo "<div class='error'>❌ Failed to initialize Gibson AI Service: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    exit;
}

// Test 4: Test API Connection
echo "<h2>4. API Connection Test</h2>\n";
try {
    // Test basic connectivity with a simple API call
    $testResult = $gibson->makeApiCallPublic('/v1/-/role', null, 'GET');
    
    if ($testResult['success']) {
        echo "<div class='success'>✅ Successfully connected to Gibson AI API</div>\n";
        echo "<div class='info'>HTTP Status Code: " . ($testResult['http_code'] ?? 'Unknown') . "</div>\n";
        
        if (isset($testResult['data']) && is_array($testResult['data'])) {
            echo "<div class='info'>Response contains " . count($testResult['data']) . " role(s)</div>\n";
        }
    } else {
        echo "<div class='error'>❌ Failed to connect to Gibson AI API</div>\n";
        echo "<div class='error'>Error: " . htmlspecialchars($testResult['error'] ?? 'Unknown error') . "</div>\n";
        echo "<div class='info'>HTTP Status Code: " . ($testResult['http_code'] ?? 'Unknown') . "</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception during API connection test: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

// Test 5: Test Database Access
echo "<h2>5. Database Access Test</h2>\n";
try {
    // Try to access a simple entity to test database connectivity
    $dbTestResult = $gibson->makeApiCallPublic('/v1/-/user', null, 'GET');
    
    if ($dbTestResult['success']) {
        echo "<div class='success'>✅ Successfully accessed Gibson AI database</div>\n";
        echo "<div class='info'>HTTP Status Code: " . ($dbTestResult['http_code'] ?? 'Unknown') . "</div>\n";
        
        if (isset($dbTestResult['data']) && is_array($dbTestResult['data'])) {
            echo "<div class='info'>Database contains " . count($dbTestResult['data']) . " user record(s)</div>\n";
        }
    } else {
        echo "<div class='warning'>⚠️ Database access test returned an error</div>\n";
        echo "<div class='warning'>Error: " . htmlspecialchars($dbTestResult['error'] ?? 'Unknown error') . "</div>\n";
        echo "<div class='info'>HTTP Status Code: " . ($dbTestResult['http_code'] ?? 'Unknown') . "</div>\n";
        
        // This might be expected if the user entity doesn't exist yet
        if (isset($dbTestResult['http_code']) && $dbTestResult['http_code'] === 400) {
            echo "<div class='info'>Note: HTTP 400 might indicate the 'user' entity doesn't exist in your database yet, which is normal for a new setup.</div>\n";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception during database access test: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

// Test 6: Test Available Entities
echo "<h2>6. Available Entities Test</h2>\n";
try {
    // Try to get schema or available entities
    $schemaResult = $gibson->makeApiCallPublic('/v1/-/schema', null, 'GET');
    
    if ($schemaResult['success']) {
        echo "<div class='success'>✅ Successfully retrieved database schema</div>\n";
        
        if (isset($schemaResult['data']) && is_array($schemaResult['data'])) {
            echo "<div class='info'>Available entities:</div>\n";
            echo "<ul>\n";
            foreach ($schemaResult['data'] as $entity) {
                if (is_string($entity)) {
                    echo "<li>" . htmlspecialchars($entity) . "</li>\n";
                } elseif (is_array($entity) && isset($entity['name'])) {
                    echo "<li>" . htmlspecialchars($entity['name']) . "</li>\n";
                }
            }
            echo "</ul>\n";
        }
    } else {
        echo "<div class='warning'>⚠️ Could not retrieve database schema</div>\n";
        echo "<div class='warning'>Error: " . htmlspecialchars($schemaResult['error'] ?? 'Unknown error') . "</div>\n";
        echo "<div class='info'>HTTP Status Code: " . ($schemaResult['http_code'] ?? 'Unknown') . "</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception during schema test: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

// Test 7: Connection Summary
echo "<h2>7. Connection Summary</h2>\n";

$gibsonEnabled = getenv('GIBSON_ENABLED') !== 'false';
$hasApiKey = !empty(getenv('GIBSON_API_KEY'));
$hasDatabaseId = !empty(getenv('GIBSON_DATABASE_ID'));
$curlAvailable = function_exists('curl_init');

if ($gibsonEnabled && $hasApiKey && $hasDatabaseId && $curlAvailable) {
    echo "<div class='success'>✅ Gibson AI database connection is properly configured and should be working</div>\n";
    echo "<div class='info'>Your application can now use Gibson AI for data storage and retrieval.</div>\n";
} else {
    echo "<div class='warning'>⚠️ Gibson AI database connection has some issues:</div>\n";
    echo "<ul>\n";
    if (!$gibsonEnabled) echo "<li>Gibson AI is disabled (GIBSON_ENABLED=false)</li>\n";
    if (!$hasApiKey) echo "<li>Missing Gibson API key</li>\n";
    if (!$hasDatabaseId) echo "<li>Missing Gibson database ID</li>\n";
    if (!$curlAvailable) echo "<li>cURL extension not available</li>\n";
    echo "</ul>\n";
}

echo "<h2>8. Next Steps</h2>\n";
echo "<div class='info'>\n";
echo "<p><strong>If connection is working:</strong></p>\n";
echo "<ul>\n";
echo "<li>Your application will use Gibson AI for data storage</li>\n";
echo "<li>Check the main application at <a href='http://localhost:8088'>http://localhost:8088</a></li>\n";
echo "<li>Monitor logs at <code>logs/gibson.log</code> for API activity</li>\n";
echo "</ul>\n";

echo "<p><strong>If connection has issues:</strong></p>\n";
echo "<ul>\n";
echo "<li>Verify your Gibson AI credentials in <code>project.env</code></li>\n";
echo "<li>Check if your Gibson AI account is active</li>\n";
echo "<li>The application will fall back to local storage if Gibson AI is unavailable</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>