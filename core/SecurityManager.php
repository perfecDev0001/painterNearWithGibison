<?php
/**
 * Enhanced Security Manager
 * Comprehensive security features for the Painter Near Me platform
 */

class SecurityManager {
    private $logFile;
    private $rateLimitFile;
    private $blockedIpsFile;
    private $maxAttempts;
    private $timeWindow;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/../logs/security.log';
        $this->rateLimitFile = __DIR__ . '/../logs/rate_limits.json';
        $this->blockedIpsFile = __DIR__ . '/../logs/blocked_ips.json';
        $this->maxAttempts = 10;
        $this->timeWindow = 3600; // 1 hour
        
        $this->ensureLogDirectory();
        $this->setSecurityHeaders();
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Set comprehensive security headers
     */
    public function setSecurityHeaders() {
        // Prevent XSS attacks
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // HSTS for HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://cdnjs.cloudflare.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' https://api.stripe.com; " .
               "frame-src https://js.stripe.com;";
        
        header("Content-Security-Policy: $csp");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Feature Policy / Permissions Policy
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
    
    /**
     * Rate limiting implementation
     */
    public function checkRateLimit($identifier = null, $maxRequests = 60, $timeWindow = 3600) {
        $identifier = $identifier ?: $this->getClientIdentifier();
        
        $rateLimits = $this->loadRateLimits();
        $currentTime = time();
        
        // Clean old entries
        $rateLimits = array_filter($rateLimits, function($limit) use ($currentTime) {
            return ($currentTime - $limit['first_request']) < $this->timeWindow;
        });
        
        if (!isset($rateLimits[$identifier])) {
            $rateLimits[$identifier] = [
                'requests' => 1,
                'first_request' => $currentTime,
                'last_request' => $currentTime
            ];
        } else {
            $rateLimits[$identifier]['requests']++;
            $rateLimits[$identifier]['last_request'] = $currentTime;
        }
        
        $this->saveRateLimits($rateLimits);
        
        // Check if limit exceeded
        if ($rateLimits[$identifier]['requests'] > $maxRequests) {
            $this->logSecurityEvent('RATE_LIMIT_EXCEEDED', [
                'identifier' => $identifier,
                'requests' => $rateLimits[$identifier]['requests'],
                'time_window' => $timeWindow
            ]);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Brute force protection
     */
    public function checkBruteForce($identifier = null) {
        $identifier = $identifier ?: $this->getClientIdentifier();
        
        if ($this->isIpBlocked($identifier)) {
            $this->logSecurityEvent('BLOCKED_IP_ACCESS_ATTEMPT', ['identifier' => $identifier]);
            return false;
        }
        
        return $this->checkRateLimit($identifier, $this->maxAttempts, $this->timeWindow);
    }
    
    /**
     * Block IP address
     */
    public function blockIp($ip, $reason = 'Security violation', $duration = 86400) {
        $blockedIps = $this->loadBlockedIps();
        
        $blockedIps[$ip] = [
            'blocked_at' => time(),
            'expires_at' => time() + $duration,
            'reason' => $reason,
            'attempts' => ($blockedIps[$ip]['attempts'] ?? 0) + 1
        ];
        
        $this->saveBlockedIps($blockedIps);
        $this->logSecurityEvent('IP_BLOCKED', ['ip' => $ip, 'reason' => $reason]);
    }
    
    /**
     * Check if IP is blocked
     */
    public function isIpBlocked($ip) {
        $blockedIps = $this->loadBlockedIps();
        
        if (!isset($blockedIps[$ip])) {
            return false;
        }
        
        // Check if block has expired
        if (time() > $blockedIps[$ip]['expires_at']) {
            unset($blockedIps[$ip]);
            $this->saveBlockedIps($blockedIps);
            return false;
        }
        
        return true;
    }
    
    /**
     * Input sanitization
     */
    public function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            case 'sql':
                return addslashes($input);
            
            case 'string':
            default:
                // FILTER_SANITIZE_STRING is deprecated in PHP 8.1+
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate input
     */
    public function validateInput($input, $type, $options = []) {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) !== false;
            
            case 'int':
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                $flags = 0;
                
                if ($min !== null || $max !== null) {
                    $range = [];
                    if ($min !== null) $range['min_range'] = $min;
                    if ($max !== null) $range['max_range'] = $max;
                    return filter_var($input, FILTER_VALIDATE_INT, ['options' => $range]) !== false;
                }
                
                return filter_var($input, FILTER_VALIDATE_INT) !== false;
            
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
            
            case 'postcode':
                // UK postcode validation
                $pattern = '/^[A-Z]{1,2}[0-9R][0-9A-Z]?\s?[0-9][A-BD-HJLNP-UW-Z]{2}$/i';
                return preg_match($pattern, $input);
            
            case 'phone':
                // Basic phone validation
                $pattern = '/^[\+]?[0-9\s\-\(\)]+$/';
                return preg_match($pattern, $input) && strlen(preg_replace('/\D/', '', $input)) >= 10;
            
            case 'string':
                $minLength = $options['min_length'] ?? 0;
                $maxLength = $options['max_length'] ?? 1000;
                $length = strlen($input);
                return $length >= $minLength && $length <= $maxLength;
            
            default:
                return true;
        }
    }
    
    /**
     * CSRF token management
     */
    public function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check if token has expired (1 hour)
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Secure password hashing
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * File upload security
     */
    public function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) { // 5MB default
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error occurred'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        // Additional security checks
        $fileName = $file['name'];
        
        // Check for dangerous extensions
        $dangerousExts = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh', 'cmd'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (in_array($ext, $dangerousExts)) {
            return ['valid' => false, 'error' => 'Dangerous file type'];
        }
        
        return ['valid' => true, 'mime_type' => $mimeType];
    }
    
    /**
     * SQL injection prevention
     * NOTE: This method is deprecated. Use prepared statements instead.
     */
    public function escapeSql($value) {
        // Deprecated: Use prepared statements for proper SQL injection prevention
        error_log('Warning: escapeSql() is deprecated. Use prepared statements instead.');
        return addslashes($value);
    }
    
    /**
     * Secure way to escape SQL values (deprecated - use prepared statements)
     */
    public function escapeSqlValue($value, $connection = null) {
        if ($connection && method_exists($connection, 'real_escape_string')) {
            return $connection->real_escape_string($value);
        }
        return addslashes($value);
    }
    
    /**
     * XSS prevention
     */
    public function escapeHtml($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Get client identifier for rate limiting
     */
    private function getClientIdentifier() {
        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return md5($ip . $userAgent);
    }
    
    /**
     * Get real client IP address
     */
    public function getClientIp() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Load rate limits from file
     */
    private function loadRateLimits() {
        if (!file_exists($this->rateLimitFile)) {
            return [];
        }
        
        $data = file_get_contents($this->rateLimitFile);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save rate limits to file
     */
    private function saveRateLimits($rateLimits) {
        file_put_contents($this->rateLimitFile, json_encode($rateLimits), LOCK_EX);
    }
    
    /**
     * Load blocked IPs from file
     */
    private function loadBlockedIps() {
        if (!file_exists($this->blockedIpsFile)) {
            return [];
        }
        
        $data = file_get_contents($this->blockedIpsFile);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save blocked IPs to file
     */
    private function saveBlockedIps($blockedIps) {
        file_put_contents($this->blockedIpsFile, json_encode($blockedIps), LOCK_EX);
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event, $data = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'data' => $data
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get security stats
     */
    public function getSecurityStats() {
        $stats = [
            'blocked_ips' => 0,
            'rate_limited_clients' => 0,
            'recent_events' => 0
        ];
        
        // Count blocked IPs
        $blockedIps = $this->loadBlockedIps();
        $stats['blocked_ips'] = count($blockedIps);
        
        // Count rate limited clients
        $rateLimits = $this->loadRateLimits();
        $stats['rate_limited_clients'] = count($rateLimits);
        
        // Count recent security events (last 24 hours)
        if (file_exists($this->logFile)) {
            $logs = file($this->logFile, FILE_IGNORE_NEW_LINES);
            $yesterday = time() - 86400;
            
            foreach ($logs as $log) {
                $event = json_decode($log, true);
                if ($event && strtotime($event['timestamp']) > $yesterday) {
                    $stats['recent_events']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean up old log entries
     */
    public function cleanupLogs($days = 30) {
        $cutoff = time() - ($days * 86400);
        
        // Clean security logs
        if (file_exists($this->logFile)) {
            $logs = file($this->logFile, FILE_IGNORE_NEW_LINES);
            $cleanLogs = [];
            
            foreach ($logs as $log) {
                $event = json_decode($log, true);
                if ($event && strtotime($event['timestamp']) > $cutoff) {
                    $cleanLogs[] = $log;
                }
            }
            
            file_put_contents($this->logFile, implode("\n", $cleanLogs) . "\n");
        }
        
        // Clean blocked IPs
        $blockedIps = $this->loadBlockedIps();
        $blockedIps = array_filter($blockedIps, function($ip) {
            return time() < $ip['expires_at'];
        });
        $this->saveBlockedIps($blockedIps);
    }
}
?>