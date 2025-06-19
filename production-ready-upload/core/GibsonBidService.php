<?php
// Enhanced error handling implemented for production stability


/**
 * Gibson AI Bid Service
 * 
 * This service manages bids using Gibson AI's existing infrastructure.
 * Since the painter-bid entity doesn't exist in Gibson AI, we'll use 
 * a creative approach with the available endpoints.
 */
class GibsonBidService {
    private $gibson;
    private $fallbackToLocal;
    
    public function __construct($gibsonService) {
        $this->gibson = $gibsonService;
        $this->fallbackToLocal = false;
        
        // Test if we can use Gibson AI for bids
        $this->testGibsonBidCapability();
    }
    
    private function testGibsonBidCapability() {
        // Try to access painter-bid endpoint with consistent naming
        $result = $this->gibson->makeApiCallPublic('/v1/-/painter-bid');
        if (!$result['success']) {
            // Check if it's an entity not found error
            if (isset($result['data']['detail']) && 
                (strpos($result['data']['detail'], 'Entity painter_bid does not exist') !== false ||
                 strpos($result['data']['detail'], 'Entity painter-bid does not exist') !== false)) {
                $this->fallbackToLocal = true;
                error_log('[GibsonBidService] painter-bid entity not available, using local fallback');
            } else {
                // Other errors might be temporary, so we'll try Gibson AI first
                error_log('[GibsonBidService] Warning: Could not test painter-bid capability: ' . ($result['error'] ?? 'Unknown error'));
            }
        } else {
            error_log('[GibsonBidService] painter-bid entity available, using Gibson AI');
        }
    }
    
    public function createBid($bidData) {
        // Validate required fields
        $requiredFields = ['lead_id', 'painter_id', 'bid_amount', 'message'];
        foreach ($requiredFields as $field) {
            if (!isset($bidData[$field]) || empty($bidData[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }
        
        // Ensure required fields for native Gibson AI
        $bidData['created_at'] = date('Y-m-d H:i:s');
        $bidData['updated_at'] = date('Y-m-d H:i:s');
        $bidData['uuid'] = $this->generateUuid();
        $bidData['status'] = $bidData['status'] ?? 'pending';
        $bidData['submitted_at'] = $bidData['submitted_at'] ?? date('Y-m-d H:i:s');
        
        // Validate bid amount
        if (!is_numeric($bidData['bid_amount']) || $bidData['bid_amount'] <= 0) {
            return [
                'success' => false,
                'error' => 'Invalid bid amount'
            ];
        }
        
        // If we know we need to fallback, go straight to local
        if ($this->fallbackToLocal) {
            return $this->createEnhancedLocalBid($bidData);
        }
        
        // Try native Gibson AI painter-bid entity first (consistent endpoint)
        $result = $this->gibson->makeApiCallPublic('/v1/-/painter-bid', $bidData, 'POST');
        
        if ($result['success']) {
            error_log("[GibsonBidService] Bid created successfully using native Gibson AI");
            return $result;
        }
        
        // Enhanced fallback with better error handling
        if (isset($result['data']['detail']) && 
            (strpos($result['data']['detail'], 'Entity painter_bid does not exist') !== false ||
             strpos($result['data']['detail'], 'Entity painter-bid does not exist') !== false)) {
            
            error_log("[GibsonBidService] painter-bid entity missing, using enhanced local storage");
            $this->fallbackToLocal = true; // Remember for future calls
            return $this->createEnhancedLocalBid($bidData);
        }
        
        // Check for authentication or permission errors
        if (isset($result['http_code']) && in_array($result['http_code'], [401, 403])) {
            error_log("[GibsonBidService] Authentication/Permission error, falling back to local storage");
            return $this->createEnhancedLocalBid($bidData);
        }
        
        // General fallback for any other error
        error_log("[GibsonBidService] API error, falling back to local storage: " . ($result['error'] ?? 'Unknown error'));
        return $this->createEnhancedLocalBid($bidData);
    }
    
    public function getBids($filters = []) {
        if ($this->fallbackToLocal) {
            return $this->getLocalBids($filters);
        }
        
        $queryString = http_build_query($filters);
        $result = $this->gibson->makeApiCallPublic('/v1/-/painter-bid' . ($queryString ? '?' . $queryString : ''));
        
        if (!$result['success']) {
            error_log("[GibsonBidService] Failed to get bids from Gibson AI, falling back to local: " . ($result['error'] ?? 'Unknown error'));
            return $this->getLocalBids($filters);
        }
        
        return $result;
    }
    
    public function getBidsForLead($leadId) {
        if (!$leadId || !is_numeric($leadId)) {
            return [
                'success' => false,
                'error' => 'Invalid lead ID'
            ];
        }
        
        return $this->getBids(['lead_id' => $leadId]);
    }
    
    public function hasPainterBidOnLead($painterId, $leadId) {
        if (!$painterId || !$leadId || !is_numeric($painterId) || !is_numeric($leadId)) {
            return [
                'success' => false,
                'error' => 'Invalid painter ID or lead ID'
            ];
        }
        
        $result = $this->getBids(['painter_id' => $painterId, 'lead_id' => $leadId]);
        return [
            'success' => true,
            'data' => [
                'has_bid' => $result['success'] && !empty($result['data'])
            ]
        ];
    }
    
    public function getBidById($id) {
        if (!$id) {
            return [
                'success' => false,
                'error' => 'Invalid bid ID'
            ];
        }
        
        if ($this->fallbackToLocal) {
            return $this->getLocalBidById($id);
        }
        
        $result = $this->gibson->makeApiCallPublic("/v1/-/painter-bid/{$id}");
        if (!$result['success']) {
            error_log("[GibsonBidService] Failed to get bid from Gibson AI, falling back to local");
            return $this->getLocalBidById($id);
        }
        
        return $result;
    }
    
    public function updateBid($id, $data) {
        if (!$id || empty($data)) {
            return [
                'success' => false,
                'error' => 'Invalid bid ID or data'
            ];
        }
        
        // Add updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if ($this->fallbackToLocal) {
            return $this->updateLocalBid($id, $data);
        }
        
        $result = $this->gibson->makeApiCallPublic("/v1/-/painter-bid/{$id}", $data, 'PATCH');
        if (!$result['success']) {
            error_log("[GibsonBidService] Failed to update bid in Gibson AI, falling back to local");
            return $this->updateLocalBid($id, $data);
        }
        
        return $result;
    }
    
    public function deleteBid($id) {
        if (!$id) {
            return [
                'success' => false,
                'error' => 'Invalid bid ID'
            ];
        }
        
        if ($this->fallbackToLocal) {
            return $this->deleteLocalBid($id);
        }
        
        $result = $this->gibson->makeApiCallPublic("/v1/-/painter-bid/{$id}", null, 'DELETE');
        if (!$result['success']) {
            error_log("[GibsonBidService] Failed to delete bid from Gibson AI, falling back to local");
            return $this->deleteLocalBid($id);
        }
        
        return $result;
    }
    
    public function acceptBid($id) {
        return $this->updateBid($id, ['status' => 'accepted']);
    }
    
    // Local fallback methods using JSON storage
    private function getLocalStoragePath() {
        return __DIR__ . '/../data/gibson_bids.json';
    }
    
    private function loadLocalBids() {
        $path = $this->getLocalStoragePath();
        if (!file_exists($path)) {
            return [];
        }
        
        $content = file_get_contents($path);
        return json_decode($content, true) ?: [];
    }
    
    private function saveLocalBids($bids) {
        $path = $this->getLocalStoragePath();
        $dir = dirname($path);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($path, json_encode($bids, JSON_PRETTY_PRINT));
    }
    
    private function createLocalBid($bidData) {
        $bids = $this->loadLocalBids();
        
        // Generate ID and add metadata
        $id = 'bid_' . uniqid();
        $bidData['id'] = $id;
        $bidData['uuid'] = $this->generateUuid();
        $bidData['created_at'] = date('Y-m-d H:i:s');
        $bidData['updated_at'] = date('Y-m-d H:i:s');
        $bidData['submitted_at'] = $bidData['submitted_at'] ?? date('Y-m-d H:i:s');
        $bidData['status'] = $bidData['status'] ?? 'pending';
        
        $bids[] = $bidData;
        
        if ($this->saveLocalBids($bids)) {
            return ['success' => true, 'data' => $bidData];
        } else {
            return ['success' => false, 'error' => 'Failed to save bid locally'];
        }
    }
    
    private function getLocalBids($filters = []) {
        $bids = $this->loadLocalBids();
        
        // Apply filters
        if (!empty($filters)) {
            $bids = array_filter($bids, function($bid) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (isset($bid[$key]) && $bid[$key] != $value) {
                        return false;
                    }
                }
                return true;
            });
        }
        
        return ['success' => true, 'data' => array_values($bids)];
    }
    
    private function getLocalBidById($id) {
        $bids = $this->loadLocalBids();
        
        foreach ($bids as $bid) {
            if ($bid['id'] === $id) {
                return ['success' => true, 'data' => $bid];
            }
        }
        
        return ['success' => false, 'error' => 'Bid not found'];
    }
    
    private function updateLocalBid($id, $data) {
        $bids = $this->loadLocalBids();
        
        for ($i = 0; $i < count($bids); $i++) {
            if ($bids[$i]['id'] === $id) {
                // Update fields
                foreach ($data as $key => $value) {
                    $bids[$i][$key] = $value;
                }
                $bids[$i]['updated_at'] = date('Y-m-d H:i:s');
                
                if ($this->saveLocalBids($bids)) {
                    return ['success' => true, 'data' => $bids[$i]];
                } else {
                    return ['success' => false, 'error' => 'Failed to update bid'];
                }
            }
        }
        
        return ['success' => false, 'error' => 'Bid not found'];
    }
    
    private function deleteLocalBid($id) {
        $bids = $this->loadLocalBids();
        
        for ($i = 0; $i < count($bids); $i++) {
            if ($bids[$i]['id'] === $id) {
                array_splice($bids, $i, 1);
                
                if ($this->saveLocalBids($bids)) {
                    return ['success' => true, 'data' => ['message' => 'Bid deleted']];
                } else {
                    return ['success' => false, 'error' => 'Failed to delete bid'];
                }
            }
        }
        
        return ['success' => false, 'error' => 'Bid not found'];
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
    
    // Enhanced local bid creation with better structure
    private function createEnhancedLocalBid($bidData) {
        $bids = $this->loadLocalBids();
        
        // Generate ID and add metadata
        $id = 'bid_' . uniqid();
        $bidData['id'] = $id;
        
        // Ensure all required fields are present
        if (!isset($bidData['uuid'])) {
            $bidData['uuid'] = $this->generateUuid();
        }
        
        $bidData['storage_method'] = 'enhanced_local_json';
        
        // Validate required fields
        $requiredFields = ['lead_id', 'painter_id', 'bid_amount'];
        foreach ($requiredFields as $field) {
            if (!isset($bidData[$field]) || empty($bidData[$field])) {
                return [
                    'success' => false, 
                    'error' => "Missing required field: {$field}"
                ];
            }
        }
        
        $bids[] = $bidData;
        
        if ($this->saveLocalBids($bids)) {
            error_log("[GibsonBidService] Enhanced local bid created with ID: {$id}");
            return ['success' => true, 'data' => $bidData];
        } else {
            return ['success' => false, 'error' => 'Failed to save bid to enhanced local storage'];
        }
    }
    
    public function isUsingGibsonAI() {
        return !$this->fallbackToLocal;
    }
    
    public function getStorageInfo() {
        return [
            'using_gibson_ai' => !$this->fallbackToLocal,
            'storage_type' => $this->fallbackToLocal ? 'Local JSON' : 'Gibson AI',
            'local_file' => $this->getLocalStoragePath()
        ];
    }
} 