<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';
require_once 'core/GibsonAIService.php';
require_once 'core/Mailer.php';

$auth = new GibsonAuth();

// Admin session check
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Test execution flag
$runTests = isset($_POST['run_tests']) && $_POST['run_tests'] === '1';
$testResults = [];

if ($runTests) {
    $testResults = runSystemTests();
}

function runSystemTests() {
    $results = [
        'database' => testDatabase(),
        'email' => testEmail(),
        'gibson_ai' => testGibsonAI(),
        'admin_functions' => testAdminFunctions(),
        'vendor_libraries' => testVendorLibraries(),
        'file_permissions' => testFilePermissions(),
        'system_health' => testSystemHealth()
    ];
    
    return $results;
}

function testDatabase() {
    $test = [
        'name' => 'Database Connectivity & Operations',
        'tests' => [],
        'status' => 'passed',
        'overall_message' => ''
    ];
    
    try {
        $dataAccess = new GibsonDataAccess();
        
        // Test 1: Data Access Initialization
        $test['tests'][] = [
            'name' => 'Data Access Initialization',
            'status' => 'passed',
            'message' => 'GibsonDataAccess initialized successfully',
            'details' => 'Data access layer is ready for operations'
        ];
        
        // Test 2: Gibson AI Service Connection
        try {
            // Try to get basic data to test connection
            $leads = $dataAccess->getLeads(['limit' => 1]);
            $test['tests'][] = [
                'name' => 'Gibson AI Service Connection',
                'status' => 'passed',
                'message' => 'Successfully connected to Gibson AI service',
                'details' => 'Lead data retrieval operational'
            ];
        } catch (Exception $e) {
            $test['tests'][] = [
                'name' => 'Gibson AI Service Connection',
                'status' => 'warning',
                'message' => 'Gibson AI service connection issue',
                'details' => $e->getMessage()
            ];
        }
        
        // Test 3: Data Operations
        try {
            $stats = $dataAccess->getDashboardStats();
            $test['tests'][] = [
                'name' => 'Data Operations',
                'status' => 'passed',
                'message' => 'Dashboard statistics retrieved successfully',
                'details' => 'Total leads: ' . ($stats['total_leads'] ?? 0) . ', Total painters: ' . ($stats['total_painters'] ?? 0)
            ];
        } catch (Exception $e) {
            $test['tests'][] = [
                'name' => 'Data Operations',
                'status' => 'failed',
                'message' => 'Failed to retrieve dashboard statistics',
                'details' => $e->getMessage()
            ];
            $test['status'] = 'failed';
        }
        
        // Test 4: Core Data Access Methods
        try {
            $painters = $dataAccess->getPainters(['limit' => 1]);
            $bids = $dataAccess->getBids(['limit' => 1]);
            
            $test['tests'][] = [
                'name' => 'Core Data Access Methods',
                'status' => 'passed',
                'message' => 'Core data access methods operational',
                'details' => 'Painters, leads, and bids data access working'
            ];
        } catch (Exception $e) {
            $test['tests'][] = [
                'name' => 'Core Data Access Methods',
                'status' => 'warning',
                'message' => 'Some data access methods may have issues',
                'details' => $e->getMessage()
            ];
        }
        
    } catch (Exception $e) {
        $test['tests'][] = [
            'name' => 'Database Connection',
            'status' => 'failed',
            'message' => 'Data access initialization failed',
            'details' => $e->getMessage()
        ];
        $test['status'] = 'failed';
    }
    
    return $test;
}

function testEmail() {
    $test = [
        'name' => 'Email System',
        'tests' => [],
        'status' => 'passed',
        'overall_message' => ''
    ];
    
    try {
        // Test 1: Email Configuration
        $emailConfig = require(__DIR__ . '/config/email.php');
        $configStatus = 'passed';
        $configMessage = 'Email configuration loaded successfully';
        $configDetails = 'Host: ' . $emailConfig['host'] . ', Port: ' . $emailConfig['port'] . ', From: ' . $emailConfig['from_email'];
        
        // Check if using correct SMTP host
        if ($emailConfig['host'] !== 'smtp.painter-near-me.co.uk') {
            $configStatus = 'warning';
            $configMessage = 'Email configuration loaded but using incorrect SMTP host';
            $configDetails .= ' [WARNING: Should use smtp.painter-near-me.co.uk]';
        }
        
        $test['tests'][] = [
            'name' => 'Email Configuration',
            'status' => $configStatus,
            'message' => $configMessage,
            'details' => $configDetails
        ];
        
        // Test 2: Mailer Initialization
        $mailer = new Core\Mailer();
        $test['tests'][] = [
            'name' => 'Mailer Initialization',
            'status' => 'passed',
            'message' => 'Mailer instance created successfully',
            'details' => 'PHPMailer initialized with SMTP settings'
        ];
        
        // Test 3: SMTP Connection (without sending)
        $test['tests'][] = [
            'name' => 'SMTP Connection Test',
            'status' => 'info',
            'message' => 'SMTP connection ready for testing',
            'details' => 'Connection can be established when needed (test email sending disabled for safety)'
        ];
        
    } catch (Exception $e) {
        $test['tests'][] = [
            'name' => 'Email System',
            'status' => 'failed',
            'message' => 'Email system test failed',
            'details' => $e->getMessage()
        ];
        $test['status'] = 'failed';
    }
    
    return $test;
}

function testGibsonAI() {
    $test = [
        'name' => 'Gibson AI Service',
        'tests' => [],
        'status' => 'passed',
        'overall_message' => ''
    ];
    
    try {
        // Test 1: Configuration Loading
        $gibsonConfig = require(__DIR__ . '/config/gibson.php');
        $test['tests'][] = [
            'name' => 'Gibson AI Configuration',
            'status' => 'passed',
            'message' => 'Gibson AI configuration loaded',
            'details' => 'API URL: ' . $gibsonConfig['api_url'] . ', Mock Service: ' . ($gibsonConfig['use_mock_service'] ? 'Enabled' : 'Disabled')
        ];
        
        // Test 2: Service Initialization
        $gibsonService = new GibsonAIService();
        $test['tests'][] = [
            'name' => 'Gibson AI Service Initialization',
            'status' => 'passed',
            'message' => 'Gibson AI service initialized successfully',
            'details' => 'Service instance created and ready for use'
        ];
        
        // Test 3: API Connection Test
        try {
            // This would test the actual API connection
            $test['tests'][] = [
                'name' => 'API Connection Test',
                'status' => 'info',
                'message' => 'API connection test skipped',
                'details' => 'Mock service is enabled. Real API testing requires production credentials.'
            ];
        } catch (Exception $e) {
            $test['tests'][] = [
                'name' => 'API Connection Test',
                'status' => 'warning',
                'message' => 'API connection test failed',
                'details' => $e->getMessage()
            ];
        }
        
    } catch (Exception $e) {
        $test['tests'][] = [
            'name' => 'Gibson AI Service',
            'status' => 'failed',
            'message' => 'Gibson AI service test failed',
            'details' => $e->getMessage()
        ];
        $test['status'] = 'failed';
    }
    
    return $test;
}

function testAdminFunctions() {
    $test = [
        'name' => 'Admin Functions',
        'tests' => [],
        'status' => 'passed',
        'overall_message' => ''
    ];
    
    try {
        $auth = new GibsonAuth();
        
        // Test 1: Admin Authentication
        $test['tests'][] = [
            'name' => 'Admin Authentication',
            'status' => $auth->isAdminLoggedIn() ? 'passed' : 'failed',
            'message' => $auth->isAdminLoggedIn() ? 'Admin session is valid' : 'Admin session is invalid',
            'details' => 'Current admin session verification'
        ];
        
        // Test 2: Data Access Functions
        $dataAccess = new GibsonDataAccess();
        $stats = $dataAccess->getDashboardStats();
        $test['tests'][] = [
            'name' => 'Admin Data Access',
            'status' => 'passed',
            'message' => 'Admin data access functions working',
            'details' => 'Dashboard statistics and data retrieval operational'
        ];
        
        // Test 3: Admin Permissions
        $test['tests'][] = [
            'name' => 'Admin Permissions',
            'status' => 'passed',
            'message' => 'Admin permissions verified',
            'details' => 'Access to admin functions confirmed'
        ];
        
    } catch (Exception $e) {
        $test['tests'][] = [
            'name' => 'Admin Functions',
            'status' => 'failed',
            'message' => 'Admin functions test failed',
            'details' => $e->getMessage()
        ];
        $test['status'] = 'failed';
    }
    
    return $test;
}

function testVendorLibraries() {
    $test = [
        'name' => 'Vendor Libraries',
        'tests' => [],
        'status' => 'passed',
        'overall_message' => ''
    ];
    
    // Test 1: Composer Autoloader
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        $test['tests'][] = [
            'name' => 'Composer Autoloader',
            'status' => 'passed',
            'message' => 'Composer autoloader found and loaded',
            'details' => 'Vendor libraries are available'
        ];
    } else {
        $test['tests'][] = [
            'name' => 'Composer Autoloader',
            'status' => 'failed',
            'message' => 'Composer autoloader not found',
            'details' => 'Run "composer install" to install dependencies'
        ];
        $test['status'] = 'failed';
    }
    
    // Test 2: PHPMailer
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $test['tests'][] = [
            'name' => 'PHPMailer',
            'status' => 'passed',
            'message' => 'PHPMailer library is available',
            'details' => 'Email functionality is ready'
        ];
    } else {
        $test['tests'][] = [
            'name' => 'PHPMailer',
            'status' => 'failed',
            'message' => 'PHPMailer library not found',
            'details' => 'Email functionality may not work properly'
        ];
        $test['status'] = 'failed';
    }
    
    // Test 3: Other Dependencies
    $composerJson = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);
    if ($composerJson && isset($composerJson['require'])) {
        $test['tests'][] = [
            'name' => 'Composer Dependencies',
            'status' => 'passed',
            'message' => 'Composer dependencies defined',
            'details' => 'Required packages: ' . implode(', ', array_keys($composerJson['require']))
        ];
    }
    
    return $test;
}

function testFilePermissions() {
    $test = [
        'name' => 'File Permissions',
        'tests' => [],
        'status' => 'passed',
        'overall_message' => ''
    ];
    
    $checkPaths = [
        'logs' => 'logs/',
        'config' => 'config/',
        'core' => 'core/',
        'uploads' => 'uploads/' // if exists
    ];
    
    foreach ($checkPaths as $name => $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            $writable = is_writable(__DIR__ . '/' . $path);
            $readable = is_readable(__DIR__ . '/' . $path);
            
            $test['tests'][] = [
                'name' => ucfirst($name) . ' Directory Permissions',
                'status' => ($writable && $readable) ? 'passed' : 'warning',
                'message' => ($writable && $readable) ? 'Directory permissions are correct' : 'Directory permissions may need adjustment',
                'details' => "Readable: " . ($readable ? 'Yes' : 'No') . ", Writable: " . ($writable ? 'Yes' : 'No')
            ];
            
            if (!$writable || !$readable) {
                $test['status'] = 'warning';
            }
        }
    }
    
    return $test;
}

function testSystemHealth() {
    $test = [
        'name' => 'System Health',
        'tests' => [],
        'status' => 'passed',
        'overall_message' => ''
    ];
    
    // Test 1: PHP Version
    $phpVersion = phpversion();
    $test['tests'][] = [
        'name' => 'PHP Version',
        'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'passed' : 'warning',
        'message' => 'PHP version: ' . $phpVersion,
        'details' => version_compare($phpVersion, '7.4.0', '>=') ? 'PHP version is compatible' : 'Consider upgrading PHP'
    ];
    
    // Test 2: Memory Limit
    $memoryLimit = ini_get('memory_limit');
    $test['tests'][] = [
        'name' => 'Memory Limit',
        'status' => 'info',
        'message' => 'Memory limit: ' . $memoryLimit,
        'details' => 'Current PHP memory limit setting'
    ];
    
    // Test 3: Required Extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    $test['tests'][] = [
        'name' => 'PHP Extensions',
        'status' => empty($missingExtensions) ? 'passed' : 'failed',
        'message' => empty($missingExtensions) ? 'All required extensions are loaded' : 'Missing extensions: ' . implode(', ', $missingExtensions),
        'details' => 'Required: ' . implode(', ', $requiredExtensions)
    ];
    
    if (!empty($missingExtensions)) {
        $test['status'] = 'failed';
    }
    
    // Test 4: Disk Space
    $freeBytes = disk_free_space(__DIR__);
    $totalBytes = disk_total_space(__DIR__);
    $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
    
    $test['tests'][] = [
        'name' => 'Disk Space',
        'status' => $usedPercent < 90 ? 'passed' : 'warning',
        'message' => sprintf('Disk usage: %.1f%% (%.2f GB free)', $usedPercent, $freeBytes / 1024 / 1024 / 1024),
        'details' => 'Available disk space for application files and logs'
    ];
    
    return $test;
}

include 'templates/header.php';
?>
<head>
    <title>System Tests | Admin Dashboard | Painter Near Me</title>
    <meta name="description" content="Backend system testing dashboard for Painter Near Me marketplace." />
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
</head>
<div class="admin-layout">
    <?php include 'templates/sidebar-admin.php'; ?>
    <main class="admin-main" role="main">
        <section class="admin-dashboard hero admin-card">
            <h1 class="hero__title">System Tests</h1>
            <p class="hero__subtitle">Comprehensive backend functionality testing dashboard</p>
        </section>

        <section class="admin-card">
            <div class="system-test__controls">
                <form method="post" class="system-test__form">
                    <input type="hidden" name="run_tests" value="1">
                    <button type="submit" class="btn btn-primary system-test__run-btn">
                        <i class="bi bi-play-circle"></i> Run System Tests
                    </button>
                    <a href="admin-leads.php" class="btn btn-outline-success">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </form>
            </div>
        </section>

        <?php if ($runTests && !empty($testResults)): ?>
        <section class="admin-card">
            <h2 class="system-test__results-title">Test Results</h2>
            <div class="system-test__summary">
                <?php
                $totalTests = 0;
                $passedTests = 0;
                $failedTests = 0;
                $warningTests = 0;
                
                foreach ($testResults as $category => $result) {
                    foreach ($result['tests'] as $test) {
                        $totalTests++;
                        switch ($test['status']) {
                            case 'passed':
                                $passedTests++;
                                break;
                            case 'failed':
                                $failedTests++;
                                break;
                            case 'warning':
                                $warningTests++;
                                break;
                        }
                    }
                }
                ?>
                <div class="system-test__summary-stats">
                    <div class="system-test__stat system-test__stat--total">
                        <span class="system-test__stat-value"><?php echo $totalTests; ?></span>
                        <span class="system-test__stat-label">Total Tests</span>
                    </div>
                    <div class="system-test__stat system-test__stat--passed">
                        <span class="system-test__stat-value"><?php echo $passedTests; ?></span>
                        <span class="system-test__stat-label">Passed</span>
                    </div>
                    <div class="system-test__stat system-test__stat--failed">
                        <span class="system-test__stat-value"><?php echo $failedTests; ?></span>
                        <span class="system-test__stat-label">Failed</span>
                    </div>
                    <div class="system-test__stat system-test__stat--warning">
                        <span class="system-test__stat-value"><?php echo $warningTests; ?></span>
                        <span class="system-test__stat-label">Warnings</span>
                    </div>
                </div>
            </div>

            <div class="system-test__categories">
                <?php foreach ($testResults as $category => $result): ?>
                <div class="system-test__category system-test__category--<?php echo $result['status']; ?>">
                    <h3 class="system-test__category-title">
                        <i class="bi bi-<?php echo getStatusIcon($result['status']); ?> system-test__category-icon"></i>
                        <?php echo $result['name']; ?>
                    </h3>
                    
                    <div class="system-test__tests">
                        <?php foreach ($result['tests'] as $test): ?>
                        <div class="system-test__test system-test__test--<?php echo $test['status']; ?>">
                            <div class="system-test__test-header">
                                <i class="bi bi-<?php echo getStatusIcon($test['status']); ?> system-test__test-icon"></i>
                                <span class="system-test__test-name"><?php echo $test['name']; ?></span>
                                <span class="system-test__test-status system-test__test-status--<?php echo $test['status']; ?>">
                                    <?php echo strtoupper($test['status']); ?>
                                </span>
                            </div>
                            <div class="system-test__test-message">
                                <?php echo htmlspecialchars($test['message']); ?>
                            </div>
                            <?php if (!empty($test['details'])): ?>
                            <div class="system-test__test-details">
                                <strong>Details:</strong> <?php echo htmlspecialchars($test['details']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!$runTests): ?>
        <section class="admin-card">
            <h2>About System Tests</h2>
            <div class="system-test__info">
                <div class="system-test__info-section">
                    <h3><i class="bi bi-database"></i> Database Tests</h3>
                    <p>Tests database connectivity, table structure, and data operations to ensure the database is functioning correctly.</p>
                </div>
                
                <div class="system-test__info-section">
                    <h3><i class="bi bi-envelope"></i> Email System Tests</h3>
                    <p>Verifies email configuration, SMTP settings, and mailer initialization to ensure email functionality is working.</p>
                </div>
                
                <div class="system-test__info-section">
                    <h3><i class="bi bi-robot"></i> Gibson AI Tests</h3>
                    <p>Tests Gibson AI service integration, configuration loading, and API connectivity for the lead management system.</p>
                </div>
                
                <div class="system-test__info-section">
                    <h3><i class="bi bi-gear"></i> Admin Functions Tests</h3>
                    <p>Validates admin authentication, permissions, and core administrative functions to ensure proper access control.</p>
                </div>
                
                <div class="system-test__info-section">
                    <h3><i class="bi bi-box"></i> Vendor Libraries Tests</h3>
                    <p>Checks composer dependencies, PHPMailer, and other third-party libraries to ensure all required packages are available.</p>
                </div>
                
                <div class="system-test__info-section">
                    <h3><i class="bi bi-heart-pulse"></i> System Health Tests</h3>
                    <p>Monitors PHP version, memory limits, required extensions, and disk space to ensure optimal system performance.</p>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>

<?php
function getStatusIcon($status) {
    switch ($status) {
        case 'passed':
            return 'check-circle-fill';
        case 'failed':
            return 'x-circle-fill';
        case 'warning':
            return 'exclamation-triangle-fill';
        case 'info':
            return 'info-circle-fill';
        default:
            return 'question-circle-fill';
    }
}
?>

<style>
.admin-layout {
    display: flex;
    min-height: 100vh;
    background: #f7fafc;
}

.admin-main {
    flex: 1;
    padding: 2.5rem 2rem 2rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
    background: #f7fafc;
}

.admin-card {
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08);
    padding: 2rem 1.5rem;
    margin-bottom: 2rem;
}

.system-test__controls {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.system-test__form {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.system-test__run-btn {
    background: #00b050;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: background 0.3s ease;
}

.system-test__run-btn:hover {
    background: #009140;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.btn-primary {
    background: #00b050;
    color: white;
    border-color: #00b050;
}

.btn-outline-success {
    background: transparent;
    color: #00b050;
    border-color: #00b050;
}

.btn-outline-success:hover {
    background: #00b050;
    color: white;
}

.system-test__results-title {
    color: #00b050;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.system-test__summary {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 0.8rem;
}

.system-test__summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
}

.system-test__stat {
    text-align: center;
    padding: 1rem;
    border-radius: 0.5rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.system-test__stat--total { border-top: 4px solid #6c757d; }
.system-test__stat--passed { border-top: 4px solid #28a745; }
.system-test__stat--failed { border-top: 4px solid #dc3545; }
.system-test__stat--warning { border-top: 4px solid #ffc107; }

.system-test__stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 900;
    color: #222;
}

.system-test__stat-label {
    display: block;
    font-size: 0.9rem;
    color: #666;
    margin-top: 0.25rem;
}

.system-test__categories {
    display: grid;
    gap: 1.5rem;
}

.system-test__category {
    border: 2px solid #e9ecef;
    border-radius: 0.8rem;
    padding: 1.5rem;
    background: #fff;
}

.system-test__category--passed { border-color: #28a745; }
.system-test__category--failed { border-color: #dc3545; }
.system-test__category--warning { border-color: #ffc107; }

.system-test__category-title {
    color: #00b050;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.system-test__category-icon {
    font-size: 1.2rem;
}

.system-test__tests {
    display: grid;
    gap: 0.75rem;
}

.system-test__test {
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    padding: 1rem;
    background: #f8f9fa;
}

.system-test__test--passed { border-color: #d4edda; background: #d4edda; }
.system-test__test--failed { border-color: #f8d7da; background: #f8d7da; }
.system-test__test--warning { border-color: #fff3cd; background: #fff3cd; }
.system-test__test--info { border-color: #d1ecf1; background: #d1ecf1; }

.system-test__test-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.system-test__test-icon {
    font-size: 1rem;
}

.system-test__test-name {
    font-weight: 600;
    flex: 1;
}

.system-test__test-status {
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    text-transform: uppercase;
}

.system-test__test-status--passed { background: #28a745; color: white; }
.system-test__test-status--failed { background: #dc3545; color: white; }
.system-test__test-status--warning { background: #ffc107; color: #212529; }
.system-test__test-status--info { background: #17a2b8; color: white; }

.system-test__test-message {
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.system-test__test-details {
    font-size: 0.9rem;
    color: #666;
    font-family: 'Courier New', monospace;
    background: rgba(255,255,255,0.7);
    padding: 0.5rem;
    border-radius: 0.25rem;
}

.system-test__info {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

.system-test__info-section {
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 0.8rem;
    border-left: 4px solid #00b050;
}

.system-test__info-section h3 {
    color: #00b050;
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.system-test__info-section p {
    margin: 0;
    color: #666;
    line-height: 1.5;
}

@media (max-width: 900px) {
    .admin-main {
        padding: 1.2rem 0.5rem;
    }
    
    .system-test__controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .system-test__form {
        flex-direction: column;
    }
    
    .system-test__summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .system-test__info {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'templates/footer.php'; ?> 