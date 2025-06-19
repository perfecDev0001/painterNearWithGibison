<?php
// Enhanced error handling implemented for production stability


/**
 * Mock Gibson AI Service for Testing and Development
 * This provides a local simulation of Gibson AI API functionality
 */
class MockGibsonAIService {
    private $dataStore;
    private $config;
    private $dataFile;
    
    public function __construct() {
        $this->dataFile = __DIR__ . '/../data/mock_data.json';
        
        // Create data directory if it doesn't exist
        $dataDir = dirname($this->dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Load existing data or initialize with defaults
        $this->loadData();
        
        $this->config = [
            'next_id' => $this->getHighestId() + 1
        ];
    }
    
    private function loadData() {
        if (file_exists($this->dataFile)) {
            $json = file_get_contents($this->dataFile);
            $this->dataStore = json_decode($json, true);
            
            // Ensure all required keys exist
            $this->dataStore = array_merge([
                'painters' => [],
                'leads' => [],
                'bids' => [],
                'admin_users' => [],
                'sessions' => []
            ], $this->dataStore ?? []);
        } else {
            $this->dataStore = [
                'painters' => [],
                'leads' => [],
                'bids' => [],
                'admin_users' => [],
                'sessions' => []
            ];
            $this->initializeTestData();
            $this->saveData();
        }
    }
    
    private function saveData() {
        file_put_contents($this->dataFile, json_encode($this->dataStore, JSON_PRETTY_PRINT));
    }
    
    private function getHighestId() {
        $highestId = 0;
        foreach ($this->dataStore as $table) {
            if (is_array($table)) {
                foreach ($table as $item) {
                    if (isset($item['id']) && $item['id'] > $highestId) {
                        $highestId = $item['id'];
                    }
                }
            }
        }
        return $highestId;
    }
    
    private function initializeTestData() {
        $this->dataStore = [
            'painters' => [
                [
                    'id' => 1,
                    'email' => 'john@brightpainting.co.uk',
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'company_name' => 'Bright Painting Solutions',
                    'contact_name' => 'John Smith',
                    'phone' => '07700900123',
                    'location' => 'London, UK',
                    'services' => ['interior', 'exterior'],
                    'experience_years' => 8,
                    'status' => 'active',
                    'insurance' => 'yes',
                    'created_at' => '2024-01-15 10:30:00',
                    'updated_at' => '2024-01-15 10:30:00'
                ],
                [
                    'id' => 2,
                    'email' => 'sarah@elitepainters.co.uk',
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'company_name' => 'Elite Painters Ltd',
                    'contact_name' => 'Sarah Johnson',
                    'phone' => '07700900456',
                    'location' => 'Manchester, UK',
                    'services' => ['interior', 'commercial'],
                    'experience_years' => 12,
                    'status' => 'active',
                    'insurance' => 'yes',
                    'created_at' => '2024-01-10 14:20:00',
                    'updated_at' => '2024-01-10 14:20:00'
                ]
            ],
            'leads' => [],
            'bids' => [],
            'sessions' => [],
            'admin_users' => [
                [
                    'id' => 1,
                    'email' => 'admin@painter-near-me.co.uk',
                    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                    'username' => 'admin',
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'role' => 'super_admin',
                    'status' => 'active',
                    'created_at' => '2024-01-01 10:00:00',
                    'updated_at' => '2024-01-01 10:00:00'
                ],
                [
                    'id' => 2,
                    'email' => 'admin@test.com',
                    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                    'username' => 'testadmin',
                    'first_name' => 'Test',
                    'last_name' => 'Admin',
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => '2024-01-01 10:00:00',
                    'updated_at' => '2024-01-01 10:00:00'
                ],
                [
                    'id' => 3,
                    'email' => 'admin@localhost',
                    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                    'username' => 'localadmin',
                    'first_name' => 'Local',
                    'last_name' => 'Admin',
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => '2024-01-01 10:00:00',
                    'updated_at' => '2024-01-01 10:00:00'
                ]
            ],
            'customers' => [
                [
                    'id' => 1,
                    'email' => 'customer@test.com',
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'first_name' => 'John',
                    'last_name' => 'Customer',
                    'phone' => '07700900789',
                    'postcode' => 'SW1A 1AA',
                    'status' => 'active',
                    'created_at' => '2024-01-15 09:00:00',
                    'updated_at' => '2024-01-15 09:00:00'
                ]
            ]
        ];
        
        $this->saveData();
    }
    
    private function getNextId() {
        return $this->config['next_id']++;
    }
    
    private function findById($table, $id) {
        foreach ($this->dataStore[$table] as $item) {
            if ($item['id'] == $id) {
                return $item;
            }
        }
        return null;
    }
    
    private function filterData($table, $filters = []) {
        $data = $this->dataStore[$table];
        
        // Apply filters
        foreach ($filters as $key => $value) {
            if (in_array($key, ['limit', 'offset', 'order_by', 'order', 'sort', 'exclude_painter_id'])) {
                continue;
            }
            
            $data = array_filter($data, function($item) use ($key, $value) {
                return isset($item[$key]) && $item[$key] == $value;
            });
        }
        
        // Apply sorting
        if (isset($filters['sort'])) {
            $sortField = 'created_at';
            $sortOrder = 'desc';
            
            if ($filters['sort'] === 'created_at_desc') {
                $sortField = 'created_at';
                $sortOrder = 'desc';
            } elseif ($filters['sort'] === 'created_at_asc') {
                $sortField = 'created_at';
                $sortOrder = 'asc';
            }
            
            usort($data, function($a, $b) use ($sortField, $sortOrder) {
                $aValue = $a[$sortField] ?? '';
                $bValue = $b[$sortField] ?? '';
                
                if ($sortOrder === 'desc') {
                    return strcmp($bValue, $aValue);
                } else {
                    return strcmp($aValue, $bValue);
                }
            });
        }
        
        // Apply limit
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $data = array_slice($data, 0, (int)$filters['limit']);
        }
        
        return array_values($data);
    }
    
    // Authentication Methods
    public function authenticateUser($email, $password) {
        // Check customers first
        foreach ($this->dataStore['customers'] as $customer) {
            if ($customer['email'] === $email && password_verify($password, $customer['password_hash'])) {
                $token = bin2hex(random_bytes(32));
                $this->dataStore['sessions'][$token] = [
                    'user_id' => $customer['id'],
                    'type' => 'customer',
                    'created_at' => time(),
                    'expires_at' => time() + 3600
                ];
                
                return [
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'user' => $customer,
                        'user_type' => 'customer',
                        'expires_in' => 3600
                    ]
                ];
            }
        }
        
        // Check painters
        foreach ($this->dataStore['painters'] as $painter) {
            if ($painter['email'] === $email && password_verify($password, $painter['password_hash'])) {
                $token = bin2hex(random_bytes(32));
                $this->dataStore['sessions'][$token] = [
                    'user_id' => $painter['id'],
                    'type' => 'painter',
                    'created_at' => time(),
                    'expires_at' => time() + 3600
                ];
                
                return [
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'user' => $painter,
                        'user_type' => 'painter',
                        'expires_in' => 3600
                    ]
                ];
            }
        }
        
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    public function authenticateAdmin($email, $password) {
        foreach ($this->dataStore['admin_users'] as $admin) {
            if ($admin['email'] === $email && password_verify($password, $admin['password_hash'])) {
                $token = bin2hex(random_bytes(32));
                $this->dataStore['sessions'][$token] = [
                    'user_id' => $admin['id'],
                    'type' => 'admin',
                    'created_at' => time(),
                    'expires_at' => time() + 3600
                ];
                
                return [
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'user' => $admin,
                        'expires_in' => 3600
                    ]
                ];
            }
        }
        
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    public function registerUser($userData) {
        // Determine user type based on data provided
        $userType = 'customer'; // Default to customer
        if (isset($userData['company_name']) || isset($userData['business_name'])) {
            $userType = 'painter';
        }
        
        // Check if email exists in both customers and painters
        if ($userType === 'customer') {
            foreach ($this->dataStore['customers'] as $customer) {
                if ($customer['email'] === $userData['email']) {
                    return ['success' => false, 'error' => 'Email already exists'];
                }
            }
            
            // Check painters too to avoid duplicates
            foreach ($this->dataStore['painters'] as $painter) {
                if ($painter['email'] === $userData['email']) {
                    return ['success' => false, 'error' => 'Email already exists'];
                }
            }
        } else {
            foreach ($this->dataStore['painters'] as $painter) {
                if ($painter['email'] === $userData['email']) {
                    return ['success' => false, 'error' => 'Email already exists'];
                }
            }
            
            // Check customers too to avoid duplicates
            foreach ($this->dataStore['customers'] as $customer) {
                if ($customer['email'] === $userData['email']) {
                    return ['success' => false, 'error' => 'Email already exists'];
                }
            }
        }
        
        // Validate required fields
        if (empty($userData['email']) || empty($userData['password_hash'])) {
            return ['success' => false, 'error' => 'Email and password are required'];
        }
        
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }
        
        $user = array_merge($userData, [
            'id' => $this->getNextId(),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($userType === 'customer') {
            $this->dataStore['customers'][] = $user;
        } else {
            $this->dataStore['painters'][] = $user;
        }
        
        $this->saveData();
        
        return ['success' => true, 'data' => $user];
    }
    
    public function resetPassword($email) {
        return ['success' => true, 'data' => ['message' => 'Password reset email sent']];
    }
    
    // Painter Management
    public function createPainter($painterData) {
        // Validate required fields for painters
        $requiredFields = ['email', 'company_name', 'contact_name', 'phone'];
        foreach ($requiredFields as $field) {
            if (empty($painterData[$field])) {
                return ['success' => false, 'error' => "Missing required field: {$field}"];
            }
        }
        
        // Ensure password is properly hashed
        if (isset($painterData['password']) && !isset($painterData['password_hash'])) {
            $painterData['password_hash'] = password_hash($painterData['password'], PASSWORD_DEFAULT);
            unset($painterData['password']);
        }
        
        $result = $this->registerUser($painterData);
        if ($result['success']) {
            $this->saveData();
        }
        return $result;
    }
    
    public function getPainters($filters = []) {
        $data = $this->filterData('painters', $filters);
        return ['success' => true, 'data' => $data];
    }
    
    public function getPainterById($id) {
        $painter = $this->findById('painters', $id);
        return $painter ? ['success' => true, 'data' => $painter] : ['success' => false, 'error' => 'Painter not found'];
    }
    
    public function updatePainter($id, $data) {
        foreach ($this->dataStore['painters'] as &$painter) {
            if ($painter['id'] == $id) {
                $painter = array_merge($painter, $data, ['updated_at' => date('Y-m-d H:i:s')]);
                $this->saveData();
                return ['success' => true, 'data' => $painter];
            }
        }
        return ['success' => false, 'error' => 'Painter not found'];
    }
    
    public function deletePainter($id) {
        foreach ($this->dataStore['painters'] as $key => $painter) {
            if ($painter['id'] == $id) {
                unset($this->dataStore['painters'][$key]);
                return ['success' => true, 'data' => ['message' => 'Painter deleted']];
            }
        }
        return ['success' => false, 'error' => 'Painter not found'];
    }
    
    // Lead Management
    public function createLead($leadData) {
        $lead = array_merge($leadData, [
            'id' => $this->getNextId(),
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->dataStore['leads'][] = $lead;
        $this->saveData();
        return ['success' => true, 'data' => $lead];
    }
    
    public function getLeads($filters = []) {
        $data = $this->filterData('leads', $filters);
        return ['success' => true, 'data' => $data];
    }
    
    public function getLeadById($id) {
        $lead = $this->findById('leads', $id);
        return $lead ? ['success' => true, 'data' => $lead] : ['success' => false, 'error' => 'Lead not found'];
    }
    
    public function updateLead($id, $data) {
        foreach ($this->dataStore['leads'] as &$lead) {
            if ($lead['id'] == $id) {
                $lead = array_merge($lead, $data, ['updated_at' => date('Y-m-d H:i:s')]);
                $this->saveData();
                return ['success' => true, 'data' => $lead];
            }
        }
        return ['success' => false, 'error' => 'Lead not found'];
    }
    
    public function deleteLead($id) {
        foreach ($this->dataStore['leads'] as $key => $lead) {
            if ($lead['id'] == $id) {
                unset($this->dataStore['leads'][$key]);
                return ['success' => true, 'data' => ['message' => 'Lead deleted']];
            }
        }
        return ['success' => false, 'error' => 'Lead not found'];
    }
    
    // Bid Management
    public function createBid($bidData) {
        $bid = array_merge($bidData, [
            'id' => $this->getNextId(),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->dataStore['bids'][] = $bid;
        $this->saveData();
        return ['success' => true, 'data' => $bid];
    }
    
    public function getBids($filters = []) {
        $data = $this->filterData('bids', $filters);
        
        // Join with lead and painter data
        foreach ($data as &$bid) {
            $lead = $this->findById('leads', $bid['lead_id']);
            $painter = $this->findById('painters', $bid['painter_id']);
            
            if ($lead) {
                $bid['job_title'] = $lead['job_title'];
                $bid['location'] = $lead['location'];
                $bid['lead_status'] = $lead['status'];
            }
            
            if ($painter) {
                $bid['company_name'] = $painter['company_name'];
                $bid['contact_name'] = $painter['contact_name'];
            }
        }
        
        return ['success' => true, 'data' => $data];
    }
    
    public function getBidById($id) {
        $bid = $this->findById('bids', $id);
        return $bid ? ['success' => true, 'data' => $bid] : ['success' => false, 'error' => 'Bid not found'];
    }
    
    public function updateBid($id, $data) {
        foreach ($this->dataStore['bids'] as &$bid) {
            if ($bid['id'] == $id) {
                $bid = array_merge($bid, $data);
                $this->saveData();
                return ['success' => true, 'data' => $bid];
            }
        }
        return ['success' => false, 'error' => 'Bid not found'];
    }
    
    public function deleteBid($id) {
        foreach ($this->dataStore['bids'] as $key => $bid) {
            if ($bid['id'] == $id) {
                unset($this->dataStore['bids'][$key]);
                return ['success' => true, 'data' => ['message' => 'Bid deleted']];
            }
        }
        return ['success' => false, 'error' => 'Bid not found'];
    }

    public function getBidsForLead($leadId) {
        $bids = array_filter($this->dataStore['bids'], fn($b) => $b['lead_id'] == $leadId);
        return ['success' => true, 'data' => array_values($bids)];
    }

    public function hasPainterBidOnLead($painterId, $leadId) {
        foreach ($this->dataStore['bids'] as $bid) {
            if ($bid['painter_id'] == $painterId && $bid['lead_id'] == $leadId) {
                return ['success' => true, 'data' => ['has_bid' => true]];
            }
        }
        return ['success' => true, 'data' => ['has_bid' => false]];
    }

    // Lead claiming system
    public function claimLead($leadId, $painterId) {
        $claim = [
            'id' => $this->getNextId(),
            'lead_id' => $leadId,
            'painter_id' => $painterId,
            'claimed_at' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];
        
        if (!isset($this->dataStore['lead_claims'])) {
            $this->dataStore['lead_claims'] = [];
        }
        
        $this->dataStore['lead_claims'][] = $claim;
        $this->saveData();
        return ['success' => true, 'data' => $claim];
    }

    public function getLeadClaims($filters = []) {
        if (!isset($this->dataStore['lead_claims'])) {
            $this->dataStore['lead_claims'] = [];
        }
        $data = $this->filterData('lead_claims', $filters);
        return ['success' => true, 'data' => $data];
    }

    public function hasPainterClaimedLead($painterId, $leadId) {
        if (!isset($this->dataStore['lead_claims'])) {
            return ['success' => true, 'data' => ['has_claimed' => false]];
        }
        
        foreach ($this->dataStore['lead_claims'] as $claim) {
            if ($claim['painter_id'] == $painterId && $claim['lead_id'] == $leadId && $claim['status'] === 'active') {
                return ['success' => true, 'data' => ['has_claimed' => true]];
            }
        }
        return ['success' => true, 'data' => ['has_claimed' => false]];
    }
    
    // Schema Management
    public function createSchema() {
        return ['success' => true, 'data' => ['message' => 'Schema created successfully']];
    }
    
    // Statistics
    public function getStats() {
        return [
            'success' => true,
            'data' => [
                'total_leads' => count($this->dataStore['leads']),
                'open_leads' => count(array_filter($this->dataStore['leads'], fn($l) => $l['status'] === 'open')),
                'assigned_leads' => count(array_filter($this->dataStore['leads'], fn($l) => $l['status'] === 'assigned')),
                'closed_leads' => count(array_filter($this->dataStore['leads'], fn($l) => $l['status'] === 'completed' || $l['status'] === 'closed')),
                'total_painters' => count($this->dataStore['painters']),
                'active_painters' => count(array_filter($this->dataStore['painters'], fn($p) => $p['status'] === 'active')),
                'total_bids' => count($this->dataStore['bids']),
                'pending_bids' => count(array_filter($this->dataStore['bids'], fn($b) => $b['status'] === 'pending'))
            ]
        ];
    }
    
    // Additional methods for compatibility
    public function getLead($leadId) {
        return $this->getLeadById($leadId);
    }
    
    public function getUserProfile($userId) {
        return $this->getPainterById($userId);
    }
    
    public function getAllUsers($filters = []) {
        if (isset($filters['type']) && $filters['type'] === 'painter') {
            return $this->getPainters();
        }
        return ['success' => true, 'data' => []];
    }
    
    // Alias methods for compatibility
    public function getAnalytics() {
        return $this->getStats();
    }
    
    public function getDashboardStats() {
        return $this->getStats();
    }
    
    public function createAdmin($adminData) {
        // Check if admin with this email already exists
        foreach ($this->dataStore['admin_users'] as $admin) {
            if ($admin['email'] === $adminData['email']) {
                return ['success' => false, 'error' => 'Admin user already exists with this email'];
            }
        }
        
        // Create admin user
        $admin = [
            'id' => $this->getNextId(),
            'username' => $adminData['username'] ?? $adminData['name'] ?? 'admin',
            'name' => $adminData['name'] ?? $adminData['username'] ?? 'Administrator',
            'email' => $adminData['email'],
            'password_hash' => isset($adminData['password']) ? 
                password_hash($adminData['password'], PASSWORD_DEFAULT) : 
                $adminData['password_hash'],
            'role' => $adminData['role'] ?? 'admin',
            'role_id' => $adminData['role_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->dataStore['admin_users'][] = $admin;
        $this->saveData();
        
        return ['success' => true, 'data' => $admin];
    }
    
    public function checkAdminExists() {
        if (!isset($this->dataStore['admin_users']) || empty($this->dataStore['admin_users'])) {
            // Initialize data if not present
            $this->initializeTestData();
        }
        
        return !empty($this->dataStore['admin_users']);
    }

    // Conversation/Messaging Methods
    public function getProjectConversation($projectUuid) {
        $conversations = $this->dataStore['conversations'] ?? [];
        $messages = [];
        foreach ($conversations as $conversation) {
            if ($conversation['project_uuid'] === $projectUuid) {
                $messages = array_merge($messages, $conversation['messages'] ?? []);
            }
        }
        return ['success' => true, 'data' => $messages];
    }

    public function submitMessageToProjectConversation($projectUuid, $messageContent) {
        if (!isset($this->dataStore['conversations'])) {
            $this->dataStore['conversations'] = [];
        }

        $conversationFound = false;
        foreach ($this->dataStore['conversations'] as &$conversation) {
            if ($conversation['project_uuid'] === $projectUuid) {
                $message = [
                    'uuid' => $this->generateUuid(),
                    'content' => $messageContent,
                    'date_created' => date('c'),
                    'role_id' => 1
                ];
                $conversation['messages'][] = $message;
                $conversationFound = true;
                break;
            }
        }

        if (!$conversationFound) {
            $this->dataStore['conversations'][] = [
                'uuid' => $this->generateUuid(),
                'project_uuid' => $projectUuid,
                'messages' => [
                    [
                        'uuid' => $this->generateUuid(),
                        'content' => $messageContent,
                        'date_created' => date('c'),
                        'role_id' => 1
                    ]
                ],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        $this->saveData();
        return ['success' => true, 'data' => ['content' => $messageContent, 'refetch' => true]];
    }

    public function createConversation($leadId, $customerId, $painterId) {
        if (!isset($this->dataStore['conversations'])) {
            $this->dataStore['conversations'] = [];
        }

        $conversation = [
            'id' => $this->getNextId(),
            'uuid' => $this->generateUuid(),
            'lead_id' => $leadId,
            'customer_id' => $customerId,
            'painter_id' => $painterId,
            'messages' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->dataStore['conversations'][] = $conversation;
        $this->saveData();
        return ['success' => true, 'data' => $conversation];
    }

    public function getConversationMessages($conversationId) {
        $conversation = $this->findById('conversations', $conversationId);
        if ($conversation) {
            return ['success' => true, 'data' => $conversation['messages'] ?? []];
        }
        return ['success' => false, 'error' => 'Conversation not found'];
    }

    public function sendMessage($conversationId, $senderId, $messageText, $senderType = 'user') {
        if (!isset($this->dataStore['messages'])) {
            $this->dataStore['messages'] = [];
        }

        $message = [
            'id' => $this->getNextId(),
            'uuid' => $this->generateUuid(),
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'message_text' => $messageText,
            'is_read' => false,
            'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->dataStore['messages'][] = $message;

        // Update conversation's last message time
        foreach ($this->dataStore['conversations'] as &$conversation) {
            if ($conversation['id'] == $conversationId) {
                $conversation['updated_at'] = date('Y-m-d H:i:s');
                if (!isset($conversation['messages'])) {
                    $conversation['messages'] = [];
                }
                $conversation['messages'][] = $message;
                break;
            }
        }

        $this->saveData();
        return ['success' => true, 'data' => $message];
    }

    public function getConversationsByLead($leadId) {
        $conversations = array_filter($this->dataStore['conversations'] ?? [], function($conv) use ($leadId) {
            return $conv['lead_id'] == $leadId;
        });
        return ['success' => true, 'data' => array_values($conversations)];
    }

    public function getConversationsByUser($userId, $userType = 'customer') {
        $field = $userType === 'customer' ? 'customer_id' : 'painter_id';
        $conversations = array_filter($this->dataStore['conversations'] ?? [], function($conv) use ($userId, $field) {
            return $conv[$field] == $userId;
        });
        return ['success' => true, 'data' => array_values($conversations)];
    }

    public function markMessageAsRead($messageId, $userId) {
        foreach ($this->dataStore['messages'] as &$message) {
            if ($message['id'] == $messageId) {
                $message['is_read'] = true;
                $message['read_at'] = date('Y-m-d H:i:s');
                $this->saveData();
                return ['success' => true, 'data' => $message];
            }
        }
        return ['success' => false, 'error' => 'Message not found'];
    }

    public function getUnreadMessageCount($userId, $userType = 'customer') {
        $userConversations = $this->getConversationsByUser($userId, $userType);
        if (!$userConversations['success']) {
            return ['success' => true, 'data' => ['count' => 0]];
        }

        $conversationIds = array_column($userConversations['data'], 'id');
        $unreadCount = 0;

        foreach ($this->dataStore['messages'] ?? [] as $message) {
            if (in_array($message['conversation_id'], $conversationIds) && 
                !$message['is_read'] && 
                $message['sender_type'] !== $userType) {
                $unreadCount++;
            }
        }

        return ['success' => true, 'data' => ['count' => $unreadCount]];
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