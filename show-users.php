<?php
/**
 * Show Users from Gibson AI Database
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

require_once 'bootstrap.php';
require_once 'core/GibsonAIService.php';

echo "👥 Gibson AI Users Database\n";
echo "============================\n\n";

try {
    $gibson = new GibsonAIService();
    
    // Get all users
    $result = $gibson->makeApiCallPublic('/v1/-/user', null, 'GET');
    
    if ($result['success'] && isset($result['data'])) {
        $users = $result['data'];
        echo "✅ Found " . count($users) . " users\n\n";
        
        foreach ($users as $index => $user) {
            echo "--- User " . ($index + 1) . " ---\n";
            foreach ($user as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                // Hide sensitive data
                if (in_array($key, ['password', 'password_hash', 'api_key'])) {
                    $value = '[HIDDEN]';
                }
                echo sprintf("%-20s: %s\n", ucwords(str_replace('_', ' ', $key)), $value);
            }
            echo "\n";
        }
        
        // Export option
        echo "💾 Export to JSON file? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $export = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($export) === 'y') {
            // Remove sensitive data before export
            $exportUsers = array_map(function($user) {
                unset($user['password'], $user['password_hash'], $user['api_key']);
                return $user;
            }, $users);
            
            $filename = "gibson_users_export_" . date('Y-m-d_H-i-s') . ".json";
            file_put_contents($filename, json_encode($exportUsers, JSON_PRETTY_PRINT));
            echo "✅ Users exported to: $filename\n";
        }
        
    } else {
        echo "❌ Failed to retrieve users: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🏁 Done!\n";
?>