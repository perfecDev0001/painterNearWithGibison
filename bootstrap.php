<?php
/**
 * Bootstrap File for Painter Near Me
 * This file should be included at the start of every page
 * Handles environment setup, error handling, and common configurations
 */

// Prevent direct access
if (!defined('PAINTER_NEAR_ME_INIT')) {
    define('PAINTER_NEAR_ME_INIT', true);
}

// Set document root and base path
define('ROOT_PATH', __DIR__);
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CORE_PATH', ROOT_PATH . '/core');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Ensure required directories exist
$requiredDirs = [LOGS_PATH, UPLOADS_PATH];
foreach ($requiredDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Load environment configuration with caching
function loadEnvironment() {
    static $loaded = false;
    if ($loaded) return; // Prevent multiple loads
    
    $cacheFile = ROOT_PATH . '/cache/env.cache';
    $cacheValid = false;
    
    // Check if cache is valid (5 minutes)
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        $cacheValid = ($cacheData && (time() - $cacheData['timestamp']) < 300);
    }
    
    if ($cacheValid) {
        // Load from cache
        foreach ($cacheData['env'] as $key => $value) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    } else {
        // Load from files and cache
        $envData = [];
        
        // Load project.env file if it exists
        $envFile = ROOT_PATH . '/project.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $envData[$key] = $value;
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                }
            }
        }
        
        // Load .env file if it exists
        $envFile = ROOT_PATH . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $envData[$key] = $value;
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                }
            }
        }
        
        // Load gibson environment if it exists
        $gibsonEnvFile = ROOT_PATH . '/.gibson-env';
        if (file_exists($gibsonEnvFile)) {
            $lines = file($gibsonEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, 'export ') === 0) {
                    $line = substr($line, 7); // Remove 'export '
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1], '"');
                    $envData[$key] = $value;
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                }
            }
        }
        
        // Cache the environment data
        if (!file_exists(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0755, true);
        }
        file_put_contents($cacheFile, json_encode([
            'env' => $envData,
            'timestamp' => time()
        ]));
    }
    
    $loaded = true;
}

// Initialize environment
loadEnvironment();

// Determine if we're in development mode
$isDevelopment = (
    getenv('APP_ENV') === 'development' ||
    ($_ENV['APP_ENV'] ?? '') === 'development' ||
    getenv('GIBSON_DEVELOPMENT_MODE') === 'true' || 
    ($_ENV['GIBSON_DEVELOPMENT_MODE'] ?? '') === 'true' ||
    (isset($_GET['debug']) && $_GET['debug'] === '1' && (getenv('ALLOW_DEBUG_PARAM') === 'true'))
);

// Set environment constant
define('ENVIRONMENT', $isDevelopment ? 'development' : 'production');

// Initialize error handler
require_once CORE_PATH . '/ErrorHandler.php';
ErrorHandler::initialize();

// Set timezone
date_default_timezone_set('Europe/London');

// Session configuration for production
if (!$isDevelopment) {
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// Memory and execution limits for shared hosting
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);
ini_set('max_input_time', 30);

// Upload limits
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_file_uploads', 5);

// Security headers function
function setSecurityHeaders() {
    if (!headers_sent()) {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Strict transport security (only for HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://cdn.jsdelivr.net; " .
               "connect-src 'self' https://api.stripe.com; " .
               "frame-src https://js.stripe.com;";
        header("Content-Security-Policy: $csp");
    }
}

// Force HTTPS in production
function forceHTTPS() {
    // Only run in web environment (not CLI)
    if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
        return;
    }
    
    if (ENVIRONMENT === 'production' && 
        (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') &&
        (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')) {
        $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirectURL", true, 301);
        exit();
    }
}

// Database connection helper with fallback
function getDatabaseConnection() {
    static $connection = null;
    
    if ($connection !== null) {
        return $connection;
    }
    
    $configFile = CONFIG_PATH . '/database.php';
    if (!file_exists($configFile)) {
        throw new Exception('Database configuration file not found');
    }
    
    $config = require $configFile;
    
    try {
        $connection = new mysqli(
            $config['host'] ?? 'localhost',
            $config['username'] ?? '',
            $config['password'] ?? '',
            $config['database'] ?? '',
            $config['port'] ?? 3306
        );
        
        if ($connection->connect_error) {
            throw new Exception('Database connection failed: ' . $connection->connect_error);
        }
        
        $connection->set_charset($config['charset'] ?? 'utf8mb4');
        
        return $connection;
    } catch (Exception $e) {
        error_log('Database connection error: ' . $e->getMessage());
        
        // In production, show maintenance page
        if (ENVIRONMENT === 'production') {
            showMaintenancePage();
            exit();
        }
        
        throw $e;
    }
}

// Maintenance page for database issues
function showMaintenancePage() {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Maintenance | Painter Near Me</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 40px; background: #f5f5f5; text-align: center; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; }
            .icon { font-size: 64px; margin-bottom: 20px; }
            h1 { color: #333; margin-bottom: 20px; }
            p { color: #666; line-height: 1.6; margin-bottom: 30px; }
            .btn { display: inline-block; padding: 12px 24px; background: #00b050; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">🔧</div>
            <h1>Site Under Maintenance</h1>
            <p>We're currently performing scheduled maintenance to improve our services.</p>
            <p>We'll be back online shortly. Thank you for your patience!</p>
            <a href="/" class="btn">Try Again</a>
        </div>
    </body>
    </html>
    <?php
}

// Auto-loader for core classes
spl_autoload_register(function ($className) {
    // Remove namespace prefix if present
    $className = ltrim($className, '\\');
    
    // Replace namespace separators with directory separators
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    // Core classes
    $coreFile = CORE_PATH . '/' . $className . '.php';
    if (file_exists($coreFile)) {
        require_once $coreFile;
        return;
    }
    
    // Try without Core namespace
    if (strpos($className, 'Core' . DIRECTORY_SEPARATOR) === 0) {
        $coreFile = CORE_PATH . '/' . substr($className, 5) . '.php';
        if (file_exists($coreFile)) {
            require_once $coreFile;
            return;
        }
    }
});

// Apply security measures in production
if (ENVIRONMENT === 'production') {
    // forceHTTPS(); // DISABLED - hosting provider handles HTTPS redirect
    setSecurityHeaders();
}

// Global constants
define('SITE_NAME', 'Painter Near Me');
define('SITE_URL', ENVIRONMENT === 'production' ? 'https://painter-near-me.co.uk' : 'http://localhost');
define('VERSION', '1.0.0');

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function redirectTo($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit();
}

function getCurrentUrl() {
    // Return empty string if not in web environment
    if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
        return '';
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Bootstrap completed
define('BOOTSTRAP_LOADED', true); 