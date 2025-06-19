<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';
require_once 'core/StripePaymentManager.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();
$paymentManager = new StripePaymentManager();

// Require login
$auth->requireLogin();

$painterId = $auth->getCurrentPainterId();
$leadId = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
$errors = [];
$success = false;

if (!$leadId) {
    die('Lead ID is required.');
}

// Fetch lead
$lead = $dataAccess->getLeadById($leadId);
if (!$lead || $lead['status'] !== 'open') {
    die('Lead not found or not available.');
}

// Check if painter has already claimed this lead
$alreadyClaimed = $dataAccess->hasPainterClaimedLead($painterId, $leadId);
if ($alreadyClaimed) {
    header('Location: bid.php?lead_id=' . $leadId);
    exit();
}

// Check if painter has payment methods set up
$paymentMethods = $paymentManager->getPainterPaymentMethods($painterId);
$hasPaymentMethods = !empty($paymentMethods);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'claim_lead') {
        $paymentMethodId = $_POST['payment_method_id'] ?? '';
        
        if (empty($paymentMethodId)) {
            $errors[] = 'Please select a payment method.';
        }
        
        if (empty($errors)) {
            // Process payment for lead access
            $leadPrice = 15.00; // £15 per lead
            
            $paymentResult = $paymentManager->processLeadAccessPayment(
                $painterId,
                $leadId,
                $leadPrice,
                $paymentMethodId
            );
            
            if ($paymentResult['success']) {
                // Claim the lead after successful payment
                $claimResult = $dataAccess->claimLead($leadId, $painterId);
                
                if ($claimResult['success']) {
                    $success = true;
                    
                    // Send confirmation email
                    try {
                        require_once __DIR__ . '/core/Mailer.php';
                        $mailer = new Core\Mailer();
                        
                        $painter = $dataAccess->getPainterById($painterId);
                        $painterEmail = $painter['email'] ?? '';
                        $painterName = $painter['company_name'] ?? 'Unknown Company';
                        
                        if ($painterEmail) {
                            $subject = 'Lead Access Purchased - ' . htmlspecialchars($lead['job_title']);
                            $message = "<h2>Lead Access Purchased Successfully</h2>";
                            $message .= "<p>Dear " . htmlspecialchars($painterName) . ",</p>";
                            $message .= "<p>You have successfully purchased access to the following lead:</p>";
                            $message .= "<h3>Lead Details:</h3>";
                            $message .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse; width: 100%;'>";
                            $message .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Job Title:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['job_title']) . "</td></tr>";
                            $message .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Location:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['location']) . "</td></tr>";
                            $message .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Customer:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['customer_name']) . "</td></tr>";
                            $message .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Email:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['customer_email']) . "</td></tr>";
                            $message .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Phone:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['customer_phone'] ?? 'Not provided') . "</td></tr>";
                            $message .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Amount Paid:</td><td style='border: 1px solid #ddd; padding: 8px;'>£" . number_format($leadPrice, 2) . "</td></tr>";
                            $message .= "</table>";
                            
                            $message .= "<h3>Next Steps:</h3>";
                            $message .= "<p>You can now:</p>";
                            $message .= "<ul>";
                            $message .= "<li>Contact the customer directly using the information above</li>";
                            $message .= "<li>Submit a competitive bid for this project</li>";
                            $message .= "<li>Schedule a consultation or site visit</li>";
                            $message .= "</ul>";
                            
                            $message .= "<p><a href='https://painter-near-me.co.uk/bid.php?lead_id={$leadId}' style='background:#00b050;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Submit Your Bid Now</a></p>";
                            $message .= "<p>Thank you for using Painter Near Me!</p>";
                            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
                            
                            $mailer->sendMail($painterEmail, $subject, $message, strip_tags($message), $painterName);
                        }
                        
                    } catch (Exception $e) {
                        error_log("Email error: " . $e->getMessage());
                    }
                    
                    // Redirect to bidding page after successful claim
                    header('Location: bid.php?lead_id=' . $leadId . '&claimed=1');
                    exit();
                    
                } else {
                    $errors[] = 'Payment processed but failed to claim lead. Please contact support.';
                }
            } else {
                $errors[] = 'Payment failed: ' . ($paymentResult['error'] ?? 'Unknown error');
            }
        }
    }
}

include 'templates/header.php';
?>
<head>
    <title>Claim Lead Access | Painter Near Me</title>
    <meta name="description" content="Purchase access to customer lead details on Painter Near Me." />
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
</head>
<main role="main">
    <div style="display:flex;gap:2.5rem;align-items:flex-start;max-width:1100px;margin:0 auto;">
        <div>
            <?php include 'templates/sidebar-painter.php'; ?>
        </div>
        <div style="flex:1;min-width:0;">
            <section class="claim-hero hero">
                <h1 class="hero__title">Claim Lead Access</h1>
                <p class="hero__subtitle">Purchase access to customer contact details and project information.</p>
            </section>
            
            <section class="claim-main">
                <div class="claim-main__container" style="max-width:700px;margin:2.5rem auto;">
                    
                    <?php if (!empty($errors)): ?>
                        <div class="errors" style="background:#fee2e2;color:#dc2626;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="success" style="background:#d1fae5;color:#065f46;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
                            Lead access purchased successfully! You can now contact the customer and submit your bid.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Lead Preview -->
                    <div class="lead-preview" style="background:#fff;border-radius:1.2rem;box-shadow:0 4px 16px rgba(0,176,80,0.08);padding:2rem;margin-bottom:2rem;">
                        <h2 style="color:#00b050;margin-bottom:1rem;">Lead Preview</h2>
                        <div class="lead-details" style="margin-bottom:1.5rem;">
                            <h3 style="margin:0 0 0.5rem 0;color:#222;"><?php echo htmlspecialchars($lead['job_title']); ?></h3>
                            <p style="color:#666;margin:0 0 1rem 0;"><?php echo htmlspecialchars(substr($lead['job_description'], 0, 200)) . '...'; ?></p>
                            <div style="color:#666;font-size:0.98rem;">
                                <strong>Location:</strong> <?php echo htmlspecialchars($lead['location']); ?><br>
                                <strong>Posted:</strong> <?php echo $dataAccess->formatDate($lead['created_at']); ?>
                            </div>
                        </div>
                        
                        <div class="access-pricing" style="background:#f8fafc;padding:1.5rem;border-radius:0.8rem;border:1px solid #e2e8f0;">
                            <h4 style="margin:0 0 1rem 0;color:#00b050;">Access Pricing</h4>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                                <span style="font-size:1.1rem;">Lead Access Fee:</span>
                                <span style="font-size:1.5rem;font-weight:700;color:#00b050;">£15.00</span>
                            </div>
                            <div style="font-size:0.9rem;color:#666;">
                                <p style="margin:0;">One-time payment for:</p>
                                <ul style="margin:0.5rem 0;padding-left:1.2rem;">
                                    <li>Full customer contact details</li>
                                    <li>Complete project description</li>
                                    <li>Ability to submit competitive bids</li>
                                    <li>Direct communication with customer</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$hasPaymentMethods): ?>
                        <!-- No Payment Methods -->
                        <div class="no-payment-methods" style="background:#fff3cd;border:1px solid #ffeaa7;padding:1.5rem;border-radius:0.8rem;text-align:center;">
                            <h3 style="color:#856404;margin:0 0 1rem 0;">Payment Method Required</h3>
                            <p style="color:#856404;margin:0 0 1.5rem 0;">You need to set up a payment method before claiming leads.</p>
                            <a href="profile.php?tab=payment" class="btn-primary" style="background:#00b050;color:#fff;padding:0.75rem 1.5rem;border-radius:0.5rem;text-decoration:none;">Set Up Payment Method</a>
                        </div>
                    <?php else: ?>
                        <!-- Payment Form -->
                        <form method="POST" class="claim-form" style="background:#fff;border-radius:1.2rem;box-shadow:0 4px 16px rgba(0,176,80,0.08);padding:2rem;">
                            <input type="hidden" name="action" value="claim_lead">
                            
                            <h3 style="color:#00b050;margin:0 0 1.5rem 0;">Select Payment Method</h3>
                            
                            <div class="payment-methods" style="margin-bottom:2rem;">
                                <?php foreach ($paymentMethods as $method): ?>
                                    <div class="payment-method" style="border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem;margin-bottom:1rem;cursor:pointer;" onclick="selectPaymentMethod('<?php echo $method['stripe_payment_method_id']; ?>')">
                                        <label style="cursor:pointer;display:flex;align-items:center;gap:0.8rem;">
                                            <input type="radio" name="payment_method_id" value="<?php echo htmlspecialchars($method['stripe_payment_method_id']); ?>" required>
                                            <div>
                                                <div style="font-weight:600;">
                                                    <i class="bi bi-credit-card"></i>
                                                    **** **** **** <?php echo htmlspecialchars($method['card_last4']); ?>
                                                </div>
                                                <div style="font-size:0.9rem;color:#666;">
                                                    <?php echo ucfirst($method['card_brand']); ?> • Expires <?php echo htmlspecialchars($method['card_exp_month']); ?>/<?php echo htmlspecialchars($method['card_exp_year']); ?>
                                                    <?php if ($method['is_default']): ?>
                                                        <span style="background:#00b050;color:white;padding:0.2rem 0.5rem;border-radius:0.3rem;font-size:0.8rem;margin-left:0.5rem;">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="payment-summary" style="background:#f8fafc;padding:1.5rem;border-radius:0.8rem;margin-bottom:2rem;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                                    <span style="font-size:1.1rem;">Total Amount:</span>
                                    <span style="font-size:1.5rem;font-weight:700;color:#00b050;">£15.00</span>
                                </div>
                                <div style="font-size:0.9rem;color:#666;">
                                    <p style="margin:0;">By proceeding, you agree to:</p>
                                    <ul style="margin:0.5rem 0;padding-left:1.2rem;">
                                        <li>Pay £15.00 for access to this lead</li>
                                        <li>Use the customer information responsibly</li>
                                        <li>Provide professional service if selected</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="form-actions" style="text-align:center;">
                                <button type="submit" class="btn-claim" style="background:#00b050;color:#fff;border:none;padding:1rem 2rem;border-radius:0.8rem;font-size:1.1rem;font-weight:700;cursor:pointer;transition:background 0.2s;">
                                    <i class="bi bi-credit-card"></i> Purchase Lead Access - £15.00
                                </button>
                                <div style="margin-top:1rem;">
                                    <a href="leads.php" style="color:#666;text-decoration:none;">← Back to Available Leads</a>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<?php include 'templates/footer.php'; ?>

<script>
function selectPaymentMethod(methodId) {
    // Remove selected class from all methods
    document.querySelectorAll('.payment-method').forEach(el => {
        el.style.borderColor = '#e2e8f0';
        el.style.backgroundColor = '#fff';
    });
    
    // Add selected styling to clicked method
    event.currentTarget.style.borderColor = '#00b050';
    event.currentTarget.style.backgroundColor = '#f0f9ff';
    
    // Check the radio button
    const radio = event.currentTarget.querySelector('input[type="radio"]');
    radio.checked = true;
}

// Add hover effects
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.payment-method').forEach(method => {
        method.addEventListener('mouseenter', function() {
            if (!this.querySelector('input[type="radio"]').checked) {
                this.style.backgroundColor = '#f8fafc';
            }
        });
        
        method.addEventListener('mouseleave', function() {
            if (!this.querySelector('input[type="radio"]').checked) {
                this.style.backgroundColor = '#fff';
            }
        });
    });
});
</script>

<style>
.btn-claim:hover {
    background: #009140;
}

.payment-method:hover {
    border-color: #00b050 !important;
}

.claim-form input[type="radio"] {
    accent-color: #00b050;
}
</style> 