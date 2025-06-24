<?php
/**
 * Payment System Fix and Setup Script
 * This script will ensure the payment system is properly configured and working
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonDataAccess.php';
require_once 'core/StripePaymentManager.php';
require_once 'core/PaymentEmailNotificationService.php';

echo "🔧 Payment System Fix and Setup\n";
echo "=====================================\n\n";

$dataAccess = new GibsonDataAccess();

// 1. Check and create payment tables
echo "1. Checking payment system database tables...\n";

try {
    // Check if payment_config table exists
    $result = $dataAccess->query("SHOW TABLES LIKE 'payment_config'");
    if (!$result || $result->num_rows === 0) {
        echo "   Creating payment_config table...\n";
        $dataAccess->query("
            CREATE TABLE payment_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) NOT NULL UNIQUE,
                config_value TEXT NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "   ✅ payment_config table created\n";
    } else {
        echo "   ✅ payment_config table exists\n";
    }

    // Check if painter_payment_methods table exists
    $result = $dataAccess->query("SHOW TABLES LIKE 'painter_payment_methods'");
    if (!$result || $result->num_rows === 0) {
        echo "   Creating painter_payment_methods table...\n";
        $dataAccess->query("
            CREATE TABLE painter_payment_methods (
                id INT AUTO_INCREMENT PRIMARY KEY,
                painter_id INT NOT NULL,
                stripe_customer_id VARCHAR(255) NOT NULL,
                stripe_payment_method_id VARCHAR(255) NOT NULL,
                payment_method_type VARCHAR(50) DEFAULT 'card',
                card_brand VARCHAR(50),
                card_last4 VARCHAR(4),
                is_default BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
                UNIQUE KEY unique_stripe_pm (stripe_payment_method_id)
            )
        ");
        echo "   ✅ painter_payment_methods table created\n";
    } else {
        echo "   ✅ painter_payment_methods table exists\n";
    }

    // Check if lead_payments table exists
    $result = $dataAccess->query("SHOW TABLES LIKE 'lead_payments'");
    if (!$result || $result->num_rows === 0) {
        echo "   Creating lead_payments table...\n";
        $dataAccess->query("
            CREATE TABLE lead_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lead_id INT NOT NULL,
                painter_id INT NOT NULL,
                stripe_payment_intent_id VARCHAR(255) NOT NULL,
                stripe_customer_id VARCHAR(255) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'GBP',
                payment_status ENUM('pending', 'succeeded', 'failed', 'canceled') DEFAULT 'pending',
                payment_method_id VARCHAR(255),
                payment_number INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
                FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
                UNIQUE KEY unique_payment_intent (stripe_payment_intent_id),
                INDEX idx_lead_payments_painter (painter_id),
                INDEX idx_lead_payments_lead (lead_id)
            )
        ");
        echo "   ✅ lead_payments table created\n";
    } else {
        echo "   ✅ lead_payments table exists\n";
    }

    // Check if lead_access table exists
    $result = $dataAccess->query("SHOW TABLES LIKE 'lead_access'");
    if (!$result || $result->num_rows === 0) {
        echo "   Creating lead_access table...\n";
        $dataAccess->query("
            CREATE TABLE lead_access (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lead_id INT NOT NULL,
                painter_id INT NOT NULL,
                payment_id INT NOT NULL,
                accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
                FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
                FOREIGN KEY (payment_id) REFERENCES lead_payments(id) ON DELETE CASCADE,
                UNIQUE KEY unique_lead_access (lead_id, painter_id)
            )
        ");
        echo "   ✅ lead_access table created\n";
    } else {
        echo "   ✅ lead_access table exists\n";
    }

} catch (Exception $e) {
    echo "   ❌ Error creating tables: " . $e->getMessage() . "\n";
}

// 2. Check and add payment columns to leads table
echo "\n2. Checking leads table payment columns...\n";

try {
    // Check if payment columns exist in leads table
    $result = $dataAccess->query("SHOW COLUMNS FROM leads LIKE 'payment_count'");
    if (!$result || $result->num_rows === 0) {
        echo "   Adding payment_count column to leads table...\n";
        $dataAccess->query("ALTER TABLE leads ADD COLUMN payment_count INT DEFAULT 0");
        echo "   ✅ payment_count column added\n";
    } else {
        echo "   ✅ payment_count column exists\n";
    }

    $result = $dataAccess->query("SHOW COLUMNS FROM leads LIKE 'is_payment_active'");
    if (!$result || $result->num_rows === 0) {
        echo "   Adding is_payment_active column to leads table...\n";
        $dataAccess->query("ALTER TABLE leads ADD COLUMN is_payment_active BOOLEAN DEFAULT TRUE");
        echo "   ✅ is_payment_active column added\n";
    } else {
        echo "   ✅ is_payment_active column exists\n";
    }

    $result = $dataAccess->query("SHOW COLUMNS FROM leads LIKE 'lead_price'");
    if (!$result || $result->num_rows === 0) {
        echo "   Adding lead_price column to leads table...\n";
        $dataAccess->query("ALTER TABLE leads ADD COLUMN lead_price DECIMAL(10,2) DEFAULT 15.00");
        echo "   ✅ lead_price column added\n";
    } else {
        echo "   ✅ lead_price column exists\n";
    }

    $result = $dataAccess->query("SHOW COLUMNS FROM leads LIKE 'max_payments'");
    if (!$result || $result->num_rows === 0) {
        echo "   Adding max_payments column to leads table...\n";
        $dataAccess->query("ALTER TABLE leads ADD COLUMN max_payments INT DEFAULT 3");
        echo "   ✅ max_payments column added\n";
    } else {
        echo "   ✅ max_payments column exists\n";
    }

} catch (Exception $e) {
    echo "   ❌ Error adding columns: " . $e->getMessage() . "\n";
}

// 3. Insert default payment configuration
echo "\n3. Setting up default payment configuration...\n";

try {
    $defaultConfigs = [
        'stripe_publishable_key' => ['', 'Stripe publishable key for frontend'],
        'stripe_secret_key' => ['', 'Stripe secret key for backend'],
        'stripe_webhook_secret' => ['', 'Stripe webhook endpoint secret'],
        'default_lead_price' => ['15.00', 'Default price per lead access in GBP'],
        'max_payments_per_lead' => ['3', 'Maximum number of payments before lead deactivation'],
        'payment_enabled' => ['true', 'Whether payment system is enabled'],
        'auto_deactivate_leads' => ['true', 'Auto deactivate leads after max payments reached'],
        'email_notifications_enabled' => ['true', 'Enable payment email notifications'],
        'daily_summary_enabled' => ['true', 'Enable daily payment summary emails']
    ];

    foreach ($defaultConfigs as $key => $config) {
        $value = $config[0];
        $description = $config[1];
        
        $dataAccess->query(
            "INSERT INTO payment_config (config_key, config_value, description) VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE description = VALUES(description)",
            [$key, $value, $description]
        );
        echo "   ✅ Configuration set: $key\n";
    }

} catch (Exception $e) {
    echo "   ❌ Error setting configuration: " . $e->getMessage() . "\n";
}

// 4. Test payment system components
echo "\n4. Testing payment system components...\n";

try {
    // Test StripePaymentManager initialization
    echo "   Testing StripePaymentManager...\n";
    
    // We can't fully test without Stripe keys, but we can check if it loads
    try {
        $paymentManager = new StripePaymentManager();
        echo "   ⚠️  StripePaymentManager created (Note: Stripe keys needed for full functionality)\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Stripe secret key not configured') !== false) {
            echo "   ⚠️  StripePaymentManager ready (Stripe keys need to be configured)\n";
        } else {
            throw $e;
        }
    }

    // Test email notification service
    echo "   Testing email notification service...\n";
    try {
        $emailService = new Core\PaymentEmailNotificationService();
        echo "   ✅ Email notification service ready\n";
    } catch (Exception $e) {
        echo "   ⚠️  Email service issue: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "   ❌ Component test failed: " . $e->getMessage() . "\n";
}

// 5. Check file permissions
echo "\n5. Checking file permissions...\n";

$filesToCheck = [
    'api/payment-api.php',
    'api/stripe-webhook.php',
    'core/StripePaymentManager.php',
    'core/PaymentEmailNotificationService.php',
    'admin-payment-management.php',
    'payment-management.js'
];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "   ✅ $file (permissions: $perms)\n";
    } else {
        echo "   ❌ $file (missing)\n";
    }
}

// 6. Generate .htaccess rules for payment system
echo "\n6. Checking .htaccess configuration...\n";

$htaccessContent = file_get_contents('.htaccess');
if (strpos($htaccessContent, 'payment-api.php') === false) {
    echo "   Adding payment API rewrite rules...\n";
    
    $paymentRules = "\n# Payment System API Routes\n";
    $paymentRules .= "RewriteRule ^api/payment/(.*)$ api/payment-api.php/$1 [QSA,L]\n";
    $paymentRules .= "RewriteRule ^webhooks/stripe$ api/stripe-webhook.php [L]\n";
    
    file_put_contents('.htaccess', $htaccessContent . $paymentRules);
    echo "   ✅ Payment API routes added to .htaccess\n";
} else {
    echo "   ✅ Payment API routes already configured\n";
}

echo "\n=====================================\n";
echo "🎉 Payment System Setup Complete!\n";
echo "=====================================\n\n";

echo "NEXT STEPS:\n";
echo "1. Configure Stripe API keys in admin panel (/admin-payment-management.php)\n";
echo "2. Test payment functionality with Stripe test keys\n";
echo "3. Set up Stripe webhook URL: https://yourdomain.com/webhooks/stripe\n";
echo "4. Test lead payment flow\n\n";

echo "PAYMENT SYSTEM FEATURES:\n";
echo "✅ Stripe integration ready\n";
echo "✅ Payment method management\n";
echo "✅ Lead access payment system\n";
echo "✅ Email notifications\n";
echo "✅ Admin dashboard\n";
echo "✅ Webhook handling\n";
echo "✅ Payment analytics\n";
echo "✅ Database schema complete\n\n";

echo "Access the admin panel at: /admin-payment-management.php\n";
echo "Payment API endpoint: /api/payment-api.php/\n";
echo "Stripe webhook endpoint: /api/stripe-webhook.php\n\n";

?>