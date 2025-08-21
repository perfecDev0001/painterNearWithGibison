<?php
// Vendor Dashboard
// Dashboard for approved vendors to manage their products and orders

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Require login
$auth->requireLogin();

$user = $auth->getCurrentUser();

// Check if user is a vendor
if (!$user || $user['type'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$vendorId = $user['id'];

// Get vendor profile
$vendorProfile = $dataAccess->getVendorById($vendorId);

// Check if vendor is approved
if (!$vendorProfile || $vendorProfile['status'] !== 'active') {
    // Redirect to pending approval page
    header("Location: vendor-pending-approval.php");
    exit();
}

include 'templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Vendor Dashboard | Painter Near Me</title>
    <meta name="description" content="Manage your products, orders, and vendor account on Painter Near Me." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .vendor-dashboard {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #00b050, #00d460);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .dashboard-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #00b050, #00d460);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        
        .action-title {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .action-description {
            color: #7f8c8d;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .action-button {
            background: linear-gradient(135deg, #00b050, #00d460);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .action-button:hover {
            background: linear-gradient(135deg, #009640, #00c050);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,176,80,0.3);
            color: white;
        }
        
        .recent-activity {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        
        .activity-title {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: #00b050;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .coming-soon {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem;
            }
            
            .dashboard-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main role="main">
    <div class="vendor-dashboard">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Welcome back, <?php echo htmlspecialchars($vendorProfile['business_name']); ?>!</h1>
            <p class="dashboard-subtitle">Manage your products and grow your business on Painter Near Me</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-number">0</div>
                <div class="stat-label">Products Listed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-cart-check"></i>
                </div>
                <div class="stat-number">0</div>
                <div class="stat-label">Orders Received</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-currency-pound"></i>
                </div>
                <div class="stat-number">£0</div>
                <div class="stat-label">Total Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-star"></i>
                </div>
                <div class="stat-number">5.0</div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>
        
        <div class="quick-actions">
            <div class="action-card">
                <h3 class="action-title">Add Products</h3>
                <p class="action-description">
                    Start selling by adding your painting supplies, tools, and materials to our marketplace.
                </p>
                <a href="vendor-products.php" class="action-button">
                    <i class="bi bi-plus-circle"></i> Add Products
                </a>
            </div>
            
            <div class="action-card">
                <h3 class="action-title">Manage Orders</h3>
                <p class="action-description">
                    View and process customer orders, update shipping status, and manage your inventory.
                </p>
                <a href="vendor-orders.php" class="action-button">
                    <i class="bi bi-list-check"></i> View Orders
                </a>
            </div>
            
            <div class="action-card">
                <h3 class="action-title">Store Settings</h3>
                <p class="action-description">
                    Update your business profile, payment settings, and shipping preferences.
                </p>
                <a href="vendor-settings.php" class="action-button">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </div>
            
            <div class="action-card">
                <h3 class="action-title">Analytics</h3>
                <p class="action-description">
                    Track your sales performance, customer insights, and business growth metrics.
                </p>
                <a href="vendor-analytics.php" class="action-button">
                    <i class="bi bi-graph-up"></i> View Analytics
                </a>
            </div>
        </div>
        
        <div class="recent-activity">
            <h3 class="activity-title">Recent Activity</h3>
            
            <div class="coming-soon">
                <i class="bi bi-clock-history" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                <p>Your recent activity will appear here once you start using the platform.</p>
                <p>Add your first product to get started!</p>
            </div>
        </div>
    </div>
</main>

<script>
// Add some interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat cards
    const statCards = document.querySelectorAll('.stat-card');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });
});
</script>
</body>
</html>