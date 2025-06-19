<?php
// Database Configuration with Enhanced Error Handling

// Load environment variables if available
$envFile = __DIR__ . '/../project.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key) && !empty($value)) {
                putenv("$key=$value");
            }
        }
    }
}

// Database configuration with fallbacks
$config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_DATABASE') ?: 'painter_near_me',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'timezone' => getenv('DB_TIMEZONE') ?: '+00:00',
    'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
    
    // Connection options
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ],
    
    // Connection pool settings
    'pool' => [
        'max_connections' => 10,
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1
    ],
    
    // Backup and maintenance
    'backup' => [
        'enabled' => true,
        'path' => __DIR__ . '/../database/backups',
        'retention_days' => 30
    ],
    
    // Logging
    'logging' => [
        'enabled' => getenv('DB_LOGGING') === 'true',
        'slow_query_threshold' => 2.0, // seconds
        'log_file' => __DIR__ . '/../logs/database.log'
    ]
];

// Validate configuration
$errors = [];

// Check required fields
if (empty($config['host'])) {
    $errors[] = 'Database host is required';
}

if (empty($config['database'])) {
    $errors[] = 'Database name is required';
}

// Validate port
if ($config['port'] < 1 || $config['port'] > 65535) {
    $errors[] = 'Invalid database port number';
}

// Log configuration errors
if (!empty($errors)) {
    error_log('Database Configuration Errors: ' . implode(', ', $errors));
}

return $config; 