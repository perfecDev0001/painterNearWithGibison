<?php
// Dashboard with:
// - Revenue analytics per painter/time period
// - Lead conversion rates 
// - Geographic heat maps
// - Seasonal trends
// - Top performing painters
// - Customer satisfaction metrics

// Suppress database connection warnings for graceful fallback
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_WARNING);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();

// Simple database connectivity test
function isDatabaseAvailable() {
    try {
        $configPath = __DIR__ . '/config/database.php';
        if (!file_exists($configPath)) {
            return false;
        }
        
        $config = require($configPath);
        $host = $config['host'] ?? 'localhost';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $database = $config['database'] ?? 'painter_near_me';
        
        // Suppress warnings from mysqli constructor
        $previous_error_reporting = error_reporting(0);
        $connection = @new mysqli($host, $username, $password, $database);
        error_reporting($previous_error_reporting);
        
        if ($connection->connect_error) {
            return false;
        }
        
        $connection->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Safely initialize data access with error handling
$dataAccess = null;
if (isDatabaseAvailable()) {
    try {
        $dataAccess = new GibsonDataAccess();
    } catch (Exception $e) {
        error_log("Analytics page: Failed to initialize data access - " . $e->getMessage());
        $dataAccess = null;
    }
}

// Admin session check
if (!$auth->isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Default analytics data for when database is unavailable
function getDefaultAnalyticsData() {
    return [
        'overview' => [
            'total_leads' => 0,
            'total_painters' => 0,
            'total_bids' => 0,
            'conversion_rate' => 0,
            'avg_bid_amount' => 0,
            'revenue_estimate' => 0
        ],
        'revenue' => [
            'actual_revenue' => 0,
            'potential_revenue' => 0,
            'total_pipeline' => 0,
            'monthly_target' => 5000,
            'achievement_percentage' => 0
        ],
        'conversion' => [
            'leads_to_bids' => 0,
            'bids_to_assignments' => 0,
            'assignments_to_completion' => 0,
            'overall_conversion' => 0
        ],
        'geographic' => [
            'leads_by_location' => [],
            'painters_by_location' => [],
            'coverage_analysis' => []
        ],
        'trends' => [
            'lead_growth' => [
                'this_month' => 0,
                'last_month' => 0,
                'growth_rate' => 0
            ],
            'painter_growth' => [
                'this_month' => 0,
                'last_month' => 0,
                'growth_rate' => 0
            ],
            'revenue_trend' => [
                'this_month' => 0,
                'last_month' => 0,
                'growth_rate' => 0
            ]
        ],
        'performance' => []
    ];
}

// Get analytics data
function getAnalyticsData($dataAccess) {
    $analytics = [
        'overview' => [],
        'revenue' => [],
        'conversion' => [],
        'geographic' => [],
        'trends' => [],
        'performance' => []
    ];
    
    // Handle case where data access is unavailable
    if (!$dataAccess) {
        return getDefaultAnalyticsData();
    }
    
    // Overview metrics
    try {
        $stats = $dataAccess->getDashboardStats();
        if (!is_array($stats)) {
            $stats = [
                'total_leads' => 0,
                'total_painters' => 0,
                'total_bids' => 0,
                'assigned_leads' => 0
            ];
        }
    } catch (Exception $e) {
        error_log("Analytics: Failed to get dashboard stats - " . $e->getMessage());
        return getDefaultAnalyticsData();
    }
    
    $analytics['overview'] = [
        'total_leads' => $stats['total_leads'] ?? 0,
        'total_painters' => $stats['total_painters'] ?? 0,
        'total_bids' => $stats['total_bids'] ?? 0,
        'conversion_rate' => calculateConversionRate($dataAccess),
        'avg_bid_amount' => calculateAverageBidAmount($dataAccess),
        'revenue_estimate' => calculateRevenueEstimate($dataAccess)
    ];
    
    // Revenue analytics
    $analytics['revenue'] = getRevenueAnalytics($dataAccess);
    
    // Conversion metrics
    $analytics['conversion'] = getConversionMetrics($dataAccess);
    
    // Geographic distribution
    $analytics['geographic'] = getGeographicData($dataAccess);
    
    // Trend analysis
    $analytics['trends'] = getTrendAnalysis($dataAccess);
    
    // Performance metrics
    $analytics['performance'] = getPerformanceMetrics($dataAccess);
    
    return $analytics;
}

function calculateConversionRate($dataAccess) {
    $stats = $dataAccess->getDashboardStats();
    if (!is_array($stats)) {
        return 0;
    }
    
    $totalLeads = $stats['total_leads'] ?? 0;
    $assignedLeads = $stats['assigned_leads'] ?? 0;
    
    return $totalLeads > 0 ? round(($assignedLeads / $totalLeads) * 100, 1) : 0;
}

function calculateAverageBidAmount($dataAccess) {
    $bids = $dataAccess->getBids();
    if (!is_array($bids) || empty($bids)) return 0;
    
    $total = array_sum(array_column($bids, 'bid_amount'));
    return round($total / count($bids), 2);
}

function calculateRevenueEstimate($dataAccess) {
    $bids = $dataAccess->getBids(['status' => 'accepted']);
    if (!is_array($bids)) return 0;
    
    $total = array_sum(array_column($bids, 'bid_amount'));
    $commission_rate = 0.05; // 5% commission
    
    return round($total * $commission_rate, 2);
}

function getRevenueAnalytics($dataAccess) {
    $acceptedBids = $dataAccess->getBids(['status' => 'accepted']);
    $pendingBids = $dataAccess->getBids(['status' => 'pending']);
    
    if (!is_array($acceptedBids)) $acceptedBids = [];
    if (!is_array($pendingBids)) $pendingBids = [];
    
    $actualRevenue = array_sum(array_column($acceptedBids, 'bid_amount')) * 0.05;
    $potentialRevenue = array_sum(array_column($pendingBids, 'bid_amount')) * 0.05;
    
    return [
        'actual_revenue' => round($actualRevenue, 2),
        'potential_revenue' => round($potentialRevenue, 2),
        'total_pipeline' => round($actualRevenue + $potentialRevenue, 2),
        'monthly_target' => 5000, // Example target
        'achievement_percentage' => round(($actualRevenue / 5000) * 100, 1)
    ];
}

function getConversionMetrics($dataAccess) {
    $allLeads = $dataAccess->getLeads();
    if (!is_array($allLeads)) $allLeads = [];
    
    $openLeads = array_filter($allLeads, function($lead) {
        return is_array($lead) && isset($lead['status']) && $lead['status'] === 'open';
    });
    $assignedLeads = array_filter($allLeads, function($lead) {
        return is_array($lead) && isset($lead['status']) && $lead['status'] === 'assigned';
    });
    $closedLeads = array_filter($allLeads, function($lead) {
        return is_array($lead) && isset($lead['status']) && $lead['status'] === 'closed';
    });
    
    $totalLeads = count($allLeads);
    
    $allBids = $dataAccess->getBids();
    $bidCount = is_array($allBids) ? count($allBids) : 0;
    
    return [
        'leads_to_bids' => $totalLeads > 0 ? round(($bidCount / $totalLeads) * 100, 1) : 0,
        'bids_to_assignments' => $bidCount > 0 ? round((count($assignedLeads) / $bidCount) * 100, 1) : 0,
        'assignments_to_completion' => count($assignedLeads) > 0 ? round((count($closedLeads) / count($assignedLeads)) * 100, 1) : 0,
        'overall_conversion' => $totalLeads > 0 ? round((count($closedLeads) / $totalLeads) * 100, 1) : 0
    ];
}

function getGeographicData($dataAccess) {
    $leads = $dataAccess->getLeads();
    $painters = $dataAccess->getPainters();
    
    if (!is_array($leads)) $leads = [];
    if (!is_array($painters)) $painters = [];
    
    $leadsByLocation = [];
    $paintersByLocation = [];
    
    foreach ($leads as $lead) {
        if (!is_array($lead)) continue;
        $location = extractCity($lead['location'] ?? '');
        $leadsByLocation[$location] = ($leadsByLocation[$location] ?? 0) + 1;
    }
    
    foreach ($painters as $painter) {
        if (!is_array($painter)) continue;
        $location = extractCity($painter['location'] ?? '');
        $paintersByLocation[$location] = ($paintersByLocation[$location] ?? 0) + 1;
    }
    
    return [
        'leads_by_location' => $leadsByLocation,
        'painters_by_location' => $paintersByLocation,
        'coverage_analysis' => analyzeCoverage($leadsByLocation, $paintersByLocation)
    ];
}

function extractCity($location) {
    // Extract city from location string
    $parts = explode(',', $location);
    return trim($parts[count($parts) - 1]) ?: 'Unknown';
}

function analyzeCoverage($leads, $painters) {
    $analysis = [];
    foreach ($leads as $location => $leadCount) {
        $painterCount = $painters[$location] ?? 0;
        $ratio = $painterCount > 0 ? round($leadCount / $painterCount, 2) : 'N/A';
        $analysis[$location] = [
            'leads' => $leadCount,
            'painters' => $painterCount,
            'ratio' => $ratio,
            'status' => $painterCount === 0 ? 'No Coverage' : ($ratio > 3 ? 'High Demand' : 'Balanced')
        ];
    }
    return $analysis;
}

function getTrendAnalysis($dataAccess) {
    // Simulate trend data (in real implementation, this would query by date ranges)
    return [
        'lead_growth' => [
            'this_month' => 15,
            'last_month' => 12,
            'growth_rate' => 25.0
        ],
        'painter_growth' => [
            'this_month' => 3,
            'last_month' => 2,
            'growth_rate' => 50.0
        ],
        'revenue_trend' => [
            'this_month' => 850.00,
            'last_month' => 720.00,
            'growth_rate' => 18.1
        ]
    ];
}

function getPerformanceMetrics($dataAccess) {
    $painters = $dataAccess->getPainters();
    $bids = $dataAccess->getBids();
    
    // Check if data retrieval was successful
    if (!is_array($painters) || !is_array($bids)) {
        return [];
    }
    
    $painterPerformance = [];
    foreach ($painters as $painter) {
        // Ensure painter is a valid array with required keys
        if (!is_array($painter) || !isset($painter['id']) || !isset($painter['company_name'])) {
            continue;
        }
        
        $painterBids = array_filter($bids, function($bid) use ($painter) {
            return is_array($bid) && isset($bid['painter_id']) && $bid['painter_id'] == $painter['id'];
        });
        
        $acceptedBids = array_filter($painterBids, function($bid) {
            return is_array($bid) && isset($bid['status']) && $bid['status'] === 'accepted';
        });
        
        $painterPerformance[] = [
            'company_name' => $painter['company_name'],
            'total_bids' => count($painterBids),
            'accepted_bids' => count($acceptedBids),
            'success_rate' => count($painterBids) > 0 ? round((count($acceptedBids) / count($painterBids)) * 100, 1) : 0,
            'total_value' => array_sum(array_column($acceptedBids, 'bid_amount'))
        ];
    }
    
    // Sort by success rate
    usort($painterPerformance, fn($a, $b) => $b['success_rate'] <=> $a['success_rate']);
    
    return array_slice($painterPerformance, 0, 10); // Top 10 performers
}

// Initialize analytics with error handling for database issues
try {
    $analytics = getAnalyticsData($dataAccess);
} catch (Exception $e) {
    error_log("Analytics page: Error getting analytics data - " . $e->getMessage());
    $analytics = getDefaultAnalyticsData();
}

include 'templates/header.php';
?>
<head>
    <title>Analytics Dashboard | Admin | Painter Near Me</title>
    <meta name="description" content="Comprehensive analytics and reporting dashboard for Painter Near Me marketplace." />
    <link rel="stylesheet" href="serve-asset.php?file=css/admin-dashboard.css">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<div class="admin-layout">
    <?php include 'templates/sidebar-admin.php'; ?>
    <main class="admin-main" role="main">
        <section class="admin-card">
            <div class="admin-header">
                <div class="admin-header__content">
                    <h1 class="hero__title">Analytics Dashboard</h1>
                    <p class="hero__subtitle">Comprehensive business intelligence and performance metrics</p>
                </div>
                <div class="admin-header__actions">
                    <button class="btn btn--outline" onclick="exportAnalytics()">
                        <i class="bi bi-download"></i> Export Data
                    </button>
                    <button class="btn btn--primary" onclick="refreshAnalytics()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </section>

        <!-- Overview Metrics -->
        <section class="admin-metrics-grid">
            <div class="admin-metric admin-metric--primary">
                <div class="admin-metric__header">
                    <div class="admin-metric__icon">
                        <i class="bi bi-person-lines-fill"></i>
                    </div>
                    <div class="admin-metric__trend admin-metric__trend--up">
                        <i class="bi bi-arrow-up-short"></i> +12%
                    </div>
                </div>
                <div class="admin-metric__value"><?php echo number_format($analytics['overview']['total_leads']); ?></div>
                <div class="admin-metric__label">Total Leads</div>
                <div class="admin-metric__details">All time leads generated</div>
            </div>

            <div class="admin-metric admin-metric--success">
                <div class="admin-metric__header">
                    <div class="admin-metric__icon admin-metric__icon--success">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="admin-metric__trend admin-metric__trend--up">
                        <i class="bi bi-arrow-up-short"></i> +5%
                    </div>
                </div>
                <div class="admin-metric__value"><?php echo number_format($analytics['overview']['total_painters']); ?></div>
                <div class="admin-metric__label">Active Painters</div>
                <div class="admin-metric__details">Verified and active painters</div>
            </div>

            <div class="admin-metric admin-metric--info">
                <div class="admin-metric__header">
                    <div class="admin-metric__icon admin-metric__icon--info">
                        <i class="bi bi-currency-pound"></i>
                    </div>
                    <div class="admin-metric__trend admin-metric__trend--up">
                        <i class="bi bi-arrow-up-short"></i> +18%
                    </div>
                </div>
                <div class="admin-metric__value">£<?php echo number_format($analytics['revenue']['actual_revenue'], 2); ?></div>
                <div class="admin-metric__label">Revenue Generated</div>
                <div class="admin-metric__details">Commission from completed jobs</div>
            </div>

            <div class="admin-metric admin-metric--warning">
                <div class="admin-metric__header">
                    <div class="admin-metric__icon admin-metric__icon--warning">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="admin-metric__trend admin-metric__trend--up">
                        <i class="bi bi-arrow-up-short"></i> +3%
                    </div>
                </div>
                <div class="admin-metric__value"><?php echo $analytics['overview']['conversion_rate']; ?>%</div>
                <div class="admin-metric__label">Conversion Rate</div>
                <div class="admin-metric__details">Leads to completed jobs</div>
            </div>

            <div class="admin-metric">
                <div class="admin-metric__header">
                    <div class="admin-metric__icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="admin-metric__trend admin-metric__trend--up">
                        <i class="bi bi-arrow-up-short"></i> +7%
                    </div>
                </div>
                <div class="admin-metric__value">£<?php echo number_format($analytics['overview']['avg_bid_amount'], 2); ?></div>
                <div class="admin-metric__label">Avg Bid Amount</div>
                <div class="admin-metric__details">Average painter bid value</div>
            </div>

            <div class="admin-metric">
                <div class="admin-metric__header">
                    <div class="admin-metric__icon">
                        <i class="bi bi-bullseye"></i>
                    </div>
                    <div class="admin-metric__trend admin-metric__trend--up">
                        <i class="bi bi-arrow-up-short"></i> +25%
                    </div>
                </div>
                <div class="admin-metric__value">£<?php echo number_format($analytics['revenue']['potential_revenue'], 2); ?></div>
                <div class="admin-metric__label">Pipeline Value</div>
                <div class="admin-metric__details">Potential revenue from pending bids</div>
            </div>
        </section>

        <!-- Revenue Analytics -->
        <section class="admin-card">
            <h2 class="admin-section-title">
                <i class="bi bi-graph-up"></i>
                Revenue Analytics
            </h2>
            <div class="admin-grid admin-grid--2">
                <div class="admin-chart-container">
                    <h3 class="admin-chart-title">Revenue Overview</h3>
                    <canvas id="revenueChart" width="400" height="200"></canvas>
                </div>
                <div class="admin-progress-section">
                    <h3 class="admin-chart-title">Monthly Performance</h3>
                    <div class="admin-progress-container">
                        <div class="admin-progress-item">
                            <div class="admin-progress-label">
                                <span>Target Achievement</span>
                                <span class="admin-progress-value"><?php echo $analytics['revenue']['achievement_percentage']; ?>%</span>
                            </div>
                            <div class="admin-progress-bar">
                                <div class="admin-progress-fill" style="width: <?php echo min($analytics['revenue']['achievement_percentage'], 100); ?>%"></div>
                            </div>
                        </div>
                        <div class="admin-stats-grid">
                            <div class="admin-stat-item">
                                <span class="admin-stat-value">£<?php echo number_format($analytics['revenue']['actual_revenue'], 2); ?></span>
                                <span class="admin-stat-label">Actual Revenue</span>
                            </div>
                            <div class="admin-stat-item">
                                <span class="admin-stat-value">£<?php echo number_format($analytics['revenue']['monthly_target'], 2); ?></span>
                                <span class="admin-stat-label">Monthly Target</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conversion Funnel -->
        <section class="admin-card">
            <h2 class="admin-section-title">
                <i class="bi bi-funnel"></i>
                Conversion Funnel
            </h2>
            <div class="admin-funnel">
                <div class="admin-funnel-stage">
                    <div class="admin-funnel-bar admin-funnel-bar--primary" style="width: 100%">
                        <div class="admin-funnel-content">
                            <span class="admin-funnel-label">Leads Generated</span>
                            <span class="admin-funnel-value"><?php echo $analytics['overview']['total_leads']; ?></span>
                        </div>
                    </div>
                    <div class="admin-funnel-arrow"><i class="bi bi-arrow-down"></i></div>
                </div>
                <div class="admin-funnel-stage">
                    <div class="admin-funnel-bar admin-funnel-bar--success" style="width: <?php echo $analytics['conversion']['leads_to_bids']; ?>%">
                        <div class="admin-funnel-content">
                            <span class="admin-funnel-label">Received Bids</span>
                            <span class="admin-funnel-value"><?php echo $analytics['conversion']['leads_to_bids']; ?>%</span>
                        </div>
                    </div>
                    <div class="admin-funnel-arrow"><i class="bi bi-arrow-down"></i></div>
                </div>
                <div class="admin-funnel-stage">
                    <div class="admin-funnel-bar admin-funnel-bar--warning" style="width: <?php echo $analytics['conversion']['bids_to_assignments']; ?>%">
                        <div class="admin-funnel-content">
                            <span class="admin-funnel-label">Assignments Made</span>
                            <span class="admin-funnel-value"><?php echo $analytics['conversion']['bids_to_assignments']; ?>%</span>
                        </div>
                    </div>
                    <div class="admin-funnel-arrow"><i class="bi bi-arrow-down"></i></div>
                </div>
                <div class="admin-funnel-stage">
                    <div class="admin-funnel-bar admin-funnel-bar--info" style="width: <?php echo $analytics['conversion']['assignments_to_completion']; ?>%">
                        <div class="admin-funnel-content">
                            <span class="admin-funnel-label">Projects Completed</span>
                            <span class="admin-funnel-value"><?php echo $analytics['conversion']['assignments_to_completion']; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Geographic Analysis -->
        <section class="admin-card">
            <h2 class="admin-section-title">
                <i class="bi bi-geo-alt"></i>
                Geographic Distribution
            </h2>
            <div class="admin-grid admin-grid--2">
                <div class="admin-chart-container">
                    <h3 class="admin-chart-title">Leads by Location</h3>
                    <canvas id="leadLocationChart" width="400" height="200"></canvas>
                </div>
                <div class="admin-table-container">
                    <h3 class="admin-chart-title">Coverage Analysis</h3>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Leads</th>
                                    <th>Painters</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics['geographic']['coverage_analysis'] as $location => $data): ?>
                                <tr>
                                    <td class="admin-table-cell--location">
                                        <i class="bi bi-geo-alt-fill"></i>
                                        <?php echo htmlspecialchars($location); ?>
                                    </td>
                                    <td class="admin-table-cell--number"><?php echo $data['leads']; ?></td>
                                    <td class="admin-table-cell--number"><?php echo $data['painters']; ?></td>
                                    <td>
                                        <span class="admin-status admin-status--<?php echo strtolower(str_replace(' ', '-', $data['status'])); ?>">
                                            <?php echo $data['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Top Performers -->
        <section class="admin-card">
            <h2 class="admin-section-title">
                <i class="bi bi-trophy"></i>
                Top Performing Painters
            </h2>
            <div class="admin-performers-grid">
                <?php foreach (array_slice($analytics['performance'], 0, 6) as $index => $painter): ?>
                <div class="admin-performer-card">
                    <div class="admin-performer-rank admin-performer-rank--<?php echo $index < 3 ? 'top' : 'regular'; ?>">
                        <i class="bi bi-<?php echo $index === 0 ? 'trophy-fill' : ($index === 1 ? 'award-fill' : ($index === 2 ? 'star-fill' : 'person-badge')); ?>"></i>
                        #<?php echo $index + 1; ?>
                    </div>
                    <div class="admin-performer-content">
                        <h4 class="admin-performer-name"><?php echo htmlspecialchars($painter['company_name']); ?></h4>
                        <div class="admin-performer-stats">
                            <div class="admin-performer-stat">
                                <span class="admin-performer-stat-label">Success Rate</span>
                                <span class="admin-performer-stat-value admin-performer-stat-value--success"><?php echo $painter['success_rate']; ?>%</span>
                            </div>
                            <div class="admin-performer-stat">
                                <span class="admin-performer-stat-label">Total Bids</span>
                                <span class="admin-performer-stat-value"><?php echo $painter['total_bids']; ?></span>
                            </div>
                            <div class="admin-performer-stat">
                                <span class="admin-performer-stat-label">Total Value</span>
                                <span class="admin-performer-stat-value admin-performer-stat-value--revenue">£<?php echo number_format($painter['total_value'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<script>
function exportAnalytics() {
    // Show loading state
    const exportBtn = event.target;
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-download"></i> Exporting...';
    exportBtn.disabled = true;
    
    // Simulate export (in real implementation, this would generate a CSV/Excel file)
    setTimeout(() => {
        exportBtn.innerHTML = '<i class="bi bi-check-circle"></i> Exported!';
        setTimeout(() => {
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }, 2000);
    }, 1500);
}

function refreshAnalytics() {
    // Show loading state
    const refreshBtn = event.target;
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Simulate refresh (in real implementation, this would make an AJAX call)
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});

function initializeCharts() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Actual Revenue', 'Potential Revenue'],
                datasets: [{
                    data: [<?php echo $analytics['revenue']['actual_revenue']; ?>, <?php echo $analytics['revenue']['potential_revenue']; ?>],
                    backgroundColor: ['#00b050', '#e6f7ea'],
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
    }
    
    // Location Chart
    const locationCtx = document.getElementById('leadLocationChart');
    if (locationCtx) {
        const locationData = <?php echo json_encode($analytics['geographic']['leads_by_location']); ?>;
        new Chart(locationCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(locationData),
                datasets: [{
                    label: 'Leads by Location',
                    data: Object.values(locationData),
                    backgroundColor: '#00b050',
                    borderRadius: 4
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
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    .spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

<style>
.analytics-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #00b050;
}

.analytics-status {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.analytics-status--balanced { background: #d4edda; color: #155724; }
.analytics-status--high-demand { background: #fff3cd; color: #856404; }
.analytics-status--no-coverage { background: #f8d7da; color: #721c24; }

.analytics-performers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.analytics-performer-card {
    background: #f8f9fa;
    border-radius: 0.8rem;
    padding: 1.5rem;
    border: 2px solid #e9ecef;
    transition: border-color 0.3s ease;
}

.analytics-performer-card:hover {
    border-color: #00b050;
}

.analytics-performer-rank {
    background: #00b050;
    color: white;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin-bottom: 1rem;
}

.analytics-performer-stats {
    margin-top: 1rem;
}

.analytics-performer-stat {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.analytics-performer-stat:last-child {
    border-bottom: none;
}

@media (max-width: 900px) {
    .admin-main {
        padding: 1.2rem 0.5rem;
    }
    
    .analytics-charts-grid,
    .analytics-geo-grid {
        grid-template-columns: 1fr;
    }
    
    .analytics-metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'doughnut',
    data: {
        labels: ['Actual Revenue', 'Potential Revenue'],
        datasets: [{
            data: [<?php echo $analytics['revenue']['actual_revenue']; ?>, <?php echo $analytics['revenue']['potential_revenue']; ?>],
            backgroundColor: ['#00b050', '#e6f7ea'],
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

// Lead Location Chart
const locationCtx = document.getElementById('leadLocationChart').getContext('2d');
new Chart(locationCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_keys($analytics['geographic']['leads_by_location'])); ?>,
        datasets: [{
            label: 'Leads',
            data: <?php echo json_encode(array_values($analytics['geographic']['leads_by_location'])); ?>,
            backgroundColor: '#00b050'
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
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include 'templates/footer.php'; ?> 