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

// Fetch lead
$lead = $dataAccess->getLeadById($leadId);
if (!$lead || $lead['status'] !== 'open') {
    die('Lead not found or not open.');
}

// Check if painter has claimed this lead
$hasClaimed = $dataAccess->hasPainterClaimedLead($painterId, $leadId);
$hasAccess = $hasClaimed || $paymentManager->painterHasLeadAccess($painterId, $leadId); // Legacy support

if (!$hasAccess) {
    header('Location: lead-claim.php?lead_id=' . $leadId);
    exit();
}

// Check if already bid
$alreadyBid = $dataAccess->hasPainterBidOnLead($painterId, $leadId);
if ($alreadyBid) {
    die('You have already bid on this lead.');
}

// Get existing bids for pricing insight (anonymized)
$existingBids = $dataAccess->getBidsByLead($leadId);
$bidCount = count($existingBids);
$averageBid = 0;
$lowestBid = 0;
$highestBid = 0;

if ($bidCount > 0) {
    $bidAmounts = array_map(function($bid) { return floatval($bid['bid_amount']); }, $existingBids);
    $averageBid = array_sum($bidAmounts) / count($bidAmounts);
    $lowestBid = min($bidAmounts);
    $highestBid = max($bidAmounts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bidAmount = floatval($_POST['bid_amount']);
    $message = trim($_POST['message']);
    $timeline = trim($_POST['timeline']);
    $materials_included = isset($_POST['materials_included']) ? 1 : 0;
    $warranty_period = intval($_POST['warranty_period'] ?? 0);
    $warranty_details = trim($_POST['warranty_details'] ?? '');
    $project_approach = trim($_POST['project_approach'] ?? '');
    
    // Enhanced validation
    if ($bidAmount <= 0) {
        $errors[] = 'Please enter a valid bid amount greater than £0.';
    }
    
    if ($bidAmount < 50) {
        $errors[] = 'Minimum bid amount is £50.';
    }
    
    if ($bidAmount > 50000) {
        $errors[] = 'Maximum bid amount is £50,000. For larger projects, please contact us directly.';
    }
    
    if (empty($message) || strlen($message) < 20) {
        $errors[] = 'Please provide a detailed message (minimum 20 characters) explaining your approach.';
    }
    
    if (strlen($message) > 1500) {
        $errors[] = 'Message cannot exceed 1,500 characters.';
    }
    
    if (empty($timeline)) {
        $errors[] = 'Please specify your estimated project timeline.';
    }
    
    if ($warranty_period > 0 && empty($warranty_details)) {
        $errors[] = 'Please provide warranty details if offering a warranty period.';
    }
    
    if (empty($errors)) {
        $bidData = [
            'lead_id' => $leadId,
            'painter_id' => $painterId,
            'bid_amount' => $bidAmount,
            'message' => $message,
            'timeline' => $timeline,
            'materials_included' => $materials_included,
            'warranty_period' => $warranty_period,
            'warranty_details' => $warranty_details,
            'project_approach' => $project_approach,
            'status' => 'pending',
            'submitted_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $dataAccess->createBid($bidData);
        if ($result['success']) {
            $success = true;
            
            // Send email notifications for bid submission
            try {
                require_once __DIR__ . '/core/Mailer.php';
                $mailer = new Core\Mailer();
                
                // Get painter details for email
                $painter = $dataAccess->getPainterById($painterId);
                $painterEmail = $painter['email'] ?? '';
                $painterName = $painter['company_name'] ?? 'Unknown Company';
                
                // Enhanced confirmation email to painter
                if ($painterEmail) {
                    $confirmSubject = 'Bid Submitted Successfully - ' . htmlspecialchars($lead['job_title']);
                    $confirmMessage = "<h2>Bid Submitted Successfully</h2>";
                    $confirmMessage .= "<p>Dear " . htmlspecialchars($painterName) . ",</p>";
                    $confirmMessage .= "<p>Your enhanced bid has been successfully submitted for the following job:</p>";
                    $confirmMessage .= "<h3>Job Details:</h3>";
                    $confirmMessage .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse; width: 100%;'>";
                    $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Job Title:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['job_title']) . "</td></tr>";
                    $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Location:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['location']) . "</td></tr>";
                    $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Your Bid Amount:</td><td style='border: 1px solid #ddd; padding: 8px;'>£" . number_format($bidAmount, 2) . "</td></tr>";
                    $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Timeline:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($timeline) . "</td></tr>";
                    $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Materials Included:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . ($materials_included ? 'Yes' : 'No') . "</td></tr>";
                    if ($warranty_period > 0) {
                        $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Warranty:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . $warranty_period . " months</td></tr>";
                    }
                    $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Bid Status:</td><td style='border: 1px solid #ddd; padding: 8px;'>Pending Customer Review</td></tr>";
                    $confirmMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Submitted:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . date('Y-m-d H:i:s') . "</td></tr>";
                    $confirmMessage .= "</table>";
                    
                    if ($bidCount > 0) {
                        $confirmMessage .= "<h3>Competitive Analysis:</h3>";
                        $confirmMessage .= "<p>There " . ($bidCount == 1 ? "is currently 1 other bid" : "are currently " . $bidCount . " other bids") . " on this job.</p>";
                        $confirmMessage .= "<p><strong>Your bid positioning:</strong> ";
                        
                        if ($bidAmount <= $lowestBid) {
                            $confirmMessage .= "Most competitive (lowest price)";
                        } elseif ($bidAmount >= $highestBid) {
                            $confirmMessage .= "Premium pricing (highest price)";
                        } else {
                            $confirmMessage .= "Competitively positioned (mid-range)";
                        }
                        $confirmMessage .= "</p>";
                    }
                    
                    $confirmMessage .= "<h3>What Happens Next?</h3>";
                    $confirmMessage .= "<p>Your detailed bid is now under customer review. You will be notified if:</p>";
                    $confirmMessage .= "<ul>";
                    $confirmMessage .= "<li>The customer selects your bid</li>";
                    $confirmMessage .= "<li>The customer requests additional information</li>";
                    $confirmMessage .= "<li>The job status changes</li>";
                    $confirmMessage .= "</ul>";
                    $confirmMessage .= "<p><a href='https://painter-near-me.co.uk/my-bids.php' style='background:#00b050;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>View Your Bids</a></p>";
                    $confirmMessage .= "<p>Thank you for using Painter Near Me!</p>";
                    $confirmMessage .= "<p>Best regards,<br>The Painter Near Me Team</p>";
                    
                    $mailer->sendMail($painterEmail, $confirmSubject, $confirmMessage, strip_tags($confirmMessage), $painterName);
                }
                
                // Enhanced admin notification email
                if (!defined('ADMIN_EMAIL')) {
                    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
                }
                
                $adminSubject = 'Enhanced Bid Submitted - ' . htmlspecialchars($lead['job_title']);
                $adminMessage = "<h2>Enhanced Bid Submitted</h2>";
                $adminMessage .= "<p>A detailed bid has been submitted on Painter Near Me:</p>";
                $adminMessage .= "<h3>Job Details:</h3>";
                $adminMessage .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse; width: 100%;'>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Job Title:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['job_title']) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Customer:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['customer_name']) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Location:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($lead['location']) . "</td></tr>";
                $adminMessage .= "</table>";
                $adminMessage .= "<h3>Enhanced Bid Details:</h3>";
                $adminMessage .= "<table cellpadding='6' style='font-size:1.1rem; border-collapse: collapse; width: 100%;'>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Painter:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($painterName) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Painter Email:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($painterEmail) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Bid Amount:</td><td style='border: 1px solid #ddd; padding: 8px;'>£" . number_format($bidAmount, 2) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Timeline:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($timeline) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Materials Included:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . ($materials_included ? 'Yes' : 'No') . "</td></tr>";
                if ($warranty_period > 0) {
                    $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Warranty:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . $warranty_period . " months</td></tr>";
                    if ($warranty_details) {
                        $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Warranty Details:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . nl2br(htmlspecialchars($warranty_details)) . "</td></tr>";
                    }
                }
                if ($project_approach) {
                    $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Project Approach:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . nl2br(htmlspecialchars($project_approach)) . "</td></tr>";
                }
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Message:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . nl2br(htmlspecialchars($message)) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold; border: 1px solid #ddd; padding: 8px;'>Submitted:</td><td style='border: 1px solid #ddd; padding: 8px;'>" . date('Y-m-d H:i:s') . "</td></tr>";
                $adminMessage .= "</table>";
                
                if ($bidCount > 0) {
                    $adminMessage .= "<h3>Competition Analysis:</h3>";
                    $adminMessage .= "<p><strong>Total bids on this job:</strong> " . ($bidCount + 1) . " (including this new bid)</p>";
                    $adminMessage .= "<p><strong>Price range:</strong> £" . number_format(min($lowestBid, $bidAmount), 2) . " - £" . number_format(max($highestBid, $bidAmount), 2) . "</p>";
                }
                
                $adminMessage .= "<p><strong>Review and manage this bid in the admin panel.</strong></p>";
                
                $mailer->sendMail(ADMIN_EMAIL, $adminSubject, $adminMessage, strip_tags($adminMessage));
                
                // Send notification to customer about new bid
                require_once __DIR__ . '/core/EmailNotificationService.php';
                $emailService = new Core\EmailNotificationService();
                $emailService->sendBidReceivedNotification(
                    $lead['customer_email'],
                    $lead['customer_name'],
                    $lead['job_title'],
                    $painterName,
                    $bidAmount,
                    $leadId
                );
                
            } catch (Exception $e) {
                error_log("Failed to send bid notification emails: " . $e->getMessage());
            }
        } else {
            $errors[] = 'Failed to submit bid: ' . ($result['error'] ?? 'Unknown error');
        }
    }
}

include 'templates/header.php';
?>
<head>
    <title>Submit Enhanced Bid | Painter Near Me</title>
    <meta name="description" content="Submit a detailed, competitive bid for a painting job on Painter Near Me." />
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
    <style>
        .bid-insight-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #e1bee7;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .bid-insight-title {
            color: #4a148c;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        
        .competitive-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .competitive-stat {
            background: rgba(255,255,255,0.7);
            padding: 0.8rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        
        .competitive-stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #4a148c;
        }
        
        .competitive-stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.2rem;
        }
        
        .form-section {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,176,80,0.05);
        }
        
        .form-section-title {
            color: #00b050;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e6f7ea;
        }
        
        .bid-form__group {
            margin-bottom: 1.2rem;
        }
        
        .bid-form__label {
            display: block;
            font-weight: 700;
            color: #00b050;
            margin-bottom: 0.5rem;
        }
        
        .bid-form__input,
        .bid-form__textarea,
        .bid-form__select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.8rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .bid-form__input:focus,
        .bid-form__textarea:focus,
        .bid-form__select:focus {
            border-color: #00b050;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
        }
        
        .bid-form__checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .pricing-suggestion {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 0.5rem;
            padding: 0.8rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #856404;
        }
        
        .character-counter {
            font-size: 0.8rem;
            color: #666;
            text-align: right;
            margin-top: 0.3rem;
        }
        
        .required-field::after {
            content: "*";
            color: #dc2626;
            margin-left: 0.2rem;
        }
    </style>
</head>
<main role="main">
    <div style="display:flex;gap:2.5rem;align-items:flex-start;max-width:1200px;margin:0 auto;">
        <div>
            <?php include 'templates/sidebar-painter.php'; ?>
        </div>
        <div style="flex:1;min-width:0;">
            <section class="bid-hero hero">
                <h1 class="hero__title">Submit Enhanced Bid</h1>
                <p class="hero__subtitle">Create a detailed, competitive proposal for: <strong><?php echo htmlspecialchars($lead['job_title']); ?></strong></p>
            </section>
            
            <section class="bid-main">
                <div class="bid-main__container" style="max-width:800px;margin:2rem auto;">
                    
                    <!-- Job Information -->
                    <div class="form-section">
                        <div class="form-section-title">📋 Job Information</div>
                        <div style="color:#444;margin-bottom:0.7rem;">
                            <strong>Description:</strong><br>
                            <?php echo nl2br(htmlspecialchars($lead['job_description'])); ?>
                        </div>
                        <div style="color:#666;font-size:0.98rem;">
                            <strong>Location:</strong> <?php echo htmlspecialchars($lead['location']); ?> |
                            <strong>Posted:</strong> <?php echo $dataAccess->formatDate($lead['created_at']); ?>
                        </div>
                    </div>
                    
                    <!-- Competitive Intelligence -->
                    <?php if ($bidCount > 0): ?>
                    <div class="bid-insight-card">
                        <div class="bid-insight-title">📊 Competitive Intelligence</div>
                        <p>There <?php echo $bidCount == 1 ? 'is currently <strong>1 other bid</strong>' : 'are currently <strong>' . $bidCount . ' other bids</strong>'; ?> on this job.</p>
                        <div class="competitive-stats">
                            <div class="competitive-stat">
                                <div class="competitive-stat-value">£<?php echo number_format($lowestBid, 0); ?></div>
                                <div class="competitive-stat-label">Lowest Bid</div>
                            </div>
                            <div class="competitive-stat">
                                <div class="competitive-stat-value">£<?php echo number_format($averageBid, 0); ?></div>
                                <div class="competitive-stat-label">Average Bid</div>
                            </div>
                            <div class="competitive-stat">
                                <div class="competitive-stat-value">£<?php echo number_format($highestBid, 0); ?></div>
                                <div class="competitive-stat-label">Highest Bid</div>
                            </div>
                        </div>
                        <p style="margin-top:1rem;font-size:0.9rem;color:#666;"><em>Use this information to position your bid competitively. Consider value-added services to differentiate your proposal.</em></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="form-section" style="background:#e6f7ea;border:2px solid #00b050;">
                            <div style="text-align:center;color:#00b050;">
                                <h2>🎉 Bid Submitted Successfully!</h2>
                                <p>Your enhanced bid has been submitted and the customer has been notified.</p>
                                <div style="margin-top:1.5rem;">
                                    <a href="my-bids.php" style="background:#00b050;color:white;padding:0.8rem 1.5rem;text-decoration:none;border-radius:0.8rem;margin-right:1rem;">View My Bids</a>
                                    <a href="leads.php" style="background:#6c757d;color:white;padding:0.8rem 1.5rem;text-decoration:none;border-radius:0.8rem;">Back to Leads</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="form-section" style="background:#fee2e2;border:2px solid #dc2626;">
                                <div style="color:#dc2626;">
                                    <h3>❌ Please fix the following errors:</h3>
                                    <ul style="margin:0.5rem 0 0 1.5rem;">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form class="enhanced-bid-form" method="post" action="" novalidate>
                            
                            <!-- Pricing Section -->
                            <div class="form-section">
                                <div class="form-section-title">💰 Pricing & Materials</div>
                                
                                <div class="bid-form__group">
                                    <label class="bid-form__label required-field" for="bid-amount">Total Bid Amount (£)</label>
                                    <input class="bid-form__input" 
                                           type="number" 
                                           step="0.01" 
                                           min="50" 
                                           max="50000" 
                                           id="bid-amount" 
                                           name="bid_amount" 
                                           value="<?php echo isset($_POST['bid_amount']) ? htmlspecialchars($_POST['bid_amount']) : ''; ?>"
                                           required 
                                           placeholder="Enter your competitive bid amount" />
                                    <?php if ($bidCount > 0): ?>
                                    <div class="pricing-suggestion">
                                        💡 <strong>Pricing Insight:</strong> Current bids range from £<?php echo number_format($lowestBid, 0); ?> to £<?php echo number_format($highestBid, 0); ?>. 
                                        Consider pricing competitively while highlighting your unique value proposition.
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="bid-form__group">
                                    <div class="bid-form__checkbox-group">
                                        <input type="checkbox" 
                                               id="materials-included" 
                                               name="materials_included" 
                                               <?php echo isset($_POST['materials_included']) ? 'checked' : ''; ?> />
                                        <label for="materials-included" style="font-weight:normal;margin:0;">
                                            <strong>Materials Included</strong> - My bid includes all necessary materials and supplies
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Timeline Section -->
                            <div class="form-section">
                                <div class="form-section-title">⏰ Project Timeline</div>
                                
                                <div class="bid-form__group">
                                    <label class="bid-form__label required-field" for="timeline">Estimated Project Duration</label>
                                    <select class="bid-form__select" id="timeline" name="timeline" required>
                                        <option value="">Select timeline...</option>
                                        <option value="1-2 days" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == '1-2 days') ? 'selected' : ''; ?>>1-2 days</option>
                                        <option value="3-5 days" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == '3-5 days') ? 'selected' : ''; ?>>3-5 days</option>
                                        <option value="1 week" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == '1 week') ? 'selected' : ''; ?>>1 week</option>
                                        <option value="2 weeks" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == '2 weeks') ? 'selected' : ''; ?>>2 weeks</option>
                                        <option value="3-4 weeks" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == '3-4 weeks') ? 'selected' : ''; ?>>3-4 weeks</option>
                                        <option value="1-2 months" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == '1-2 months') ? 'selected' : ''; ?>>1-2 months</option>
                                        <option value="2+ months" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == '2+ months') ? 'selected' : ''; ?>>2+ months</option>
                                        <option value="Flexible" <?php echo (isset($_POST['timeline']) && $_POST['timeline'] == 'Flexible') ? 'selected' : ''; ?>>Flexible</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Warranty Section -->
                            <div class="form-section">
                                <div class="form-section-title">🛡️ Warranty & Guarantees</div>
                                
                                <div class="bid-form__group">
                                    <label class="bid-form__label" for="warranty-period">Warranty Period (Months)</label>
                                    <select class="bid-form__select" id="warranty-period" name="warranty_period">
                                        <option value="0">No warranty offered</option>
                                        <option value="6" <?php echo (isset($_POST['warranty_period']) && $_POST['warranty_period'] == '6') ? 'selected' : ''; ?>>6 months</option>
                                        <option value="12" <?php echo (isset($_POST['warranty_period']) && $_POST['warranty_period'] == '12') ? 'selected' : ''; ?>>12 months</option>
                                        <option value="24" <?php echo (isset($_POST['warranty_period']) && $_POST['warranty_period'] == '24') ? 'selected' : ''; ?>>24 months</option>
                                        <option value="36" <?php echo (isset($_POST['warranty_period']) && $_POST['warranty_period'] == '36') ? 'selected' : ''; ?>>36 months</option>
                                    </select>
                                </div>
                                
                                <div class="bid-form__group">
                                    <label class="bid-form__label" for="warranty-details">Warranty Details</label>
                                    <textarea class="bid-form__textarea" 
                                              id="warranty-details" 
                                              name="warranty_details" 
                                              rows="3" 
                                              placeholder="Describe what your warranty covers (e.g., paint finish, workmanship, touch-ups)..."><?php echo isset($_POST['warranty_details']) ? htmlspecialchars($_POST['warranty_details']) : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Project Approach -->
                            <div class="form-section">
                                <div class="form-section-title">🎨 Project Approach</div>
                                
                                <div class="bid-form__group">
                                    <label class="bid-form__label" for="project-approach">Your Project Approach</label>
                                    <textarea class="bid-form__textarea" 
                                              id="project-approach" 
                                              name="project_approach" 
                                              rows="4" 
                                              maxlength="500"
                                              placeholder="Describe your approach: preparation methods, paint types, techniques, quality measures..."><?php echo isset($_POST['project_approach']) ? htmlspecialchars($_POST['project_approach']) : ''; ?></textarea>
                                    <div class="character-counter">
                                        <span id="approach-counter">0</span>/500 characters
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detailed Message -->
                            <div class="form-section">
                                <div class="form-section-title">💬 Detailed Proposal</div>
                                
                                <div class="bid-form__group">
                                    <label class="bid-form__label required-field" for="message">Detailed Message to Customer</label>
                                    <textarea class="bid-form__textarea" 
                                              id="message" 
                                              name="message" 
                                              rows="6" 
                                              required 
                                              minlength="20"
                                              maxlength="1500"
                                              placeholder="Explain why you're the best choice for this job. Include your experience, approach, and what makes your service unique. Be specific and professional."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                    <div class="character-counter">
                                        <span id="message-counter">0</span>/1500 characters (minimum 20)
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Section -->
                            <div class="form-section">
                                <div style="text-align:center;">
                                    <button type="submit" class="bid-form__submit" style="background:#00b050;color:#fff;font-weight:700;border:none;border-radius:1rem;padding:1rem 2rem;font-size:1.1rem;cursor:pointer;transition:background 0.2s;min-width:200px;">
                                        🚀 Submit Enhanced Bid
                                    </button>
                                    <div style="margin-top:1rem;">
                                        <a href="leads.php" style="color:#666;text-decoration:underline;">← Back to Available Leads</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
// Character counters
function updateCharacterCounter(textareaId, counterId, maxLength) {
    const textarea = document.getElementById(textareaId);
    const counter = document.getElementById(counterId);
    
    if (textarea && counter) {
        const currentLength = textarea.value.length;
        counter.textContent = currentLength;
        
        if (currentLength > maxLength * 0.9) {
            counter.style.color = '#dc2626';
        } else if (currentLength > maxLength * 0.7) {
            counter.style.color = '#f59e0b';
        } else {
            counter.style.color = '#666';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const approachTextarea = document.getElementById('project-approach');
    
    if (messageTextarea) {
        updateCharacterCounter('message', 'message-counter', 1500);
        messageTextarea.addEventListener('input', () => updateCharacterCounter('message', 'message-counter', 1500));
    }
    
    if (approachTextarea) {
        updateCharacterCounter('project-approach', 'approach-counter', 500);
        approachTextarea.addEventListener('input', () => updateCharacterCounter('project-approach', 'approach-counter', 500));
    }
    
    // Form validation
    const form = document.querySelector('.enhanced-bid-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const bidAmount = parseFloat(document.getElementById('bid-amount').value);
            const message = document.getElementById('message').value.trim();
            const timeline = document.getElementById('timeline').value;
            
            if (bidAmount < 50) {
                alert('Minimum bid amount is £50.');
                e.preventDefault();
                return;
            }
            
            if (bidAmount > 50000) {
                alert('Maximum bid amount is £50,000.');
                e.preventDefault();
                return;
            }
            
            if (message.length < 20) {
                alert('Please provide a detailed message (minimum 20 characters).');
                e.preventDefault();
                return;
            }
            
            if (!timeline) {
                alert('Please select an estimated timeline.');
                e.preventDefault();
                return;
            }
        });
    }
});

// Pricing intelligence helper
const bidAmountInput = document.getElementById('bid-amount');
if (bidAmountInput) {
    bidAmountInput.addEventListener('blur', function() {
        const bidAmount = parseFloat(this.value);
        <?php if ($bidCount > 0): ?>
        const lowestBid = <?php echo $lowestBid; ?>;
        const averageBid = <?php echo $averageBid; ?>;
        const highestBid = <?php echo $highestBid; ?>;
        
        if (bidAmount > 0) {
            let message = '';
            if (bidAmount <= lowestBid) {
                message = '💰 Your bid is the most competitive (lowest price)! Consider highlighting unique value-adds.';
            } else if (bidAmount <= averageBid) {
                message = '📊 Your bid is below average. Great competitive positioning!';
            } else if (bidAmount <= highestBid) {
                message = '📈 Your bid is above average. Make sure to emphasize premium quality and service.';
            } else {
                message = '💎 Your bid is the highest. Clearly communicate your premium value proposition.';
            }
            
            // Show temporary message
            let existingSuggestion = this.parentNode.querySelector('.dynamic-suggestion');
            if (existingSuggestion) {
                existingSuggestion.remove();
            }
            
            const suggestion = document.createElement('div');
            suggestion.className = 'pricing-suggestion dynamic-suggestion';
            suggestion.innerHTML = message;
            this.parentNode.appendChild(suggestion);
            
            setTimeout(() => {
                if (suggestion.parentNode) {
                    suggestion.parentNode.removeChild(suggestion);
                }
            }, 5000);
        }
        <?php endif; ?>
    });
}
</script>

<?php include 'templates/footer.php'; ?> 