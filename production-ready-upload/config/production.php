<?php
/**
 * Production Configuration for 20i Shared Hosting
 * This file contains all production-specific settings
 */

// Error handling for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Ensure logs directory exists
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Production PHP settings
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Set timezone
date_default_timezone_set('Europe/London');

// Memory and execution limits for shared hosting
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);
ini_set('max_input_time', 30);

return [
    'environment' => 'production',
    'debug' => false,
    'app_url' => 'https://painter-near-me.co.uk',
    'maintenance_mode' => false,
    
    // Session configuration
    'session' => [
        'lifetime' => 3600, // 1 hour
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ],
    
    // Cache configuration
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // 5 minutes
        'prefix' => 'painter_'
    ],
    
    // File upload limits
    'upload' => [
        'max_size' => '10M',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
        'upload_path' => __DIR__ . '/../uploads'
    ],
    
    // Security settings
    'security' => [
        'csrf_protection' => true,
        'force_https' => true,
        'content_security_policy' => true
    ]
]; 