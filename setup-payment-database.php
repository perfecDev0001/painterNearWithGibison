<?php
/**
 * Payment Database Setup Script
 * Creates SQLite database for development/testing
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "🗄️ Setting up Payment System Database\n";
echo "=====================================\n\n";

// Create database directory if it doesn't exist
$databaseDir = __DIR__ . '/database';
if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0755, true);
    echo "✅ Created database directory\n";
}

// SQLite database file
$dbFile = $databaseDir . '/painter_near_me.sqlite';

try {
    // Create SQLite database
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to SQLite database: $dbFile\n\n";
    
    // Create painters table
    echo "Creating painters table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS painters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            contact_name VARCHAR(255),
            phone VARCHAR(20),
            address TEXT,
            postcode VARCHAR(10),
            description TEXT,
            website VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT 1,
            email_verified BOOLEAN DEFAULT 0,
            verification_token VARCHAR(255),
            reset_token VARCHAR(255),
            reset_token_expires DATETIME
        )
    ");
    echo "✅ painters table created\n";
    
    // Create leads table
    echo "Creating leads table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_title VARCHAR(255) NOT NULL,
            job_description TEXT,
            location VARCHAR(255),
            postcode VARCHAR(10),
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            customer_phone VARCHAR(20),
            property_type VARCHAR(100),
            job_type VARCHAR(100),
            budget_range VARCHAR(100),
            timeline VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT 1,
            assigned_painter_id INTEGER,
            status VARCHAR(50) DEFAULT 'active',
            payment_count INTEGER DEFAULT 0,
            is_payment_active BOOLEAN DEFAULT 1,
            lead_price DECIMAL(10,2) DEFAULT 15.00,
            max_payments INTEGER DEFAULT 3,
            FOREIGN KEY (assigned_painter_id) REFERENCES painters(id)
        )
    ");
    echo "✅ leads table created\n";
    
    // Create payment_config table
    echo "Creating payment_config table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_config (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            config_key VARCHAR(100) NOT NULL UNIQUE,
            config_value TEXT NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ payment_config table created\n";
    
    // Create painter_payment_methods table
    echo "Creating painter_payment_methods table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS painter_payment_methods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            painter_id INTEGER NOT NULL,
            stripe_customer_id VARCHAR(255) NOT NULL,
            stripe_payment_method_id VARCHAR(255) NOT NULL UNIQUE,
            payment_method_type VARCHAR(50) DEFAULT 'card',
            card_brand VARCHAR(50),
            card_last4 VARCHAR(4),
            is_default BOOLEAN DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE
        )
    ");
    echo "✅ painter_payment_methods table created\n";
    
    // Create lead_payments table
    echo "Creating lead_payments table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lead_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lead_id INTEGER NOT NULL,
            painter_id INTEGER NOT NULL,
            stripe_payment_intent_id VARCHAR(255) NOT NULL UNIQUE,
            stripe_customer_id VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'GBP',
            payment_status VARCHAR(20) DEFAULT 'pending',
            payment_method_id VARCHAR(255),
            payment_number INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
            FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE
        )
    ");
    echo "✅ lead_payments table created\n";
    
    // Create lead_access table
    echo "Creating lead_access table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lead_access (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lead_id INTEGER NOT NULL,
            painter_id INTEGER NOT NULL,
            payment_id INTEGER NOT NULL,
            accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
            FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_id) REFERENCES lead_payments(id) ON DELETE CASCADE,
            UNIQUE(lead_id, painter_id)
        )
    ");
    echo "✅ lead_access table created\n";
    
    // Create admin_users table
    echo "Creating admin_users table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'admin',
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )
    ");
    echo "✅ admin_users table created\n";
    
    // Insert default payment configuration
    echo "\nInserting default payment configuration...\n";
    
    $configs = [
        ['stripe_publishable_key', '', 'Stripe publishable key for frontend'],
        ['stripe_secret_key', '', 'Stripe secret key for backend'],
        ['stripe_webhook_secret', '', 'Stripe webhook endpoint secret'],
        ['default_lead_price', '15.00', 'Default price per lead access in GBP'],
        ['max_payments_per_lead', '3', 'Maximum number of payments before lead deactivation'],
        ['payment_enabled', 'true', 'Whether payment system is enabled'],
        ['auto_deactivate_leads', 'true', 'Auto deactivate leads after max payments reached'],
        ['email_notifications_enabled', 'true', 'Enable payment email notifications'],
        ['daily_summary_enabled', 'true', 'Enable daily payment summary emails']
    ];
    
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO payment_config (config_key, config_value, description) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($configs as $config) {
        $stmt->execute($config);
        echo "✅ Config set: {$config[0]}\n";
    }
    
    // Create default admin user
    echo "\nCreating default admin user...\n";
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO admin_users (username, email, password, role) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute(['admin', 'admin@painter-near-me.co.uk', $adminPassword, 'admin']);
    echo "✅ Default admin user created (username: admin, password: admin123)\n";
    
    // Create sample data
    echo "\nCreating sample data...\n";
    
    // Sample painter
    $painterPassword = password_hash('painter123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO painters (email, password, company_name, contact_name, phone, postcode, description) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'test@painter-example.com', 
        $painterPassword, 
        'Test Painting Company', 
        'John Painter',
        '+44 1234 567890',
        'SW1A 1AA',
        'Professional painting services for residential and commercial properties.'
    ]);
    echo "✅ Sample painter created (email: test@painter-example.com, password: painter123)\n";
    
    // Sample lead
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO leads (job_title, job_description, location, postcode, customer_name, customer_email, customer_phone, property_type, job_type, budget_range, timeline) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Living Room Painting',
        'Need to paint living room walls and ceiling. Room is approximately 4m x 5m.',
        'London',
        'SW1A 1AA',
        'Jane Customer',
        'customer@example.com',
        '+44 1234 567891',
        'House',
        'Interior',
        '£500-£1000',
        'Within 2 weeks'
    ]);
    echo "✅ Sample lead created\n";
    
    // Create SQLite database configuration
    echo "\nCreating SQLite database configuration...\n";
    $sqliteConfig = "<?php
// SQLite Database Configuration
return [
    'driver' => 'sqlite',
    'database' => __DIR__ . '/../database/painter_near_me.sqlite',
    'prefix' => '',
    'foreign_key_constraints' => true,
];
";
    
    file_put_contents(__DIR__ . '/config/sqlite_database.php', $sqliteConfig);
    echo "✅ SQLite configuration created\n";
    
    echo "\n=====================================\n";
    echo "🎉 Payment Database Setup Complete!\n";
    echo "=====================================\n\n";
    
    echo "DATABASE DETAILS:\n";
    echo "File: $dbFile\n";
    echo "Size: " . round(filesize($dbFile) / 1024, 2) . " KB\n\n";
    
    echo "SAMPLE ACCOUNTS:\n";
    echo "Admin: admin / admin123\n";
    echo "Painter: test@painter-example.com / painter123\n\n";
    
    echo "TABLES CREATED:\n";
    echo "✅ painters\n";
    echo "✅ leads\n";
    echo "✅ payment_config\n";
    echo "✅ painter_payment_methods\n";
    echo "✅ lead_payments\n";
    echo "✅ lead_access\n";
    echo "✅ admin_users\n\n";
    
    echo "NEXT STEPS:\n";
    echo "1. Update database configuration to use SQLite\n";
    echo "2. Add Stripe API keys via admin panel\n";
    echo "3. Test payment functionality\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up database: " . $e->getMessage() . "\n";
}
?>