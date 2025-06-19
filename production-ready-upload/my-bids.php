<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Require login
$auth->requireLogin();

$painterId = $auth->getCurrentPainterId();

// Handle bid actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $bidId = intval($_POST['bid_id'] ?? 0);
    $action = $_POST['action'];
    
    switch ($action) {
        case 'withdraw':
            if ($bidId) {
                $dataAccess->updateBid($bidId, ['status' => 'withdrawn']);
                $success = "Bid withdrawn successfully.";
            }
            break;
        case 'resubmit':
            if ($bidId && isset($_POST['new_amount'])) {
                $newAmount = floatval($_POST['new_amount']);
                if ($newAmount > 0) {
                    $dataAccess->updateBid($bidId, [
                        'bid_amount' => $newAmount,
                        'status' => 'pending',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $success = "Bid updated and resubmitted successfully.";
                }
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    if (isset($success)) {
        header('Location: my-bids.php?success=' . urlencode($success));
        exit();
    }
}

// Get filters and sorting
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'desc';
$search = $_GET['search'] ?? '';

// Fetch all bids by this painter
$bids = $dataAccess->getBidsByPainter($painterId);

// Apply filters and sorting
$filteredBids = $bids;

// Filter by status
if ($statusFilter !== 'all') {
    $filteredBids = array_filter($filteredBids, function($bid) use ($statusFilter) {
        return $bid['status'] === $statusFilter;
    });
}

// Filter by search
if (!empty($search)) {
    $filteredBids = array_filter($filteredBids, function($bid) use ($search) {
        return stripos($bid['job_title'], $search) !== false || 
               stripos($bid['location'], $search) !== false ||
               stripos($bid['message'], $search) !== false;
    });
}

// Sort bids
usort($filteredBids, function($a, $b) use ($sortBy, $sortOrder) {
    $aVal = $a[$sortBy] ?? 0;
    $bVal = $b[$sortBy] ?? 0;
    
    if ($sortBy === 'bid_amount') {
        $result = floatval($aVal) <=> floatval($bVal);
    } else {
        $result = strcmp($aVal, $bVal);
    }
    
    return $sortOrder === 'desc' ? -$result : $result;
});

// Calculate analytics
$totalBids = count($bids);
$pendingBids = count(array_filter($bids, fn($b) => $b['status'] === 'pending'));
$acceptedBids = count(array_filter($bids, fn($b) => $b['status'] === 'accepted'));
$rejectedBids = count(array_filter($bids, fn($b) => $b['status'] === 'rejected'));
$withdrawnBids = count(array_filter($bids, fn($b) => $b['status'] === 'withdrawn'));

$winRate = $totalBids > 0 ? round(($acceptedBids / $totalBids) * 100, 1) : 0;

$totalBidValue = array_sum(array_map(fn($b) => floatval($b['bid_amount']), $bids));
$avgBidValue = $totalBids > 0 ? $totalBidValue / $totalBids : 0;

$acceptedBidValue = array_sum(array_map(fn($b) => floatval($b['bid_amount']), 
    array_filter($bids, fn($b) => $b['status'] === 'accepted')));

// Get recent activity
$recentBids = array_slice(array_reverse($bids), 0, 5);

include 'templates/header.php';
?>
<head>
    <title>My Bids Dashboard | Painter Near Me</title>
    <meta name="description" content="Comprehensive bid management dashboard for painters on Painter Near Me." />
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
    </script>
</head>

<main class="bid-dashboard" role="main">
    <div class="bid-dashboard__layout">
        <aside class="bid-dashboard__sidebar">
            <?php include 'templates/sidebar-painter.php'; ?>
        </aside>
        
        <div class="bid-dashboard__main">
            <!-- Success Message -->
            <?php if (isset($_GET['success'])): ?>
                <div class="bid-dashboard__alert bid-dashboard__alert--success">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Header Section -->
            <section class="bid-dashboard__header">
                <div class="bid-dashboard__title-section">
                    <h1 class="bid-dashboard__title">Bid Management Dashboard</h1>
                    <p class="bid-dashboard__subtitle">Comprehensive tracking and analytics for all your bids</p>
                </div>
                
                <!-- Quick Stats -->
                <div class="bid-dashboard__quick-stats">
                    <div class="bid-stat">
                        <div class="bid-stat__value"><?php echo $totalBids; ?></div>
                        <div class="bid-stat__label">Total Bids</div>
                    </div>
                    <div class="bid-stat bid-stat--success">
                        <div class="bid-stat__value"><?php echo $winRate; ?>%</div>
                        <div class="bid-stat__label">Win Rate</div>
                    </div>
                    <div class="bid-stat bid-stat--primary">
                        <div class="bid-stat__value">£<?php echo number_format($acceptedBidValue, 0); ?></div>
                        <div class="bid-stat__label">Won Value</div>
                    </div>
                </div>
            </section>

            <!-- Analytics Section -->
            <section class="bid-dashboard__analytics">
                <h2 class="bid-dashboard__section-title">Performance Analytics</h2>
                
                <div class="bid-analytics">
                    <div class="bid-analytics__grid">
                        <!-- Bid Status Distribution -->
                        <div class="analytics-card">
                            <h3 class="analytics-card__title">Bid Status Distribution</h3>
                            <div class="analytics-card__content">
                                <div class="status-chart">
                                    <div class="status-item">
                                        <span class="status-indicator status-indicator--pending"></span>
                                        <span class="status-label">Pending: <?php echo $pendingBids; ?></span>
                                        <span class="status-percentage"><?php echo $totalBids > 0 ? round(($pendingBids/$totalBids)*100, 1) : 0; ?>%</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-indicator status-indicator--accepted"></span>
                                        <span class="status-label">Accepted: <?php echo $acceptedBids; ?></span>
                                        <span class="status-percentage"><?php echo $totalBids > 0 ? round(($acceptedBids/$totalBids)*100, 1) : 0; ?>%</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-indicator status-indicator--rejected"></span>
                                        <span class="status-label">Rejected: <?php echo $rejectedBids; ?></span>
                                        <span class="status-percentage"><?php echo $totalBids > 0 ? round(($rejectedBids/$totalBids)*100, 1) : 0; ?>%</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-indicator status-indicator--withdrawn"></span>
                                        <span class="status-label">Withdrawn: <?php echo $withdrawnBids; ?></span>
                                        <span class="status-percentage"><?php echo $totalBids > 0 ? round(($withdrawnBids/$totalBids)*100, 1) : 0; ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Overview -->
                        <div class="analytics-card">
                            <h3 class="analytics-card__title">Financial Overview</h3>
                            <div class="analytics-card__content">
                                <div class="financial-metrics">
                                    <div class="metric">
                                        <div class="metric__value">£<?php echo number_format($totalBidValue, 0); ?></div>
                                        <div class="metric__label">Total Bid Value</div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric__value">£<?php echo number_format($avgBidValue, 0); ?></div>
                                        <div class="metric__label">Average Bid</div>
                                    </div>
                                    <div class="metric metric--success">
                                        <div class="metric__value">£<?php echo number_format($acceptedBidValue, 0); ?></div>
                                        <div class="metric__label">Accepted Value</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="analytics-card analytics-card--full">
                            <h3 class="analytics-card__title">Recent Activity</h3>
                            <div class="analytics-card__content">
                                <?php if (empty($recentBids)): ?>
                                    <p class="empty-state">No recent bid activity</p>
                                <?php else: ?>
                                    <div class="activity-list">
                                        <?php foreach ($recentBids as $bid): ?>
                                            <div class="activity-item">
                                                <div class="activity-item__info">
                                                    <div class="activity-item__title"><?php echo htmlspecialchars($bid['job_title']); ?></div>
                                                    <div class="activity-item__meta">
                                                        £<?php echo number_format($bid['bid_amount'], 0); ?> • 
                                                        <?php echo $dataAccess->formatDate($bid['created_at']); ?>
                                                    </div>
                                                </div>
                                                <div class="activity-item__status status-badge status-badge--<?php echo $bid['status']; ?>">
                                                    <?php echo ucfirst($bid['status']); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Filters and Search -->
            <section class="bid-dashboard__filters">
                <div class="filters-toolbar">
                    <div class="filters-toolbar__search">
                        <form method="GET" class="search-form">
                            <input type="text" name="search" placeholder="Search bids..." 
                                   value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                            <button type="submit" class="search-btn">
                                <i class="icon-search">🔍</i>
                            </button>
                        </form>
                    </div>
                    
                    <div class="filters-toolbar__controls">
                        <div class="filter-group">
                            <label for="status-filter" class="filter-label">Status:</label>
                            <select name="status" id="status-filter" class="filter-select" onchange="updateFilters()">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="accepted" <?php echo $statusFilter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="withdrawn" <?php echo $statusFilter === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort-filter" class="filter-label">Sort by:</label>
                            <select name="sort" id="sort-filter" class="filter-select" onchange="updateFilters()">
                                <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Date</option>
                                <option value="bid_amount" <?php echo $sortBy === 'bid_amount' ? 'selected' : ''; ?>>Amount</option>
                                <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                                <option value="job_title" <?php echo $sortBy === 'job_title' ? 'selected' : ''; ?>>Job Title</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="order-filter" class="filter-label">Order:</label>
                            <select name="order" id="order-filter" class="filter-select" onchange="updateFilters()">
                                <option value="desc" <?php echo $sortOrder === 'desc' ? 'selected' : ''; ?>>Descending</option>
                                <option value="asc" <?php echo $sortOrder === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="filters-summary">
                    Showing <?php echo count($filteredBids); ?> of <?php echo $totalBids; ?> bids
                </div>
            </section>

            <!-- Bids List -->
            <section class="bid-dashboard__list">
                <?php if (empty($filteredBids)): ?>
                    <div class="empty-state-card">
                        <div class="empty-state-card__icon">📊</div>
                        <h3 class="empty-state-card__title">No Bids Found</h3>
                        <p class="empty-state-card__message">
                            <?php if (empty($bids)): ?>
                                You haven't submitted any bids yet. Start bidding on leads to see them here!
                            <?php else: ?>
                                No bids match your current filters. Try adjusting your search criteria.
                            <?php endif; ?>
                        </p>
                        <?php if (empty($bids)): ?>
                            <a href="leads.php" class="btn btn--primary">Browse Available Leads</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bids-grid">
                        <?php foreach ($filteredBids as $bid): ?>
                            <div class="bid-card" data-bid-id="<?php echo $bid['id']; ?>">
                                <div class="bid-card__header">
                                    <div class="bid-card__title-section">
                                        <h3 class="bid-card__title"><?php echo htmlspecialchars($bid['job_title']); ?></h3>
                                        <div class="bid-card__location">📍 <?php echo htmlspecialchars($bid['location']); ?></div>
                                    </div>
                                    <div class="bid-card__status">
                                        <span class="status-badge status-badge--<?php echo $bid['status']; ?>">
                                            <?php echo ucfirst($bid['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="bid-card__content">
                                    <div class="bid-card__metrics">
                                        <div class="metric-item">
                                            <span class="metric-label">Your Bid:</span>
                                            <span class="metric-value metric-value--large">£<?php echo number_format($bid['bid_amount'], 0); ?></span>
                                        </div>
                                        
                                        <?php if (isset($bid['competitor_count']) && $bid['competitor_count'] > 0): ?>
                                            <div class="metric-item">
                                                <span class="metric-label">Competing Bids:</span>
                                                <span class="metric-value"><?php echo $bid['competitor_count']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($bid['lead_budget']) && $bid['lead_budget'] > 0): ?>
                                            <div class="metric-item">
                                                <span class="metric-label">Lead Budget:</span>
                                                <span class="metric-value">£<?php echo number_format($bid['lead_budget'], 0); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($bid['message'])): ?>
                                        <div class="bid-card__message">
                                            <div class="message-label">Your Proposal:</div>
                                            <div class="message-content"><?php echo nl2br(htmlspecialchars(substr($bid['message'], 0, 150))); ?><?php echo strlen($bid['message']) > 150 ? '...' : ''; ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="bid-card__timeline">
                                        <div class="timeline-item">
                                            <span class="timeline-label">Submitted:</span>
                                            <span class="timeline-value"><?php echo $dataAccess->formatDate($bid['created_at']); ?></span>
                                        </div>
                                        
                                        <?php if (isset($bid['updated_at']) && $bid['updated_at'] !== $bid['created_at']): ?>
                                            <div class="timeline-item">
                                                <span class="timeline-label">Updated:</span>
                                                <span class="timeline-value"><?php echo $dataAccess->formatDate($bid['updated_at']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="bid-card__actions">
                                    <?php if ($bid['status'] === 'pending'): ?>
                                        <button class="btn btn--outline btn--small" onclick="editBid(<?php echo $bid['id']; ?>, <?php echo $bid['bid_amount']; ?>)">
                                            Edit Bid
                                        </button>
                                        <button class="btn btn--ghost btn--small" onclick="withdrawBid(<?php echo $bid['id']; ?>)">
                                            Withdraw
                                        </button>
                                    <?php elseif ($bid['status'] === 'rejected'): ?>
                                        <button class="btn btn--outline btn--small" onclick="editBid(<?php echo $bid['id']; ?>, <?php echo $bid['bid_amount']; ?>)">
                                            Resubmit
                                        </button>
                                    <?php elseif ($bid['status'] === 'accepted'): ?>
                                        <div class="success-message">🎉 Congratulations! Your bid was accepted.</div>
                                    <?php endif; ?>
                                    
                                    <a href="bid.php?lead_id=<?php echo $bid['lead_id']; ?>" class="btn btn--ghost btn--small">
                                        View Lead
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<!-- Edit Bid Modal -->
<div id="editBidModal" class="modal" style="display: none;">
    <div class="modal__overlay" onclick="closeModal()"></div>
    <div class="modal__content">
        <div class="modal__header">
            <h3 class="modal__title">Edit Bid Amount</h3>
            <button class="modal__close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" class="modal__form">
            <input type="hidden" name="action" value="resubmit">
            <input type="hidden" name="bid_id" id="editBidId">
            
            <div class="form-group">
                <label for="editBidAmount" class="form-label">New Bid Amount (£)</label>
                <input type="number" name="new_amount" id="editBidAmount" class="form-input" 
                       min="1" step="1" required>
                <div class="form-help">Enter your updated bid amount</div>
            </div>
            
            <div class="modal__actions">
                <button type="button" class="btn btn--outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn--primary">Update Bid</button>
            </div>
        </form>
    </div>
</div>

<!-- Withdraw Bid Modal -->
<div id="withdrawBidModal" class="modal" style="display: none;">
    <div class="modal__overlay" onclick="closeModal()"></div>
    <div class="modal__content">
        <div class="modal__header">
            <h3 class="modal__title">Withdraw Bid</h3>
            <button class="modal__close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal__body">
            <p>Are you sure you want to withdraw this bid? This action cannot be undone.</p>
        </div>
        <form method="POST" class="modal__form">
            <input type="hidden" name="action" value="withdraw">
            <input type="hidden" name="bid_id" id="withdrawBidId">
            
            <div class="modal__actions">
                <button type="button" class="btn btn--outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn--error">Withdraw Bid</button>
            </div>
        </form>
    </div>
</div>

<?php include 'templates/footer.php'; ?>

<style>
/* Bid Dashboard Layout */
.bid-dashboard {
    background: #f8fafc;
    min-height: 100vh;
    padding: 2rem 0;
}

.bid-dashboard__layout {
    display: flex;
    gap: 2rem;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.bid-dashboard__sidebar {
    width: 280px;
    flex-shrink: 0;
}

.bid-dashboard__main {
    flex: 1;
    min-width: 0;
}

/* Alert Messages */
.bid-dashboard__alert {
    padding: 1rem 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    font-weight: 600;
}

.bid-dashboard__alert--success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

/* Header Section */
.bid-dashboard__header {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.bid-dashboard__title-section {
    margin-bottom: 2rem;
}

.bid-dashboard__title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #00b050;
    margin: 0 0 0.5rem 0;
}

.bid-dashboard__subtitle {
    font-size: 1.1rem;
    color: #6b7280;
    margin: 0;
}

.bid-dashboard__quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.bid-stat {
    text-align: center;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 0.75rem;
    border: 2px solid #e5e7eb;
}

.bid-stat--success {
    background: #d1fae5;
    border-color: #10b981;
}

.bid-stat--primary {
    background: #dbeafe;
    border-color: #3b82f6;
}

.bid-stat__value {
    font-size: 2rem;
    font-weight: 800;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.bid-stat__label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 600;
}

/* Analytics Section */
.bid-dashboard__analytics {
    margin-bottom: 2rem;
}

.bid-dashboard__section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 1.5rem 0;
}

.bid-analytics__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.analytics-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.analytics-card--full {
    grid-column: 1 / -1;
}

.analytics-card__title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 1rem 0;
}

.analytics-card__content {
    color: #4b5563;
}

/* Status Chart */
.status-chart {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.status-indicator--pending { background: #f59e0b; }
.status-indicator--accepted { background: #10b981; }
.status-indicator--rejected { background: #ef4444; }
.status-indicator--withdrawn { background: #6b7280; }

.status-label {
    flex: 1;
    font-weight: 500;
}

.status-percentage {
    font-weight: 600;
    color: #00b050;
}

/* Financial Metrics */
.financial-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
}

.metric {
    text-align: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 0.5rem;
}

.metric--success {
    background: #d1fae5;
}

.metric__value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.metric__label {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 500;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.activity-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 0.5rem;
}

.activity-item__info {
    flex: 1;
}

.activity-item__title {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.activity-item__meta {
    font-size: 0.875rem;
    color: #6b7280;
}

.activity-item__status {
    margin-left: 1rem;
}

/* Filters Section */
.bid-dashboard__filters {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.filters-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.filters-toolbar__search {
    flex: 1;
    max-width: 400px;
}

.search-form {
    display: flex;
    gap: 0.5rem;
}

.search-input {
    flex: 1;
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
}

.search-btn {
    padding: 0.5rem 1rem;
    background: #00b050;
    color: white;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
}

.filters-toolbar__controls {
    display: flex;
    gap: 1rem;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
}

.filter-select {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    background: white;
}

.filters-summary {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge--pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge--accepted {
    background: #d1fae5;
    color: #065f46;
}

.status-badge--rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge--withdrawn {
    background: #f3f4f6;
    color: #374151;
}

/* Bids Grid */
.bids-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

.bid-card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
    overflow: hidden;
}

.bid-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.bid-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem 1.5rem 0 1.5rem;
    margin-bottom: 1rem;
}

.bid-card__title-section {
    flex: 1;
}

.bid-card__title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem 0;
    line-height: 1.3;
}

.bid-card__location {
    font-size: 0.875rem;
    color: #6b7280;
}

.bid-card__content {
    padding: 0 1.5rem;
    margin-bottom: 1.5rem;
}

.bid-card__metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.metric-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.metric-label {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 500;
}

.metric-value {
    font-weight: 700;
    color: #1f2937;
}

.metric-value--large {
    font-size: 1.25rem;
    color: #00b050;
}

.bid-card__message {
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 0.5rem;
}

.message-label {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.message-content {
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.4;
}

.bid-card__timeline {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.timeline-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
}

.timeline-label {
    color: #6b7280;
    font-weight: 500;
}

.timeline-value {
    color: #374151;
    font-weight: 600;
}

.bid-card__actions {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.success-message {
    color: #065f46;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Empty State */
.empty-state-card {
    background: white;
    border-radius: 1rem;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.empty-state-card__icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.empty-state-card__title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem 0;
}

.empty-state-card__message {
    color: #6b7280;
    margin: 0 0 2rem 0;
    line-height: 1.5;
}

/* Buttons */
.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
}

.btn--small {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
}

.btn--primary {
    background: #00b050;
    color: white;
}

.btn--primary:hover {
    background: #059142;
}

.btn--outline {
    background: transparent;
    color: #00b050;
    border: 1px solid #00b050;
}

.btn--outline:hover {
    background: #00b050;
    color: white;
}

.btn--ghost {
    background: transparent;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

.btn--ghost:hover {
    background: #f3f4f6;
    color: #374151;
}

.btn--error {
    background: #ef4444;
    color: white;
}

.btn--error:hover {
    background: #dc2626;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
}

.modal__overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal__content {
    position: relative;
    background: white;
    border-radius: 1rem;
    max-width: 500px;
    margin: 5% auto;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
}

.modal__title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.modal__close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal__body {
    padding: 1rem 1.5rem;
}

.modal__form {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
}

.modal__actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 1rem;
}

.form-input:focus {
    outline: none;
    border-color: #00b050;
    box-shadow: 0 0 0 2px rgba(0, 176, 80, 0.1);
}

.form-help {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .bid-dashboard__layout {
        flex-direction: column;
    }
    
    .bid-dashboard__sidebar {
        width: 100%;
    }
    
    .filters-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .filters-toolbar__controls {
        flex-wrap: wrap;
    }
    
    .bids-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .bid-dashboard {
        padding: 1rem 0;
    }
    
    .bid-dashboard__layout {
        padding: 0 0.5rem;
    }
    
    .bid-dashboard__title {
        font-size: 2rem;
    }
    
    .bid-dashboard__quick-stats {
        grid-template-columns: 1fr;
    }
    
    .bid-analytics__grid {
        grid-template-columns: 1fr;
    }
    
    .bid-card__actions {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
function updateFilters() {
    const status = document.getElementById('status-filter').value;
    const sort = document.getElementById('sort-filter').value;
    const order = document.getElementById('order-filter').value;
    
    const url = new URL(window.location);
    url.searchParams.set('status', status);
    url.searchParams.set('sort', sort);
    url.searchParams.set('order', order);
    
    window.location.href = url.toString();
}

function editBid(bidId, currentAmount) {
    document.getElementById('editBidId').value = bidId;
    document.getElementById('editBidAmount').value = currentAmount;
    document.getElementById('editBidModal').style.display = 'block';
}

function withdrawBid(bidId) {
    document.getElementById('withdrawBidId').value = bidId;
    document.getElementById('withdrawBidModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editBidModal').style.display = 'none';
    document.getElementById('withdrawBidModal').style.display = 'none';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Auto-dismiss success messages
setTimeout(function() {
    const alert = document.querySelector('.bid-dashboard__alert--success');
    if (alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }
}, 5000);
</script> 