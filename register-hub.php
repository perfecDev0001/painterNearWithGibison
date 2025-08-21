<?php
// Multi-Role Registration Hub
// Allows users to choose their registration type

// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Error handling
if (getenv('GIBSON_DEVELOPMENT_MODE') === 'true' || $_ENV['GIBSON_DEVELOPMENT_MODE'] === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(0);
}

require_once 'core/GibsonAuth.php';

$auth = new GibsonAuth();

// Check if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    switch ($user['type']) {
        case 'painter':
            header("Location: dashboard.php");
            break;
        case 'customer':
            header("Location: customer-dashboard.php");
            break;
        case 'admin':
            header("Location: admin-dashboard.php");
            break;
        default:
            header("Location: dashboard.php");
    }
    exit();
}

if (file_exists('templates/header.php')) {
    include 'templates/header.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Join Painter Near Me | Choose Your Account Type</title>
    <meta name="description" content="Join Painter Near Me as a customer, painter, or vendor. Choose your account type to get started." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .registration-hub {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .registration-hub__header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .registration-hub__title {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .registration-hub__subtitle {
            font-size: 1.2rem;
            color: #7f8c8d;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .registration-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .registration-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .registration-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,176,80,0.15), 0 2px 12px rgba(0,0,0,0.08);
            border-color: #00b050;
        }
        
        .registration-card__icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #00b050, #00d460);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }
        
        .registration-card__title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .registration-card__description {
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .registration-card__features {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .registration-card__features li {
            padding: 0.5rem 0;
            color: #34495e;
            position: relative;
            padding-left: 1.5rem;
        }
        
        .registration-card__features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #00b050;
            font-weight: bold;
        }
        
        .registration-card__button {
            background: linear-gradient(135deg, #00b050, #00d460);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            width: 100%;
        }
        
        .registration-card__button:hover {
            background: linear-gradient(135deg, #009640, #00c050);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,176,80,0.3);
        }
        
        .registration-card--popular {
            border-color: #00b050;
            position: relative;
        }
        
        .registration-card--popular:before {
            content: "Most Popular";
            position: absolute;
            top: 1rem;
            right: -2rem;
            background: #00b050;
            color: white;
            padding: 0.5rem 3rem;
            font-size: 0.9rem;
            font-weight: 600;
            transform: rotate(45deg);
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 1rem;
        }
        
        .login-link__text {
            color: #7f8c8d;
            margin-bottom: 1rem;
        }
        
        .login-link__button {
            color: #00b050;
            text-decoration: none;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border: 2px solid #00b050;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .login-link__button:hover {
            background: #00b050;
            color: white;
        }
        
        @media (max-width: 768px) {
            .registration-options {
                grid-template-columns: 1fr;
            }
            
            .registration-hub__title {
                font-size: 2rem;
            }
            
            .registration-card {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
<main role="main">
    <div class="registration-hub">
        <div class="registration-hub__header">
            <h1 class="registration-hub__title">Join Painter Near Me</h1>
            <p class="registration-hub__subtitle">
                Choose your account type to get started. Whether you're looking for painting services, 
                offering professional painting, or selling painting supplies, we have the perfect solution for you.
            </p>
        </div>
        
        <div class="registration-options">
            <!-- Customer Registration -->
            <div class="registration-card registration-card--popular">
                <div class="registration-card__icon">
                    <i class="bi bi-person-heart"></i>
                </div>
                <h2 class="registration-card__title">I Need Painting Services</h2>
                <p class="registration-card__description">
                    Get quotes from verified painters in your area. Post your project and receive competitive bids.
                </p>
                <ul class="registration-card__features">
                    <li>Post unlimited painting projects</li>
                    <li>Get quotes from verified painters</li>
                    <li>Secure payment protection</li>
                    <li>Project management tools</li>
                    <li>Review and rating system</li>
                </ul>
                <a href="register-customer.php" class="registration-card__button">
                    Register as Customer
                </a>
            </div>
            
            <!-- Painter Registration -->
            <div class="registration-card">
                <div class="registration-card__icon">
                    <i class="bi bi-brush"></i>
                </div>
                <h2 class="registration-card__title">I'm a Professional Painter</h2>
                <p class="registration-card__description">
                    Join our network of professional painters. Bid on projects and grow your business.
                </p>
                <ul class="registration-card__features">
                    <li>Access to local painting leads</li>
                    <li>Professional profile showcase</li>
                    <li>Secure payment processing</li>
                    <li>Customer communication tools</li>
                    <li>Business growth analytics</li>
                </ul>
                <a href="register.php" class="registration-card__button">
                    Register as Painter
                </a>
            </div>
            
            <!-- Vendor Registration -->
            <div class="registration-card">
                <div class="registration-card__icon">
                    <i class="bi bi-shop"></i>
                </div>
                <h2 class="registration-card__title">I Sell Painting Supplies</h2>
                <p class="registration-card__description">
                    Sell your painting products to our network of professionals and DIY customers.
                </p>
                <ul class="registration-card__features">
                    <li>Online product catalog</li>
                    <li>Inventory management</li>
                    <li>Order processing system</li>
                    <li>Payment integration</li>
                    <li>Sales analytics dashboard</li>
                </ul>
                <a href="register-vendor.php" class="registration-card__button">
                    Register as Vendor
                </a>
            </div>
        </div>
        
        <div class="login-link">
            <p class="login-link__text">Already have an account?</p>
            <a href="login.php" class="login-link__button">Sign In</a>
        </div>
    </div>
</main>

<script>
// Add smooth scrolling and animations
document.addEventListener('DOMContentLoaded', function() {
    // Animate cards on scroll
    const cards = document.querySelectorAll('.registration-card');
    
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
    
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });
});
</script>
</body>
</html>