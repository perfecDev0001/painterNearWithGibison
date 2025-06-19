<?php

class BidDatabaseService {
    private $connection;
    
    public function __construct() {
        $this->connection = $this->getConnection();
    }
    
    private function getConnection() {
        if ($this->connection !== null) {
            try {
                // Test existing connection
                $this->connection->query('SELECT 1');
                return $this->connection;
            } catch (PDOException $e) {
                // Connection is stale, create new one
                $this->connection = null;
            }
        }
        
        try {
            $config = require __DIR__ . '/../config/database.php';
            
            // Validate configuration
            if (empty($config['host']) || empty($config['database'])) {
                throw new PDOException('Invalid database configuration');
            }
            
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            
            // Connection options with better defaults
            $options = $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['collation']}"
            ];
            
            // Add timezone setting
            if (!empty($config['timezone'])) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] .= ", time_zone = '{$config['timezone']}'";
            }
            
            // Retry logic for connection
            $maxRetries = $config['pool']['retry_attempts'] ?? 3;
            $retryDelay = $config['pool']['retry_delay'] ?? 1;
            $lastException = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $this->connection = new PDO($dsn, $config['username'], $config['password'], $options);
                    
                    // Test the connection
                    $this->connection->query('SELECT 1');
                    
                    error_log("[BidDatabaseService] Database connected successfully (attempt {$attempt})");
                    return $this->connection;
                    
                } catch (PDOException $e) {
                    $lastException = $e;
                    error_log("[BidDatabaseService] Connection attempt {$attempt} failed: " . $e->getMessage());
                    
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                    }
                }
            }
            
            // All attempts failed
            throw $lastException;
            
        } catch (PDOException $e) {
            error_log("Database connection failed after all attempts: " . $e->getMessage());
            $this->connection = null;
            return null;
        }
    }
    
    public function isDatabaseAvailable() {
        return $this->connection !== null;
    }
    
    public function createBid($bidData) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            // Generate UUID for the bid
            $uuid = $this->generateUuid();
            
            $sql = "INSERT INTO painter_bid (
                uuid, lead_id, painter_id, bid_amount, message, timeline,
                materials_included, warranty_period, warranty_details, 
                project_approach, status, submitted_at
            ) VALUES (
                :uuid, :lead_id, :painter_id, :bid_amount, :message, :timeline,
                :materials_included, :warranty_period, :warranty_details,
                :project_approach, :status, :submitted_at
            )";
            
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute([
                ':uuid' => $uuid,
                ':lead_id' => $bidData['lead_id'],
                ':painter_id' => $bidData['painter_id'],
                ':bid_amount' => $bidData['bid_amount'],
                ':message' => $bidData['message'],
                ':timeline' => $bidData['timeline'] ?? '',
                ':materials_included' => $bidData['materials_included'] ?? false,
                ':warranty_period' => $bidData['warranty_period'] ?? 0,
                ':warranty_details' => $bidData['warranty_details'] ?? '',
                ':project_approach' => $bidData['project_approach'] ?? '',
                ':status' => $bidData['status'] ?? 'pending',
                ':submitted_at' => $bidData['submitted_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $bidId = $this->connection->lastInsertId();
                return [
                    'success' => true,
                    'data' => array_merge(['id' => $bidId, 'uuid' => $uuid], $bidData)
                ];
            } else {
                return ['success' => false, 'error' => 'Failed to create bid'];
            }
            
        } catch (PDOException $e) {
            error_log("Create bid error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getBids($filters = []) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $sql = "SELECT * FROM painter_bid WHERE 1=1";
            $params = [];
            
            if (isset($filters['painter_id'])) {
                $sql .= " AND painter_id = :painter_id";
                $params[':painter_id'] = $filters['painter_id'];
            }
            
            if (isset($filters['lead_id'])) {
                $sql .= " AND lead_id = :lead_id";
                $params[':lead_id'] = $filters['lead_id'];
            }
            
            if (isset($filters['status'])) {
                $sql .= " AND status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['limit'])) {
                $sql .= " LIMIT :limit";
                $params[':limit'] = (int)$filters['limit'];
            }
            
            $sql .= " ORDER BY submitted_at DESC";
            
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                if ($key === ':limit') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            $bids = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $bids];
            
        } catch (PDOException $e) {
            error_log("Get bids error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getBidById($id) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $sql = "SELECT * FROM painter_bid WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            $bid = $stmt->fetch();
            
            if ($bid) {
                return ['success' => true, 'data' => $bid];
            } else {
                return ['success' => false, 'error' => 'Bid not found'];
            }
            
        } catch (PDOException $e) {
            error_log("Get bid by ID error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function updateBid($id, $data) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = [
                'bid_amount', 'message', 'timeline', 'materials_included',
                'warranty_period', 'warranty_details', 'project_approach', 'status'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'error' => 'No valid fields to update'];
            }
            
            $sql = "UPDATE painter_bid SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result && $stmt->rowCount() > 0) {
                return $this->getBidById($id);
            } else {
                return ['success' => false, 'error' => 'Bid not found or no changes made'];
            }
            
        } catch (PDOException $e) {
            error_log("Update bid error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function deleteBid($id) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $sql = "DELETE FROM painter_bid WHERE id = :id";
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute([':id' => $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Bid deleted successfully'];
            } else {
                return ['success' => false, 'error' => 'Bid not found'];
            }
            
        } catch (PDOException $e) {
            error_log("Delete bid error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getBidsForLead($leadId) {
        return $this->getBids(['lead_id' => $leadId]);
    }
    
    public function hasPainterBidOnLead($painterId, $leadId) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $sql = "SELECT COUNT(*) as count FROM painter_bid WHERE painter_id = :painter_id AND lead_id = :lead_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                ':painter_id' => $painterId,
                ':lead_id' => $leadId
            ]);
            
            $result = $stmt->fetch();
            $hasBid = $result['count'] > 0;
            
            return [
                'success' => true,
                'data' => ['has_bid' => $hasBid, 'count' => (int)$result['count']]
            ];
            
        } catch (PDOException $e) {
            error_log("Check painter bid error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function acceptBid($id) {
        return $this->updateBid($id, ['status' => 'accepted']);
    }
    
    public function rejectBid($id) {
        return $this->updateBid($id, ['status' => 'rejected']);
    }
    
    public function withdrawBid($id) {
        return $this->updateBid($id, ['status' => 'withdrawn']);
    }
    
    public function getBidAnalytics($filters = []) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $sql = "SELECT * FROM painter_bid_analytics WHERE 1=1";
            $params = [];
            
            if (isset($filters['painter_id'])) {
                $sql .= " AND painter_id = :painter_id";
                $params[':painter_id'] = $filters['painter_id'];
            }
            
            if (isset($filters['lead_id'])) {
                $sql .= " AND lead_id = :lead_id";
                $params[':lead_id'] = $filters['lead_id'];
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $analytics = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $analytics];
            
        } catch (PDOException $e) {
            error_log("Get bid analytics error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getLeadBidSummary($leadId) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $sql = "SELECT * FROM lead_bid_summary WHERE lead_id = :lead_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':lead_id' => $leadId]);
            
            $summary = $stmt->fetch();
            
            return ['success' => true, 'data' => $summary ?: []];
            
        } catch (PDOException $e) {
            error_log("Get lead bid summary error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getPainterBidPerformance($painterId) {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            $sql = "SELECT * FROM painter_bid_performance WHERE painter_id = :painter_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':painter_id' => $painterId]);
            
            $performance = $stmt->fetch();
            
            return ['success' => true, 'data' => $performance ?: []];
            
        } catch (PDOException $e) {
            error_log("Get painter bid performance error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    private function generateUuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
    
    public function setupDatabase() {
        if (!$this->isDatabaseAvailable()) {
            return ['success' => false, 'error' => 'Database not available'];
        }
        
        try {
            // Read and execute the painter bid schema
            $schemaFile = __DIR__ . '/../database/painter_bid_system.sql';
            if (!file_exists($schemaFile)) {
                return ['success' => false, 'error' => 'Schema file not found'];
            }
            
            $sql = file_get_contents($schemaFile);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->connection->exec($statement);
                }
            }
            
            return ['success' => true, 'message' => 'Database schema created successfully'];
            
        } catch (PDOException $e) {
            error_log("Setup database error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database setup error: ' . $e->getMessage()];
        }
    }
} 