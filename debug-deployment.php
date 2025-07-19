<?php
/**
 * Deployment Debug and Troubleshooting Script
 * This script helps diagnose common deployment issues
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔧 PainterNearMe Deployment Debug Tool\n";
echo "=====================================\n";

class DeploymentDebugger {
    private $issues = [];
    private $warnings = [];
    private $successes = [];
    
    public function runAllTests() {
        echo "🧪 Running comprehensive deployment tests...\n\n";
        
        $this->testEnvironmentConfiguration();
        $this->testFilePermissions();
        $this->testDatabaseConnections();
        $this->testGibsonAI();
        $this->testEmailConfiguration();
        $this->testStripeConfiguration();
        $this->testSecuritySettings();
        $this->testWebServerConfiguration();
        
        $this->displayResults();
    }
    
    private function testEnvironmentConfiguration() {
        echo "📋 Testing Environment Configuration...\n";
        
        // Check if project.env exists
        if (file_exists('project.env')) {
            $this->addSuccess("project.env file exists");
            
            // Load and check environment variables
            $envVars = $this->loadEnvFile('project.env');
            
            $requiredVars = [
                'GIBSON_API_KEY',
                'GIBSON_DATABASE_ID',
                'STRIPE_PUBLISHABLE_KEY',
                'STRIPE_SECRET_KEY',
                'SMTP_HOST',
                'SMTP_USERNAME',
                'APP_URL'
            ];
            
            foreach ($requiredVars as $var) {
                if (isset($envVars[$var]) && !empty($envVars[$var])) {
                    $this->addSuccess("$var is configured");
                } else {
                    $this->addIssue("$var is missing or empty in project.env");
                }
            }
            
            // Check production settings
            if (isset($envVars['APP_ENV']) && $envVars['APP_ENV'] === 'production') {
                $this->addSuccess("APP_ENV set to production");
            } else {
                $this->addWarning("APP_ENV should be set to 'production'");
            }
            
            if (isset($envVars['APP_DEBUG']) && $envVars['APP_DEBUG'] === 'false') {
                $this->addSuccess("APP_DEBUG disabled for production");
            } else {
                $this->addWarning("APP_DEBUG should be 'false' for production");
            }
            
        } else {
            $this->addIssue("project.env file not found");
        }
        
        echo "\n";
    }
    
    private function testFilePermissions() {
        echo "🔐 Testing File Permissions...\n";
        
        $fileChecks = [
            'project.env' => 0600,
            'bootstrap.php' => 0644,
            'index.php' => 0644,
            '.htaccess' => 0644
        ];
        
        foreach ($fileChecks as $file => $expectedPerms) {
            if (file_exists($file)) {
                $actualPerms = fileperms($file) & 0777;
                if ($actualPerms === $expectedPerms) {
                    $this->addSuccess("$file has correct permissions (" . decoct($expectedPerms) . ")");
                } else {
                    $this->addWarning("$file permissions are " . decoct($actualPerms) . ", should be " . decoct($expectedPerms));
                }
            } else {
                $this->addIssue("$file not found");
            }
        }
        
        $dirChecks = [
            'uploads' => 0777,
            'logs' => 0777,
            'assets' => 0755,
            'config' => 0755,
            'core' => 0755
        ];
        
        foreach ($dirChecks as $dir => $expectedPerms) {
            if (is_dir($dir)) {
                $actualPerms = fileperms($dir) & 0777;
                if ($actualPerms === $expectedPerms) {
                    $this->addSuccess("$dir/ has correct permissions (" . decoct($expectedPerms) . ")");
                } else {
                    $this->addWarning("$dir/ permissions are " . decoct($actualPerms) . ", should be " . decoct($expectedPerms));
                }
            } else {
                $this->addIssue("$dir/ directory not found");
            }
        }
        
        echo "\n";
    }
    
    private function testDatabaseConnections() {
        echo "🗄️  Testing Database Connections...\n";
        
        // Test MySQL connection (fallback)
        $envVars = $this->loadEnvFile('project.env');
        
        if (isset($envVars['DB_HOST'])) {
            try {
                $mysqli = new mysqli(
                    $envVars['DB_HOST'],
                    $envVars['DB_USERNAME'] ?? '',
                    $envVars['DB_PASSWORD'] ?? '',
                    $envVars['DB_DATABASE'] ?? '',
                    $envVars['DB_PORT'] ?? 3306
                );
                
                if ($mysqli->connect_error) {
                    $this->addWarning("MySQL connection failed: " . $mysqli->connect_error);
                } else {
                    $this->addSuccess("MySQL fallback database connected");
                    $mysqli->close();
                }
            } catch (Exception $e) {
                $this->addWarning("MySQL connection error: " . $e->getMessage());
            }
        }
        
        echo "\n";
    }
    
    private function testGibsonAI() {
        echo "🤖 Testing Gibson AI Connection...\n";
        
        try {
            if (file_exists('core/GibsonAIService.php')) {
                require_once 'bootstrap.php';
                require_once 'core/GibsonAIService.php';
                
                $gibson = new GibsonAIService();
                
                // Test basic API call
                $result = $gibson->makeApiCall('/v1/-/role');
                
                if ($result['success']) {
                    $this->addSuccess("Gibson AI connection successful");
                    $this->addSuccess("Found " . count($result['data']) . " roles in database");
                } else {
                    $this->addIssue("Gibson AI connection failed: " . $result['error']);
                }
                
            } else {
                $this->addIssue("GibsonAIService.php not found");
            }
        } catch (Exception $e) {
            $this->addIssue("Gibson AI test failed: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function testEmailConfiguration() {
        echo "📧 Testing Email Configuration...\n";
        
        $envVars = $this->loadEnvFile('project.env');
        
        if (isset($envVars['SMTP_HOST']) && isset($envVars['SMTP_USERNAME'])) {
            // Test SMTP connection (basic)
            $smtp_host = $envVars['SMTP_HOST'];
            $smtp_port = $envVars['SMTP_PORT'] ?? 587;
            
            $connection = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
            if ($connection) {
                $this->addSuccess("SMTP server is reachable ($smtp_host:$smtp_port)");
                fclose($connection);
            } else {
                $this->addWarning("Cannot reach SMTP server: $errstr ($errno)");
            }
        } else {
            $this->addIssue("SMTP configuration incomplete");
        }
        
        echo "\n";
    }
    
    private function testStripeConfiguration() {
        echo "💳 Testing Stripe Configuration...\n";
        
        $envVars = $this->loadEnvFile('project.env');
        
        if (isset($envVars['STRIPE_PUBLISHABLE_KEY'])) {
            if (strpos($envVars['STRIPE_PUBLISHABLE_KEY'], 'pk_live_') === 0) {
                $this->addSuccess("Stripe publishable key is live key");
            } elseif (strpos($envVars['STRIPE_PUBLISHABLE_KEY'], 'pk_test_') === 0) {
                $this->addWarning("Stripe publishable key is test key (should be live for production)");
            } else {
                $this->addIssue("Invalid Stripe publishable key format");
            }
        } else {
            $this->addIssue("Stripe publishable key not configured");
        }
        
        if (isset($envVars['STRIPE_SECRET_KEY'])) {
            if (strpos($envVars['STRIPE_SECRET_KEY'], 'sk_live_') === 0) {
                $this->addSuccess("Stripe secret key is live key");
            } elseif (strpos($envVars['STRIPE_SECRET_KEY'], 'sk_test_') === 0) {
                $this->addWarning("Stripe secret key is test key (should be live for production)");
            } else {
                $this->addIssue("Invalid Stripe secret key format");
            }
        } else {
            $this->addIssue("Stripe secret key not configured");
        }
        
        echo "\n";
    }
    
    private function testSecuritySettings() {
        echo "🛡️  Testing Security Settings...\n";
        
        // Test if sensitive files are protected
        $protectedFiles = ['project.env', 'config/database.php'];
        
        foreach ($protectedFiles as $file) {
            if (file_exists($file)) {
                // Simulate HTTP request to check if file is accessible
                $url = $this->getCurrentDomain() . '/' . $file;
                $headers = @get_headers($url);
                
                if ($headers && strpos($headers[0], '403') !== false) {
                    $this->addSuccess("$file is properly protected (403 Forbidden)");
                } elseif ($headers && strpos($headers[0], '404') !== false) {
                    $this->addSuccess("$file returns 404 (acceptable)");
                } else {
                    $this->addIssue("$file may be accessible via web browser");
                }
            }
        }
        
        // Check HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $this->addSuccess("HTTPS is enabled");
        } else {
            $this->addWarning("HTTPS not detected (may be handled by proxy)");
        }
        
        echo "\n";
    }
    
    private function testWebServerConfiguration() {
        echo "🌐 Testing Web Server Configuration...\n";
        
        // Check if .htaccess is working
        if (file_exists('.htaccess')) {
            $this->addSuccess(".htaccess file exists");
            
            // Check if mod_rewrite is working
            if (function_exists('apache_get_modules')) {
                $modules = apache_get_modules();
                if (in_array('mod_rewrite', $modules)) {
                    $this->addSuccess("mod_rewrite is enabled");
                } else {
                    $this->addWarning("mod_rewrite may not be enabled");
                }
            }
        } else {
            $this->addIssue(".htaccess file not found");
        }
        
        // Check PHP version
        $phpVersion = phpversion();
        if (version_compare($phpVersion, '7.4', '>=')) {
            $this->addSuccess("PHP version is $phpVersion (compatible)");
        } else {
            $this->addIssue("PHP version is $phpVersion (requires 7.4+)");
        }
        
        // Check required PHP extensions
        $requiredExtensions = ['mysqli', 'curl', 'json', 'mbstring'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $this->addSuccess("PHP extension '$ext' is loaded");
            } else {
                $this->addIssue("PHP extension '$ext' is missing");
            }
        }
        
        echo "\n";
    }
    
    private function loadEnvFile($file) {
        $vars = [];
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $vars[$key] = $value;
                }
            }
        }
        return $vars;
    }
    
    private function getCurrentDomain() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host";
    }
    
    private function addSuccess($message) {
        $this->successes[] = $message;
        echo "✅ $message\n";
    }
    
    private function addWarning($message) {
        $this->warnings[] = $message;
        echo "⚠️  $message\n";
    }
    
    private function addIssue($message) {
        $this->issues[] = $message;
        echo "❌ $message\n";
    }
    
    private function displayResults() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 DEPLOYMENT TEST RESULTS\n";
        echo str_repeat("=", 50) . "\n";
        
        echo "✅ Successes: " . count($this->successes) . "\n";
        echo "⚠️  Warnings: " . count($this->warnings) . "\n";
        echo "❌ Issues: " . count($this->issues) . "\n\n";
        
        if (!empty($this->issues)) {
            echo "🚨 CRITICAL ISSUES TO FIX:\n";
            foreach ($this->issues as $issue) {
                echo "   • $issue\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS TO REVIEW:\n";
            foreach ($this->warnings as $warning) {
                echo "   • $warning\n";
            }
            echo "\n";
        }
        
        if (empty($this->issues)) {
            echo "🎉 No critical issues found! Your deployment looks good.\n";
        } else {
            echo "🔧 Please fix the critical issues above before going live.\n";
        }
        
        echo "\n📋 NEXT STEPS:\n";
        echo "1. Fix any critical issues listed above\n";
        echo "2. Review and address warnings\n";
        echo "3. Test website functionality manually\n";
        echo "4. Monitor error logs after deployment\n";
    }
}

// Run the debugger
$debugger = new DeploymentDebugger();
$debugger->runAllTests();
?>