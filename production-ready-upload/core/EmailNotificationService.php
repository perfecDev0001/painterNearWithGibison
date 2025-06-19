<?php
namespace Core;

require_once __DIR__ . '/Mailer.php';

class EmailNotificationService {
    private $mailer;
    private $adminEmail;
    
    public function __construct() {
        $this->mailer = new Mailer();
        
        if (!defined('ADMIN_EMAIL')) {
            define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
        }
        $this->adminEmail = ADMIN_EMAIL;
    }
    
    /**
     * Send customer notification when bid is received on their job
     */
    public function sendBidReceivedNotification($customerEmail, $customerName, $jobTitle, $painterName, $bidAmount, $leadId = null) {
        try {
            $subject = 'New Bid Received - ' . $jobTitle;
            $message = "<h2>New Bid Received for Your Job</h2>";
            $message .= "<p>Dear " . htmlspecialchars($customerName) . ",</p>";
            $message .= "<p>Great news! You've received a new bid for your painting job.</p>";
            $message .= "<h3>Job Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem;'>";
            $message .= "<tr><td style='font-weight:bold;'>Job Title:</td><td>" . htmlspecialchars($jobTitle) . "</td></tr>";
            $message .= "</table>";
            $message .= "<h3>Bid Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem;'>";
            $message .= "<tr><td style='font-weight:bold;'>Company:</td><td>" . htmlspecialchars($painterName) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Bid Amount:</td><td>£" . number_format($bidAmount, 2) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Received:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
            $message .= "</table>";
            
            // Add link to view all bids if leadId is provided
            if ($leadId) {
                $viewBidsUrl = "https://painter-near-me.co.uk/quote-bids.php?lead_id=" . urlencode($leadId) . "&email=" . urlencode($customerEmail);
                $message .= "<div style='text-align:center;margin:20px 0;'>";
                $message .= "<a href='" . $viewBidsUrl . "' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:600;display:inline-block;'>📊 View All Bids & Compare</a>";
                $message .= "</div>";
            }
            
            $message .= "<h3>What's Next?</h3>";
            $message .= "<p>We recommend:</p>";
            $message .= "<ul>";
            $message .= "<li>📋 <strong>View your bid dashboard</strong> to see all bids with competitive analysis</li>";
            $message .= "<li>📊 <strong>Compare pricing</strong> - see how this bid ranks against others</li>";
            $message .= "<li>📝 <strong>Review proposal details</strong> including timeline, materials, and warranty</li>";
            $message .= "<li>✅ <strong>Accept or reject bids</strong> directly from your dashboard</li>";
            $message .= "</ul>";
            
            $message .= "<h3>🧭 Decision Tips:</h3>";
            $message .= "<ul>";
            $message .= "<li>Don't just choose the lowest price - consider value and quality</li>";
            $message .= "<li>Check timeline compatibility with your schedule</li>";
            $message .= "<li>Look for comprehensive proposals with clear details</li>";
            $message .= "<li>Consider warranty periods and material inclusions</li>";
            $message .= "</ul>";
            
            $message .= "<p>More painters may submit bids, so check your dashboard regularly or wait for more notifications.</p>";
            $message .= "<p>Thank you for using Painter Near Me!</p>";
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            return $this->mailer->sendMail($customerEmail, $subject, $message, strip_tags($message), $customerName);
            
        } catch (Exception $e) {
            error_log("Failed to send bid received notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification when bid status changes (accepted/rejected)
     */
    public function sendBidStatusUpdate($painterEmail, $painterName, $jobTitle, $status, $customerMessage = '') {
        try {
            $statusText = ucfirst($status);
            $subject = 'Bid ' . $statusText . ' - ' . $jobTitle;
            $message = "<h2>Bid Status Update: " . $statusText . "</h2>";
            $message .= "<p>Dear " . htmlspecialchars($painterName) . ",</p>";
            
            if ($status === 'accepted') {
                $message .= "<p>Congratulations! Your bid has been accepted for the following job:</p>";
            } else {
                $message .= "<p>Thank you for your bid. Unfortunately, your bid was not selected for the following job:</p>";
            }
            
            $message .= "<h3>Job Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem;'>";
            $message .= "<tr><td style='font-weight:bold;'>Job Title:</td><td>" . htmlspecialchars($jobTitle) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Status:</td><td>" . $statusText . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Updated:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
            $message .= "</table>";
            
            if ($customerMessage) {
                $message .= "<h3>Message from Customer:</h3>";
                $message .= "<p style='background:#f5f5f5;padding:10px;border-radius:5px;'>" . nl2br(htmlspecialchars($customerMessage)) . "</p>";
            }
            
            if ($status === 'accepted') {
                $message .= "<h3>Next Steps:</h3>";
                $message .= "<p>Please contact the customer to arrange the work details and schedule.</p>";
            } else {
                $message .= "<h3>Keep Bidding!</h3>";
                $message .= "<p>Don't be discouraged - there are always new opportunities available.</p>";
                $message .= "<p><a href='https://painter-near-me.co.uk/leads.php' style='background:#00b050;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>View New Leads</a></p>";
            }
            
            $message .= "<p>Thank you for using Painter Near Me!</p>";
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            return $this->mailer->sendMail($painterEmail, $subject, $message, strip_tags($message), $painterName);
            
        } catch (Exception $e) {
            error_log("Failed to send bid status update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send admin notification for password changes
     */
    public function sendAdminPasswordChangeNotification($adminUser, $changedBy) {
        try {
            $subject = 'Admin Password Changed - Security Alert';
            $message = "<h2>Admin Password Changed</h2>";
            $message .= "<p>This is a security notification that an admin password has been changed.</p>";
            $message .= "<h3>Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem;'>";
            $message .= "<tr><td style='font-weight:bold;'>Admin User:</td><td>" . htmlspecialchars($adminUser) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Changed By:</td><td>" . htmlspecialchars($changedBy) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Change Time:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>IP Address:</td><td>" . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</td></tr>";
            $message .= "</table>";
            $message .= "<p><strong>If this change was not authorized, please investigate immediately.</strong></p>";
            $message .= "<p>Security Team,<br>Painter Near Me</p>";
            
            return $this->mailer->sendMail($this->adminEmail, $subject, $message, strip_tags($message));
            
        } catch (Exception $e) {
            error_log("Failed to send admin password change notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send system alert notification
     */
    public function sendSystemAlert($alertType, $details, $severity = 'medium') {
        try {
            $subject = 'System Alert: ' . ucfirst($alertType) . ' [' . strtoupper($severity) . ']';
            $message = "<h2>System Alert: " . ucfirst($alertType) . "</h2>";
            $message .= "<p>A system alert has been triggered on Painter Near Me.</p>";
            $message .= "<h3>Alert Details:</h3>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem;'>";
            $message .= "<tr><td style='font-weight:bold;'>Type:</td><td>" . htmlspecialchars($alertType) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Severity:</td><td>" . strtoupper($severity) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Time:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
            $message .= "</table>";
            $message .= "<h3>Details:</h3>";
            $message .= "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;'>" . htmlspecialchars(print_r($details, true)) . "</pre>";
            $message .= "<p>Please investigate this alert as soon as possible.</p>";
            $message .= "<p>System Monitoring,<br>Painter Near Me</p>";
            
            return $this->mailer->sendMail($this->adminEmail, $subject, $message, strip_tags($message));
            
        } catch (Exception $e) {
            error_log("Failed to send system alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats() {
        // In a production environment, this would query a database for email statistics
        return [
            'total_sent' => 0,
            'total_failed' => 0,
            'recent_activity' => []
        ];
    }
} 