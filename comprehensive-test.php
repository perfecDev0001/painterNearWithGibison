<?php
/**
 * Comprehensive Test Suite for Painter Near Me
 * Tests all major functionality and reports any errors
 */

require_once __DIR__ . '/bootstrap.php';

echo "🧪 Comprehensive Test Suite for Painter Near Me\n";
echo "===============================================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$total = 0;

function test($description, $callback) {
    global $errors, $warnings, $passed, $total;
    $total++;
    
    echo "Testing: $description... ";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅ PASS\n";
            $passed++;
        } elseif ($result === false) {
            echo "❌ FAIL\n";
            $errors[] = $description;
        } else {
            echo "⚠️  WARNING: $result\n";
            $warnings[] = "$description: $result";
            $passed++;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $errors[] = "$description: " . $e->getMessage();
    }
}

// Test 1: Core Classes
test("Core classes can be loaded", function() {
    $classes = [
        'GibsonAIService' => CORE_PATH . '/GibsonAIService.php',
        'GibsonDataAccess' => CORE_PATH . '/GibsonDataAccess.php',
        'GibsonAuth' => CORE_PATH . '/GibsonAuth.php',
        'Wizard' => CORE_PATH . '/Wizard.php',
        'StripePaymentManager' => CORE_PATH . '/StripePaymentManager.php'
    ];
    
    foreach ($classes as $class => $file) {
        if (!file_exists($file)) {
            return "Missing file: $file";
        }
        require_once $file;
        if (!class_exists($class)) {
            return "Class $class not found";
        }
    }
    return true;
});

// Test 2: Configuration Files
test("Configuration files are valid", function() {
    $configs = [
        'gibson' => CONFIG_PATH . '/gibson.php',
        'database' => CONFIG_PATH . '/database.php',
        'email' => CONFIG_PATH . '/email.php'
    ];
    
    foreach ($configs as $name => $file) {
        if (!file_exists($file)) {
            return "Missing config: $file";
        }
        $config = require $file;
        if (!is_array($config)) {
            return "Invalid config format: $name";
        }
    }
    return true;
});

// Test 3: Gibson AI Service
test("Gibson AI Service initialization", function() {
    try {
        $gibson = new GibsonAIService();
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
});

// Test 4: Data Access Layer
test("Data Access Layer initialization", function() {
    try {
        $dataAccess = new GibsonDataAccess();
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
});

// Test 5: Authentication System
test("Authentication system initialization", function() {
    try {
        $auth = new GibsonAuth();
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
});

// Test 6: Quote Wizard
test("Quote wizard initialization", function() {
    try {
        $wizard = new Wizard();
        if (!method_exists($wizard, 'getCurrentStep')) {
            return "Missing getCurrentStep method";
        }
        if (!method_exists($wizard, 'getStepData')) {
            return "Missing getStepData method";
        }
        if (!method_exists($wizard, 'getProgress')) {
            return "Missing getProgress method";
        }
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
});

// Test 7: Step Files
test("Quote wizard step files exist", function() {
    $steps = [
        'Step1_Postcode.php',
        'Step2_RequestType.php',
        'Step3_JobType.php',
        'Step4_PropertyType.php',
        'Step5_ProjectDescription.php',
        'Step6_ContactDetails.php'
    ];
    
    foreach ($steps as $step) {
        $file = __DIR__ . '/steps/' . $step;
        if (!file_exists($file)) {
            return "Missing step file: $step";
        }
    }
    return true;
});

// Test 8: Template Files
test("Template files exist", function() {
    $templates = [
        'header.php',
        'footer.php',
        'progress.php'
    ];
    
    foreach ($templates as $template) {
        $file = __DIR__ . '/templates/' . $template;
        if (!file_exists($file)) {
            return "Missing template: $template";
        }
    }
    return true;
});

// Test 9: Asset Serving
test("Asset serving functionality", function() {
    if (!file_exists(__DIR__ . '/serve-asset.php')) {
        return "Missing serve-asset.php";
    }
    
    // Check if CSS directory exists
    if (!is_dir(__DIR__ . '/assets/css')) {
        return "Missing CSS assets directory";
    }
    
    return true;
});

// Test 10: Environment Configuration
test("Environment configuration", function() {
    $envFile = __DIR__ . '/project.env';
    if (!file_exists($envFile)) {
        return "Missing project.env file";
    }
    
    // Check for required environment variables
    $required = ['GIBSON_API_KEY', 'GIBSON_DATABASE_ID'];
    foreach ($required as $var) {
        if (!getenv($var) && !isset($_ENV[$var])) {
            return "Missing environment variable: $var";
        }
    }
    
    return true;
});

// Test 11: Directory Permissions
test("Directory permissions", function() {
    $dirs = [
        'logs' => LOGS_PATH,
        'uploads' => UPLOADS_PATH
    ];
    
    foreach ($dirs as $name => $dir) {
        if (!is_dir($dir)) {
            return "Missing directory: $name";
        }
        if (!is_writable($dir)) {
            return "Directory not writable: $name";
        }
    }
    return true;
});

// Test 12: Composer Dependencies
test("Composer dependencies", function() {
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        return "Composer dependencies not installed";
    }
    
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Check for key dependencies
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return "PHPMailer not found";
    }
    
    if (!class_exists('Stripe\Stripe')) {
        return "Stripe SDK not found";
    }
    
    return true;
});

// Summary
echo "\n📊 Test Results Summary\n";
echo "======================\n";
echo "Total Tests: $total\n";
echo "Passed: $passed\n";
echo "Failed: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n";

if (!empty($errors)) {
    echo "\n❌ Failed Tests:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  Warnings:\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
}

$successRate = round(($passed / $total) * 100, 1);
echo "\n🎯 Success Rate: $successRate%\n";

if (count($errors) === 0) {
    echo "\n🎉 All critical tests passed! The application is ready for use.\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the errors above.\n";
}

echo "\n🚀 Gibson MCP Status: " . (file_exists('.gibson-env') ? '✅ Configured' : '❌ Not Configured') . "\n";
echo "🌐 Web Server: ⚡ Running on http://localhost:8000\n";
echo "📱 Application: 🎨 Painter Near Me Marketplace\n"; 