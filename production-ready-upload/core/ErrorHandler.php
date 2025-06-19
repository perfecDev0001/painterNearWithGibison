<?php
/**
 * Enhanced Error Handler for Painter Near Me
 * Provides comprehensive error handling, logging, and security features
 */

class ErrorHandler {
    private static $initialized = false;
    private static $logFile;
    
    public static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        self::$logFile = __DIR__ . '/../logs/error.log';
        
        // Ensure logs directory exists
        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set error handling based on environment
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            self::setDevelopmentErrorHandling();
        } else {
            self::setProductionErrorHandling();
        }
        
        // Register handlers
        set_error_handler([__CLASS__, 'handleError']);
        set_exception_handler([__CLASS__, 'handleException']);
        register_shutdown_function([__CLASS__, 'handleFatalError']);
        
        self::$initialized = true;
    }
    
    private static function setDevelopmentErrorHandling() {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        ini_set('log_errors', 1);
        ini_set('error_log', self::$logFile);
    }
    
    private static function setProductionErrorHandling() {
        // Set error reporting for production - hide errors from users but log them
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        ini_set('log_errors', 1);
        
        // Set error log location if writable
        $logPath = __DIR__ . '/../logs/error.log';
        if (is_writable(dirname($logPath))) {
            ini_set('error_log', $logPath);
        }
        
        // Additional security headers
        if (!headers_sent()) {
            header('X-Powered-By: ');
            header_remove('X-Powered-By');
        }
    }
    
    public static function handleError($severity, $message, $file, $line) {
        // Don't handle suppressed errors (@)
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error = [
            'type' => 'ERROR',
            'severity' => self::getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ];
        
        self::logError($error);
        
        // In development, show error details
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 4px;'>";
            echo "<strong>Error:</strong> {$message}<br>";
            echo "<strong>File:</strong> {$file}<br>";
            echo "<strong>Line:</strong> {$line}<br>";
            echo "</div>";
        }
        
        return true;
    }
    
    public static function handleException($exception) {
        $error = [
            'type' => 'EXCEPTION',
            'severity' => 'FATAL',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ];
        
        self::logError($error);
        
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            self::showFriendlyErrorPage();
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 4px;'>";
            echo "<h3>Uncaught Exception</h3>";
            echo "<strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "<br>";
            echo "<strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "<br>";
            echo "<strong>Line:</strong> " . $exception->getLine() . "<br>";
            echo "<strong>Stack Trace:</strong><pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
            echo "</div>";
        }
    }
    
    public static function handleFatalError() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'FATAL_ERROR',
                'severity' => 'FATAL',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
            ];
            
            self::logError($errorData);
            
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                self::showFriendlyErrorPage();
            }
        }
    }
    
    private static function logError($error) {
        $logEntry = sprintf(
            "[%s] %s: %s in %s on line %d (IP: %s, URL: %s)\n",
            $error['timestamp'],
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line'],
            $error['ip'],
            $error['url']
        );
        
        if (isset($error['trace'])) {
            $logEntry .= "Stack trace:\n" . $error['trace'] . "\n";
        }
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private static function showFriendlyErrorPage() {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Temporarily Unavailable | Painter Near Me</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            margin: 0; 
            padding: 40px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 500px; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .icon { 
            font-size: 64px; 
            margin-bottom: 20px; 
            color: #00b050;
        }
        h1 { 
            color: #333; 
            margin-bottom: 20px; 
            font-size: 24px;
            font-weight: 600;
        }
        p { 
            color: #666; 
            line-height: 1.6; 
            margin-bottom: 30px; 
        }
        .btn { 
            display: inline-block; 
            padding: 12px 24px; 
            background: #00b050; 
            color: white; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #009140;
        }
        .error-id {
            font-size: 12px;
            color: #999;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Service Temporarily Unavailable</h1>
        <p>We\'re experiencing a temporary issue with our service. Our team has been notified and is working to resolve this quickly.</p>
        <p>Please try again in a few minutes.</p>
        <a href="/" class="btn">Return to Homepage</a>
        <div class="error-id">Error ID: ' . date('YmdHis') . '-' . substr(md5(uniqid()), 0, 8) . '</div>
    </div>
</body>
</html>';
        exit();
    }
    
    private static function getSeverityName($severity) {
        switch ($severity) {
            case E_ERROR: return 'E_ERROR';
            case E_WARNING: return 'E_WARNING';
            case E_PARSE: return 'E_PARSE';
            case E_NOTICE: return 'E_NOTICE';
            case E_CORE_ERROR: return 'E_CORE_ERROR';
            case E_CORE_WARNING: return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
            case E_USER_ERROR: return 'E_USER_ERROR';
            case E_USER_WARNING: return 'E_USER_WARNING';
            case E_USER_NOTICE: return 'E_USER_NOTICE';
            case E_STRICT: return 'E_STRICT';
            case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: return 'E_DEPRECATED';
            case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
            default: return 'UNKNOWN';
        }
    }
} 