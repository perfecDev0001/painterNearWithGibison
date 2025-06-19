<?php
// Start session early to avoid header issues
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'core/GibsonAIService.php';
require_once 'core/GibsonAuth.php';

echo "=== LIVE DEPLOYMENT AUTHENTICATION DEBUG ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$gibson = new GibsonAIService();
$auth = new GibsonAuth();

// 1. Check Gibson AI connectivity
echo "1. GIBSON AI CONNECTIVITY:\n";
try {
    $apiUrl = $gibson->getApiUrl();
    $dbId = $gibson->getDatabaseId();
    echo "   ✓ API URL: $apiUrl\n";
    echo "   ✓ Database ID: $dbId\n";
    echo "   ✓ Connection: OK\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 2. Check all users in database
echo "\n2. ALL USERS IN DATABASE:\n";
try {
    $users = $gibson->getAllUsers();
    echo "   Raw response: " . json_encode($users, JSON_PRETTY_PRINT) . "\n";
    
    if (is_array($users)) {
        foreach ($users as $index => $user) {
            echo "   User $index:\n";
            echo "     - ID: " . ($user['id'] ?? 'N/A') . "\n";
            echo "     - Email: " . ($user['email'] ?? 'N/A') . "\n";
            echo "     - Role ID: " . ($user['role_id'] ?? 'N/A') . "\n";
            echo "     - Password Hash: " . (isset($user['password_hash']) ? substr($user['password_hash'], 0, 20) . '...' : 'N/A') . "\n";
            echo "     - Created: " . ($user['created_at'] ?? 'N/A') . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error getting users: " . $e->getMessage() . "\n";
}

// 3. Create or find admin user
echo "\n3. ADMIN USER MANAGEMENT:\n";
try {
    // Check if admin exists
    $adminExists = $gibson->checkAdminExists();
    echo "   Admin exists: " . ($adminExists ? 'YES' : 'NO') . "\n";
    
    if (!$adminExists) {
        echo "   Creating new admin user...\n";
        $adminData = [
            'email' => 'admin@painter-near-me.co.uk',
            'password' => 'AdminPass123!',
            'name' => 'System Administrator',
            'role_id' => 1
        ];
        
        $result = $gibson->createAdmin($adminData);
        echo "   Admin creation result: " . json_encode($result) . "\n";
    }
    
    // Test admin authentication
    $adminCredentials = [
        ['email' => 'admin@painter-near-me.co.uk', 'password' => 'AdminPass123!'],
        ['email' => 'admin@test.com', 'password' => 'password123'],
        ['email' => 'admin@localhost', 'password' => 'admin123']
    ];
    
    foreach ($adminCredentials as $cred) {
        $result = $gibson->authenticateAdmin($cred['email'], $cred['password']);
        $status = $result && $result['success'] ? '✓' : '❌';
        echo "   $status Admin login test: {$cred['email']} / {$cred['password']}\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Admin management error: " . $e->getMessage() . "\n";
}

// 4. Test customer authentication
echo "\n4. CUSTOMER AUTHENTICATION:\n";
try {
    // Find a customer user for testing
    $users = $gibson->getAllUsers();
    $customerUser = null;
    
    if (is_array($users)) {
        foreach ($users as $user) {
            if (isset($user['role_id']) && $user['role_id'] == 4) { // Customer role
                $customerUser = $user;
                break;
            }
        }
    }
    
    if ($customerUser) {
        echo "   Found customer: " . $customerUser['email'] . "\n";
        
        // Test with a known password
        $testPasswords = ['password123', 'CustomerPass123!', 'test123456'];
        foreach ($testPasswords as $password) {
            $result = $gibson->authenticateUser($customerUser['email'], $password);
            $status = $result && $result['success'] ? '✓' : '❌';
            echo "   $status Customer login test: {$customerUser['email']} / $password\n";
            
            if ($result && $result['success']) {
                // Test session establishment
                echo "     - User ID: " . ($result['user']['id'] ?? 'N/A') . "\n";
                echo "     - Email: " . ($result['user']['email'] ?? 'N/A') . "\n";
                echo "     - Role: " . ($result['user']['role_id'] ?? 'N/A') . "\n";
                break;
            }
        }
    } else {
        echo "   No customer users found\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Customer auth error: " . $e->getMessage() . "\n";
}

// 5. Test painter authentication
echo "\n5. PAINTER AUTHENTICATION:\n";
try {
    // Find painter users
    $users = $gibson->getAllUsers();
    $painterUser = null;
    
    if (is_array($users)) {
        foreach ($users as $user) {
            if (isset($user['role_id']) && $user['role_id'] == 3) { // Painter role
                $painterUser = $user;
                break;
            }
        }
    }
    
    if ($painterUser) {
        echo "   Found painter: " . $painterUser['email'] . "\n";
        
        // Test with known passwords
        $testPasswords = ['password123', 'PainterPass123!', 'test123456'];
        foreach ($testPasswords as $password) {
            $result = $gibson->authenticateUser($painterUser['email'], $password);
            $status = $result && $result['success'] ? '✓' : '❌';
            echo "   $status Painter login test: {$painterUser['email']} / $password\n";
            
            if ($result && $result['success']) {
                echo "     - User ID: " . ($result['user']['id'] ?? 'N/A') . "\n";
                echo "     - Email: " . ($result['user']['email'] ?? 'N/A') . "\n";
                echo "     - Role: " . ($result['user']['role_id'] ?? 'N/A') . "\n";
                break;
            }
        }
    } else {
        echo "   No painter users found\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Painter auth error: " . $e->getMessage() . "\n";
}

// 6. Session debugging
echo "\n6. SESSION DEBUGGING:\n";
try {
    session_start();
    echo "   Session ID: " . session_id() . "\n";
    echo "   Session data: " . json_encode($_SESSION) . "\n";
    
    // Test session establishment manually
    if (isset($_SESSION['user_id'])) {
        echo "   Current user ID from session: " . $_SESSION['user_id'] . "\n";
        
        // Try to get current user
        $currentUser = $auth->getCurrentUser();
        echo "   Current user data: " . json_encode($currentUser) . "\n";
    } else {
        echo "   No active user session\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Session error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?> 