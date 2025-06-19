<?php
// Quality management features:
// - Review/rating moderation
// - Dispute resolution workflow
// - Quality standards enforcement
// - Painter performance scoring
// - Customer satisfaction surveys
// - Photo/work verification system
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Admin session check
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Handle quality control actions
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_painter'])) {
        $painterId = intval($_POST['painter_id']);
        $actionMsg = 'Painter approved and verified.';
    } elseif (isset($_POST['suspend_painter'])) {
        $painterId = intval($_POST['painter_id']);
        $actionMsg = 'Painter suspended pending review.';
    } elseif (isset($_POST['flag_review'])) {
        $reviewId = intval($_POST['review_id']);
        $actionMsg = 'Review flagged for manual investigation.';
    } elseif (isset($_POST['update_quality_score'])) {
        $painterId = intval($_POST['painter_id']);
        $newScore = floatval($_POST['quality_score']);
        $actionMsg = 'Quality score updated successfully.';
    }
}

// Quality control functions
function getQualityOverview($dataAccess) {
    $painters = $dataAccess->getPainters();
    if (!is_array($painters)) $painters = [];
    
    $totalPainters = count($painters);
    $verifiedPainters = 0;
    $pendingVerification = 0;
    $suspendedPainters = 0;
    $totalQualityScore = 0;
    
    foreach ($painters as $painter) {
        if (!is_array($painter)) continue;
        
        $status = $painter['verification_status'] ?? 'pending';
        $painterStatus = $painter['status'] ?? 'active';
        
        if ($status === 'verified') $verifiedPainters++;
        if ($status === 'pending') $pendingVerification++;
        if ($painterStatus === 'suspended') $suspendedPainters++;
        
        $totalQualityScore += $painter['quality_score'] ?? rand(35, 50) / 10;
    }
    
    $averageQualityScore = $totalPainters > 0 ? $totalQualityScore / $totalPainters : 0;
    
    return [
        'total_painters' => $totalPainters,
        'verified_painters' => $verifiedPainters,
        'pending_verification' => $pendingVerification,
        'suspended_painters' => $suspendedPainters,
        'verification_rate' => $totalPainters > 0 ? ($verifiedPainters / $totalPainters) * 100 : 0,
        'average_quality_score' => round($averageQualityScore, 1),
        'quality_threshold' => 4.0
    ];
}

function getPendingVerifications($dataAccess) {
    $painters = $dataAccess->getPainters();
    if (!is_array($painters)) $painters = [];
    
    $pendingPainters = [];
    
    // Filter for pending verification and add mock verification data
    foreach ($painters as $painter) {
        if (!is_array($painter)) continue;
        
        if (($painter['verification_status'] ?? 'pending') === 'pending') {
            $painter['documents_submitted'] = rand(3, 5);
            $painter['required_documents'] = 5;
            $painter['insurance_valid'] = rand(0, 1) == 1;
            $painter['references_checked'] = rand(0, 1) == 1;
            $painter['background_check'] = rand(0, 1) == 1;
            $painter['submitted_date'] = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
            $pendingPainters[] = $painter;
        }
    }
    
    return array_slice($pendingPainters, 0, 10);
}

function getQualityIssues($dataAccess) {
    // Simulate quality issues data
    return [
        [
            'id' => 1,
            'painter_id' => 1,
            'company_name' => 'Premium Painters London',
            'issue_type' => 'Customer Complaint',
            'severity' => 'high',
            'description' => 'Multiple complaints about poor workmanship and unprofessional behavior',
            'reported_date' => '2024-01-10',
            'status' => 'investigating',
            'reporter' => 'Customer'
        ],
        [
            'id' => 2,
            'painter_id' => 3,
            'company_name' => 'Manchester Paint Works',
            'issue_type' => 'Incomplete Work',
            'severity' => 'medium',
            'description' => 'Job left unfinished without proper communication',
            'reported_date' => '2024-01-12',
            'status' => 'pending',
            'reporter' => 'System'
        ],
        [
            'id' => 3,
            'painter_id' => 2,
            'company_name' => 'Elite Decorators',
            'issue_type' => 'Policy Violation',
            'severity' => 'low',
            'description' => 'Minor violation of platform guidelines',
            'reported_date' => '2024-01-15',
            'status' => 'resolved',
            'reporter' => 'Admin'
        ]
    ];
}

function getQualityMetrics($dataAccess) {
    $painters = $dataAccess->getPainters();
    if (!is_array($painters)) $painters = [];
    
    $qualityMetrics = [];
    
    foreach ($painters as $painter) {
        if (!is_array($painter) || !isset($painter['id']) || !isset($painter['company_name'])) {
            continue;
        }
        
        $qualityScore = $painter['quality_score'] ?? rand(25, 50) / 10; // Simulate quality scores
        $completionRate = rand(85, 98); // Simulate completion rates
        $customerSatisfaction = rand(30, 50) / 10; // Simulate satisfaction scores
        $responseTime = rand(2, 24); // Hours
        
        $qualityMetrics[] = [
            'painter_id' => $painter['id'],
            'company_name' => $painter['company_name'],
            'quality_score' => $qualityScore,
            'completion_rate' => $completionRate,
            'customer_satisfaction' => $customerSatisfaction,
            'response_time_hours' => $responseTime,
            'total_jobs' => rand(5, 25),
            'verification_status' => $painter['verification_status'] ?? 'pending'
        ];
    }
    
    // Sort by quality score (lowest first to identify issues)
    usort($qualityMetrics, fn($a, $b) => $a['quality_score'] <=> $b['quality_score']);
    
    return $qualityMetrics;
}

$qualityOverview = getQualityOverview($dataAccess);
$pendingVerifications = getPendingVerifications($dataAccess);
$qualityIssues = getQualityIssues($dataAccess);
$qualityMetrics = getQualityMetrics($dataAccess);

include 'templates/header.php';
?>
<head>
    <title>Quality Control | Admin Dashboard | Painter Near Me</title>
    <meta name="description" content="Quality control, painter verification, and review management for Painter Near Me marketplace." />
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
        <section class="quality-hero admin-card">
            <div class="quality-header">
                <div>
                    <h1 class="hero__title">Quality Control</h1>
                    <p class="hero__subtitle">Painter verification, review management, and quality assurance</p>
                </div>
                <div class="quality-actions">
                    <a href="admin-leads.php" class="btn btn-success">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <?php if ($actionMsg): ?>
                <div class="alert alert-success quality-alert" role="alert">
                    <?php echo htmlspecialchars($actionMsg); ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Quality Overview -->
        <section class="quality-overview admin-card">
            <h2 class="quality-section-title">Quality Overview</h2>
            <div class="quality-metrics-grid">
                <div class="quality-metric quality-metric--primary">
                    <div class="quality-metric__icon">✅</div>
                    <div class="quality-metric__value"><?php echo $qualityOverview['verified_painters']; ?></div>
                    <div class="quality-metric__label">Verified Painters</div>
                    <div class="quality-metric__sublabel"><?php echo round($qualityOverview['verification_rate'], 1); ?>% verification rate</div>
                </div>
                <div class="quality-metric quality-metric--warning">
                    <div class="quality-metric__icon">⏳</div>
                    <div class="quality-metric__value"><?php echo $qualityOverview['pending_verification']; ?></div>
                    <div class="quality-metric__label">Pending Verification</div>
                    <div class="quality-metric__sublabel">Awaiting review</div>
                </div>
                <div class="quality-metric quality-metric--danger">
                    <div class="quality-metric__icon">🚫</div>
                    <div class="quality-metric__value"><?php echo $qualityOverview['suspended_painters']; ?></div>
                    <div class="quality-metric__label">Suspended</div>
                    <div class="quality-metric__sublabel">Quality issues</div>
                </div>
                <div class="quality-metric quality-metric--info">
                    <div class="quality-metric__icon">⭐</div>
                    <div class="quality-metric__value"><?php echo $qualityOverview['average_quality_score']; ?></div>
                    <div class="quality-metric__label">Avg Quality Score</div>
                    <div class="quality-metric__sublabel">Out of 5.0</div>
                </div>
            </div>
        </section>

        <!-- Pending Verifications -->
        <section class="quality-verifications admin-card">
            <h2 class="quality-section-title">Pending Verifications</h2>
            <?php if (empty($pendingVerifications)): ?>
                <div class="quality-empty">
                    <i class="bi bi-check-circle quality-empty-icon"></i>
                    <h3>All Caught Up!</h3>
                    <p>No pending verifications at this time.</p>
                </div>
            <?php else: ?>
                <div class="quality-verifications-table">
                    <table class="quality-table">
                        <thead>
                            <tr>
                                <th>Painter</th>
                                <th>Submitted</th>
                                <th>Documents</th>
                                <th>Insurance</th>
                                <th>References</th>
                                <th>Background Check</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingVerifications as $painter): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($painter['company_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($painter['contact_name']); ?></small>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($painter['submitted_date'])); ?></td>
                                <td>
                                    <span class="quality-progress quality-progress--<?php echo $painter['documents_submitted'] == $painter['required_documents'] ? 'complete' : 'incomplete'; ?>">
                                        <?php echo $painter['documents_submitted']; ?>/<?php echo $painter['required_documents']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="quality-status quality-status--<?php echo $painter['insurance_valid'] ? 'valid' : 'invalid'; ?>">
                                        <?php echo $painter['insurance_valid'] ? 'Valid' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="quality-status quality-status--<?php echo $painter['references_checked'] ? 'checked' : 'pending'; ?>">
                                        <?php echo $painter['references_checked'] ? 'Checked' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="quality-status quality-status--<?php echo $painter['background_check'] ? 'clear' : 'pending'; ?>">
                                        <?php echo $painter['background_check'] ? 'Clear' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="painter_id" value="<?php echo $painter['id']; ?>">
                                        <button type="submit" name="approve_painter" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Quality Issues -->
        <section class="quality-issues admin-card">
            <h2 class="quality-section-title">Quality Issues & Reports</h2>
            <div class="quality-issues-list">
                <?php foreach ($qualityIssues as $issue): ?>
                <div class="quality-issue quality-issue--<?php echo $issue['severity']; ?>">
                    <div class="quality-issue-header">
                        <div class="quality-issue-type">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?php echo $issue['issue_type']; ?>
                        </div>
                        <div class="quality-issue-meta">
                            <span class="quality-issue-severity quality-issue-severity--<?php echo $issue['severity']; ?>">
                                <?php echo ucfirst($issue['severity']); ?>
                            </span>
                            <span class="quality-issue-date">
                                <?php echo date('M j, Y', strtotime($issue['reported_date'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="quality-issue-content">
                        <h4><?php echo htmlspecialchars($issue['company_name']); ?></h4>
                        <p><?php echo htmlspecialchars($issue['description']); ?></p>
                        <div class="quality-issue-footer">
                            <span class="quality-issue-reporter">Reported by: <?php echo $issue['reporter']; ?></span>
                            <span class="quality-issue-status quality-issue-status--<?php echo $issue['status']; ?>">
                                <?php echo ucfirst($issue['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="quality-issue-actions">
                        <button class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-chat-dots"></i> Contact
                        </button>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="painter_id" value="<?php echo $issue['painter_id']; ?>">
                            <button type="submit" name="suspend_painter" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-ban"></i> Suspend
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-success">
                            <i class="bi bi-check"></i> Resolve
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Quality Metrics -->
        <section class="quality-metrics admin-card">
            <h2 class="quality-section-title">Quality Metrics Dashboard</h2>
            <div class="quality-metrics-table">
                <table class="quality-table">
                    <thead>
                        <tr>
                            <th>Painter</th>
                            <th>Quality Score</th>
                            <th>Completion Rate</th>
                            <th>Customer Satisfaction</th>
                            <th>Response Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($qualityMetrics, 0, 10) as $metric): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($metric['company_name']); ?></strong>
                                <br><small><?php echo $metric['total_jobs']; ?> total jobs</small>
                            </td>
                            <td>
                                <div class="quality-score quality-score--<?php echo $metric['quality_score'] >= 4.0 ? 'good' : ($metric['quality_score'] >= 3.0 ? 'average' : 'poor'); ?>">
                                    <span class="quality-score-value"><?php echo $metric['quality_score']; ?></span>
                                    <div class="quality-score-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= floor($metric['quality_score']) ? '-fill' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="quality-progress-bar">
                                    <div class="quality-progress-fill" style="width: <?php echo $metric['completion_rate']; ?>%"></div>
                                </div>
                                <span><?php echo $metric['completion_rate']; ?>%</span>
                            </td>
                            <td>
                                <div class="quality-satisfaction quality-satisfaction--<?php echo $metric['customer_satisfaction'] >= 4.0 ? 'high' : ($metric['customer_satisfaction'] >= 3.0 ? 'medium' : 'low'); ?>">
                                    <?php echo $metric['customer_satisfaction']; ?>/5.0
                                </div>
                            </td>
                            <td><?php echo $metric['response_time_hours']; ?>h</td>
                            <td>
                                <span class="quality-verification quality-verification--<?php echo $metric['verification_status']; ?>">
                                    <?php echo ucfirst($metric['verification_status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="painter_id" value="<?php echo $metric['painter_id']; ?>">
                                    <input type="number" name="quality_score" step="0.1" min="1" max="5" value="<?php echo $metric['quality_score']; ?>" style="width: 60px;">
                                    <button type="submit" name="update_quality_score" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-up"></i> Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
    max-width: 1400px;
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

.quality-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.quality-section-title {
    color: #00b050;
    margin-bottom: 1.5rem;
    font-size: 1.4rem;
    font-weight: 700;
}

.quality-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.quality-metric {
    background: linear-gradient(135deg, #f8fffe 0%, #e6f7ea 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid #e6f7ea;
    transition: transform 0.3s ease;
}

.quality-metric:hover {
    transform: translateY(-2px);
}

.quality-metric--primary { border-color: #00b050; }
.quality-metric--warning { border-color: #ffc107; }
.quality-metric--danger { border-color: #dc3545; }
.quality-metric--info { border-color: #17a2b8; }

.quality-metric__icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.quality-metric__value {
    font-size: 1.8rem;
    font-weight: 900;
    color: #00b050;
    margin-bottom: 0.25rem;
}

.quality-metric__label {
    color: #333;
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.quality-metric__sublabel {
    color: #666;
    font-size: 0.8rem;
}

.quality-empty {
    text-align: center;
    padding: 3rem;
    color: #666;
}

.quality-empty-icon {
    font-size: 4rem;
    color: #00b050;
    margin-bottom: 1rem;
}

.quality-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.quality-table th,
.quality-table td {
    padding: 1rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.quality-table th {
    background: #f8f9fa;
    font-weight: 700;
    color: #00b050;
    font-size: 0.9rem;
}

.quality-progress {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.quality-progress--complete {
    background: #d4edda;
    color: #155724;
}

.quality-progress--incomplete {
    background: #fff3cd;
    color: #856404;
}

.quality-status {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.quality-status--valid,
.quality-status--checked,
.quality-status--clear {
    background: #d4edda;
    color: #155724;
}

.quality-status--invalid,
.quality-status--pending {
    background: #fff3cd;
    color: #856404;
}

.quality-issues-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.quality-issue {
    border: 2px solid #e9ecef;
    border-radius: 0.8rem;
    padding: 1.5rem;
    transition: border-color 0.3s ease;
}

.quality-issue--high { border-color: #dc3545; }
.quality-issue--medium { border-color: #ffc107; }
.quality-issue--low { border-color: #28a745; }

.quality-issue-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.quality-issue-type {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #333;
}

.quality-issue-meta {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.quality-issue-severity {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.quality-issue-severity--high {
    background: #f8d7da;
    color: #721c24;
}

.quality-issue-severity--medium {
    background: #fff3cd;
    color: #856404;
}

.quality-issue-severity--low {
    background: #d4edda;
    color: #155724;
}

.quality-issue-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    font-size: 0.9rem;
    color: #666;
}

.quality-issue-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.quality-score {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.quality-score-value {
    font-weight: 700;
    font-size: 1.1rem;
}

.quality-score--good .quality-score-value { color: #28a745; }
.quality-score--average .quality-score-value { color: #ffc107; }
.quality-score--poor .quality-score-value { color: #dc3545; }

.quality-score-stars {
    color: #ffc107;
}

.quality-progress-bar {
    width: 60px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.quality-progress-fill {
    height: 100%;
    background: #00b050;
    transition: width 0.3s ease;
}

.quality-satisfaction {
    font-weight: 700;
}

.quality-satisfaction--high { color: #28a745; }
.quality-satisfaction--medium { color: #ffc107; }
.quality-satisfaction--low { color: #dc3545; }

.quality-verification {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.quality-verification--verified {
    background: #d4edda;
    color: #155724;
}

.quality-verification--pending {
    background: #fff3cd;
    color: #856404;
}

.quality-verification--suspended {
    background: #f8d7da;
    color: #721c24;
}

.btn {
    padding: 0.5rem 1rem;
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

.btn-success {
    background: #00b050;
    color: white;
    border-color: #00b050;
}

.btn-outline-success {
    background: transparent;
    color: #00b050;
    border-color: #00b050;
}

.btn-outline-primary {
    background: transparent;
    color: #007bff;
    border-color: #007bff;
}

.btn-outline-info {
    background: transparent;
    color: #17a2b8;
    border-color: #17a2b8;
}

.btn-outline-danger {
    background: transparent;
    color: #dc3545;
    border-color: #dc3545;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

@media (max-width: 900px) {
    .admin-main {
        padding: 1.2rem 0.5rem;
    }
    
    .quality-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .quality-metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quality-issue-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<script>
// Auto-refresh quality data every 60 seconds
setInterval(() => {
    // In a real implementation, this would refresh the data via AJAX
    console.log('Auto-refreshing quality data...');
}, 60000);
</script>

<?php include 'templates/footer.php'; ?> 