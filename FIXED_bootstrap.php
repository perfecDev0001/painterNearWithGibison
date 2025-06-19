<?php
/**
 * Bootstrap File for Painter Near Me - SYNTAX ERROR FIXED
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
$requiredDirs = array(LOGS_PATH, UPLOADS_PATH);
foreach ($requiredDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Load environment configuration
function loadEnvironment() {
    // Load project.env file if it exists
    $envFile = ROOT_PATH . '/project.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                $parts = explode('=', $line, 2);
                if (count($parts) >= 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                }
            }
        }
    }
}

// Initialize environment
loadEnvironment();

// Determine if we're in development mode
$appEnv = getenv('APP_ENV');
if (!$appEnv && isset($_ENV['APP_ENV'])) {
    $appEnv = $_ENV['APP_ENV'];
}

$isDevelopment = ($appEnv === 'development');

// Set environment constant
define('ENVIRONMENT', $isDevelopment ? 'development' : 'production');

// Initialize error handler if it exists
if (file_exists(CORE_PATH . '/ErrorHandler.php')) {
    require_once CORE_PATH . '/ErrorHandler.php';
    ErrorHandler::initialize();
}

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

// Security headers function
function setSecurityHeaders() {
    if (!headers_sent()) {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

// Auto-loader for core classes
spl_autoload_register(function ($className) {
    $className = ltrim($className, '\\');
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    $coreFile = CORE_PATH . '/' . $className . '.php';
    if (file_exists($coreFile)) {
        require_once $coreFile;
        return;
    }
    
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
    setSecurityHeaders();
}

// Global constants
define('SITE_NAME', 'Painter Near Me');
if (ENVIRONMENT === 'production') {
    define('SITE_URL', 'https://painter-near-me.co.uk');
} else {
    define('SITE_URL', 'http://localhost');
}
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
    header("Location: " . $url, true, $statusCode);
    exit();
}

function getCurrentUrl() {
    if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
        return '';
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Bootstrap completed
define('BOOTSTRAP_LOADED', true); 