<?php
// Vendor Pending Approval Page
// Shows when vendor application is under review

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

// If vendor is approved, redirect to dashboard
if ($vendorProfile && $vendorProfile['status'] === 'active') {
    header("Location: vendor-dashboard.php");
    exit();
}

include 'templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Application Under Review | Painter Near Me</title>
    <meta name="description" content="Your vendor application is currently under review. We'll notify you once it's approved." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .pending-page {
            max-width: 600px;
            margin: 3rem auto;
            padding: 0 1rem;
            text-align: center;
        }
        
        .pending-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pending-title {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .pending-message {
            font-size: 1.2rem;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .pending-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
        }
        
        .status-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .status-info h4 {
            color: #856404;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .status-info p {
            color: #856404;
            margin-bottom: 0.5rem;
        }
        
        .timeline {
            text-align: left;
            margin: 2rem 0;
        }
        
        .timeline h4 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .timeline-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .timeline-item:not(:last-child):before {
            content: '';
            position: absolute;
            left: 15px;
            top: 30px;
            width: 2px;
            height: 40px;
            background: #e9ecef;
        }
        
        .timeline-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .timeline-icon.completed {
            background: #00b050;
            color: white;
        }
        
        .timeline-icon.current {
            background: #f39c12;
            color: white;
        }
        
        .timeline-icon.pending {
            background: #e9ecef;
            color: #7f8c8d;
        }
        
        .timeline-content h5 {
            color: #2c3e50;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }
        
        .timeline-content p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .contact-info {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .contact-info h4 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #00b050, #00d460);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #009640, #00c050);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,176,80,0.3);
            color: white;
        }
        
        .btn-secondary {
            background: #fff;
            color: #00b050;
            border: 2px solid #00b050;
        }
        
        .btn-secondary:hover {
            background: #00b050;
            color: white;
        }
        
        @media (max-width: 768px) {
            .pending-card {
                padding: 2rem;
            }
            
            .pending-title {
                font-size: 2rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<main role="main">
    <div class="pending-page">
        <div class="pending-icon">
            <i class="bi bi-clock-history"></i>
        </div>
        
        <h1 class="pending-title">Application Under Review</h1>
        <p class="pending-message">
            Thank you for applying to become a vendor on Painter Near Me. 
            Your application is currently being reviewed by our team.
        </p>
        
        <div class="pending-card">
            <div class="status-info">
                <h4><i class="bi bi-info-circle"></i> Current Status</h4>
                <p><strong>Status:</strong> <?php echo ucfirst($vendorProfile['status'] ?? 'pending'); ?></p>
                <p><strong>Application Date:</strong> <?php echo date('F j, Y', strtotime($vendorProfile['created_at'] ?? 'now')); ?></p>
                <p><strong>Business:</strong> <?php echo htmlspecialchars($vendorProfile['business_name'] ?? 'N/A'); ?></p>
            </div>
            
            <div class="timeline">
                <h4>Review Process</h4>
                
                <div class="timeline-item">
                    <div class="timeline-icon completed">
                        <i class="bi bi-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h5>Application Submitted</h5>
                        <p>Your vendor application and documents have been received.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon current">
                        <i class="bi bi-search"></i>
                    </div>
                    <div class="timeline-content">
                        <h5>Document Review</h5>
                        <p>Our team is reviewing your business documents and credentials.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon pending">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h5>Verification</h5>
                        <p>Final verification and approval process.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon pending">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="timeline-content">
                        <h5>Account Activation</h5>
                        <p>Your vendor account will be activated and you can start selling.</p>
                    </div>
                </div>
            </div>
            
            <div class="contact-info">
                <h4>Need Help?</h4>
                <p>If you have any questions about your application or need to update your information, please contact us:</p>
                <p>
                    <strong>Email:</strong> vendors@painter-near-me.co.uk<br>
                    <strong>Phone:</strong> +44 (0) 20 1234 5678<br>
                    <strong>Hours:</strong> Monday - Friday, 9:00 AM - 5:00 PM GMT
                </p>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="logout.php" class="btn btn-secondary">
                Logout
            </a>
            <a href="index.php" class="btn btn-primary">
                Return to Homepage
            </a>
        </div>
    </div>
</main>

<script>
// Add some interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Animate timeline items
    const timelineItems = document.querySelectorAll('.timeline-item');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateX(0)';
            }
        });
    }, observerOptions);
    
    timelineItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        item.style.transition = `opacity 0.5s ease ${index * 0.1}s, transform 0.5s ease ${index * 0.1}s`;
        observer.observe(item);
    });
    
    // Auto-refresh page every 5 minutes to check for status updates
    setTimeout(() => {
        window.location.reload();
    }, 300000); // 5 minutes
});
</script>
</body>
</html>