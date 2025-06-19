<?php
require_once 'core/GibsonAIService.php';

echo "=== FIXING LIVE DEPLOYMENT ACCOUNTS ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$gibson = new GibsonAIService();

// 1. Create customer user (role_id: 2)
echo "1. Creating customer user (role_id: 2)...\n";
$customerData = [
    'email' => 'customer@painter-near-me.co.uk',
    'password' => 'CustomerPass123!',
    'name' => 'Test Customer',
    'role_id' => 2
];
$result = $gibson->registerUser($customerData);
echo "Customer result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// 2. Create painter user (role_id: 3) with known password
echo "2. Creating painter user (role_id: 3)...\n";
$painterData = [
    'email' => 'painter@painter-near-me.co.uk',
    'password' => 'PainterPass123!',
    'name' => 'Test Painter Company',
    'role_id' => 3
];
$result2 = $gibson->registerUser($painterData);
echo "Painter result: " . json_encode($result2, JSON_PRETTY_PRINT) . "\n\n";

// 3. Try to update admin password - delete and recreate
echo "3. Recreating admin with known password...\n";
$adminData = [
    'email' => 'new-admin@painter-near-me.co.uk',
    'password' => 'AdminPass123!',
    'name' => 'New System Administrator',
    'role_id' => 1
];
$result3 = $gibson->registerUser($adminData);
echo "New admin result: " . json_encode($result3, JSON_PRETTY_PRINT) . "\n\n";

echo "=== ACCOUNT CREATION COMPLETE ===\n";
?> 