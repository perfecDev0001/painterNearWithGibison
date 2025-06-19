<?php
namespace Core;

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/GibsonDataAccess.php';

class PaymentEmailNotificationService {
    private $mailer;
    private $dataAccess;
    private $adminEmail;
    
    public function __construct() {
        $this->mailer = new Mailer();
        $this->dataAccess = new \GibsonDataAccess();
        
        if (!defined('ADMIN_EMAIL')) {
            define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
        }
        $this->adminEmail = ADMIN_EMAIL;
    }
    
    /**
     * Check if email notifications are enabled
     */
    private function areEmailNotificationsEnabled() {
        $result = $this->dataAccess->query(
            "SELECT config_value FROM payment_config WHERE config_key = 'email_notifications_enabled'"
        );
        
        if ($result && $result->num_rows > 0) {
            $config = $result->fetch_assoc();
            return $config['config_value'] === 'true';
        }
        
        return true; // Default to enabled if not configured
    }
    
    /**
     * Send notification when payment is successful
     */
    public function sendPaymentSuccessNotification($painterId, $leadId, $paymentId, $amount) {
        if (!$this->areEmailNotificationsEnabled()) {
            return true; // Skip sending but return success
        }
        
        try {
            $painter = $this->dataAccess->getPainterById($painterId);
            $lead = $this->dataAccess->getLeadById($leadId);
            
            if (!$painter || !$lead) {
                throw new Exception('Painter or lead not found');
            }
            
            $subject = 'Payment Successful - Lead Access Granted';
            $message = "<h2>Payment Successful!</h2>";
            $message .= "<p>Dear " . htmlspecialchars($painter['company_name']) . ",</p>";
            $message .= "<p>Your payment has been processed successfully and you now have access to the lead details.</p>";
            
            $message .= "<h3>Payment Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Amount Paid:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>£" . number_format($amount, 2) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Payment ID:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>#" . $paymentId . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Payment Date:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('Y-m-d H:i:s') . "</td></tr>";
            $message .= "</table>";
            
            $message .= "<h3>Lead Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Job Title:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($lead['job_title']) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Location:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($lead['location']) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Customer:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($lead['customer_name']) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Contact Email:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($lead['customer_email']) . "</td></tr>";
            $message .= "</table>";
            
            $message .= "<h3>Next Steps:</h3>";
            $message .= "<ul>";
            $message .= "<li>Review the complete job description and requirements</li>";
            $message .= "<li>Contact the customer directly to discuss the project</li>";
            $message .= "<li>Submit a competitive bid if you're interested</li>";
            $message .= "<li>Follow up promptly to secure the work</li>";
            $message .= "</ul>";
            
            $message .= "<p><a href='https://painter-near-me.co.uk/leads.php' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:5px; display: inline-block; margin: 10px 0;'>View Lead Details</a></p>";
            
            $message .= "<p>Thank you for using Painter Near Me!</p>";
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            return $this->mailer->sendMail(
                $painter['email'], 
                $subject, 
                $message, 
                strip_tags($message), 
                $painter['contact_name']
            );
            
        } catch (Exception $e) {
            error_log("Failed to send payment success notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification when payment fails
     */
    public function sendPaymentFailedNotification($painterId, $leadId, $amount, $errorMessage = '') {
        if (!$this->areEmailNotificationsEnabled()) {
            return true; // Skip sending but return success
        }
        
        try {
            $painter = $this->dataAccess->getPainterById($painterId);
            $lead = $this->dataAccess->getLeadById($leadId);
            
            if (!$painter || !$lead) {
                throw new Exception('Painter or lead not found');
            }
            
            $subject = 'Payment Failed - Lead Access Not Granted';
            $message = "<h2>Payment Failed</h2>";
            $message .= "<p>Dear " . htmlspecialchars($painter['company_name']) . ",</p>";
            $message .= "<p>Unfortunately, your payment could not be processed and access to the lead has not been granted.</p>";
            
            $message .= "<h3>Payment Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Amount:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>£" . number_format($amount, 2) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Lead:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($lead['job_title']) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Attempted:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('Y-m-d H:i:s') . "</td></tr>";
            if ($errorMessage) {
                $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Error:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($errorMessage) . "</td></tr>";
            }
            $message .= "</table>";
            
            $message .= "<h3>What to do next:</h3>";
            $message .= "<ul>";
            $message .= "<li>Check your payment method details are correct</li>";
            $message .= "<li>Ensure you have sufficient funds available</li>";
            $message .= "<li>Try the payment again or use a different payment method</li>";
            $message .= "<li>Contact us if the problem persists</li>";
            $message .= "</ul>";
            
            $message .= "<p><a href='https://painter-near-me.co.uk/leads.php' style='background:#007bff;color:white;padding:12px 24px;text-decoration:none;border-radius:5px; display: inline-block; margin: 10px 0;'>Try Again</a></p>";
            
            $message .= "<p>If you continue to experience issues, please contact our support team.</p>";
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            return $this->mailer->sendMail(
                $painter['email'], 
                $subject, 
                $message, 
                strip_tags($message), 
                $painter['contact_name']
            );
            
        } catch (Exception $e) {
            error_log("Failed to send payment failed notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification when payment method is added
     */
    public function sendPaymentMethodAddedNotification($painterId, $cardBrand, $cardLast4) {
        if (!$this->areEmailNotificationsEnabled()) {
            return true; // Skip sending but return success
        }
        
        try {
            $painter = $this->dataAccess->getPainterById($painterId);
            
            if (!$painter) {
                throw new Exception('Painter not found');
            }
            
            $subject = 'Payment Method Added Successfully';
            $message = "<h2>Payment Method Added</h2>";
            $message .= "<p>Dear " . htmlspecialchars($painter['company_name']) . ",</p>";
            $message .= "<p>A new payment method has been successfully added to your account.</p>";
            
            $message .= "<h3>Payment Method Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Card Type:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . strtoupper($cardBrand) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Card Number:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>**** **** **** " . $cardLast4 . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Added:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('Y-m-d H:i:s') . "</td></tr>";
            $message .= "</table>";
            
            $message .= "<h3>Security Notice:</h3>";
            $message .= "<p>If you did not add this payment method, please contact us immediately to secure your account.</p>";
            
            $message .= "<p>You can now use this payment method to purchase lead access.</p>";
            
            $message .= "<p><a href='https://painter-near-me.co.uk/profile.php?tab=payment' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:5px; display: inline-block; margin: 10px 0;'>Manage Payment Methods</a></p>";
            
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            return $this->mailer->sendMail(
                $painter['email'], 
                $subject, 
                $message, 
                strip_tags($message), 
                $painter['contact_name']
            );
            
        } catch (Exception $e) {
            error_log("Failed to send payment method added notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification when payment method is removed
     */
    public function sendPaymentMethodRemovedNotification($painterId, $cardBrand, $cardLast4) {
        if (!$this->areEmailNotificationsEnabled()) {
            return true; // Skip sending but return success
        }
        
        try {
            $painter = $this->dataAccess->getPainterById($painterId);
            
            if (!$painter) {
                throw new Exception('Painter not found');
            }
            
            $subject = 'Payment Method Removed';
            $message = "<h2>Payment Method Removed</h2>";
            $message .= "<p>Dear " . htmlspecialchars($painter['company_name']) . ",</p>";
            $message .= "<p>A payment method has been removed from your account.</p>";
            
            $message .= "<h3>Removed Payment Method:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Card Type:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . strtoupper($cardBrand) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Card Number:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>**** **** **** " . $cardLast4 . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Removed:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('Y-m-d H:i:s') . "</td></tr>";
            $message .= "</table>";
            
            $message .= "<h3>Security Notice:</h3>";
            $message .= "<p>If you did not remove this payment method, please contact us immediately to secure your account.</p>";
            
            $message .= "<p>To continue purchasing lead access, please ensure you have at least one active payment method on your account.</p>";
            
            $message .= "<p><a href='https://painter-near-me.co.uk/profile.php?tab=payment' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:5px; display: inline-block; margin: 10px 0;'>Manage Payment Methods</a></p>";
            
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            return $this->mailer->sendMail(
                $painter['email'], 
                $subject, 
                $message, 
                strip_tags($message), 
                $painter['contact_name']
            );
            
        } catch (Exception $e) {
            error_log("Failed to send payment method removed notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification when lead is deactivated after reaching max payments
     */
    public function sendLeadDeactivatedNotification($leadId) {
        if (!$this->areEmailNotificationsEnabled()) {
            return true; // Skip sending but return success
        }
        
        try {
            $lead = $this->dataAccess->getLeadById($leadId);
            
            if (!$lead) {
                throw new Exception('Lead not found');
            }
            
            // Notify customer that their lead has been deactivated
            $subject = 'Lead Deactivated - Maximum Payments Reached';
            $message = "<h2>Lead Deactivated</h2>";
            $message .= "<p>Dear " . htmlspecialchars($lead['customer_name']) . ",</p>";
            $message .= "<p>Your job posting has been deactivated as it has reached the maximum number of painter payments.</p>";
            
            $message .= "<h3>Job Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Job Title:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($lead['job_title']) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Location:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($lead['location']) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Posted:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('Y-m-d', strtotime($lead['created_at'])) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Painters Interested:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . $lead['payment_count'] . "</td></tr>";
            $message .= "</table>";
            
            $message .= "<h3>What this means:</h3>";
            $message .= "<ul>";
            $message .= "<li>Your job posting is no longer visible to new painters</li>";
            $message .= "<li>Painters who have already paid for access can still contact you</li>";
            $message .= "<li>You should have received multiple enquiries by now</li>";
            $message .= "</ul>";
            
            $message .= "<h3>Next Steps:</h3>";
            $message .= "<ul>";
            $message .= "<li>Review the bids and proposals you've received</li>";
            $message .= "<li>Contact painters who interest you</li>";
            $message .= "<li>Choose the best painter for your project</li>";
            $message .= "<li>If you need more options, consider posting a new job</li>";
            $message .= "</ul>";
            
            $message .= "<p><a href='https://painter-near-me.co.uk/post-job.php' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:5px; display: inline-block; margin: 10px 0;'>Post New Job</a></p>";
            
            $message .= "<p>Thank you for using Painter Near Me!</p>";
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            return $this->mailer->sendMail(
                $lead['customer_email'], 
                $subject, 
                $message, 
                strip_tags($message), 
                $lead['customer_name']
            );
            
        } catch (Exception $e) {
            error_log("Failed to send lead deactivated notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send admin notification for payment events
     */
    public function sendAdminPaymentNotification($type, $details) {
        try {
            $subject = 'Payment System Alert: ' . ucfirst($type);
            $message = "<h2>Payment System Notification</h2>";
            $message .= "<p>A payment system event has occurred that requires attention.</p>";
            
            $message .= "<h3>Event Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Event Type:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($type) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>Timestamp:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('Y-m-d H:i:s') . "</td></tr>";
            
            foreach ($details as $key => $value) {
                $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) . ":</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($value) . "</td></tr>";
            }
            
            $message .= "</table>";
            
            $message .= "<p><a href='https://painter-near-me.co.uk/admin-payment-management.php' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:5px; display: inline-block; margin: 10px 0;'>View Payment Dashboard</a></p>";
            
            $message .= "<p>Please review this event in the admin panel.</p>";
            $message .= "<p>Payment System,<br>Painter Near Me</p>";
            
            return $this->mailer->sendMail(
                $this->adminEmail, 
                $subject, 
                $message, 
                strip_tags($message)
            );
            
        } catch (Exception $e) {
            error_log("Failed to send admin payment notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send daily payment summary to admin
     */
    public function sendDailyPaymentSummary() {
        try {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            // Get today's payment statistics
            $result = $this->dataAccess->query(
                "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN payment_status = 'succeeded' THEN amount ELSE 0 END) as total_revenue,
                    COUNT(CASE WHEN payment_status = 'succeeded' THEN 1 END) as successful_payments,
                    COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_payments,
                    COUNT(DISTINCT painter_id) as unique_painters
                 FROM lead_payments 
                 WHERE DATE(created_at) = ?",
                [$today]
            );
            
            $stats = $result ? $result->fetch_assoc() : [];
            
            $subject = 'Daily Payment Summary - ' . date('Y-m-d');
            $message = "<h2>Daily Payment Summary</h2>";
            $message .= "<p>Here's your daily payment summary for " . date('F j, Y') . ":</p>";
            
            $message .= "<h3>Today's Statistics:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse; width: 100%;'>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee; background: #f8f9fa;'>Total Payments:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . ($stats['total_payments'] ?? 0) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee; background: #f8f9fa;'>Successful Payments:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . ($stats['successful_payments'] ?? 0) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee; background: #f8f9fa;'>Failed Payments:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . ($stats['failed_payments'] ?? 0) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee; background: #f8f9fa;'>Total Revenue:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>£" . number_format($stats['total_revenue'] ?? 0, 2) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold; padding: 8px; border-bottom: 1px solid #eee; background: #f8f9fa;'>Unique Painters:</td><td style='padding: 8px; border-bottom: 1px solid #eee;'>" . ($stats['unique_painters'] ?? 0) . "</td></tr>";
            $message .= "</table>";
            
            $successRate = ($stats['total_payments'] ?? 0) > 0 ? 
                round((($stats['successful_payments'] ?? 0) / ($stats['total_payments'] ?? 1)) * 100, 1) : 0;
            
            $message .= "<h3>Performance:</h3>";
            $message .= "<p>Success Rate: <strong>" . $successRate . "%</strong></p>";
            
            $message .= "<p><a href='https://painter-near-me.co.uk/admin-payment-management.php' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:5px; display: inline-block; margin: 10px 0;'>View Full Payment Dashboard</a></p>";
            
            $message .= "<p>Daily Report,<br>Painter Near Me Payment System</p>";
            
            return $this->mailer->sendMail(
                $this->adminEmail, 
                $subject, 
                $message, 
                strip_tags($message)
            );
            
        } catch (Exception $e) {
            error_log("Failed to send daily payment summary: " . $e->getMessage());
            return false;
        }
    }
} 