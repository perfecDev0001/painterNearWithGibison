<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/core/GibsonAIService.php';
require_once __DIR__ . '/core/GibsonDataAccess.php';

class WebhookHandler {
    private $service;
    private $dataAccess;
    private $secretKey;
    private $logFile;

    public function __construct() {
        $this->service = new GibsonAIService();
        $this->dataAccess = new GibsonDataAccess();
        $this->secretKey = defined('WEBHOOK_SECRET') ? WEBHOOK_SECRET : getenv('WEBHOOK_SECRET');
        $this->logFile = __DIR__ . '/logs/webhook.log';
        
        // Ensure logs directory exists
        $logsDir = dirname($this->logFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }

    /**
     * Verify the webhook signature
     */
    private function verifySignature($payload, $signature) {
        if (empty($this->secretKey)) {
            $this->log('Error: Webhook secret key not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Log webhook activities
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        // Ensure we can write to the log file
        if (is_writable(dirname($this->logFile))) {
            file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        } else {
            // Fallback to error log if we can't write to our log file
            error_log("Webhook: $message");
        }
    }

    /**
     * Process incoming webhook data
     */
    public function handleRequest() {
        // Set content type for JSON response
        header('Content-Type: application/json');
        
        // Get the raw POST data
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

        // For development/testing, allow requests without signature if no secret is set
        if (!empty($this->secretKey) && !$this->verifySignature($payload, $signature)) {
            $this->log('Error: Invalid webhook signature');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            return;
        }

        // Parse the JSON payload
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Error: Invalid JSON payload - ' . json_last_error_msg());
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload']);
            return;
        }

        // Process based on event type
        $eventType = $data['event_type'] ?? 'unknown';
        $this->log("Processing event: $eventType");

        try {
            switch ($eventType) {
                case 'new_lead':
                    $this->handleNewLead($data);
                    break;
                case 'lead_updated':
                    $this->handleLeadUpdate($data);
                    break;
                case 'new_bid':
                    $this->handleNewBid($data);
                    break;
                case 'bid_accepted':
                    $this->handleBidAccepted($data);
                    break;
                case 'payment_received':
                    $this->handlePayment($data);
                    break;
                case 'form_submission':
                    $this->handleFormSubmission($data);
                    break;
                case 'painter_registered':
                    $this->handlePainterRegistration($data);
                    break;
                case 'notification':
                    $this->handleNotification($data);
                    break;
                default:
                    $this->log("Unknown event type: $eventType");
                    http_response_code(400);
                    echo json_encode(['error' => 'Unknown event type']);
                    return;
            }

            // Success response
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'event_type' => $eventType,
                'processed_at' => date('c')
            ]);
            
        } catch (Exception $e) {
            $this->log("Error processing webhook: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * Handle new lead notifications
     */
    private function handleNewLead($data) {
        // Validate required fields
        if (empty($data['lead_id'])) {
            throw new Exception('Missing required lead_id');
        }

        $leadId = $data['lead_id'];
        
        // Update lead status through Gibson AI
        $updateResult = $this->dataAccess->updateLeadStatus($leadId, 'received');
        
        if (!$updateResult['success']) {
            throw new Exception('Failed to update lead status: ' . ($updateResult['error'] ?? 'Unknown error'));
        }
        
        $this->log("Processed new lead: $leadId");
        
        // Send notifications if specified
        if (isset($data['notify_admin']) && $data['notify_admin']) {
            $this->sendAdminNotification('new_lead', $data);
        }
    }

    /**
     * Handle lead updates
     */
    private function handleLeadUpdate($data) {
        if (empty($data['lead_id']) || empty($data['updates'])) {
            throw new Exception('Missing required lead update data');
        }

        $leadId = $data['lead_id'];
        $updates = $data['updates'];
        
        $updateResult = $this->dataAccess->updateLead($leadId, $updates);
        
        if (!$updateResult['success']) {
            throw new Exception('Failed to update lead: ' . ($updateResult['error'] ?? 'Unknown error'));
        }
        
        $this->log("Updated lead: $leadId");
    }

    /**
     * Handle new bid notifications
     */
    private function handleNewBid($data) {
        if (empty($data['bid_id']) || empty($data['lead_id'])) {
            throw new Exception('Missing required bid data');
        }

        $bidId = $data['bid_id'];
        $leadId = $data['lead_id'];
        
        // Get bid details
        $bidResult = $this->dataAccess->getBid($bidId);
        if (!$bidResult['success']) {
            throw new Exception('Failed to retrieve bid details');
        }
        
        $this->log("Processed new bid: $bidId for lead: $leadId");
        
        // Send notification to customer if specified
        if (isset($data['notify_customer']) && $data['notify_customer']) {
            $this->sendCustomerNotification('new_bid', $data);
        }
    }

    /**
     * Handle bid acceptance
     */
    private function handleBidAccepted($data) {
        if (empty($data['bid_id']) || empty($data['lead_id'])) {
            throw new Exception('Missing required bid acceptance data');
        }

        $bidId = $data['bid_id'];
        $leadId = $data['lead_id'];
        
        // Update bid status to accepted
        $updateResult = $this->dataAccess->updateBid($bidId, ['status' => 'accepted']);
        if (!$updateResult['success']) {
            throw new Exception('Failed to update bid status');
        }
        
        // Update lead status to assigned
        $leadUpdateResult = $this->dataAccess->updateLeadStatus($leadId, 'assigned');
        if (!$leadUpdateResult['success']) {
            throw new Exception('Failed to update lead status');
        }
        
        $this->log("Processed bid acceptance: $bidId for lead: $leadId");
    }

    /**
     * Handle payment notifications
     */
    private function handlePayment($data) {
        // Validate required fields
        if (empty($data['payment_id']) || empty($data['amount'])) {
            throw new Exception('Missing required payment data');
        }

        $paymentId = $data['payment_id'];
        $amount = $data['amount'];
        
        // In a real implementation, this would update payment records through Gibson AI
        // For now, we'll just log the payment
        $this->log("Processed payment: $paymentId, amount: $amount");
        
        // If there's a lead_id associated with this payment, update the lead
        if (!empty($data['lead_id'])) {
            $leadId = $data['lead_id'];
            $updateResult = $this->dataAccess->updateLeadStatus($leadId, 'paid');
            if ($updateResult['success']) {
                $this->log("Updated lead $leadId status to paid");
            }
        }
    }

    /**
     * Handle form submissions
     */
    private function handleFormSubmission($data) {
        // Validate required fields
        if (empty($data['form_id']) || empty($data['submission_data'])) {
            throw new Exception('Missing required form data');
        }

        $formId = $data['form_id'];
        $submissionData = $data['submission_data'];
        
        // Process different types of form submissions
        switch ($formId) {
            case 'contact_form':
                $this->processContactForm($submissionData);
                break;
            case 'quote_request':
                $this->processQuoteRequest($submissionData);
                break;
            case 'painter_application':
                $this->processPainterApplication($submissionData);
                break;
            default:
                $this->log("Unknown form type: $formId");
        }
        
        $this->log("Processed form submission for form: $formId");
    }

    /**
     * Handle painter registration
     */
    private function handlePainterRegistration($data) {
        if (empty($data['painter_id'])) {
            throw new Exception('Missing painter_id');
        }

        $painterId = $data['painter_id'];
        $this->log("Processed painter registration: $painterId");
        
        // Send welcome email or other onboarding actions
        if (isset($data['send_welcome']) && $data['send_welcome']) {
            $this->sendWelcomeEmail($painterId);
        }
    }

    /**
     * Handle general notifications
     */
    private function handleNotification($data) {
        if (empty($data['message'])) {
            throw new Exception('Missing notification message');
        }

        $message = $data['message'];
        $type = $data['type'] ?? 'info';
        
        $this->log("Notification ($type): $message");
    }

    /**
     * Process contact form submissions
     */
    private function processContactForm($data) {
        // Extract contact information and create a lead if appropriate
        if (isset($data['service_type']) && $data['service_type'] === 'painting') {
            $leadData = [
                'customer_name' => $data['name'] ?? 'Unknown',
                'customer_email' => $data['email'] ?? '',
                'customer_phone' => $data['phone'] ?? '',
                'job_title' => 'Contact Form Inquiry',
                'job_description' => $data['message'] ?? '',
                'location' => $data['location'] ?? 'Not specified',
                'status' => 'open'
            ];
            
            $result = $this->dataAccess->createLead($leadData);
            if ($result['success']) {
                $this->log("Created lead from contact form");
            }
        }
    }

    /**
     * Process quote request forms
     */
    private function processQuoteRequest($data) {
        $leadData = [
            'customer_name' => $data['customer_name'] ?? 'Unknown',
            'customer_email' => $data['customer_email'] ?? '',
            'customer_phone' => $data['customer_phone'] ?? '',
            'job_title' => $data['job_title'] ?? 'Quote Request',
            'job_description' => $data['job_description'] ?? '',
            'location' => $data['location'] ?? 'Not specified',
            'status' => 'open'
        ];
        
        $result = $this->dataAccess->createLead($leadData);
        if ($result['success']) {
            $this->log("Created lead from quote request");
        }
    }

    /**
     * Process painter application forms
     */
    private function processPainterApplication($data) {
        // This would typically create a pending painter profile
        $this->log("Received painter application from: " . ($data['email'] ?? 'unknown'));
    }

    /**
     * Send admin notifications
     */
    private function sendAdminNotification($type, $data) {
        // Define ADMIN_EMAIL constant if not already defined
        if (!defined('ADMIN_EMAIL')) {
            define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
        }
        
        require_once __DIR__ . '/core/Mailer.php';
        
        $adminEmail = ADMIN_EMAIL;
        $subject = "Painter Near Me - " . ucfirst(str_replace('_', ' ', $type));
        $message = "<h2>New " . ucfirst(str_replace('_', ' ', $type)) . " Event</h2>";
        $message .= "<p>A new $type event has occurred on Painter Near Me.</p>";
        $message .= "<h3>Event Data:</h3>";
        $message .= "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
        
        try {
            $mailer = new Core\Mailer();
            $result = $mailer->sendMail($adminEmail, $subject, $message, strip_tags($message));
            if ($result) {
                $this->log("Sent admin notification for: $type");
            } else {
                $this->log("Failed to send admin notification for: $type");
            }
        } catch (Exception $e) {
            $this->log("Error sending admin notification for $type: " . $e->getMessage());
        }
    }

    /**
     * Send customer notifications
     */
    private function sendCustomerNotification($type, $data) {
        // Implementation would depend on having customer email in the data
        $this->log("Would send customer notification for: $type");
    }

    /**
     * Send welcome email to new painters
     */
    private function sendWelcomeEmail($painterId) {
        $this->log("Would send welcome email to painter: $painterId");
    }
}

// Initialize and handle the webhook
try {
    $handler = new WebhookHandler();
    $handler->handleRequest();
} catch (Exception $e) {
    error_log("Webhook handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook handler initialization failed']);
} 