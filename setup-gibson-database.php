<?php
/**
 * Gibson AI Database Setup Script
 * This script helps initialize the Gibson AI database with required tables and data
 */

require_once 'bootstrap.php';
require_once 'core/GibsonAIService.php';

echo "🗄️  Gibson AI Database Setup\n";
echo "============================\n";

class GibsonDatabaseSetup {
    private $gibson;
    
    public function __construct() {
        $this->gibson = new GibsonAIService();
    }
    
    /**
     * Initialize basic roles in the database
     */
    public function setupRoles() {
        echo "📋 Setting up user roles...\n";
        
        $roles = [
            ['name' => 'admin', 'description' => 'Administrator with full access'],
            ['name' => 'painter', 'description' => 'Painter with painter dashboard access'],
            ['name' => 'customer', 'description' => 'Customer with basic access']
        ];
        
        foreach ($roles as $role) {
            $roleData = [
                'uuid' => $this->generateUuid(),
                'name' => $role['name'],
                'description' => $role['description']
            ];
            
            $result = $this->gibson->makeApiCall('/v1/-/role', $roleData, 'POST');
            
            if ($result['success']) {
                echo "✅ Created role: {$role['name']}\n";
            } else {
                echo "⚠️  Role {$role['name']} may already exist or error: {$result['error']}\n";
            }
        }
    }
    
    /**
     * Setup job lead statuses
     */
    public function setupJobLeadStatuses() {
        echo "\n📊 Setting up job lead statuses...\n";
        
        $statuses = [
            ['status_name' => 'open', 'description' => 'Lead is open for bids'],
            ['status_name' => 'in_progress', 'description' => 'Lead is being worked on'],
            ['status_name' => 'completed', 'description' => 'Lead has been completed'],
            ['status_name' => 'cancelled', 'description' => 'Lead was cancelled']
        ];
        
        foreach ($statuses as $status) {
            $statusData = [
                'uuid' => $this->generateUuid(),
                'status_name' => $status['status_name'],
                'description' => $status['description']
            ];
            
            $result = $this->gibson->makeApiCall('/v1/-/job-lead-status', $statusData, 'POST');
            
            if ($result['success']) {
                echo "✅ Created status: {$status['status_name']}\n";
            } else {
                echo "⚠️  Status {$status['status_name']} may already exist or error: {$result['error']}\n";
            }
        }
    }
    
    /**
     * Setup service categories
     */
    public function setupServiceCategories() {
        echo "\n🎨 Setting up service categories...\n";
        
        $categories = [
            ['name' => 'interior_painting', 'description' => 'Interior painting services'],
            ['name' => 'exterior_painting', 'description' => 'Exterior painting services'],
            ['name' => 'commercial_painting', 'description' => 'Commercial painting services'],
            ['name' => 'decorative_painting', 'description' => 'Decorative and specialty painting']
        ];
        
        foreach ($categories as $category) {
            $categoryData = [
                'uuid' => $this->generateUuid(),
                'name' => $category['name'],
                'description' => $category['description']
            ];
            
            $result = $this->gibson->makeApiCall('/v1/-/service-category', $categoryData, 'POST');
            
            if ($result['success']) {
                echo "✅ Created category: {$category['name']}\n";
            } else {
                echo "⚠️  Category {$category['name']} may already exist or error: {$result['error']}\n";
            }
        }
    }
    
    /**
     * Setup notification types
     */
    public function setupNotificationTypes() {
        echo "\n🔔 Setting up notification types...\n";
        
        $types = [
            ['name' => 'new_lead', 'description' => 'New lead available'],
            ['name' => 'payment_received', 'description' => 'Payment received for lead'],
            ['name' => 'lead_claimed', 'description' => 'Lead has been claimed'],
            ['name' => 'system_alert', 'description' => 'System alert notification']
        ];
        
        foreach ($types as $type) {
            $typeData = [
                'uuid' => $this->generateUuid(),
                'name' => $type['name'],
                'description' => $type['description']
            ];
            
            $result = $this->gibson->makeApiCall('/v1/-/notification-type', $typeData, 'POST');
            
            if ($result['success']) {
                echo "✅ Created notification type: {$type['name']}\n";
            } else {
                echo "⚠️  Type {$type['name']} may already exist or error: {$result['error']}\n";
            }
        }
    }
    
    /**
     * Test database connectivity
     */
    public function testConnection() {
        echo "\n🔗 Testing Gibson AI connection...\n";
        
        try {
            // Test basic API call
            $result = $this->gibson->makeApiCall('/v1/-/role');
            
            if ($result['success']) {
                echo "✅ Gibson AI connection successful!\n";
                echo "📊 Found " . count($result['data']) . " roles in database\n";
                return true;
            } else {
                echo "❌ Connection failed: {$result['error']}\n";
                return false;
            }
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Create initial admin user
     */
    public function createAdminUser($name, $email, $password) {
        echo "\n👤 Creating admin user...\n";
        
        // First get admin role ID
        $rolesResult = $this->gibson->makeApiCall('/v1/-/role');
        if (!$rolesResult['success']) {
            echo "❌ Could not retrieve roles\n";
            return false;
        }
        
        $adminRoleId = null;
        foreach ($rolesResult['data'] as $role) {
            if ($role['name'] === 'admin') {
                $adminRoleId = $role['id'];
                break;
            }
        }
        
        if (!$adminRoleId) {
            echo "❌ Admin role not found. Run setupRoles() first.\n";
            return false;
        }
        
        $userData = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role_id' => $adminRoleId
        ];
        
        $result = $this->gibson->registerUser($userData);
        
        if ($result['success']) {
            echo "✅ Admin user created successfully!\n";
            echo "📧 Email: $email\n";
            echo "🔑 You can now login at your admin panel\n";
            return true;
        } else {
            echo "❌ Failed to create admin user: {$result['error']}\n";
            return false;
        }
    }
    
    /**
     * Generate UUID
     */
    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Run complete setup
     */
    public function runCompleteSetup() {
        echo "🚀 Running complete Gibson AI database setup...\n";
        echo "===============================================\n";
        
        if (!$this->testConnection()) {
            echo "❌ Cannot proceed without Gibson AI connection\n";
            return false;
        }
        
        $this->setupRoles();
        $this->setupJobLeadStatuses();
        $this->setupServiceCategories();
        $this->setupNotificationTypes();
        
        echo "\n✅ Database setup completed!\n";
        echo "📋 Next steps:\n";
        echo "   1. Create admin user with createAdminUser()\n";
        echo "   2. Test the website functionality\n";
        echo "   3. Configure payment system\n";
        
        return true;
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $setup = new GibsonDatabaseSetup();
    
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'test':
                $setup->testConnection();
                break;
            case 'setup':
                $setup->runCompleteSetup();
                break;
            case 'admin':
                if (isset($argv[2]) && isset($argv[3]) && isset($argv[4])) {
                    $setup->createAdminUser($argv[2], $argv[3], $argv[4]);
                } else {
                    echo "Usage: php setup-gibson-database.php admin \"Name\" \"email@domain.com\" \"password\"\n";
                }
                break;
            default:
                echo "Usage: php setup-gibson-database.php [test|setup|admin]\n";
                echo "  test  - Test Gibson AI connection\n";
                echo "  setup - Run complete database setup\n";
                echo "  admin - Create admin user (requires name, email, password)\n";
        }
    } else {
        echo "Gibson AI Database Setup Options:\n";
        echo "================================\n";
        echo "1. Test connection: php setup-gibson-database.php test\n";
        echo "2. Complete setup: php setup-gibson-database.php setup\n";
        echo "3. Create admin: php setup-gibson-database.php admin \"Name\" \"email\" \"password\"\n";
    }
}
?>