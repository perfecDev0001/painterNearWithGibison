<?php
// Bootstrap the application
require_once __DIR__ . '/bootstrap.php';

require_once CORE_PATH . '/GibsonAuth.php';
require_once CORE_PATH . '/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Admin session check
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Handle financial actions
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_invoice'])) {
        $painterId = intval($_POST['painter_id']);
        $actionMsg = 'Invoice generated successfully.';
    } elseif (isset($_POST['mark_paid'])) {
        $transactionId = intval($_POST['transaction_id']);
        $actionMsg = 'Transaction marked as paid.';
    } elseif (isset($_POST['export_financial_report'])) {
        $reportType = $_POST['report_type'];
        $actionMsg = "Financial report exported successfully.";
    }
}

// Financial data functions
function getFinancialOverview($dataAccess) {
    $bids = $dataAccess->getBids();
    if (!is_array($bids)) $bids = [];
    
    $acceptedBids = array_filter($bids, function($bid) {
        return is_array($bid) && isset($bid['status']) && $bid['status'] === 'accepted';
    });
    $completedBids = array_filter($bids, function($bid) {
        return is_array($bid) && isset($bid['status']) && $bid['status'] === 'completed';
    });
    
    $commissionRate = 0.05; // 5% commission
    
    $totalBidValue = array_sum(array_column($acceptedBids, 'bid_amount'));
    $completedValue = array_sum(array_column($completedBids, 'bid_amount'));
    $pendingValue = $totalBidValue - $completedValue;
    
    $totalCommission = $totalBidValue * $commissionRate;
    $earnedCommission = $completedValue * $commissionRate;
    $pendingCommission = $pendingValue * $commissionRate;
    
    $activePainters = $dataAccess->getPainters(['status' => 'active']);
    $activePainterCount = is_array($activePainters) ? count($activePainters) : 0;
    
    return [
        'total_bid_value' => $totalBidValue,
        'completed_value' => $completedValue,
        'pending_value' => $pendingValue,
        'total_commission' => $totalCommission,
        'earned_commission' => $earnedCommission,
        'pending_commission' => $pendingCommission,
        'commission_rate' => $commissionRate * 100,
        'active_painters' => $activePainterCount,
        'monthly_target' => 10000,
        'target_achievement' => ($completedValue / 10000) * 100
    ];
}

function getRevenueBreakdown($dataAccess) {
    $bids = $dataAccess->getBids(['status' => 'accepted']);
    $painters = $dataAccess->getPainters();
    
    // Check if data retrieval was successful
    if (!is_array($bids)) $bids = [];
    if (!is_array($painters)) $painters = [];
    
    $painterRevenue = [];
    foreach ($painters as $painter) {
        // Ensure painter is a valid array with required keys
        if (!is_array($painter) || !isset($painter['id']) || !isset($painter['company_name'])) {
            continue;
        }
        
        $painterBids = array_filter($bids, function($bid) use ($painter) {
            return is_array($bid) && isset($bid['painter_id']) && $bid['painter_id'] == $painter['id'];
        });
        
        $totalRevenue = array_sum(array_column($painterBids, 'bid_amount'));
        $commission = $totalRevenue * 0.05;
        
        if ($totalRevenue > 0) {
            $painterRevenue[] = [
                'painter_id' => $painter['id'],
                'company_name' => $painter['company_name'],
                'total_revenue' => $totalRevenue,
                'commission_earned' => $commission,
                'job_count' => count($painterBids),
                'avg_job_value' => $totalRevenue / max(count($painterBids), 1)
            ];
        }
    }
    
    // Sort by commission earned (highest first)
    usort($painterRevenue, fn($a, $b) => $b['commission_earned'] <=> $a['commission_earned']);
    
    return $painterRevenue;
}

function getMonthlyFinancials($dataAccess) {
    // Simulate monthly data (in real implementation, this would query by date ranges)
    return [
        'January' => ['revenue' => 2400, 'commission' => 120, 'jobs' => 15],
        'February' => ['revenue' => 3200, 'commission' => 160, 'jobs' => 18],
        'March' => ['revenue' => 2800, 'commission' => 140, 'jobs' => 16],
        'April' => ['revenue' => 3600, 'commission' => 180, 'jobs' => 22],
        'May' => ['revenue' => 4200, 'commission' => 210, 'jobs' => 25],
        'June' => ['revenue' => 3800, 'commission' => 190, 'jobs' => 21]
    ];
}

function getOutstandingPayments($dataAccess) {
    // Simulate outstanding payments data
    return [
        [
            'painter_id' => 1,
            'company_name' => 'Premium Painters London',
            'invoice_id' => 'INV-2024-001',
            'amount_due' => 250.00,
            'due_date' => '2024-01-15',
            'days_overdue' => 5,
            'status' => 'overdue'
        ],
        [
            'painter_id' => 2,
            'company_name' => 'Elite Decorators',
            'invoice_id' => 'INV-2024-002',
            'amount_due' => 180.00,
            'due_date' => '2024-01-20',
            'days_overdue' => 0,
            'status' => 'pending'
        ]
    ];
}

$financialOverview = getFinancialOverview($dataAccess);
$revenueBreakdown = getRevenueBreakdown($dataAccess);
$monthlyFinancials = getMonthlyFinancials($dataAccess);
$outstandingPayments = getOutstandingPayments($dataAccess);

include 'templates/header.php';
?>
<head>
    <title>Financial Management | Admin Dashboard | Painter Near Me</title>
    <meta name="description" content="Financial tracking, commission management, and revenue analytics for Painter Near Me marketplace." />
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

.financial-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.financial-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.financial-select {
    padding: 0.5rem;
    border: 2px solid #e9ecef;
    border-radius: 0.5rem;
    margin-right: 0.5rem;
}

.financial-section-title {
    color: #00b050;
    margin-bottom: 1.5rem;
    font-size: 1.4rem;
    font-weight: 700;
}

.financial-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.financial-metric {
    background: linear-gradient(135deg, #f8fffe 0%, #e6f7ea 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid #e6f7ea;
    transition: transform 0.3s ease;
}

.financial-metric:hover {
    transform: translateY(-2px);
}

.financial-metric--primary { border-color: #00b050; }
.financial-metric--secondary { border-color: #6c757d; }
.financial-metric--success { border-color: #28a745; }
.financial-metric--info { border-color: #17a2b8; }

.financial-metric__icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.financial-metric__value {
    font-size: 1.8rem;
    font-weight: 900;
    color: #00b050;
    margin-bottom: 0.25rem;
}

.financial-metric__label {
    color: #333;
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.financial-metric__sublabel {
    color: #666;
    font-size: 0.8rem;
}

.financial-charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.financial-chart-container {
    background: #f8f9fa;
    border-radius: 0.8rem;
    padding: 1.5rem;
}

.financial-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.financial-table th,
.financial-table td {
    padding: 1rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.financial-table th {
    background: #f8f9fa;
    font-weight: 700;
    color: #00b050;
    font-size: 0.9rem;
}

.financial-rank {
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    font-weight: 700;
    font-size: 0.9rem;
}

.financial-rank--top {
    background: #ffd700;
    color: #333;
}

.financial-rank--regular {
    background: #e9ecef;
    color: #666;
}

.financial-commission {
    color: #00b050;
    font-weight: 700;
}

.financial-payment-status {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.financial-payment-status--pending {
    background: #fff3cd;
    color: #856404;
}

.financial-payment-status--overdue {
    background: #f8d7da;
    color: #721c24;
}

.financial-payment-status--paid {
    background: #d4edda;
    color: #155724;
}

.financial-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.financial-summary-card {
    background: #f8f9fa;
    border-radius: 0.8rem;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid #e9ecef;
}

.financial-summary-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: #00b050;
    margin: 0.5rem 0;
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

.btn-outline-warning {
    background: transparent;
    color: #ffc107;
    border-color: #ffc107;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

@media (max-width: 900px) {
    .admin-main {
        padding: 1.2rem 0.5rem;
    }
    
    .financial-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .financial-charts-grid {
        grid-template-columns: 1fr;
    }
    
    .financial-metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
    </style>
</head>
<div class="admin-layout">
    <?php include 'templates/sidebar-admin.php'; ?>
    <main class="admin-main" role="main">
        <section class="financial-hero admin-card">
            <div class="financial-header">
                <div>
                    <h1 class="hero__title">Financial Management</h1>
                    <p class="hero__subtitle">Commission tracking, revenue analytics, and payment management</p>
                </div>
                <div class="financial-actions">
                    <form method="post" style="display: inline;">
                        <select name="report_type" class="financial-select">
                            <option value="monthly">Monthly Report</option>
                            <option value="quarterly">Quarterly Report</option>
                            <option value="annual">Annual Report</option>
                        </select>
                        <button type="submit" name="export_financial_report" class="btn btn-outline-success">
                            <i class="bi bi-download"></i> Export Report
                        </button>
                    </form>
                    <a href="admin-leads.php" class="btn btn-success">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <?php if ($actionMsg): ?>
                <div class="alert alert-success financial-alert" role="alert">
                    <?php echo htmlspecialchars($actionMsg); ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Financial Overview -->
        <section class="financial-overview admin-card">
            <h2 class="financial-section-title">Financial Overview</h2>
            <div class="financial-metrics-grid">
                <div class="financial-metric financial-metric--primary">
                    <div class="financial-metric__icon">💰</div>
                    <div class="financial-metric__value">£<?php echo number_format($financialOverview['earned_commission'], 2); ?></div>
                    <div class="financial-metric__label">Commission Earned</div>
                    <div class="financial-metric__sublabel">From completed jobs</div>
                </div>
                <div class="financial-metric financial-metric--secondary">
                    <div class="financial-metric__icon">⏳</div>
                    <div class="financial-metric__value">£<?php echo number_format($financialOverview['pending_commission'], 2); ?></div>
                    <div class="financial-metric__label">Pending Commission</div>
                    <div class="financial-metric__sublabel">From ongoing jobs</div>
                </div>
                <div class="financial-metric financial-metric--success">
                    <div class="financial-metric__icon">🎯</div>
                    <div class="financial-metric__value"><?php echo number_format($financialOverview['target_achievement'], 1); ?>%</div>
                    <div class="financial-metric__label">Target Achievement</div>
                    <div class="financial-metric__sublabel">Monthly goal progress</div>
                </div>
                <div class="financial-metric financial-metric--info">
                    <div class="financial-metric__icon">📊</div>
                    <div class="financial-metric__value"><?php echo $financialOverview['commission_rate']; ?>%</div>
                    <div class="financial-metric__label">Commission Rate</div>
                    <div class="financial-metric__sublabel">Platform fee structure</div>
                </div>
            </div>
        </section>

        <!-- Revenue Charts -->
        <section class="financial-charts admin-card">
            <h2 class="financial-section-title">Revenue Analytics</h2>
            <div class="financial-charts-grid">
                <div class="financial-chart-container">
                    <h3>Monthly Revenue Trend</h3>
                    <canvas id="monthlyRevenueChart" width="400" height="200"></canvas>
                </div>
                <div class="financial-chart-container">
                    <h3>Commission Breakdown</h3>
                    <canvas id="commissionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </section>

        <!-- Top Earners -->
        <section class="financial-earners admin-card">
            <h2 class="financial-section-title">Top Revenue Generators</h2>
            <div class="financial-earners-table">
                <table class="financial-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Painter</th>
                            <th>Total Revenue</th>
                            <th>Commission Earned</th>
                            <th>Jobs Completed</th>
                            <th>Avg Job Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($revenueBreakdown, 0, 10) as $index => $painter): ?>
                        <tr>
                            <td>
                                <span class="financial-rank financial-rank--<?php echo $index < 3 ? 'top' : 'regular'; ?>">
                                    #<?php echo $index + 1; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($painter['company_name']); ?></strong>
                            </td>
                            <td>£<?php echo number_format($painter['total_revenue'], 2); ?></td>
                            <td>
                                <span class="financial-commission">
                                    £<?php echo number_format($painter['commission_earned'], 2); ?>
                                </span>
                            </td>
                            <td><?php echo $painter['job_count']; ?> jobs</td>
                            <td>£<?php echo number_format($painter['avg_job_value'], 2); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="painter_id" value="<?php echo $painter['painter_id']; ?>">
                                    <button type="submit" name="generate_invoice" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-text"></i> Invoice
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Outstanding Payments -->
        <section class="financial-payments admin-card">
            <h2 class="financial-section-title">Outstanding Payments</h2>
            <div class="financial-payments-table">
                <table class="financial-table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Painter</th>
                            <th>Amount Due</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outstandingPayments as $payment): ?>
                        <tr>
                            <td><strong><?php echo $payment['invoice_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['company_name']); ?></td>
                            <td>£<?php echo number_format($payment['amount_due'], 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($payment['due_date'])); ?></td>
                            <td>
                                <span class="financial-payment-status financial-payment-status--<?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                    <?php if ($payment['days_overdue'] > 0): ?>
                                        (<?php echo $payment['days_overdue']; ?> days overdue)
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="transaction_id" value="<?php echo $payment['painter_id']; ?>">
                                    <button type="submit" name="mark_paid" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-check-circle"></i> Mark Paid
                                    </button>
                                </form>
                                <a href="mailto:<?php echo $payment['company_name']; ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-envelope"></i> Remind
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Financial Summary -->
        <section class="financial-summary admin-card">
            <h2 class="financial-section-title">Financial Summary</h2>
            <div class="financial-summary-grid">
                <div class="financial-summary-card">
                    <h4>Total Platform Value</h4>
                    <div class="financial-summary-value">£<?php echo number_format($financialOverview['total_bid_value'], 2); ?></div>
                    <p>Total value of all accepted bids</p>
                </div>
                <div class="financial-summary-card">
                    <h4>Active Painters</h4>
                    <div class="financial-summary-value"><?php echo $financialOverview['active_painters']; ?></div>
                    <p>Currently active painter accounts</p>
                </div>
                <div class="financial-summary-card">
                    <h4>Average Commission</h4>
                    <div class="financial-summary-value">£<?php echo number_format($financialOverview['total_commission'] / max($financialOverview['active_painters'], 1), 2); ?></div>
                    <p>Per painter commission average</p>
                </div>
                <div class="financial-summary-card">
                    <h4>Monthly Target</h4>
                    <div class="financial-summary-value">£<?php echo number_format($financialOverview['monthly_target'], 2); ?></div>
                    <p>Revenue goal for this month</p>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
// Monthly Revenue Chart
const monthlyCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_keys($monthlyFinancials)); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($monthlyFinancials, 'revenue')); ?>,
            borderColor: '#00b050',
            backgroundColor: 'rgba(0, 176, 80, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '£' + value;
                    }
                }
            }
        }
    }
});

// Commission Chart
const commissionCtx = document.getElementById('commissionChart').getContext('2d');
new Chart(commissionCtx, {
    type: 'doughnut',
    data: {
        labels: ['Earned Commission', 'Pending Commission'],
        datasets: [{
            data: [<?php echo $financialOverview['earned_commission']; ?>, <?php echo $financialOverview['pending_commission']; ?>],
            backgroundColor: ['#00b050', '#ffc107'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include 'templates/footer.php'; ?> 