<?php
require_once 'core/GibsonAIService.php';

echo "=== CHECKING AVAILABLE ROLES ===\n";

$gibson = new GibsonAIService();

// Check available roles
echo "1. Checking available roles...\n";
$response = $gibson->makeApiCallPublic('/v1/-/role');
echo "Roles response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";

// Check role entities
echo "2. Checking role entity structure...\n";
$entities = $gibson->makeApiCallPublic('/v1/-/');
echo "Available entities: " . json_encode($entities, JSON_PRETTY_PRINT) . "\n\n";

// Try to create customer role if it doesn't exist
echo "3. Attempting to create customer role...\n";
$customerRole = [
    'id' => 2,
    'name' => 'customer',
    'description' => 'Customer role for job seekers'
];
$roleResult = $gibson->makeApiCallPublic('/v1/-/role', $customerRole, 'POST');
echo "Customer role creation: " . json_encode($roleResult, JSON_PRETTY_PRINT) . "\n\n";

echo "=== ROLE CHECK COMPLETE ===\n";
?> 