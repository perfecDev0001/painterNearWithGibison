<?php
require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';
require_once 'core/StripePaymentManager.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();
$paymentManager = new StripePaymentManager();

// Require login
$auth->requireLogin();

$painterId = $auth->getCurrentPainterId();

// Fetch all open leads
$leads = $dataAccess->getOpenLeads($painterId);

// Check which leads this painter has claimed and bid on
foreach ($leads as &$lead) {
    $lead['has_claimed'] = $dataAccess->hasPainterClaimedLead($painterId, $lead['id']);
    $lead['has_bid'] = $dataAccess->hasPainterBidOnLead($painterId, $lead['id']);
    // Legacy support for payment manager
    $lead['has_access'] = $lead['has_claimed'] || $paymentManager->painterHasLeadAccess($painterId, $lead['id']);
}

include 'templates/header.php';
?>
<head>
    <title>Available Leads | Painter Near Me</title>
    <meta name="description" content="Browse available painting job leads on Painter Near Me." />
    <script src="https://js.stripe.com/v3/"></script>
    <script src="payment-management.js"></script>
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
            <section class="leads-hero hero">
                <h1 class="hero__title">Available Leads</h1>
                <p class="hero__subtitle">Browse and purchase access to painting jobs in your area.</p>
            </section>
            <section class="leads-main">
                <div class="leads-main__container" style="max-width:900px;margin:2.5rem auto;">
                    <?php if (empty($leads)): ?>
                        <div class="leads-main__empty" style="background:#e6f7ea;color:#00b050;padding:1.2rem;border-radius:0.7rem;text-align:center;">No leads available at the moment.</div>
                    <?php else: ?>
                        <div class="leads-list">
                            <?php foreach ($leads as $lead): ?>
                                <div class="leads-list__item" style="background:#fff;border-radius:1.2rem;box-shadow:0 4px 16px rgba(0,176,80,0.08),0 1.5px 8px rgba(0,0,0,0.04);padding:1.5rem 2rem;margin-bottom:1.5rem;">
                                    <div class="leads-list__title" style="font-size:1.2rem;font-weight:700;color:#00b050;margin-bottom:0.5rem;">
                                        <?php echo htmlspecialchars($lead['job_title']); ?>
                                    </div>
                                    
                                                        <?php if ($lead['has_claimed'] || $lead['has_access']): ?>
                        <!-- Full lead details for painters who have claimed access -->
                        <div class="leads-list__desc" style="color:#444;margin-bottom:0.7rem;">
                            <?php echo nl2br(htmlspecialchars($lead['job_description'])); ?>
                        </div>
                        <div class="leads-list__meta" style="color:#666;font-size:0.98rem;margin-bottom:0.7rem;">
                            <strong>Location:</strong> <?php echo htmlspecialchars($lead['location']); ?>
                        </div>
                        <div class="leads-list__meta" style="color:#666;font-size:0.98rem;margin-bottom:0.7rem;">
                            <strong>Customer:</strong> <?php echo htmlspecialchars($lead['customer_name']); ?>
                        </div>
                        <div class="leads-list__meta" style="color:#666;font-size:0.98rem;margin-bottom:0.7rem;">
                            <strong>Contact:</strong> <?php echo htmlspecialchars($lead['customer_email']); ?>
                        </div>
                        <div class="leads-list__meta" style="color:#666;font-size:0.98rem;margin-bottom:0.7rem;">
                            <strong>Posted:</strong> <?php echo $dataAccess->formatDate($lead['created_at']); ?>
                        </div>
                        <div class="leads-list__access-status" style="background:#e6f7ea;color:#00b050;padding:0.5rem 1rem;border-radius:0.5rem;margin-bottom:1rem;display:inline-block;">
                            <i class="bi bi-check-circle"></i> Lead Claimed
                        </div>
                        
                        <?php if ($lead['has_bid']): ?>
                            <div class="leads-list__bid-status" style="color:#888;font-weight:600;">You have already submitted a bid for this project.</div>
                            <a href="my-bids.php" style="color:#00b050;text-decoration:none;font-weight:600;">View My Bids →</a>
                        <?php else: ?>
                            <a href="bid.php?lead_id=<?php echo $lead['id']; ?>" class="leads-list__bid-btn" style="display:inline-block;padding:0.6rem 1.5rem;background:#00b050;color:#fff;border-radius:1rem;font-weight:700;text-decoration:none;transition:background 0.2s;">Submit Your Bid</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Limited preview for leads that haven't been claimed -->
                                        <div class="leads-list__preview" style="color:#666;margin-bottom:0.7rem;font-style:italic;">
                                            <?php echo substr(strip_tags($lead['job_description']), 0, 100); ?>...
                                        </div>
                                        <div class="leads-list__meta" style="color:#666;font-size:0.98rem;margin-bottom:0.7rem;">
                                            <strong>Location:</strong> <?php echo htmlspecialchars($lead['location']); ?>
                                        </div>
                                        <div class="leads-list__meta" style="color:#666;font-size:0.98rem;margin-bottom:0.7rem;">
                                            <strong>Posted:</strong> <?php echo $dataAccess->formatDate($lead['created_at']); ?>
                                        </div>
                                        
                                                                <div class="leads-list__claim-info" style="background:#fff3cd;border:1px solid #ffeaa7;padding:1rem;border-radius:0.5rem;margin-bottom:1rem;">
                            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                                <i class="bi bi-lock-fill" style="color:#856404;"></i>
                                <strong style="color:#856404;">Lead Access Required</strong>
                            </div>
                            <p style="margin:0;color:#856404;font-size:0.9rem;">Claim this lead for £<?php echo number_format($lead['lead_price'] ?? 15.00, 2); ?> to view full details, contact information, and submit bids.</p>
                        </div>
                        
                        <a href="lead-claim.php?lead_id=<?php echo $lead['id']; ?>" class="leads-list__claim-btn" 
                           style="display:inline-block;padding:0.6rem 1.5rem;background:#007bff;color:#fff;border:none;border-radius:1rem;font-weight:700;text-decoration:none;transition:background 0.2s;">
                            <i class="bi bi-credit-card"></i> Claim Lead - £<?php echo number_format($lead['lead_price'] ?? 15.00, 2); ?>
                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<!-- Payment Modal -->
<div id="payment-modal" class="payment-modal" style="display:none;">
    <div class="payment-modal__content">
        <div class="payment-modal__header">
            <h3>Purchase Lead Access</h3>
            <button class="payment-modal__close" onclick="closePaymentModal()">&times;</button>
        </div>
        <div class="payment-modal__body">
            <div id="payment-form-container">
                <!-- Payment form will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>

<script>
function purchaseLeadAccess(leadId, price) {
    // Check if painter has payment methods set up
    fetch('/api/payment-api.php/payment-methods')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.methods.length > 0) {
                // Show payment modal with existing methods
                showPaymentModal(leadId, price, data.methods);
            } else {
                // Redirect to setup payment method first
                if (confirm('You need to set up a payment method first. Would you like to do that now?')) {
                    window.location.href = 'profile.php?tab=payment';
                }
            }
        })
        .catch(error => {
            console.error('Error checking payment methods:', error);
            alert('Error loading payment information. Please try again.');
        });
}

function showPaymentModal(leadId, price, paymentMethods) {
    const modal = document.getElementById('payment-modal');
    const container = document.getElementById('payment-form-container');
    
    let methodsHtml = '<div class="payment-methods">';
    methodsHtml += '<h4>Select Payment Method:</h4>';
    
    paymentMethods.forEach(method => {
        methodsHtml += `
            <div class="payment-method-option" onclick="selectPaymentMethod('${method.stripe_payment_method_id}')">
                <i class="bi bi-credit-card"></i>
                **** **** **** ${method.card_last4} (${method.card_brand})
                ${method.is_default ? '<span class="default-badge">Default</span>' : ''}
            </div>
        `;
    });
    
    methodsHtml += '</div>';
    methodsHtml += `
        <div class="payment-summary">
            <p><strong>Total: £${price.toFixed(2)}</strong></p>
            <button id="confirm-purchase" class="btn btn-primary" onclick="confirmPurchase(${leadId})" disabled>
                Purchase Access
            </button>
        </div>
    `;
    
    container.innerHTML = methodsHtml;
    modal.style.display = 'block';
}

let selectedPaymentMethodId = null;

function selectPaymentMethod(methodId) {
    selectedPaymentMethodId = methodId;
    
    // Update UI
    document.querySelectorAll('.payment-method-option').forEach(el => {
        el.classList.remove('selected');
    });
    event.target.closest('.payment-method-option').classList.add('selected');
    
    document.getElementById('confirm-purchase').disabled = false;
}

function confirmPurchase(leadId) {
    if (!selectedPaymentMethodId) return;
    
    const button = document.getElementById('confirm-purchase');
    button.disabled = true;
    button.textContent = 'Processing...';
    
    fetch('/api/payment-api.php/purchase-lead', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            lead_id: leadId,
            payment_method_id: selectedPaymentMethodId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.requires_action) {
                // Handle 3D Secure
                handlePaymentConfirmation(data.client_secret);
            } else {
                // Payment successful
                alert('Access purchased successfully!');
                location.reload();
            }
        } else {
            alert('Payment failed: ' + data.error);
            button.disabled = false;
            button.textContent = 'Purchase Access';
        }
    })
    .catch(error => {
        console.error('Payment error:', error);
        alert('Payment failed. Please try again.');
        button.disabled = false;
        button.textContent = 'Purchase Access';
    });
}

function closePaymentModal() {
    document.getElementById('payment-modal').style.display = 'none';
    selectedPaymentMethodId = null;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('payment-modal');
    if (event.target === modal) {
        closePaymentModal();
    }
}
</script>

<style>
.leads-list__bid-btn:hover { background: #009140; color: #fff; }
.leads-list__purchase-btn:hover { background: #0056b3; }

.payment-modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.payment-modal__content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 1rem;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.payment-modal__header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.payment-modal__close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

.payment-modal__body {
    padding: 2rem;
}

.payment-method-option {
    padding: 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
}

.payment-method-option:hover {
    border-color: #00b050;
}

.payment-method-option.selected {
    border-color: #00b050;
    background-color: #e6f7ea;
}

.payment-summary {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.default-badge {
    background: #00b050;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
}
</style> 