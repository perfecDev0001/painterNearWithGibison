<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'GibsonDataAccess.php';
require_once 'PaymentEmailNotificationService.php';

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Core\PaymentEmailNotificationService;

class StripePaymentManager {
    private $dataAccess;
    private $stripeSecretKey;
    private $webhookSecret;
    private $emailNotificationService;
    
    public function __construct() {
        $this->dataAccess = new GibsonDataAccess();
        $this->emailNotificationService = new PaymentEmailNotificationService();
        $this->initializeStripe();
    }
    
    private function initializeStripe() {
        $config = $this->getPaymentConfig();
        $this->stripeSecretKey = $config['stripe_secret_key'] ?? '';
        $this->webhookSecret = $config['stripe_webhook_secret'] ?? '';
        
        if (empty($this->stripeSecretKey)) {
            throw new Exception('Stripe secret key not configured');
        }
        
        Stripe::setApiKey($this->stripeSecretKey);
    }
    
    private function getPaymentConfig() {
        $config = [];
        $result = $this->dataAccess->query("SELECT config_key, config_value FROM payment_config");
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $config[$row['config_key']] = $row['config_value'];
            }
        }
        
        return $config;
    }
    
    /**
     * Create or retrieve Stripe customer for painter
     */
    public function createOrGetCustomer($painterId) {
        try {
            // Check if painter already has a Stripe customer
            $existing = $this->dataAccess->query(
                "SELECT stripe_customer_id FROM painter_payment_methods WHERE painter_id = ? LIMIT 1",
                [$painterId]
            );
            
            if ($existing && $existing->num_rows > 0) {
                $row = $existing->fetch_assoc();
                return $row['stripe_customer_id'];
            }
            
            // Get painter details
            $painter = $this->dataAccess->getPainterById($painterId);
            if (!$painter) {
                throw new Exception('Painter not found');
            }
            
            // Create Stripe customer
            $customer = Customer::create([
                'email' => $painter['email'],
                'name' => $painter['company_name'],
                'metadata' => [
                    'painter_id' => $painterId,
                    'company_name' => $painter['company_name']
                ]
            ]);
            
            return $customer->id;
            
        } catch (Exception $e) {
            error_log("Error creating Stripe customer: " . $e->getMessage());
            throw new Exception('Failed to create customer account');
        }
    }
    
    /**
     * Save payment method for painter
     */
    public function savePaymentMethod($painterId, $paymentMethodId, $isDefault = false) {
        try {
            $customerId = $this->createOrGetCustomer($painterId);
            
            // Retrieve payment method from Stripe
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            
            // Attach to customer if not already attached
            if (!$paymentMethod->customer) {
                $paymentMethod->attach(['customer' => $customerId]);
            }
            
            // If setting as default, unset other default methods
            if ($isDefault) {
                $this->dataAccess->query(
                    "UPDATE painter_payment_methods SET is_default = FALSE WHERE painter_id = ?",
                    [$painterId]
                );
            }
            
            // Extract card details for storage
            $cardBrand = $paymentMethod->card->brand ?? null;
            $cardLast4 = $paymentMethod->card->last4 ?? null;
            
            // Save to database
            $result = $this->dataAccess->query(
                "INSERT INTO painter_payment_methods 
                (painter_id, stripe_customer_id, stripe_payment_method_id, payment_method_type, card_brand, card_last4, is_default, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
                ON DUPLICATE KEY UPDATE 
                is_default = VALUES(is_default), 
                is_active = TRUE, 
                updated_at = CURRENT_TIMESTAMP",
                [$painterId, $customerId, $paymentMethodId, $paymentMethod->type, $cardBrand, $cardLast4, $isDefault]
            );
            
            // Send email notification
            $this->emailNotificationService->sendPaymentMethodAddedNotification(
                $painterId, 
                $cardBrand, 
                $cardLast4
            );
            
            return [
                'success' => true,
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId
            ];
            
        } catch (Exception $e) {
            error_log("Error saving payment method: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to save payment method'
            ];
        }
    }
    
    /**
     * Process payment for lead access
     */
    public function processLeadPayment($painterId, $leadId, $paymentMethodId = null) {
        try {
            // Validate painter and lead
            $painter = $this->dataAccess->getPainterById($painterId);
            $lead = $this->dataAccess->getLeadById($leadId);
            
            if (!$painter || !$lead) {
                throw new Exception('Invalid painter or lead');
            }
            
            // Check if lead is still accepting payments
            if (!$lead['is_payment_active']) {
                throw new Exception('This lead is no longer accepting payments');
            }
            
            // Check if painter already paid for this lead
            $existingAccess = $this->dataAccess->query(
                "SELECT id FROM lead_access WHERE lead_id = ? AND painter_id = ?",
                [$leadId, $painterId]
            );
            
            if ($existingAccess && $existingAccess->num_rows > 0) {
                throw new Exception('You have already paid for access to this lead');
            }
            
            // Get payment method
            if (!$paymentMethodId) {
                // Use default payment method
                $pmResult = $this->dataAccess->query(
                    "SELECT stripe_payment_method_id, stripe_customer_id FROM painter_payment_methods 
                     WHERE painter_id = ? AND is_default = TRUE AND is_active = TRUE LIMIT 1",
                    [$painterId]
                );
                
                if (!$pmResult || $pmResult->num_rows === 0) {
                    throw new Exception('No payment method found. Please add a payment method first.');
                }
                
                $pmRow = $pmResult->fetch_assoc();
                $paymentMethodId = $pmRow['stripe_payment_method_id'];
                $customerId = $pmRow['stripe_customer_id'];
            } else {
                // Validate specified payment method belongs to painter
                $pmResult = $this->dataAccess->query(
                    "SELECT stripe_customer_id FROM painter_payment_methods 
                     WHERE painter_id = ? AND stripe_payment_method_id = ? AND is_active = TRUE",
                    [$painterId, $paymentMethodId]
                );
                
                if (!$pmResult || $pmResult->num_rows === 0) {
                    throw new Exception('Invalid payment method');
                }
                
                $pmRow = $pmResult->fetch_assoc();
                $customerId = $pmRow['stripe_customer_id'];
            }
            
            // Calculate payment number and amount
            $currentPaymentCount = intval($lead['payment_count']);
            $paymentNumber = $currentPaymentCount + 1;
            $amount = floatval($lead['lead_price']);
            
            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => intval($amount * 100), // Convert to pence
                'currency' => 'gbp',
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => 'https://painter-near-me.co.uk/payment-success',
                'metadata' => [
                    'painter_id' => $painterId,
                    'lead_id' => $leadId,
                    'payment_number' => $paymentNumber,
                    'company_name' => $painter['company_name'],
                    'job_title' => $lead['job_title']
                ]
            ]);
            
            // Save payment record
            $paymentId = $this->savePaymentRecord($leadId, $painterId, $paymentIntent, $amount, $paymentNumber);
            
            if ($paymentIntent->status === 'succeeded') {
                // Payment successful, grant access
                $this->grantLeadAccess($leadId, $painterId, $paymentId);
                $this->updateLeadPaymentCount($leadId);
                
                // Send success email notification
                $this->emailNotificationService->sendPaymentSuccessNotification(
                    $painterId, $leadId, $paymentId, $amount
                );
                
                return [
                    'success' => true,
                    'payment_intent_id' => $paymentIntent->id,
                    'status' => 'succeeded',
                    'amount' => $amount,
                    'access_granted' => true
                ];
            } else {
                return [
                    'success' => false,
                    'payment_intent_id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'requires_action' => $paymentIntent->status === 'requires_action',
                    'client_secret' => $paymentIntent->client_secret
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error processing lead payment: " . $e->getMessage());
            
            // Send payment failed notification
            $this->emailNotificationService->sendPaymentFailedNotification(
                $painterId, $leadId, $amount, $e->getMessage()
            );
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function savePaymentRecord($leadId, $painterId, $paymentIntent, $amount, $paymentNumber) {
        $result = $this->dataAccess->query(
            "INSERT INTO lead_payments 
            (lead_id, painter_id, stripe_payment_intent_id, stripe_customer_id, amount, payment_status, payment_method_id, payment_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $leadId, 
                $painterId, 
                $paymentIntent->id, 
                $paymentIntent->customer,
                $amount,
                $paymentIntent->status,
                $paymentIntent->payment_method,
                $paymentNumber
            ]
        );
        
        return $this->dataAccess->getConnection()->insert_id;
    }
    
    private function grantLeadAccess($leadId, $painterId, $paymentId) {
        $this->dataAccess->query(
            "INSERT INTO lead_access (lead_id, painter_id, payment_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE accessed_at = CURRENT_TIMESTAMP",
            [$leadId, $painterId, $paymentId]
        );
    }
    
    private function updateLeadPaymentCount($leadId) {
        // Increment payment count
        $this->dataAccess->query(
            "UPDATE leads SET payment_count = payment_count + 1 WHERE id = ?",
            [$leadId]
        );
        
        // Check if lead should be deactivated
        $leadResult = $this->dataAccess->query(
            "SELECT payment_count, max_payments FROM leads WHERE id = ?",
            [$leadId]
        );
        
        if ($leadResult && $leadResult->num_rows > 0) {
            $lead = $leadResult->fetch_assoc();
            if ($lead['payment_count'] >= $lead['max_payments']) {
                $this->dataAccess->query(
                    "UPDATE leads SET is_payment_active = FALSE WHERE id = ?",
                    [$leadId]
                );
                
                // Send lead deactivation notification
                $this->emailNotificationService->sendLeadDeactivatedNotification($leadId);
                
                // Send admin notification
                $this->emailNotificationService->sendAdminPaymentNotification('lead_deactivated', [
                    'lead_id' => $leadId,
                    'payment_count' => $lead['payment_count'],
                    'max_payments' => $lead['max_payments']
                ]);
            }
        }
    }
    
    /**
     * Handle Stripe webhooks
     */
    public function handleWebhook($payload, $sigHeader) {
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
            
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
                    
                case 'payment_method.attached':
                    $this->handlePaymentMethodAttached($event->data->object);
                    break;
                    
                default:
                    error_log('Unhandled webhook event: ' . $event->type);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log('Webhook error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function handlePaymentSucceeded($paymentIntent) {
        $this->dataAccess->query(
            "UPDATE lead_payments SET payment_status = 'succeeded' WHERE stripe_payment_intent_id = ?",
            [$paymentIntent->id]
        );
        
        // Grant access if not already granted
        $paymentResult = $this->dataAccess->query(
            "SELECT id, lead_id, painter_id, amount FROM lead_payments WHERE stripe_payment_intent_id = ?",
            [$paymentIntent->id]
        );
        
        if ($paymentResult && $paymentResult->num_rows > 0) {
            $payment = $paymentResult->fetch_assoc();
            $this->grantLeadAccess($payment['lead_id'], $payment['painter_id'], $payment['id']);
            $this->updateLeadPaymentCount($payment['lead_id']);
            
            // Send success email notification
            $this->emailNotificationService->sendPaymentSuccessNotification(
                $payment['painter_id'], 
                $payment['lead_id'], 
                $payment['id'], 
                $payment['amount']
            );
        }
    }
    
    private function handlePaymentFailed($paymentIntent) {
        $this->dataAccess->query(
            "UPDATE lead_payments SET payment_status = 'failed' WHERE stripe_payment_intent_id = ?",
            [$paymentIntent->id]
        );
        
        // Get payment details for email notification
        $paymentResult = $this->dataAccess->query(
            "SELECT lead_id, painter_id, amount FROM lead_payments WHERE stripe_payment_intent_id = ?",
            [$paymentIntent->id]
        );
        
        if ($paymentResult && $paymentResult->num_rows > 0) {
            $payment = $paymentResult->fetch_assoc();
            
            // Send failure email notification
            $this->emailNotificationService->sendPaymentFailedNotification(
                $payment['painter_id'], 
                $payment['lead_id'], 
                $payment['amount'],
                'Payment processing failed'
            );
        }
    }
    
    /**
     * Get painter's payment methods
     */
    public function getPainterPaymentMethods($painterId) {
        $result = $this->dataAccess->query(
            "SELECT id, stripe_payment_method_id, payment_method_type, card_brand, card_last4, is_default, created_at 
             FROM painter_payment_methods 
             WHERE painter_id = ? AND is_active = TRUE 
             ORDER BY is_default DESC, created_at DESC",
            [$painterId]
        );
        
        $methods = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $methods[] = $row;
            }
        }
        
        return $methods;
    }
    
    /**
     * Remove payment method
     */
    public function removePaymentMethod($painterId, $paymentMethodId) {
        try {
            // Get payment method details for email notification
            $pmResult = $this->dataAccess->query(
                "SELECT card_brand, card_last4 FROM painter_payment_methods 
                 WHERE painter_id = ? AND stripe_payment_method_id = ?",
                [$painterId, $paymentMethodId]
            );
            $pmData = $pmResult ? $pmResult->fetch_assoc() : null;
            
            // Deactivate in database
            $result = $this->dataAccess->query(
                "UPDATE painter_payment_methods SET is_active = FALSE 
                 WHERE painter_id = ? AND stripe_payment_method_id = ?",
                [$painterId, $paymentMethodId]
            );
            
            // Detach from Stripe customer
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->detach();
            
            // Send email notification
            if ($pmData) {
                $this->emailNotificationService->sendPaymentMethodRemovedNotification(
                    $painterId, 
                    $pmData['card_brand'], 
                    $pmData['card_last4']
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Error removing payment method: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to remove payment method'];
        }
    }
    
    /**
     * Get payment analytics
     */
    public function getPaymentAnalytics($startDate = null, $endDate = null) {
        $whereClause = "";
        $params = [];
        
        if ($startDate && $endDate) {
            $whereClause = "WHERE lp.created_at BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        }
        
        $result = $this->dataAccess->query(
            "SELECT 
                COUNT(lp.id) as total_payments,
                SUM(CASE WHEN lp.payment_status = 'succeeded' THEN lp.amount ELSE 0 END) as total_revenue,
                COUNT(DISTINCT lp.painter_id) as unique_paying_painters,
                COUNT(DISTINCT lp.lead_id) as leads_with_payments,
                AVG(lp.amount) as average_payment_amount,
                COUNT(CASE WHEN lp.payment_status = 'succeeded' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN lp.payment_status = 'failed' THEN 1 END) as failed_payments
             FROM lead_payments lp
             $whereClause",
            $params
        );
        
        return $result ? $result->fetch_assoc() : [];
    }
    
    /**
     * Check if painter has access to lead
     */
    public function painterHasLeadAccess($painterId, $leadId) {
        $result = $this->dataAccess->query(
            "SELECT id FROM lead_access WHERE painter_id = ? AND lead_id = ?",
            [$painterId, $leadId]
        );
        
        return $result && $result->num_rows > 0;
    }
    
    /**
     * Get Stripe secret key for API calls
     */
    public function getStripeSecretKey() {
        return $this->stripeSecretKey;
    }
} 