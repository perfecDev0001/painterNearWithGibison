<?php
// Gibson AI Production Configuration

// Load environment from project.env file if environment variables not available
$envFile = __DIR__ . '/../project.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if (!getenv($key) && !empty($value)) {
                putenv("$key=$value");
            }
        }
    }
}

// Validate required environment variables
$requiredVars = ['GIBSON_API_KEY', 'GIBSON_DATABASE_ID'];
$missingVars = [];

foreach ($requiredVars as $var) {
    if (!getenv($var)) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    error_log('Gibson AI Configuration Error: Missing required environment variables: ' . implode(', ', $missingVars));
}

return [
    // Production API credentials - Never hardcode in production
    'api_key' => getenv('GIBSON_API_KEY') ?: '',
    'api_url' => getenv('GIBSON_API_URL') ?: 'https://api.gibsonai.com',
    'database_id' => getenv('GIBSON_DATABASE_ID') ?: '',
    
    // Production mode settings
    'development_mode' => getenv('GIBSON_DEVELOPMENT_MODE') === 'true',
    'use_mock_service' => getenv('GIBSON_DEVELOPMENT_MODE') === 'true', // Use mock for development
    
    // API Configuration
    'api_headers' => [
        'X-Gibson-API-Key', // Header name for authentication
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'User-Agent' => 'PainterNearMe/1.0'
    ],
    
    'authentication' => [
        'session_timeout' => 3600, // 1 hour
        'token_refresh' => true,
        'csrf_protection' => true,
        'secure_cookies' => true
    ],
    
    'api_settings' => [
        'timeout' => 30, // 30 seconds
        'max_retries' => 3,
        'retry_delay' => 1, // 1 second
        'verify_ssl' => true,
        'connection_timeout' => 10 // 10 seconds for connection
    ],
    
    'migration' => [
        'batch_size' => 100,
        'backup_before' => true,
        'rollback_on_error' => true
    ],
    
    'caching' => [
        'enabled' => true,
        'ttl' => 300 // 5 minutes
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => getenv('GIBSON_DEVELOPMENT_MODE') === 'true' ? 'DEBUG' : 'INFO',
        'file' => __DIR__ . '/../logs/gibson.log',
        'api_calls' => getenv('GIBSON_DEVELOPMENT_MODE') === 'true', // Log API calls for debugging
        'max_file_size' => '10MB'
    ],
    
    'database' => [
        'name' => getenv('GIBSON_DATABASE_ID') ?: '',
        'auto_create_schema' => false, // Don't auto-create in production
        'backup_enabled' => true
    ],
    
    // Error handling configuration
    'error_handling' => [
        'retry_on_failure' => true,
        'fallback_to_local' => true,
        'log_errors' => true,
        'throw_exceptions' => getenv('GIBSON_DEVELOPMENT_MODE') === 'true'
    ],
    
    // Validation rules
    'validation' => [
        'required_fields' => $requiredVars,
        'api_key_min_length' => 32,
        'database_id_pattern' => '/^[a-zA-Z0-9_]+$/'
    ]
]; 