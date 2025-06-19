<?php
require_once 'core/GibsonAIService.php';

echo "=== CREATING CUSTOMER ROLE AND ACCOUNTS ===\n";

$gibson = new GibsonAIService();

// 1. Create customer role without ID (let Gibson assign it)
echo "1. Creating customer role...\n";
$customerRole = [
    'name' => 'customer',
    'description' => 'Customer role for job seekers'
];
$roleResult = $gibson->makeApiCallPublic('/v1/-/role', $customerRole, 'POST');
echo "Customer role creation: " . json_encode($roleResult, JSON_PRETTY_PRINT) . "\n\n";

// 2. Check roles again to see what ID was assigned
echo "2. Checking roles after creation...\n";
$rolesResponse = $gibson->makeApiCallPublic('/v1/-/role');
echo "Updated roles: " . json_encode($rolesResponse, JSON_PRETTY_PRINT) . "\n\n";

// 3. Get customer role ID
$customerRoleId = null;
if ($rolesResponse['success'] && isset($rolesResponse['data'])) {
    foreach ($rolesResponse['data'] as $role) {
        if ($role['name'] === 'customer') {
            $customerRoleId = $role['id'];
            echo "Found customer role ID: $customerRoleId\n";
            break;
        }
    }
}

// 4. Create customer user with correct role ID
if ($customerRoleId) {
    echo "4. Creating customer user with role_id: $customerRoleId...\n";
    $customerData = [
        'email' => 'customer@painter-near-me.co.uk',
        'password' => 'CustomerPass123!',
        'name' => 'Test Customer',
        'role_id' => $customerRoleId
    ];
    $result = $gibson->registerUser($customerData);
    echo "Customer result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "4. Could not find customer role ID - skipping customer creation\n\n";
}

echo "=== CUSTOMER SETUP COMPLETE ===\n";
?> 