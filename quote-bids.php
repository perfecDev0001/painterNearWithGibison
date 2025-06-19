<?php
require_once 'core/GibsonDataAccess.php';

$dataAccess = new GibsonDataAccess();

// Get lead information from URL parameter
$leadId = $_GET['lead_id'] ?? null;
$customerEmail = $_GET['email'] ?? null;

// Check if customer is logged in via dashboard session
session_start();
if (isset($_SESSION['customer_authenticated']) && $_SESSION['customer_authenticated']) {
    $customerEmail = $_SESSION['customer_email'];
}

if (!$leadId) {
    header('Location: customer-dashboard.php');
    exit();
}

// Get lead details
$leadResult = $dataAccess->getLead($leadId);
if (!$leadResult['success'] || !isset($leadResult['data'])) {
    header('Location: customer-dashboard.php');
    exit();
}

$lead = $leadResult['data'];

// Verify customer email matches (either from URL or session)
if (!$customerEmail || strtolower($lead['customer_email']) !== strtolower($customerEmail)) {
    header('Location: customer-dashboard.php');
    exit();
}

// Get all bids for this lead
$bids = $dataAccess->getLeadBids($leadId);

// Calculate competitive analytics
$bidAnalytics = [
    'total_bids' => count($bids),
    'lowest_bid' => 0,
    'highest_bid' => 0,
    'average_bid' => 0
];

if (!empty($bids)) {
    $bidAmounts = array_column($bids, 'bid_amount');
    $bidAnalytics['lowest_bid'] = min($bidAmounts);
    $bidAnalytics['highest_bid'] = max($bidAmounts);
    $bidAnalytics['average_bid'] = array_sum($bidAmounts) / count($bidAmounts);
}

// Handle bid acceptance/rejection
$actionMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bidId = $_POST['bid_id'] ?? '';
    
    if ($action && $bidId) {
        switch ($action) {
            case 'accept':
                $result = $dataAccess->updateBid($bidId, ['status' => 'accepted']);
                if ($result['success']) {
                    // Update lead status to assigned
                    $dataAccess->updateLeadStatus($leadId, 'assigned');
                    $actionMessage = 'Bid accepted successfully! The painter has been notified.';
                    
                    // Send notification to painter
                    // Add notification logic here if needed
                }
                break;
                
            case 'reject':
                $result = $dataAccess->updateBid($bidId, ['status' => 'rejected']);
                if ($result['success']) {
                    $actionMessage = 'Bid rejected. The painter has been notified.';
                }
                break;
        }
        
        // Refresh data after action
        $bids = $dataAccess->getLeadBids($leadId);
    }
}

include 'templates/header.php';
?>
<head>
    <title>View Bids - <?php echo htmlspecialchars($lead['job_title']); ?> | Painter Near Me</title>
    <meta name="description" content="View and compare painter bids for your job posting." />
    <meta property="og:title" content="View Bids for <?php echo htmlspecialchars($lead['job_title']); ?>">
    <meta property="og:description" content="Compare competitive bids from professional painters.">
    <meta property="og:type" content="website">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' fill='%2300b050'/%3E%3Ctext x='8' y='12' font-family='Arial' font-size='10' fill='white' text-anchor='middle'%3EP%3C/text%3E%3C/svg%3E" type="image/svg+xml">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
    </script>
</head>

<main class="quote-bids" role="main">
    <div class="quote-bids__container">
        <!-- Navigation -->
        <nav class="quote-bids__nav">
            <a href="customer-dashboard.php" class="quote-bids__nav-link quote-bids__nav-link--primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <a href="/" class="quote-bids__nav-link">
                <i class="bi bi-house"></i> Home
            </a>
        </nav>

        <!-- Header Section -->
        <section class="quote-bids__header">
            <div class="quote-bids__title-section">
                <h1 class="quote-bids__title">Bids for Your Painting Job</h1>
                <h2 class="quote-bids__job-title"><?php echo htmlspecialchars($lead['job_title']); ?></h2>
                <p class="quote-bids__job-meta">
                    <strong>Location:</strong> <?php echo htmlspecialchars($lead['location']); ?> | 
                    <strong>Posted:</strong> <?php echo $dataAccess->formatDate($lead['created_at']); ?>
                </p>
            </div>
        </section>

        <?php if ($actionMessage): ?>
        <section class="quote-bids__alert">
            <div class="quote-bids__alert-content">
                <i class="quote-bids__alert-icon">✅</i>
                <?php echo htmlspecialchars($actionMessage); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Analytics Dashboard -->
        <section class="quote-bids__analytics">
            <h3 class="quote-bids__analytics-title">📊 Bid Overview</h3>
            <div class="quote-bids__metrics">
                <div class="quote-bids__metric">
                    <div class="quote-bids__metric-value"><?php echo $bidAnalytics['total_bids']; ?></div>
                    <div class="quote-bids__metric-label">Total Bids</div>
                </div>
                <?php if ($bidAnalytics['total_bids'] > 0): ?>
                <div class="quote-bids__metric">
                    <div class="quote-bids__metric-value">£<?php echo number_format($bidAnalytics['lowest_bid'], 0); ?></div>
                    <div class="quote-bids__metric-label">Lowest Bid</div>
                </div>
                <div class="quote-bids__metric">
                    <div class="quote-bids__metric-value">£<?php echo number_format($bidAnalytics['average_bid'], 0); ?></div>
                    <div class="quote-bids__metric-label">Average Bid</div>
                </div>
                <div class="quote-bids__metric">
                    <div class="quote-bids__metric-value">£<?php echo number_format($bidAnalytics['highest_bid'], 0); ?></div>
                    <div class="quote-bids__metric-label">Highest Bid</div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($bidAnalytics['total_bids'] > 1): ?>
            <div class="quote-bids__insights">
                <h4>💡 Pricing Insights</h4>
                <div class="quote-bids__insights-grid">
                    <div class="quote-bids__insight">
                        <strong>Price Range:</strong> 
                        £<?php echo number_format($bidAnalytics['highest_bid'] - $bidAnalytics['lowest_bid'], 0); ?> difference between highest and lowest
                    </div>
                    <div class="quote-bids__insight">
                        <strong>Market Average:</strong> 
                        Most bids are around £<?php echo number_format($bidAnalytics['average_bid'], 0); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Bids List -->
        <section class="quote-bids__list">
            <?php if (empty($bids)): ?>
            <div class="quote-bids__empty">
                <div class="quote-bids__empty-icon">📭</div>
                <h3>No Bids Yet</h3>
                <p>Your job has been posted and is visible to local painters. Bids typically start arriving within 24-48 hours.</p>
                <div class="quote-bids__empty-tips">
                    <h4>💡 Tips while you wait:</h4>
                    <ul>
                        <li>Make sure your job description is detailed and clear</li>
                        <li>Consider the urgency of your project</li>
                        <li>Be patient - quality painters may take time to prepare detailed bids</li>
                    </ul>
                </div>
            </div>
            <?php else: ?>
            <h3 class="quote-bids__list-title">🎨 Painter Bids (<?php echo count($bids); ?>)</h3>
            
            <div class="quote-bids__grid">
                <?php foreach ($bids as $bid): ?>
                <div class="quote-bids__card <?php echo $bid['status'] === 'accepted' ? 'quote-bids__card--accepted' : ''; ?>">
                    <div class="quote-bids__card-header">
                        <div class="quote-bids__painter-info">
                            <h4 class="quote-bids__painter-name"><?php echo htmlspecialchars($bid['company_name'] ?? 'Professional Painter'); ?></h4>
                            <div class="quote-bids__bid-amount">£<?php echo number_format($bid['bid_amount'], 2); ?></div>
                        </div>
                        <div class="quote-bids__status">
                            <?php
                            $statusClass = '';
                            $statusText = '';
                            switch ($bid['status']) {
                                case 'accepted':
                                    $statusClass = 'quote-bids__status--accepted';
                                    $statusText = '✅ Accepted';
                                    break;
                                case 'rejected':
                                    $statusClass = 'quote-bids__status--rejected';
                                    $statusText = '❌ Rejected';
                                    break;
                                default:
                                    $statusClass = 'quote-bids__status--pending';
                                    $statusText = '⏳ Pending';
                            }
                            ?>
                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </div>
                    </div>
                    
                    <!-- Bid Details -->
                    <div class="quote-bids__card-content">
                        <?php if (!empty($bid['timeline'])): ?>
                        <div class="quote-bids__detail">
                            <strong>Timeline:</strong> <?php echo htmlspecialchars($bid['timeline']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($bid['materials_included'])): ?>
                        <div class="quote-bids__detail">
                            <strong>Materials:</strong> <?php echo $bid['materials_included'] ? 'Included in price' : 'Not included'; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bid['warranty_period'])): ?>
                        <div class="quote-bids__detail">
                            <strong>Warranty:</strong> <?php echo $bid['warranty_period']; ?> months
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bid['message'])): ?>
                        <div class="quote-bids__message">
                            <strong>Proposal Message:</strong>
                            <p><?php echo nl2br(htmlspecialchars($bid['message'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="quote-bids__meta">
                            <small>Submitted: <?php echo $dataAccess->formatDateTime($bid['created_at']); ?></small>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <?php if ($bid['status'] === 'pending'): ?>
                    <div class="quote-bids__actions">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="accept">
                            <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                            <button type="submit" class="quote-bids__btn quote-bids__btn--accept" 
                                    onclick="return confirm('Accept this bid? This will assign the painter to your job.')">
                                ✅ Accept Bid
                            </button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                            <button type="submit" class="quote-bids__btn quote-bids__btn--reject"
                                    onclick="return confirm('Reject this bid? The painter will be notified.')">
                                ❌ Reject Bid
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Competitive Analysis -->
                    <?php if ($bidAnalytics['total_bids'] > 1): ?>
                    <div class="quote-bids__competitive-analysis">
                        <?php
                        $position = '';
                        $positionClass = '';
                        if ($bid['bid_amount'] == $bidAnalytics['lowest_bid']) {
                            $position = '🏆 Lowest Price';
                            $positionClass = 'quote-bids__position--best';
                        } elseif ($bid['bid_amount'] == $bidAnalytics['highest_bid']) {
                            $position = '💎 Premium Option';
                            $positionClass = 'quote-bids__position--premium';
                        } else {
                            $position = '📊 Mid-Range';
                            $positionClass = 'quote-bids__position--mid';
                        }
                        ?>
                        <span class="quote-bids__position <?php echo $positionClass; ?>"><?php echo $position; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Decision Guide -->
        <?php if (!empty($bids) && $bidAnalytics['total_bids'] > 1): ?>
        <section class="quote-bids__guide">
            <h3 class="quote-bids__guide-title">🤔 Decision Guide</h3>
            <div class="quote-bids__guide-content">
                <div class="quote-bids__guide-section">
                    <h4>Consider These Factors:</h4>
                    <ul>
                        <li><strong>Price vs Value:</strong> The lowest bid isn't always the best choice</li>
                        <li><strong>Timeline:</strong> How quickly do you need the work completed?</li>
                        <li><strong>Materials:</strong> Are materials included in the quoted price?</li>
                        <li><strong>Warranty:</strong> What guarantee do you get on the work?</li>
                        <li><strong>Communication:</strong> How detailed and professional is their proposal?</li>
                    </ul>
                </div>
                
                <div class="quote-bids__guide-section">
                    <h4>Red Flags to Watch:</h4>
                    <ul>
                        <li>Bids significantly lower than others (may indicate corner-cutting)</li>
                        <li>Vague or unprofessional proposals</li>
                        <li>No mention of materials or warranty</li>
                        <li>Unrealistic timelines (too fast or too slow)</li>
                    </ul>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Job Description Reference -->
        <section class="quote-bids__job-details">
            <h3 class="quote-bids__job-details-title">📋 Your Original Job Description</h3>
            <div class="quote-bids__job-description">
                <?php echo nl2br(htmlspecialchars($lead['job_description'])); ?>
            </div>
        </section>

    </div>
</main>

<?php include 'templates/footer.php'; ?>

<style>
/* Quote Bids Customer Dashboard Styles */
.quote-bids {
    min-height: 100vh;
    background: #f8fffe;
    padding: 2rem 1rem;
}

/* Navigation */
.quote-bids__nav {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.quote-bids__nav-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 1rem;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
    border: 2px solid #e5e7eb;
    color: #6b7280;
}

.quote-bids__nav-link:hover {
    color: #00b050;
    border-color: #00b050;
    text-decoration: none;
}

.quote-bids__nav-link--primary {
    background: #00b050;
    color: #fff;
    border-color: #00b050;
}

.quote-bids__nav-link--primary:hover {
    background: #00913d;
    color: #fff;
    border-color: #00913d;
}

.quote-bids__container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Header Section */
.quote-bids__header {
    background: #fff;
    border-radius: 1.2rem;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08), 0 1.5px 8px rgba(0,0,0,0.04);
    border-left: 4px solid #00b050;
}

.quote-bids__title {
    color: #00b050;
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
}

.quote-bids__job-title {
    color: #333;
    font-size: 1.4rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
}

.quote-bids__job-meta {
    color: #666;
    font-size: 1rem;
    margin: 0;
}

/* Alert Section */
.quote-bids__alert {
    background: #e6f7ea;
    border: 2px solid #00b050;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.quote-bids__alert-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #00b050;
    font-weight: 600;
}

.quote-bids__alert-icon {
    font-size: 1.2rem;
}

/* Analytics Section */
.quote-bids__analytics {
    background: #fff;
    border-radius: 1.2rem;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08), 0 1.5px 8px rgba(0,0,0,0.04);
}

.quote-bids__analytics-title {
    color: #333;
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
}

.quote-bids__metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.quote-bids__metric {
    text-align: center;
    padding: 1.5rem;
    background: #f8fffe;
    border-radius: 1rem;
    border: 2px solid #e8f5e8;
}

.quote-bids__metric-value {
    font-size: 2rem;
    font-weight: 800;
    color: #00b050;
    margin-bottom: 0.5rem;
}

.quote-bids__metric-label {
    color: #666;
    font-weight: 600;
    font-size: 0.9rem;
}

.quote-bids__insights {
    background: #f8fffe;
    border: 1px solid #e8f5e8;
    border-radius: 1rem;
    padding: 1.5rem;
}

.quote-bids__insights h4 {
    color: #333;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

.quote-bids__insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.quote-bids__insight {
    color: #555;
    padding: 0.5rem 0;
}

/* Empty State */
.quote-bids__empty {
    background: #fff;
    border-radius: 1.2rem;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08), 0 1.5px 8px rgba(0,0,0,0.04);
}

.quote-bids__empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.quote-bids__empty h3 {
    color: #333;
    font-size: 1.5rem;
    margin: 0 0 1rem 0;
}

.quote-bids__empty p {
    color: #666;
    font-size: 1.1rem;
    margin: 0 0 2rem 0;
}

.quote-bids__empty-tips {
    background: #f8fffe;
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: left;
    max-width: 500px;
    margin: 0 auto;
}

.quote-bids__empty-tips h4 {
    color: #333;
    margin: 0 0 1rem 0;
}

.quote-bids__empty-tips ul {
    color: #555;
    margin: 0;
    padding-left: 1.2rem;
}

.quote-bids__empty-tips li {
    margin-bottom: 0.5rem;
}

/* Bids List */
.quote-bids__list {
    margin-bottom: 2rem;
}

.quote-bids__list-title {
    color: #333;
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
}

.quote-bids__grid {
    display: grid;
    gap: 1.5rem;
}

/* Bid Cards */
.quote-bids__card {
    background: #fff;
    border-radius: 1.2rem;
    padding: 1.5rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08), 0 1.5px 8px rgba(0,0,0,0.04);
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.quote-bids__card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,176,80,0.12), 0 3px 12px rgba(0,0,0,0.08);
}

.quote-bids__card--accepted {
    border-color: #00b050;
    background: #f8fffe;
}

.quote-bids__card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e8f5e8;
}

.quote-bids__painter-name {
    color: #333;
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.quote-bids__bid-amount {
    color: #00b050;
    font-size: 1.8rem;
    font-weight: 800;
}

/* Status Badges */
.quote-bids__status--pending {
    background: #fff3cd;
    color: #856404;
    padding: 0.4rem 0.8rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
}

.quote-bids__status--accepted {
    background: #d4edda;
    color: #155724;
    padding: 0.4rem 0.8rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
}

.quote-bids__status--rejected {
    background: #f8d7da;
    color: #721c24;
    padding: 0.4rem 0.8rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Card Content */
.quote-bids__card-content {
    margin-bottom: 1.5rem;
}

.quote-bids__detail {
    color: #555;
    margin-bottom: 0.8rem;
    font-size: 0.95rem;
}

.quote-bids__message {
    background: #f8fffe;
    border-radius: 0.8rem;
    padding: 1rem;
    margin: 1rem 0;
}

.quote-bids__message strong {
    color: #333;
    display: block;
    margin-bottom: 0.5rem;
}

.quote-bids__message p {
    color: #555;
    margin: 0;
    line-height: 1.5;
}

.quote-bids__meta {
    color: #888;
    font-size: 0.85rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #f0f0f0;
}

/* Action Buttons */
.quote-bids__actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.quote-bids__btn {
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.quote-bids__btn--accept {
    background: #00b050;
    color: white;
}

.quote-bids__btn--accept:hover {
    background: #009140;
    transform: translateY(-1px);
}

.quote-bids__btn--reject {
    background: #6c757d;
    color: white;
}

.quote-bids__btn--reject:hover {
    background: #545b62;
    transform: translateY(-1px);
}

/* Competitive Analysis */
.quote-bids__competitive-analysis {
    margin-top: 1rem;
    text-align: center;
}

.quote-bids__position {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 0.6rem;
    font-weight: 600;
    font-size: 0.85rem;
}

.quote-bids__position--best {
    background: #d4edda;
    color: #155724;
}

.quote-bids__position--premium {
    background: #e2e3e5;
    color: #495057;
}

.quote-bids__position--mid {
    background: #d1ecf1;
    color: #0c5460;
}

/* Decision Guide */
.quote-bids__guide {
    background: #fff;
    border-radius: 1.2rem;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08), 0 1.5px 8px rgba(0,0,0,0.04);
}

.quote-bids__guide-title {
    color: #333;
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
}

.quote-bids__guide-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.quote-bids__guide-section h4 {
    color: #00b050;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

.quote-bids__guide-section ul {
    color: #555;
    margin: 0;
    padding-left: 1.2rem;
    line-height: 1.6;
}

.quote-bids__guide-section li {
    margin-bottom: 0.6rem;
}

/* Job Details */
.quote-bids__job-details {
    background: #fff;
    border-radius: 1.2rem;
    padding: 2rem;
    box-shadow: 0 4px 16px rgba(0,176,80,0.08), 0 1.5px 8px rgba(0,0,0,0.04);
}

.quote-bids__job-details-title {
    color: #333;
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
}

.quote-bids__job-description {
    background: #f8fffe;
    border-radius: 1rem;
    padding: 1.5rem;
    color: #555;
    line-height: 1.6;
    border: 1px solid #e8f5e8;
}

/* Responsive Design */
@media (max-width: 768px) {
    .quote-bids {
        padding: 1rem 0.5rem;
    }
    
    .quote-bids__header,
    .quote-bids__analytics,
    .quote-bids__guide,
    .quote-bids__job-details {
        padding: 1.5rem;
    }
    
    .quote-bids__title {
        font-size: 1.6rem;
    }
    
    .quote-bids__job-title {
        font-size: 1.2rem;
    }
    
    .quote-bids__metrics {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .quote-bids__metric {
        padding: 1rem;
    }
    
    .quote-bids__metric-value {
        font-size: 1.5rem;
    }
    
    .quote-bids__card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .quote-bids__actions {
        flex-direction: column;
    }
    
    .quote-bids__guide-content {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .quote-bids__insights-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .quote-bids__container {
        padding: 0 0.5rem;
    }
    
    .quote-bids__card {
        padding: 1rem;
    }
    
    .quote-bids__bid-amount {
        font-size: 1.5rem;
    }
    
    .quote-bids__metrics {
        grid-template-columns: 1fr 1fr;
    }
}
</style> 