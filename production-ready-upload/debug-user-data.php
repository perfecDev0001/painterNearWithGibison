<?php
require_once 'core/GibsonAIService.php';

$gibson = new GibsonAIService();

echo "<h1>User Database Debug</h1>\n";

// Get all users
$users = $gibson->makeApiCallPublic('/v1/-/user');
if ($users['success']) {
    echo "<h2>All Users in Database:</h2>\n";
    echo "<pre>" . json_encode($users['data'], JSON_PRETTY_PRINT) . "</pre>\n";
    
    echo "<h2>User Analysis:</h2>\n";
    foreach ($users['data'] as $user) {
        echo "<div style='margin: 10px; padding: 10px; border: 1px solid #ccc;'>\n";
        echo "<strong>User ID:</strong> " . $user['id'] . "<br>\n";
        echo "<strong>Email:</strong> " . $user['email'] . "<br>\n";
        echo "<strong>Name:</strong> " . $user['name'] . "<br>\n";
        echo "<strong>Role ID:</strong> " . $user['role_id'] . "<br>\n";
        echo "<strong>Has Password Hash:</strong> " . (isset($user['password_hash']) ? 'Yes' : 'No') . "<br>\n";
        echo "</div>\n";
    }
} else {
    echo "<div style='color: red;'>Error retrieving users: " . ($users['error'] ?? 'Unknown') . "</div>\n";
}

// Get all roles
$roles = $gibson->makeApiCallPublic('/v1/-/role');
if ($roles['success']) {
    echo "<h2>Available Roles:</h2>\n";
    echo "<pre>" . json_encode($roles['data'], JSON_PRETTY_PRINT) . "</pre>\n";
} else {
    echo "<div style='color: red;'>Error retrieving roles: " . ($roles['error'] ?? 'Unknown') . "</div>\n";
}
?> 