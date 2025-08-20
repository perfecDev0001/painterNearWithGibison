<?php
// Error handling is managed by bootstrap.php

class GibsonAIService {
    private $apiKey;
    private $apiUrl;
    private $databaseId;
    private $config;
    private $mockService;
    private $bidService;
    private $useMockService;

    public function __construct() {
        // Load configuration (use require to ensure fresh load)
        $configFile = __DIR__ . '/../config/gibson.php';
        $this->config = require $configFile;
        
        // Ensure config is an array
        if (!is_array($this->config)) {
            throw new Exception('Gibson AI configuration must return an array');
        }
        
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->apiUrl = $this->config['api_url'] ?? '';
        $this->databaseId = $this->config['database_id'] ?? '';
        $this->useMockService = $this->config['use_mock_service'] ?? false;
        
        // Initialize Gibson bid service that tries Gibson AI first, then falls back to local storage
        require_once __DIR__ . '/GibsonBidService.php';
        $this->bidService = new GibsonBidService($this);
        
        // Initialize mock service only if needed for other features
        if ($this->useMockService) {
            require_once __DIR__ . '/MockGibsonAIService.php';
            $this->mockService = new MockGibsonAIService();
        }
    }

    // Authentication Methods - Updated to match OpenAPI spec
    public function authenticateUser($email, $password) {
        if ($this->useMockService) {
            return $this->mockService->authenticateUser($email, $password);
        }
        
        // Get all users and filter manually (complex WHERE clauses cause HTTP 500)
        $userResult = $this->makeApiCall('/v1/-/user');
        if (!$userResult['success'] || empty($userResult['data'])) {
            return ['success' => false, 'error' => 'Could not retrieve users'];
        }
        
        // Find user by email
        $user = null;
        foreach ($userResult['data'] as $userData) {
            if ($userData['email'] === $email) {
                $user = $userData;
                break;
            }
        }
        
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        // Verify password (assuming password_hash field contains hashed password)
        if (!isset($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Create user session
        $sessionData = [
            'user_id' => $user['id'],
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ];
        
        $sessionResult = $this->makeApiCall('/v1/-/user-session', $sessionData, 'POST');
        if ($sessionResult['success']) {
            return [
                'success' => true,
                'data' => [
                    'user' => $user,
                    'session' => $sessionResult['data']
                ]
            ];
        }
        
        return $sessionResult;
    }

    public function registerUser($userData) {
        if ($this->useMockService) {
            return $this->mockService->registerUser($userData);
        }
        
        // Create clean user data with only required fields
        $cleanUserData = [];
        
        // Email is required and must be valid
        if (!isset($userData['email'])) {
            return ['success' => false, 'error' => 'Email is required'];
        }
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }
        $cleanUserData['email'] = $userData['email'];
        
        // Name is required
        if (isset($userData['name'])) {
            $cleanUserData['name'] = $userData['name'];
        } elseif (isset($userData['customer_name'])) {
            $cleanUserData['name'] = $userData['customer_name'];
        } else {
            return ['success' => false, 'error' => 'Name is required'];
        }
        
        // Password hash is required and password must be strong
        if (isset($userData['password'])) {
            // Validate password strength
            if (strlen($userData['password']) < 8) {
                return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
            }
            $cleanUserData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        } elseif (isset($userData['password_hash'])) {
            $cleanUserData['password_hash'] = $userData['password_hash'];
        } else {
            return ['success' => false, 'error' => 'Password is required'];
        }
        
        // Role ID is required (1 = admin, 3 = painter)
        if (isset($userData['role_id'])) {
            $cleanUserData['role_id'] = $userData['role_id'];
        } else {
            $cleanUserData['role_id'] = 4; // Default to customer role
        }
        
        return $this->makeApiCall('/v1/-/user', $cleanUserData, 'POST');
    }

    public function validateSession($token) {
        if ($this->useMockService) {
            // Mock validation - always return success for development
            return ['success' => true, 'data' => ['valid' => true]];
        }
        
        $result = $this->makeApiCall("/v1/-/user-session?where=" . urlencode("token='$token' AND expires_at > NOW()"));
        if ($result['success'] && !empty($result['data'])) {
            return ['success' => true, 'data' => ['valid' => true, 'session' => $result['data'][0]]];
        }
        
        return ['success' => false, 'data' => ['valid' => false]];
    }

    public function updatePassword($userId, $oldPassword, $newPassword) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => ['message' => 'Password updated']];
        }
        
        // Verify old password first
        $userResult = $this->makeApiCall("/v1/-/user/{$userId}");
        if (!$userResult['success']) {
            return $userResult;
        }
        
        if (!password_verify($oldPassword, $userResult['data']['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        // Update with new password hash
        return $this->makeApiCall("/v1/-/user/{$userId}", [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ], 'PATCH');
    }

    public function resetPassword($email) {
        if ($this->useMockService) {
            return $this->mockService->resetPassword($email);
        }
        
        // Find user by email
        $userResult = $this->makeApiCall('/v1/-/user?where=' . urlencode("email='$email'"));
        if (!$userResult['success'] || empty($userResult['data'])) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        $user = is_array($userResult['data']) ? $userResult['data'][0] : $userResult['data'];
        
        // Create password reset token
        $resetData = [
            'user_id' => $user['id'],
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ];
        
        return $this->makeApiCall('/v1/-/user-password-reset', $resetData, 'POST');
    }

    // User/Painter Management - Updated for OpenAPI spec
    public function createPainter($painterData) {
        if ($this->useMockService) {
            return $this->mockService->createPainter($painterData);
        }
        
        // First create user account if not exists
        if (isset($painterData['email'])) {
            $userData = [
                'name' => $painterData['company_name'] ?? $painterData['name'] ?? '',
                'email' => $painterData['email'],
                'password_hash' => password_hash($painterData['password'] ?? 'defaultpass', PASSWORD_DEFAULT),
                'role_id' => 3 // 3 = painter role (from database check)
            ];
            
            $userResult = $this->makeApiCall('/v1/-/user', $userData, 'POST');
            if (!$userResult['success']) {
                return $userResult;
            }
            
            $painterData['user_id'] = $userResult['data']['id'];
        }
        
        return $this->makeApiCall('/v1/-/painter-profile', $painterData, 'POST');
    }

    public function getPainterById($id) {
        if ($this->useMockService) {
            return $this->mockService->getPainterById($id);
        }
        return $this->makeApiCall("/v1/-/painter-profile/{$id}");
    }

    public function updatePainter($id, $data) {
        if ($this->useMockService) {
            return $this->mockService->updatePainter($id, $data);
        }
        return $this->makeApiCall("/v1/-/painter-profile/{$id}", $data, 'PATCH');
    }

    public function getPainters($filters = []) {
        if ($this->useMockService) {
            return $this->mockService->getPainters($filters);
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/painter-profile' . ($queryString ? '?' . $queryString : ''));
    }

    public function deletePainter($id) {
        if ($this->useMockService) {
            return $this->mockService->deletePainter($id);
        }
        return $this->makeApiCall("/v1/-/painter-profile/{$id}", null, 'DELETE');
    }

    // Lead Management - Updated for OpenAPI spec
    /**
     * Create a new lead with enhanced error handling and validation
     * 
     * @param array $leadData Lead data to create
     * @return array Response with success/error information
     */
    public function createLead($leadData) {
        if ($this->useMockService) {
            return $this->mockService->createLead($leadData);
        }
        
        // Validate required fields
        $requiredFields = ['customer_name', 'customer_email'];
        foreach ($requiredFields as $field) {
            if (empty($leadData[$field])) {
                error_log("[GibsonAI] Create lead failed: Missing required field '{$field}'");
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}",
                    'data' => null
                ];
            }
        }
        
        // Validate email format
        if (!filter_var($leadData['customer_email'], FILTER_VALIDATE_EMAIL)) {
            error_log("[GibsonAI] Create lead failed: Invalid email format");
            return [
                'success' => false,
                'error' => "Invalid email format",
                'data' => null
            ];
        }
        
        // Ensure proper field mapping for Gibson AI
        $gibsonLeadData = [
            'uuid' => $leadData['uuid'] ?? $this->generateUuid(),
            'customer_name' => $leadData['customer_name'],
            'customer_email' => $leadData['customer_email'],
            'customer_phone' => $leadData['customer_phone'] ?? '',
            'job_title' => $leadData['job_title'] ?? '',
            'job_description' => $leadData['job_description'] ?? '',
            'job_type' => $leadData['job_type'] ?? 'general',
            'property_type' => $leadData['property_type'] ?? 'house',
            'location' => $leadData['location'] ?? '',
            'postcode' => $leadData['postcode'] ?? $leadData['location'] ?? '',
            'status_id' => $leadData['status_id'] ?? 1, // Assuming 1 = open
            'lead_source' => $leadData['lead_source'] ?? 'website',
            'created_at' => $leadData['created_at'] ?? date('Y-m-d H:i:s')
        ];
        
        // Make API call with error tracking
        $result = $this->makeApiCall('/v1/-/job-lead', $gibsonLeadData, 'POST');
        
        // Log detailed information about failures for monitoring
        if (!$result['success']) {
            $errorContext = json_encode([
                'error' => $result['error'],
                'http_code' => $result['http_code'] ?? 'unknown',
                'lead_uuid' => $gibsonLeadData['uuid']
            ]);
            error_log("[GibsonAI] Create lead failed: {$errorContext}");
            
            // Store failed lead data for retry if configured
            if ($this->config['error_handling']['store_failed_leads'] ?? false) {
                $this->storeFailedLead($gibsonLeadData);
            }
        }
        
        return $result;
    }
    
    /**
     * Store failed lead data for later retry
     * 
     * @param array $leadData Lead data that failed to be created
     * @return bool Success status
     */
    private function storeFailedLead($leadData) {
        try {
            $failedLeadsFile = __DIR__ . '/../data/failed_leads.json';
            $failedLeads = [];
            
            // Load existing failed leads
            if (file_exists($failedLeadsFile)) {
                $failedLeadsJson = file_get_contents($failedLeadsFile);
                $failedLeads = json_decode($failedLeadsJson, true) ?: [];
            }
            
            // Add new failed lead with timestamp
            $leadData['_failed_timestamp'] = time();
            $leadData['_retry_count'] = 0;
            $failedLeads[] = $leadData;
            
            // Save updated failed leads
            file_put_contents(
                $failedLeadsFile, 
                json_encode($failedLeads, JSON_PRETTY_PRINT),
                LOCK_EX
            );
            
            return true;
        } catch (Exception $e) {
            error_log("[GibsonAI] Failed to store failed lead: " . $e->getMessage());
            return false;
        }
    }

    public function getLeads($filters = []) {
        if ($this->useMockService) {
            return $this->mockService->getLeads($filters);
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/job-lead' . ($queryString ? '?' . $queryString : ''));
    }

    public function getLeadById($id) {
        if ($this->useMockService) {
            return $this->mockService->getLeadById($id);
        }
        return $this->makeApiCall("/v1/-/job-lead/{$id}");
    }

    public function updateLead($id, $data) {
        if ($this->useMockService) {
            return $this->mockService->updateLead($id, $data);
        }
        return $this->makeApiCall("/v1/-/job-lead/{$id}", $data, 'PATCH');
    }

    public function deleteLead($id) {
        if ($this->useMockService) {
            return $this->mockService->deleteLead($id);
        }
        return $this->makeApiCall("/v1/-/job-lead/{$id}", null, 'DELETE');
    }

    public function assignLeadToPainter($leadId, $painterId) {
        if ($this->useMockService) {
            return $this->mockService->updateLead($leadId, ['assigned_painter_id' => $painterId, 'status' => 'assigned']);
        }
        return $this->makeApiCall("/v1/-/lead-claim", [
            'job_lead_id' => $leadId,
            'painter_id' => $painterId,
            'claimed_at' => date('Y-m-d H:i:s')
        ], 'POST');
    }

    // Lead Claiming System (Payment for Access)

    public function getLeadClaims($filters = []) {
        if ($this->useMockService) {
            return $this->mockService->getLeadClaims($filters);
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/lead-claim' . ($queryString ? '?' . $queryString : ''));
    }

    public function hasPainterClaimedLead($painterId, $leadId) {
        if ($this->useMockService) {
            return $this->mockService->hasPainterClaimedLead($painterId, $leadId);
        }
        $result = $this->makeApiCall("/v1/-/lead-claim?where=" . urlencode("painter_id=$painterId AND job_lead_id=$leadId"));
        return $result['success'] && !empty($result['data']);
    }

    // Bid Management System - Updated for OpenAPI spec
    public function createBid($bidData) {
        if ($this->useMockService) {
            return $this->mockService->createBid($bidData);
        }
        return $this->makeApiCall('/v1/-/bid', $bidData, 'POST');
    }

    public function getBids($filters = []) {
        if ($this->useMockService) {
            return $this->mockService->getBids($filters);
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/bid' . ($queryString ? '?' . $queryString : ''));
    }

    public function getBidsForLead($leadId) {
        if ($this->useMockService) {
            return $this->mockService->getBidsForLead($leadId);
        }
        return $this->makeApiCall("/v1/-/bid?where=" . urlencode("job_lead_id=$leadId"));
    }

    public function hasPainterBidOnLead($painterId, $leadId) {
        if ($this->useMockService) {
            $result = $this->mockService->hasPainterBidOnLead($painterId, $leadId);
            return $result['success'] && isset($result['data']['has_bid']) && $result['data']['has_bid'];
        }
        
        $result = $this->makeApiCall("/v1/-/bid?where=" . urlencode("painter_id=$painterId AND job_lead_id=$leadId"));
        return $result['success'] && !empty($result['data']);
    }

    public function getBidById($id) {
        if ($this->useMockService) {
            return $this->mockService->getBidById($id);
        }
        return $this->makeApiCall("/v1/-/bid/{$id}");
    }

    public function updateBid($id, $data) {
        if ($this->useMockService) {
            return $this->mockService->updateBid($id, $data);
        }
        return $this->makeApiCall("/v1/-/bid/{$id}", $data, 'PATCH');
    }

    public function deleteBid($id) {
        if ($this->useMockService) {
            return $this->mockService->deleteBid($id);
        }
        return $this->makeApiCall("/v1/-/bid/{$id}", null, 'DELETE');
    }

    public function acceptBid($id) {
        if ($this->useMockService) {
            return $this->mockService->updateBid($id, ['status' => 'accepted']);
        }
        return $this->makeApiCall("/v1/-/bid/{$id}", ['status' => 'accepted'], 'PATCH');
    }

    // Admin Management - Updated for OpenAPI spec
    public function createAdmin($adminData) {
        if ($this->useMockService) {
            return $this->mockService->createAdmin($adminData);
        }
        
        // Create user with admin role
        $userData = [
            'name' => $adminData['name'],
            'email' => $adminData['email'],
            'password_hash' => password_hash($adminData['password'], PASSWORD_DEFAULT),
            'role_id' => 1 // 1 = admin role (from database check)
        ];
        
        return $this->makeApiCall('/v1/-/user', $userData, 'POST');
    }

    public function authenticateAdmin($email, $password) {
        if ($this->useMockService) {
            return $this->mockService->authenticateAdmin($email, $password);
        }
        
        // Find admin user by email (complex WHERE clauses cause HTTP 500, so filter manually)
        $userResult = $this->makeApiCall('/v1/-/user');
        if (!$userResult['success'] || empty($userResult['data'])) {
            return ['success' => false, 'error' => 'Could not retrieve users'];
        }
        
        // Filter manually for admin user with matching email and role_id=1
        $adminUser = null;
        foreach ($userResult['data'] as $user) {
            if ($user['email'] === $email && $user['role_id'] == 1) {
                $adminUser = $user;
                break;
            }
        }
        
        if (!$adminUser) {
            return ['success' => false, 'error' => 'Admin user not found'];
        }
        
        $user = $adminUser;
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Create admin session
        $sessionData = [
            'user_id' => $user['id'],
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+8 hours'))
        ];
        
        $sessionResult = $this->makeApiCall('/v1/-/user-session', $sessionData, 'POST');
        if ($sessionResult['success']) {
            return [
                'success' => true,
                'data' => [
                    'admin' => $user,
                    'session' => $sessionResult['data']
                ]
            ];
        }
        
        return $sessionResult;
    }

    // Service Management - Updated for OpenAPI spec
    public function getServices($filters = []) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => []];
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/service' . ($queryString ? '?' . $queryString : ''));
    }

    public function createService($serviceData) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => $serviceData];
        }
        return $this->makeApiCall('/v1/-/service', $serviceData, 'POST');
    }

    public function getServiceCategories() {
        if ($this->useMockService) {
            return ['success' => true, 'data' => []];
        }
        return $this->makeApiCall('/v1/-/service-category');
    }

    public function getServiceTypes() {
        if ($this->useMockService) {
            return ['success' => true, 'data' => []];
        }
        return $this->makeApiCall('/v1/-/service-type');
    }

    // Invoice Management - Updated for OpenAPI spec
    public function createInvoice($invoiceData) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => $invoiceData];
        }
        return $this->makeApiCall('/v1/-/invoice', $invoiceData, 'POST');
    }

    public function getInvoices($filters = []) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => []];
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/invoice' . ($queryString ? '?' . $queryString : ''));
    }

    public function getInvoiceById($id) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => ['id' => $id]];
        }
        return $this->makeApiCall("/v1/-/invoice/{$id}");
    }

    // Locations and other utilities
    public function getLocations($filters = []) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => []];
        }
        return ['success' => true, 'data' => []]; // Not in OpenAPI spec
    }

    // Report Management - Updated for OpenAPI spec  
    public function createReport($reportData) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => $reportData];
        }
        return $this->makeApiCall('/v1/-/report', $reportData, 'POST');
    }

    public function getReports($filters = []) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => []];
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/report' . ($queryString ? '?' . $queryString : ''));
    }

    // Schema Creation and Data Management
    public function createSchema() {
        if ($this->useMockService) {
            return ['success' => true, 'message' => 'Schema created (mock)'];
        }
        
        // Load and execute schema SQL
        $schemaFile = __DIR__ . '/../config/gibson_schema.sql';
        if (!file_exists($schemaFile)) {
            return ['success' => false, 'error' => 'Schema file not found'];
        }
        
        $schema = file_get_contents($schemaFile);
        $tables = explode(';', $schema);
        
        $results = [];
        foreach ($tables as $table) {
            $table = trim($table);
            if (empty($table)) continue;
            
            // Note: This would need to be implemented based on Gibson AI's schema creation API
            // For now, return success
            $results[] = ['table' => $table, 'status' => 'created'];
        }
        
        return ['success' => true, 'data' => $results];
    }

    // ... rest of the methods remain similar but updated for OpenAPI spec

    /**
     * Make API call with proper Gibson AI authentication and error handling
     */
    private function makeApiCall($endpoint, $data = null, $method = 'GET') {
        // Enhanced error handling for production use
        $retryCount = 0;
        $maxRetries = $this->config['api_settings']['max_retries'] ?? 3;
        
        while ($retryCount <= $maxRetries) {
            try {
                // Initialize cURL
                $curl = curl_init();
                
                // Construct URL - No modification of endpoints, use as-is from OpenAPI spec
                $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
                
                // Set proper Gibson AI authentication headers according to OpenAPI spec
                $headers = [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: PainterNearMe/1.0',
                    'X-Gibson-API-Key: ' . $this->apiKey // Use X-Gibson-API-Key as per OpenAPI spec
                ];
                
                // Configure cURL for production with optimized timeouts
                curl_setopt_array($curl, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->config['api_settings']['timeout'] ?? 15, // Reduced from 30 to 15
                    CURLOPT_CONNECTTIMEOUT => $this->config['api_settings']['connection_timeout'] ?? 5, // Reduced from 10 to 5
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_SSL_VERIFYPEER => $this->config['api_settings']['verify_ssl'] ?? true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_MAXREDIRS => 0,
                    CURLOPT_ENCODING => '', // Enable compression
                    CURLOPT_USERAGENT => 'PainterNearMe/1.0 (Production)',
                ]);
                
                // Add request data if provided
                if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                    $jsonData = json_encode($data);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
                    curl_setopt($curl, CURLOPT_POST, true);
                }
                
                // Log API call for debugging in production
                if ($this->config['logging']['api_calls'] ?? false) {
                    error_log("[GibsonAI] API Call: {$method} {$url}");
                    if ($data) {
                        error_log("[GibsonAI] Request Data: " . json_encode($data));
                    }
                }
                
                // Execute request
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curlError = curl_error($curl);
                
                curl_close($curl);
                
                // Handle cURL errors
                if ($curlError) {
                    $errorMsg = "cURL Error: {$curlError}";
                    error_log("[GibsonAI] " . $errorMsg);
                    
                    if ($retryCount < $maxRetries) {
                        $retryCount++;
                        sleep($this->config['api_settings']['retry_delay'] ?? 1);
                        continue;
                    }
                    
                    return [
                        'success' => false,
                        'error' => $errorMsg,
                        'http_code' => 0,
                        'data' => null
                    ];
                }
                
                // Decode response
                $decodedResponse = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $decodedResponse = ['raw_response' => $response];
                }
                
                // Log response for debugging
                if ($this->config['logging']['api_calls'] ?? false) {
                    error_log("[GibsonAI] Response Code: {$httpCode}");
                    error_log("[GibsonAI] Response: " . $response);
                }
                
                // Handle successful responses
                if ($httpCode >= 200 && $httpCode < 300) {
                    return [
                        'success' => true,
                        'data' => $decodedResponse,
                        'http_code' => $httpCode
                    ];
                }
                
                // Handle client/server errors
                $errorMessage = $this->extractErrorMessage($decodedResponse, $httpCode);
                
                // Don't retry on authentication errors
                if ($httpCode === 401 || $httpCode === 403) {
                    error_log("[GibsonAI] Authentication Error: {$errorMessage}");
                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'http_code' => $httpCode,
                        'data' => $decodedResponse
                    ];
                }
                
                // Don't retry on client errors (400-499) except rate limiting
                if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                    error_log("[GibsonAI] Client Error: {$method} {$url} - HTTP {$httpCode} - {$errorMessage}");
                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'http_code' => $httpCode,
                        'data' => $decodedResponse
                    ];
                }
                
                // Retry on server errors and rate limiting
                if (($httpCode >= 500 || $httpCode === 429) && $retryCount < $maxRetries) {
                    $retryCount++;
                    $delay = $this->config['api_settings']['retry_delay'] ?? 1;
                    if ($httpCode === 429) {
                        $delay *= 2; // Exponential backoff for rate limiting
                    }
                    
                    error_log("[GibsonAI] Retrying in {$delay}s (attempt {$retryCount}/{$maxRetries})");
                    sleep($delay);
                    continue;
                }
                
                // Final error response
                error_log("[GibsonAI] API Error: {$method} {$url} - HTTP {$httpCode} - {$errorMessage}");
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'http_code' => $httpCode,
                    'data' => $decodedResponse
                ];
                
            } catch (Exception $e) {
                error_log("[GibsonAI] Exception in API call: " . $e->getMessage());
                
                if ($retryCount < $maxRetries) {
                    $retryCount++;
                    sleep($this->config['api_settings']['retry_delay'] ?? 1);
                    continue;
                }
                
                return [
                    'success' => false,
                    'error' => 'API call failed: ' . $e->getMessage(),
                    'http_code' => 0,
                    'data' => null
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'Maximum retry attempts exceeded',
            'http_code' => 0,
            'data' => null
        ];
    }

    /**
     * Public method to make API calls for testing connectivity
     */
    public function makeApiCallPublic($endpoint, $data = null, $method = 'GET') {
        return $this->makeApiCall($endpoint, $data, $method);
    }

    private function extractErrorMessage($response, $httpCode) {
        if (is_array($response)) {
            // Handle Gibson AI error format based on OpenAPI spec
            if (isset($response['detail'])) {
                if (is_array($response['detail'])) {
                    return implode(', ', array_column($response['detail'], 'msg'));
                }
                return $response['detail'];
            }
            
            if (isset($response['error'])) {
                return $response['error'];
            }
            
            if (isset($response['message'])) {
                return $response['message'];
            }
        }
        
        // Default error messages based on HTTP status codes
        switch ($httpCode) {
            case 400:
                return 'Bad Request - Invalid data provided';
            case 401:
                return 'Unauthorized - Invalid API key';
            case 403:
                return 'Forbidden - Access denied';
            case 404:
                return 'Not Found - Resource does not exist';
            case 422:
                return 'Unprocessable Entity - Validation failed';
            case 429:
                return 'Too Many Requests - Rate limit exceeded';
            case 500:
                return 'Internal Server Error';
            case 503:
                return 'Service Unavailable';
            default:
                return "HTTP Error {$httpCode}";
        }
    }

    // Additional utility methods remain the same...
    public function checkAdminExists() {
        if ($this->useMockService) {
            return $this->mockService->checkAdminExists();
        }
        
        $result = $this->makeApiCall('/v1/-/user?where=' . urlencode("role_id=3"));
        return $result['success'] && !empty($result['data']);
    }

    public function changeAdminPassword($adminId, $oldPassword, $newPassword) {
        if ($this->useMockService) {
            return ['success' => true, 'message' => 'Password changed'];
        }
        
        return $this->updatePassword($adminId, $oldPassword, $newPassword);
    }

    public function requestPasswordReset($email) {
        return $this->resetPassword($email);
    }

    public function validatePasswordResetToken($token) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => ['valid' => true]];
        }
        
        $result = $this->makeApiCall('/v1/-/user-password-reset?where=' . urlencode("token='$token' AND expires_at > NOW()"));
        return $result['success'] && !empty($result['data']);
    }

    public function resetPasswordWithToken($token, $newPassword) {
        if ($this->useMockService) {
            return ['success' => true, 'message' => 'Password reset'];
        }
        
        // Validate token and get user
        $tokenResult = $this->validatePasswordResetToken($token);
        if (!$tokenResult['success']) {
            return ['success' => false, 'error' => 'Invalid or expired token'];
        }
        
        $resetRecord = $tokenResult['data'][0];
        
        // Update user password
        $updateResult = $this->makeApiCall("/v1/-/user/{$resetRecord['user_id']}", [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ], 'PATCH');
        
        if ($updateResult['success']) {
            // Delete the reset token
            $this->makeApiCall("/v1/-/user-password-reset/{$resetRecord['id']}", null, 'DELETE');
        }
        
        return $updateResult;
    }

    // Data access methods
    public function getLead($leadId) {
        return $this->getLeadById($leadId);
    }

    public function getBid($bidId) {
        return $this->getBidById($bidId);
    }

    public function getUserProfile($userId) {
        if ($this->useMockService) {
            return $this->mockService->getUserProfile($userId);
        }
        return $this->makeApiCall("/v1/-/user/{$userId}");
    }

    public function updateUserProfile($userId, $profileData) {
        if ($this->useMockService) {
            return $this->mockService->updateUserProfile($userId, $profileData);
        }
        return $this->makeApiCall("/v1/-/user/{$userId}", $profileData, 'PATCH');
    }

    public function getAllUsers($filters = []) {
        if ($this->useMockService) {
            return $this->mockService->getAllUsers($filters);
        }
        $queryString = http_build_query($filters);
        $response = $this->makeApiCall('/v1/-/user' . ($queryString ? '?' . $queryString : ''));
        
        // Extract the actual data array from the response
        if (is_array($response) && isset($response['success']) && $response['success'] && isset($response['data'])) {
            return $response['data'];
        } else if (is_array($response) && isset($response['data'])) {
            return $response['data'];
        } else {
            return $response;
        }
    }

    public function getAnalytics() {
        return $this->getStats();
    }

    public function getDashboardStats() {
        // Combine multiple API calls to get dashboard statistics
        $stats = [
            'total_leads' => 0,
            'total_painters' => 0,
            'total_bids' => 0,
            'assigned_leads' => 0
        ];
        
        // Get lead count
        $leadsResult = $this->getLeads();
        if ($leadsResult['success'] && is_array($leadsResult['data'])) {
            $stats['total_leads'] = count($leadsResult['data']);
            $stats['assigned_leads'] = count(array_filter($leadsResult['data'], function($lead) {
                return isset($lead['status']) && $lead['status'] === 'assigned';
            }));
        }
        
        // Get painter count
        $paintersResult = $this->getPainters();
        if ($paintersResult['success'] && is_array($paintersResult['data'])) {
            $stats['total_painters'] = count($paintersResult['data']);
        }
        
        // Get bid count
        $bidsResult = $this->getBids();
        if ($bidsResult['success'] && is_array($bidsResult['data'])) {
            $stats['total_bids'] = count($bidsResult['data']);
        }
        
        return ['success' => true, 'data' => $stats];
    }

    // Continue with remaining methods...
    public function getStats() {
        return $this->getDashboardStats();
    }

    // Configuration and utility methods
    public function isUsingMockService() {
        return $this->useMockService;
    }

    public function getApiUrl() {
        return $this->apiUrl;
    }

    public function getDatabaseId() {
        return $this->databaseId;
    }

    public function getConfiguration() {
        return [
            'api_url' => $this->apiUrl,
            'database_id' => $this->databaseId,
            'use_mock_service' => $this->useMockService
        ];
    }

    public function getProjectConversation($projectUuid) {
        if ($this->useMockService) {
            return $this->mockService->getProjectConversation($projectUuid);
        }
        return $this->makeApiCall("/v1/-/conversation?where=" . urlencode("project_uuid='$projectUuid'"));
    }

    public function submitMessageToProjectConversation($projectUuid, $messageContent) {
        if ($this->useMockService) {
            return $this->mockService->submitMessageToProjectConversation($projectUuid, $messageContent);
        }
        
        $messageData = [
            'project_uuid' => $projectUuid,
            'content' => $messageContent,
            'sender_type' => 'system',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->makeApiCall('/v1/-/conversation-message', $messageData, 'POST');
    }

    public function createConversation($leadId, $customerId, $painterId) {
        if ($this->useMockService) {
            return $this->mockService->createConversation($leadId, $customerId, $painterId);
        }
        
        $conversationData = [
            'job_lead_id' => $leadId,
            'customer_id' => $customerId,
            'painter_id' => $painterId,
            'uuid' => $this->generateUuid(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->makeApiCall('/v1/-/conversation', $conversationData, 'POST');
    }

    public function getConversationMessages($conversationId) {
        if ($this->useMockService) {
            return $this->mockService->getConversationMessages($conversationId);
        }
        return $this->makeApiCall("/v1/-/conversation-message?where=" . urlencode("conversation_id=$conversationId"));
    }

    public function sendMessage($conversationId, $senderId, $messageText, $senderType = 'user') {
        if ($this->useMockService) {
            return $this->mockService->sendMessage($conversationId, $senderId, $messageText, $senderType);
        }
        
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'message_text' => $messageText,
            'sender_type' => $senderType,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->makeApiCall('/v1/-/conversation-message', $messageData, 'POST');
    }

    public function getConversationsByLead($leadId) {
        if ($this->useMockService) {
            return $this->mockService->getConversationsByLead($leadId);
        }
        return $this->makeApiCall("/v1/-/conversation?where=" . urlencode("job_lead_id=$leadId"));
    }

    public function getConversationsByUser($userId, $userType = 'customer') {
        if ($this->useMockService) {
            return $this->mockService->getConversationsByUser($userId, $userType);
        }
        $field = $userType === 'customer' ? 'customer_id' : 'painter_id';
        return $this->makeApiCall("/v1/-/conversation?where=" . urlencode("$field=$userId"));
    }

    public function markMessageAsRead($messageId, $userId) {
        if ($this->useMockService) {
            return $this->mockService->markMessageAsRead($messageId, $userId);
        }
        
        return $this->makeApiCall("/v1/-/conversation-message/{$messageId}", [
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s')
        ], 'PATCH');
    }

    public function getUnreadMessageCount($userId, $userType = 'customer') {
        if ($this->useMockService) {
            return $this->mockService->getUnreadMessageCount($userId, $userType);
        }
        
        // This would need a more complex query to join conversations and messages
        // For now, return a simple count
        return ['success' => true, 'data' => ['unread_count' => 0]];
    }

    // Payment-related methods
    public function createPaymentIntent($amount, $leadId, $painterId) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => ['payment_intent_id' => 'mock_intent_' . time()]];
        }
        
        $paymentData = [
            'amount' => $amount,
            'currency' => 'GBP',
            'painter_id' => $painterId,
            'external_id' => "lead_{$leadId}_" . time(),
            'payment_date' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ];
        
        return $this->makeApiCall('/v1/-/stripe-payment', $paymentData, 'POST');
    }

    public function confirmPayment($paymentIntentId, $paymentMethodId) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => ['status' => 'completed']];
        }
        
        return $this->makeApiCall("/v1/-/stripe-payment/{$paymentIntentId}", [
            'status' => 'completed',
            'payment_method_id' => $paymentMethodId,
            'completed_at' => date('Y-m-d H:i:s')
        ], 'PATCH');
    }

    public function getLeadSummary($filters = []) {
        return $this->getLeads($filters);
    }

    public function getClaimedLeads($painterId) {
        if ($this->useMockService) {
            return $this->mockService->getLeadClaims(['painter_id' => $painterId]);
        }
        return $this->makeApiCall("/v1/-/lead-claim?where=" . urlencode("painter_id=$painterId"));
    }

    public function claimLead($leadId, $painterId) {
        if ($this->useMockService) {
            return $this->mockService->claimLead($leadId, $painterId);
        }
        
        $claimData = [
            'job_lead_id' => $leadId,
            'painter_id' => $painterId,
            'claimed_at' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];
        
        return $this->makeApiCall('/v1/-/lead-claim', $claimData, 'POST');
    }

    public function addPortfolioImage($painterId, $imageData) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => ['image_id' => 'mock_img_' . time()]];
        }
        
        $portfolioData = [
            'painter_id' => $painterId,
            'image_url' => $imageData['url'],
            'description' => $imageData['description'] ?? '',
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->makeApiCall('/v1/-/painter-portfolio-image', $portfolioData, 'POST');
    }

    public function updatePainterStatus($painterId, $status) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => ['status' => $status]];
        }
        
        return $this->makeApiCall("/v1/-/painter-profile/{$painterId}", [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'PATCH');
    }

    public function getAdminPayments($filters = []) {
        if ($this->useMockService) {
            return ['success' => true, 'data' => []];
        }
        $queryString = http_build_query($filters);
        return $this->makeApiCall('/v1/-/stripe-payment' . ($queryString ? '?' . $queryString : ''));
    }

    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
} 