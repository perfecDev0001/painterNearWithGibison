<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';
require_once 'core/StripePaymentManager.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Admin session check
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_stripe_config'])) {
        $publishableKey = $_POST['stripe_publishable_key'] ?? '';
        $secretKey = $_POST['stripe_secret_key'] ?? '';
        $webhookSecret = $_POST['stripe_webhook_secret'] ?? '';
        
        // Update Stripe configuration
        $configs = [
            'stripe_publishable_key' => $publishableKey,
            'stripe_secret_key' => $secretKey,
            'stripe_webhook_secret' => $webhookSecret
        ];
        
        $success = true;
        foreach ($configs as $key => $value) {
            try {
                $result = $dataAccess->query(
                    "INSERT INTO payment_config (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = ?",
                    [$key, $value, $value]
                );
                if (!$result) $success = false;
            } catch (Exception $e) {
                error_log("Failed to update config $key: " . $e->getMessage());
                $success = false;
            }
        }
        
        $message = $success ? 'Stripe configuration updated successfully.' : 'Failed to update configuration.';
        $messageType = $success ? 'success' : 'error';
    }
    
    if (isset($_POST['update_payment_settings'])) {
        $leadPrice = floatval($_POST['default_lead_price']);
        $maxPayments = intval($_POST['max_payments_per_lead']);
        $paymentEnabled = isset($_POST['payment_enabled']) ? 'true' : 'false';
        $autoDeactivate = isset($_POST['auto_deactivate_leads']) ? 'true' : 'false';
        
        $emailNotifications = isset($_POST['email_notifications_enabled']) ? 'true' : 'false';
        $dailySummary = isset($_POST['daily_summary_enabled']) ? 'true' : 'false';
        
        $settings = [
            'default_lead_price' => $leadPrice,
            'max_payments_per_lead' => $maxPayments,
            'payment_enabled' => $paymentEnabled,
            'auto_deactivate_leads' => $autoDeactivate,
            'email_notifications_enabled' => $emailNotifications,
            'daily_summary_enabled' => $dailySummary
        ];
        
        $success = true;
        foreach ($settings as $key => $value) {
            try {
                $result = $dataAccess->query(
                    "INSERT INTO payment_config (config_key, config_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE config_value = ?",
                    [$key, $value, $value]
                );
                if (!$result) $success = false;
            } catch (Exception $e) {
                error_log("Failed to update setting $key: " . $e->getMessage());
                $success = false;
            }
        }
        
        $message = $success ? 'Payment settings updated successfully.' : 'Failed to update settings.';
        $messageType = $success ? 'success' : 'error';
    }
    
    if (isset($_POST['test_email_notifications'])) {
        require_once 'core/PaymentEmailNotificationService.php';
        
        try {
            $emailService = new Core\PaymentEmailNotificationService();
            
            // Send test admin notification
            $testResult = $emailService->sendAdminPaymentNotification('test_notification', [
                'test_type' => 'Email system test',
                'triggered_by' => 'Admin panel',
                'system_status' => 'All systems operational'
            ]);
            
            if ($testResult) {
                $message = 'Test email notification sent successfully to admin email.';
                $messageType = 'success';
            } else {
                $message = 'Failed to send test email notification. Check email configuration.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Email test failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current configuration
function getPaymentConfig($dataAccess) {
    $config = [];
    
    try {
        $result = $dataAccess->query("SELECT config_key, config_value FROM payment_config");
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $config[$row['config_key']] = $row['config_value'];
            }
        } else {
            // No results or query failed, use defaults
            $config = [
                'stripe_publishable_key' => '',
                'stripe_secret_key' => '',
                'stripe_webhook_secret' => '',
                'default_lead_price' => '15.00',
                'max_payments_per_lead' => '3',
                'payment_enabled' => 'true',
                'auto_deactivate_leads' => 'true',
                'email_notifications_enabled' => 'true',
                'daily_summary_enabled' => 'true'
            ];
        }
    } catch (Exception $e) {
        error_log("Failed to get payment config: " . $e->getMessage());
        // Return default configuration if database is not available
        $config = [
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',
            'default_lead_price' => '15.00',
            'max_payments_per_lead' => '3',
            'payment_enabled' => 'true',
            'auto_deactivate_leads' => 'true',
            'email_notifications_enabled' => 'true',
            'daily_summary_enabled' => 'true'
        ];
    }
    
    return $config;
}

// Get payment analytics
function getPaymentAnalytics($dataAccess) {
    try {
        // Overall stats
        $statsResult = $dataAccess->query("
            SELECT 
                COUNT(id) as total_payments,
                SUM(CASE WHEN payment_status = 'succeeded' THEN amount ELSE 0 END) as total_revenue,
                COUNT(DISTINCT painter_id) as unique_paying_painters,
                COUNT(DISTINCT lead_id) as leads_purchased,
                AVG(amount) as average_payment,
                COUNT(CASE WHEN payment_status = 'succeeded' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_payments
            FROM lead_payments
        ");
        
        $stats = $statsResult ? $statsResult->fetch_assoc() : [];
    } catch (Exception $e) {
        error_log("Failed to get payment analytics: " . $e->getMessage());
        $stats = [
            'total_payments' => 0,
            'total_revenue' => 0,
            'unique_paying_painters' => 0,
            'leads_purchased' => 0,
            'average_payment' => 0,
            'successful_payments' => 0,
            'failed_payments' => 0
        ];
    }
    
    // Recent payments
    try {
        $recentResult = $dataAccess->query("
            SELECT lp.*, p.company_name, l.job_title, l.location
            FROM lead_payments lp
            JOIN painters p ON lp.painter_id = p.id
            JOIN leads l ON lp.lead_id = l.id
            ORDER BY lp.created_at DESC
            LIMIT 10
        ");
        
        $recentPayments = [];
        if ($recentResult && $recentResult->num_rows > 0) {
            while ($row = $recentResult->fetch_assoc()) {
                $recentPayments[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get recent payments: " . $e->getMessage());
        $recentPayments = [];
    }
    
    // Top paying painters
    try {
        $topPaintersResult = $dataAccess->query("
            SELECT p.company_name, p.email,
                   COUNT(lp.id) as payment_count,
                   SUM(CASE WHEN lp.payment_status = 'succeeded' THEN lp.amount ELSE 0 END) as total_spent
            FROM painters p
            JOIN lead_payments lp ON p.id = lp.painter_id
            WHERE lp.payment_status = 'succeeded'
            GROUP BY p.id
            ORDER BY total_spent DESC
            LIMIT 10
        ");
        
        $topPainters = [];
        if ($topPaintersResult && $topPaintersResult->num_rows > 0) {
            while ($row = $topPaintersResult->fetch_assoc()) {
                $topPainters[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get top painters: " . $e->getMessage());
        $topPainters = [];
    }
    
    // Lead deactivation stats
    try {
        $deactivatedResult = $dataAccess->query("
            SELECT COUNT(*) as deactivated_leads 
            FROM leads 
            WHERE is_payment_active = FALSE AND payment_count >= max_payments
        ");
        
        $deactivatedLeads = $deactivatedResult ? $deactivatedResult->fetch_assoc()['deactivated_leads'] : 0;
    } catch (Exception $e) {
        error_log("Failed to get deactivated leads: " . $e->getMessage());
        $deactivatedLeads = 0;
    }
    
    return [
        'stats' => $stats,
        'recent_payments' => $recentPayments,
        'top_painters' => $topPainters,
        'deactivated_leads' => $deactivatedLeads
    ];
}

$config = getPaymentConfig($dataAccess);
$analytics = getPaymentAnalytics($dataAccess);

include 'templates/header.php';
?>
<head>
    <title>Payment Management | Admin Dashboard | Painter Near Me</title>
    <meta name="description" content="Payment system management and Stripe configuration for Painter Near Me marketplace." />
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
</head>

<div class="admin-layout">
    <?php include 'templates/sidebar-admin.php'; ?>
    <main class="admin-main" role="main">
        <section class="admin-card">
            <div class="admin-header">
                <div class="admin-header__content">
                    <h1 class="hero__title">Payment Management</h1>
                    <p class="hero__subtitle">Stripe integration, payment analytics, and lead payment system</p>
                </div>
                <div class="admin-header__actions">
                    <a href="admin-financial.php" class="btn btn--outline">
                        <i class="bi bi-graph-up"></i> Financial Reports
                    </a>
                    <a href="admin-leads.php" class="btn btn--primary">
                        <i class="bi bi-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </section>

        <!-- Status Messages -->
        <?php if ($message): ?>
        <section class="admin-card">
            <div class="payment-message payment-message--<?php echo $messageType; ?>">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Payment Analytics Overview -->
        <section class="admin-card">
            <h2 class="payment-title">Payment Analytics Overview</h2>
            <div class="payment-stats">
                <div class="payment-stat">
                    <div class="payment-stat__icon">
                        <i class="bi bi-currency-pound"></i>
                    </div>
                    <div class="payment-stat__content">
                        <div class="payment-stat__value">£<?php echo number_format($analytics['stats']['total_revenue'] ?? 0, 2); ?></div>
                        <div class="payment-stat__label">Total Revenue</div>
                    </div>
                </div>
                
                <div class="payment-stat">
                    <div class="payment-stat__icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="payment-stat__content">
                        <div class="payment-stat__value"><?php echo number_format($analytics['stats']['total_payments'] ?? 0); ?></div>
                        <div class="payment-stat__label">Total Payments</div>
                    </div>
                </div>
                
                <div class="payment-stat">
                    <div class="payment-stat__icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="payment-stat__content">
                        <div class="payment-stat__value"><?php echo number_format($analytics['stats']['unique_paying_painters'] ?? 0); ?></div>
                        <div class="payment-stat__label">Paying Painters</div>
                    </div>
                </div>
                
                <div class="payment-stat">
                    <div class="payment-stat__icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="payment-stat__content">
                        <div class="payment-stat__value"><?php echo number_format($analytics['stats']['leads_purchased'] ?? 0); ?></div>
                        <div class="payment-stat__label">Leads Purchased</div>
                    </div>
                </div>
                
                <div class="payment-stat">
                    <div class="payment-stat__icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="payment-stat__content">
                        <div class="payment-stat__value"><?php echo number_format($analytics['deactivated_leads']); ?></div>
                        <div class="payment-stat__label">Deactivated Leads</div>
                    </div>
                </div>
                
                <div class="payment-stat">
                    <div class="payment-stat__icon">
                        <i class="bi bi-percent"></i>
                    </div>
                    <div class="payment-stat__content">
                        <div class="payment-stat__value">
                            <?php 
                            $successRate = 0;
                            $total = $analytics['stats']['total_payments'] ?? 0;
                            $successful = $analytics['stats']['successful_payments'] ?? 0;
                            if ($total > 0) {
                                $successRate = ($successful / $total) * 100;
                            }
                            echo number_format($successRate, 1) . '%';
                            ?>
                        </div>
                        <div class="payment-stat__label">Success Rate</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stripe Configuration -->
        <section class="admin-card">
            <h2 class="payment-title">Stripe Configuration</h2>
            <form method="post" class="payment-config-form">
                <div class="payment-config-grid">
                    <div class="payment-config-group">
                        <label class="payment-config-label" for="stripe_publishable_key">Publishable Key</label>
                        <input 
                            type="text" 
                            id="stripe_publishable_key" 
                            name="stripe_publishable_key" 
                            class="payment-config-input"
                            value="<?php echo htmlspecialchars($config['stripe_publishable_key'] ?? ''); ?>"
                            placeholder="pk_test_..."
                        >
                        <small class="payment-config-hint">Public key for frontend integration</small>
                    </div>
                    
                    <div class="payment-config-group">
                        <label class="payment-config-label" for="stripe_secret_key">Secret Key</label>
                        <input 
                            type="password" 
                            id="stripe_secret_key" 
                            name="stripe_secret_key" 
                            class="payment-config-input"
                            value="<?php echo htmlspecialchars($config['stripe_secret_key'] ?? ''); ?>"
                            placeholder="sk_test_..."
                        >
                        <small class="payment-config-hint">Secret key for backend processing</small>
                    </div>
                    
                    <div class="payment-config-group">
                        <label class="payment-config-label" for="stripe_webhook_secret">Webhook Secret</label>
                        <input 
                            type="password" 
                            id="stripe_webhook_secret" 
                            name="stripe_webhook_secret" 
                            class="payment-config-input"
                            value="<?php echo htmlspecialchars($config['stripe_webhook_secret'] ?? ''); ?>"
                            placeholder="whsec_..."
                        >
                        <small class="payment-config-hint">Webhook endpoint secret for security</small>
                    </div>
                </div>
                
                <div class="payment-config-actions">
                    <button type="submit" name="update_stripe_config" class="btn btn-primary">
                        <i class="bi bi-credit-card"></i> Update Stripe Configuration
                    </button>
                </div>
            </form>
        </section>

        <!-- Payment Settings -->
        <section class="admin-card">
            <h2 class="payment-title">Payment Settings</h2>
            <form method="post" class="payment-settings-form">
                <div class="payment-settings-grid">
                    <div class="payment-setting-group">
                        <label class="payment-setting-label" for="default_lead_price">Default Lead Price (£)</label>
                        <input 
                            type="number" 
                            id="default_lead_price" 
                            name="default_lead_price" 
                            step="0.01" 
                            min="0" 
                            class="payment-setting-input"
                            value="<?php echo htmlspecialchars($config['default_lead_price'] ?? '15.00'); ?>"
                        >
                    </div>
                    
                    <div class="payment-setting-group">
                        <label class="payment-setting-label" for="max_payments_per_lead">Max Payments Per Lead</label>
                        <input 
                            type="number" 
                            id="max_payments_per_lead" 
                            name="max_payments_per_lead" 
                            min="1" 
                            max="10" 
                            class="payment-setting-input"
                            value="<?php echo htmlspecialchars($config['max_payments_per_lead'] ?? '3'); ?>"
                        >
                    </div>
                    
                    <div class="payment-setting-group">
                        <label class="payment-setting-checkbox">
                            <input 
                                type="checkbox" 
                                name="payment_enabled" 
                                <?php echo ($config['payment_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>
                            >
                            <span class="payment-setting-checkbox-label">Enable Payment System</span>
                        </label>
                    </div>
                    
                    <div class="payment-setting-group">
                        <label class="payment-setting-checkbox">
                            <input 
                                type="checkbox" 
                                name="auto_deactivate_leads" 
                                <?php echo ($config['auto_deactivate_leads'] ?? 'true') === 'true' ? 'checked' : ''; ?>
                            >
                            <span class="payment-setting-checkbox-label">Auto-deactivate leads after max payments</span>
                        </label>
                    </div>
                    
                    <div class="payment-setting-group">
                        <label class="payment-setting-checkbox">
                            <input 
                                type="checkbox" 
                                name="email_notifications_enabled" 
                                <?php echo ($config['email_notifications_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>
                            >
                            <span class="payment-setting-checkbox-label">Enable Email Notifications</span>
                        </label>
                        <small class="payment-setting-help">Send email notifications for payment events</small>
                    </div>
                    
                    <div class="payment-setting-group">
                        <label class="payment-setting-checkbox">
                            <input 
                                type="checkbox" 
                                name="daily_summary_enabled" 
                                <?php echo ($config['daily_summary_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>
                            >
                            <span class="payment-setting-checkbox-label">Enable Daily Payment Summary</span>
                        </label>
                        <small class="payment-setting-help">Send daily payment summary to admin email</small>
                    </div>
                </div>
                
                <div class="payment-settings-actions">
                    <button type="submit" name="update_payment_settings" class="btn btn-success">
                        <i class="bi bi-gear"></i> Update Payment Settings
                    </button>
                    <button type="submit" name="test_email_notifications" class="btn btn--outline">
                        <i class="bi bi-envelope"></i> Test Email Notifications
                    </button>
                </div>
            </form>
        </section>

        <!-- Recent Payments -->
        <section class="admin-card">
            <h2 class="payment-title">Recent Payments</h2>
            <div class="payment-table-wrapper">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Painter</th>
                            <th>Lead</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['recent_payments'] as $payment): ?>
                        <tr>
                            <td><?php echo date('M j, Y H:i', strtotime($payment['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['company_name']); ?></strong>
                            </td>
                            <td>
                                <div class="payment-lead-info">
                                    <div><?php echo htmlspecialchars($payment['job_title']); ?></div>
                                    <small><?php echo htmlspecialchars($payment['location']); ?></small>
                                </div>
                            </td>
                            <td class="payment-amount">£<?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <span class="payment-status payment-status--<?php echo $payment['payment_status']; ?>">
                                    <?php echo ucfirst($payment['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo $payment['payment_number']; ?> of 3</td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($analytics['recent_payments'])): ?>
                        <tr>
                            <td colspan="6" class="payment-no-data">No payments found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Top Paying Painters -->
        <section class="admin-card">
            <h2 class="payment-title">Top Paying Painters</h2>
            <div class="payment-painters-grid">
                <?php foreach (array_slice($analytics['top_painters'], 0, 6) as $index => $painter): ?>
                <div class="payment-painter-card">
                    <div class="payment-painter-rank">
                        <i class="bi bi-<?php echo $index === 0 ? 'trophy-fill' : ($index === 1 ? 'award-fill' : 'star-fill'); ?>"></i>
                        #<?php echo $index + 1; ?>
                    </div>
                    <div class="payment-painter-info">
                        <h4><?php echo htmlspecialchars($painter['company_name']); ?></h4>
                        <div class="payment-painter-stats">
                            <div class="payment-painter-stat">
                                <span class="label">Total Spent</span>
                                <span class="value">£<?php echo number_format($painter['total_spent'], 2); ?></span>
                            </div>
                            <div class="payment-painter-stat">
                                <span class="label">Payments</span>
                                <span class="value"><?php echo $painter['payment_count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<style>
.admin-layout {
    display: flex;
    min-height: 100vh;
    background: #f7fafc;
}

.admin-main {
    flex: 1;
    padding: 2.5rem 2rem 2rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
    background: #f7fafc;
}

.admin-card {
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08);
    padding: 2rem 1.5rem;
    margin-bottom: 2rem;
}

.payment-message {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
}

.payment-message--success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.payment-message--error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.payment-title {
    color: #00b050;
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.payment-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.payment-stat {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 0.8rem;
    border-left: 4px solid #00b050;
}

.payment-stat__icon {
    font-size: 2rem;
    color: #00b050;
}

.payment-stat__value {
    font-size: 1.8rem;
    font-weight: 900;
    color: #222;
    line-height: 1;
}

.payment-stat__label {
    font-size: 0.9rem;
    color: #666;
    margin-top: 0.25rem;
}

.payment-config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.payment-config-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.payment-config-label {
    font-weight: 600;
    color: #333;
}

.payment-config-input {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    font-family: monospace;
}

.payment-config-hint {
    color: #666;
    font-size: 0.8rem;
}

.payment-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.payment-setting-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.payment-setting-label {
    font-weight: 600;
    color: #333;
}

.payment-setting-input {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    font-size: 0.9rem;
}

.payment-setting-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.payment-setting-checkbox input {
    margin: 0;
}

.payment-setting-checkbox-label {
    font-weight: 600;
    color: #333;
}

.payment-setting-help {
    display: block;
    color: #666;
    font-size: 0.85rem;
    margin-top: 0.25rem;
    font-style: italic;
}

.payment-table-wrapper {
    overflow-x: auto;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-table th,
.payment-table td {
    padding: 1rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.payment-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #00b050;
}

.payment-lead-info div {
    font-weight: 600;
}

.payment-lead-info small {
    color: #666;
}

.payment-amount {
    font-weight: 700;
    color: #00b050;
}

.payment-status {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.payment-status--succeeded {
    background: #d4edda;
    color: #155724;
}

.payment-status--pending {
    background: #fff3cd;
    color: #856404;
}

.payment-status--failed {
    background: #f8d7da;
    color: #721c24;
}

.payment-no-data {
    text-align: center;
    color: #666;
    font-style: italic;
}

.payment-painters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.payment-painter-card {
    background: #f8f9fa;
    border-radius: 0.8rem;
    padding: 1.5rem;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.payment-painter-rank {
    background: #00b050;
    color: white;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.payment-painter-info h4 {
    margin: 0 0 0.5rem 0;
    color: #222;
}

.payment-painter-stats {
    display: flex;
    gap: 1rem;
}

.payment-painter-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.payment-painter-stat .label {
    font-size: 0.8rem;
    color: #666;
}

.payment-painter-stat .value {
    font-weight: 700;
    color: #00b050;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.btn-primary {
    background: #00b050;
    color: white;
    border-color: #00b050;
}

.btn-success {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.btn--outline {
    background: transparent;
    color: #00b050;
    border-color: #00b050;
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1.2rem 0.5rem;
    }
    
    .payment-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .payment-config-grid,
    .payment-settings-grid {
        grid-template-columns: 1fr;
    }
    
    .payment-painters-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'templates/footer.php'; ?> 