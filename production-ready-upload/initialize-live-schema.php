<?php
/**
 * Initialize Live Gibson AI Schema via MCP
 * This script creates the database schema directly using Gibson AI MCP endpoints
 */

// Bootstrap the application
require_once __DIR__ . '/bootstrap.php';

echo "🗄️  Initializing Live Gibson AI Database Schema\n";
echo "===============================================\n\n";

try {
    // Load environment
    if (file_exists(__DIR__ . '/.gibson-env')) {
        $envContent = file_get_contents(__DIR__ . '/.gibson-env');
        $lines = explode("\n", $envContent);
        foreach ($lines as $line) {
            if (strpos($line, 'export ') === 0) {
                $line = substr($line, 7); // Remove 'export '
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value, '"');
                }
            }
        }
    }
    
    $projectId = $_ENV['GIBSON_PROJECT_ID'] ?? getenv('GIBSON_PROJECT_ID') ?? null;
    $databaseId = $_ENV['GIBSON_DATABASE_ID'] ?? getenv('GIBSON_DATABASE_ID') ?? 'painter_marketplace_production';
    $apiKey = $_ENV['GIBSON_API_KEY'] ?? getenv('GIBSON_API_KEY') ?? null;
    
    if (!$projectId || !$apiKey) {
        echo "❌ Missing required Gibson AI configuration:\n";
        echo "   - Project ID: " . ($projectId ? 'Set' : 'Missing') . "\n";
        echo "   - API Key: " . ($apiKey ? 'Set' : 'Missing') . "\n";
        exit(1);
    }
    
    echo "📋 Gibson AI Configuration:\n";
    echo "   - Project ID: {$projectId}\n";
    echo "   - Database ID: {$databaseId}\n";
    echo "   - API Key: " . substr($apiKey, 0, 20) . "...\n\n";
    
    // Create cURL function for MCP calls
    function makeMCPCall($endpoint, $data = null, $method = 'GET') {
        global $apiKey, $projectId;
        
        $url = "https://api.gibsonai.com{$endpoint}";
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
            'X-Gibson-Project: ' . $projectId
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return ['success' => false, 'error' => $error, 'http_code' => 0];
        }
        
        $decoded = json_decode($response, true);
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'data' => $decoded,
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }
    
    // Test connection first
    echo "🌐 Testing Gibson AI Connection...\n";
    $testResult = makeMCPCall('/v1/projects/' . $projectId . '/health', null, 'GET');
    
    if (!$testResult['success']) {
        echo "❌ Connection test failed (HTTP {$testResult['http_code']})\n";
        echo "   This may be expected if the project doesn't exist yet.\n";
        echo "   Response: " . ($testResult['raw_response'] ?? 'No response') . "\n\n";
    } else {
        echo "✅ Gibson AI connection successful\n\n";
    }
    
    // Initialize database/collection
    echo "📂 Creating Database Collection...\n";
    $dbResult = makeMCPCall('/v1/projects/' . $projectId . '/databases', [
        'id' => $databaseId,
        'name' => 'Painter Marketplace Production',
        'description' => 'Production database for Painter Near Me marketplace platform'
    ], 'POST');
    
    if ($dbResult['success']) {
        echo "✅ Database collection created successfully\n";
    } else {
        echo "ℹ️  Database creation response (HTTP {$dbResult['http_code']}): " . $dbResult['raw_response'] . "\n";
        echo "   This may be expected if database already exists.\n";
    }
    echo "\n";
    
    // Create schema tables
    echo "🏗️  Creating Schema Tables...\n";
    echo "----------------------------\n";
    
    $schemas = [
        'users' => [
            'id' => ['type' => 'string', 'primary' => true],
            'email' => ['type' => 'string', 'unique' => true, 'required' => true],
            'password_hash' => ['type' => 'string', 'required' => true],
            'first_name' => ['type' => 'string'],
            'last_name' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'user_type' => ['type' => 'string', 'default' => 'customer'],
            'status' => ['type' => 'string', 'default' => 'active'],
            'created_at' => ['type' => 'datetime', 'auto' => true],
            'updated_at' => ['type' => 'datetime', 'auto' => true]
        ],
        'admin_users' => [
            'id' => ['type' => 'string', 'primary' => true],
            'email' => ['type' => 'string', 'unique' => true, 'required' => true],
            'password_hash' => ['type' => 'string', 'required' => true],
            'username' => ['type' => 'string', 'unique' => true],
            'full_name' => ['type' => 'string'],
            'role' => ['type' => 'string', 'default' => 'admin'],
            'permissions' => ['type' => 'json'],
            'last_login' => ['type' => 'datetime'],
            'status' => ['type' => 'string', 'default' => 'active'],
            'created_at' => ['type' => 'datetime', 'auto' => true],
            'updated_at' => ['type' => 'datetime', 'auto' => true]
        ],
        'painters' => [
            'id' => ['type' => 'string', 'primary' => true],
            'email' => ['type' => 'string', 'unique' => true, 'required' => true],
            'password_hash' => ['type' => 'string', 'required' => true],
            'company_name' => ['type' => 'string', 'required' => true],
            'contact_name' => ['type' => 'string', 'required' => true],
            'phone' => ['type' => 'string', 'required' => true],
            'location' => ['type' => 'string'],
            'postcode' => ['type' => 'string'],
            'services' => ['type' => 'json'],
            'experience_years' => ['type' => 'integer', 'default' => 0],
            'insurance' => ['type' => 'string'],
            'portfolio_images' => ['type' => 'json'],
            'rating' => ['type' => 'float', 'default' => 0.0],
            'reviews_count' => ['type' => 'integer', 'default' => 0],
            'status' => ['type' => 'string', 'default' => 'pending'],
            'created_at' => ['type' => 'datetime', 'auto' => true],
            'updated_at' => ['type' => 'datetime', 'auto' => true]
        ],
        'leads' => [
            'id' => ['type' => 'string', 'primary' => true],
            'uuid' => ['type' => 'string', 'unique' => true],
            'customer_id' => ['type' => 'string'],
            'customer_name' => ['type' => 'string', 'required' => true],
            'customer_email' => ['type' => 'string', 'required' => true],
            'customer_phone' => ['type' => 'string'],
            'job_title' => ['type' => 'string', 'required' => true],
            'job_description' => ['type' => 'text'],
            'job_type' => ['type' => 'string'],
            'property_type' => ['type' => 'string'],
            'location' => ['type' => 'string'],
            'postcode' => ['type' => 'string'],
            'status' => ['type' => 'string', 'default' => 'open'],
            'assigned_painter_id' => ['type' => 'string'],
            'budget_min' => ['type' => 'float'],
            'budget_max' => ['type' => 'float'],
            'deadline' => ['type' => 'date'],
            'images' => ['type' => 'json'],
            'created_at' => ['type' => 'datetime', 'auto' => true],
            'updated_at' => ['type' => 'datetime', 'auto' => true]
        ],
        'bids' => [
            'id' => ['type' => 'string', 'primary' => true],
            'lead_id' => ['type' => 'string', 'required' => true],
            'painter_id' => ['type' => 'string', 'required' => true],
            'bid_amount' => ['type' => 'float', 'required' => true],
            'estimated_duration' => ['type' => 'string'],
            'materials_cost' => ['type' => 'float'],
            'labour_cost' => ['type' => 'float'],
            'description' => ['type' => 'text'],
            'proposal_details' => ['type' => 'text'],
            'start_date' => ['type' => 'date'],
            'completion_date' => ['type' => 'date'],
            'warranty_period' => ['type' => 'string'],
            'terms_conditions' => ['type' => 'text'],
            'status' => ['type' => 'string', 'default' => 'pending'],
            'submitted_at' => ['type' => 'datetime', 'auto' => true],
            'updated_at' => ['type' => 'datetime', 'auto' => true]
        ]
    ];
    
    $successCount = 0;
    $failureCount = 0;
    
    foreach ($schemas as $tableName => $schema) {
        echo "📋 Creating table: {$tableName}...\n";
        
        $tableResult = makeMCPCall('/v1/projects/' . $projectId . '/databases/' . $databaseId . '/tables', [
            'name' => $tableName,
            'schema' => $schema,
            'options' => [
                'auto_timestamps' => true,
                'soft_deletes' => false
            ]
        ], 'POST');
        
        if ($tableResult['success']) {
            echo "✅ Table '{$tableName}' created successfully\n";
            $successCount++;
        } else {
            echo "❌ Table '{$tableName}' creation failed (HTTP {$tableResult['http_code']})\n";
            echo "   Response: " . $tableResult['raw_response'] . "\n";
            $failureCount++;
        }
    }
    
    echo "\n📊 Schema Creation Summary:\n";
    echo "   - Successful: {$successCount} tables\n";
    echo "   - Failed: {$failureCount} tables\n\n";
    
    // Create default admin user
    echo "👑 Creating Default Admin User...\n";
    $adminResult = makeMCPCall('/v1/projects/' . $projectId . '/databases/' . $databaseId . '/tables/admin_users/records', [
        'email' => 'admin@painter-near-me.co.uk',
        'password_hash' => password_hash('Admin123!', PASSWORD_DEFAULT),
        'username' => 'admin',
        'full_name' => 'System Administrator',
        'role' => 'super_admin',
        'permissions' => ['all'],
        'status' => 'active'
    ], 'POST');
    
    if ($adminResult['success']) {
        echo "✅ Default admin user created\n";
    } else {
        echo "❌ Admin user creation failed (HTTP {$adminResult['http_code']})\n";
        echo "   Response: " . $adminResult['raw_response'] . "\n";
    }
    
    echo "\n✅ Live Gibson AI database schema initialization completed!\n\n";
    
    echo "🔐 Default Admin Credentials:\n";
    echo "   Email: admin@painter-near-me.co.uk\n";
    echo "   Password: Admin123!\n";
    echo "   ⚠️  Change this immediately after first login!\n\n";
    
    echo "📝 Next Steps:\n";
    echo "   1. Test authentication: php test-authentication-system.php\n";
    echo "   2. Verify data access via admin dashboard\n";
    echo "   3. Update admin password through admin panel\n";
    echo "   4. Deploy to production server\n";
    
} catch (Exception $e) {
    echo "❌ Fatal error during schema initialization:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}

echo "\n🎉 Schema initialization complete!\n";
?> 