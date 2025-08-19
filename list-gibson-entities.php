<?php
/**
 * List Gibson AI Database Entities
 * Shows what data entities are available in your Gibson AI database
 */

require_once 'bootstrap.php';
require_once 'core/GibsonAIService.php';

function printStatus($message, $status = 'info') {
    $colors = [
        'success' => "\033[32m",
        'error' => "\033[31m",
        'warning' => "\033[33m",
        'info' => "\033[36m",
        'reset' => "\033[0m"
    ];
    
    $color = $colors[$status] ?? $colors['info'];
    echo $color . $message . $colors['reset'] . "\n";
}

printStatus("Gibson AI Database Entities", 'info');
printStatus(str_repeat("=", 40), 'info');

try {
    $gibson = new GibsonAIService();
    
    // Common entities to check
    $entitiesToCheck = [
        'user',
        'role', 
        'lead',
        'painter',
        'bid',
        'job',
        'quote',
        'painter-bid',
        'job-lead',
        'user-session',
        'payment',
        'notification'
    ];
    
    printStatus("\nChecking common entities:", 'info');
    
    foreach ($entitiesToCheck as $entity) {
        try {
            $result = $gibson->makeApiCallPublic("/v1/-/$entity", null, 'GET');
            
            if ($result['success']) {
                $count = isset($result['data']) && is_array($result['data']) ? count($result['data']) : 0;
                printStatus("✅ $entity: $count record(s)", 'success');
            } else {
                $httpCode = $result['http_code'] ?? 0;
                if ($httpCode === 400) {
                    printStatus("❌ $entity: Entity does not exist", 'error');
                } else {
                    printStatus("⚠️  $entity: HTTP $httpCode - " . ($result['error'] ?? 'Unknown error'), 'warning');
                }
            }
        } catch (Exception $e) {
            printStatus("❌ $entity: Exception - " . $e->getMessage(), 'error');
        }
        
        // Small delay to avoid rate limiting
        usleep(100000); // 0.1 second
    }
    
    printStatus("\nSummary:", 'info');
    printStatus("- Entities marked with ✅ exist and contain data", 'success');
    printStatus("- Entities marked with ❌ don't exist in your database", 'error');
    printStatus("- You can create missing entities through your Gibson AI dashboard", 'info');
    
} catch (Exception $e) {
    printStatus("Failed to initialize Gibson AI Service: " . $e->getMessage(), 'error');
}

echo "\n";
?>