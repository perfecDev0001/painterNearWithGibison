<?php
// Vendor Application Success Page

session_start();

// Check if user came from vendor registration
if (!isset($_SESSION['vendor_application_success'])) {
    header("Location: register-hub.php");
    exit();
}

// Clear the session flag
unset($_SESSION['vendor_application_success']);

if (file_exists('templates/header.php')) {
    include 'templates/header.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Application Submitted | Painter Near Me</title>
    <meta name="description" content="Your vendor application has been submitted successfully and is under review." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .success-page {
            max-width: 600px;
            margin: 3rem auto;
            padding: 0 1rem;
            text-align: center;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #00b050, #00d460);
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
        
        .success-title {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .success-message {
            font-size: 1.2rem;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .success-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
        }
        
        .next-steps {
            text-align: left;
            margin: 2rem 0;
        }
        
        .next-steps h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        
        .steps-list {
            list-style: none;
            padding: 0;
        }
        
        .steps-list li {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            position: relative;
            padding-left: 3rem;
        }
        
        .steps-list li:last-child {
            border-bottom: none;
        }
        
        .steps-list li:before {
            content: counter(step-counter);
            counter-increment: step-counter;
            position: absolute;
            left: 0;
            top: 1rem;
            width: 2rem;
            height: 2rem;
            background: #00b050;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .steps-list {
            counter-reset: step-counter;
        }
        
        .step-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .step-description {
            color: #7f8c8d;
            font-size: 0.95rem;
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
            .success-card {
                padding: 2rem;
            }
            
            .success-title {
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
    <div class="success-page">
        <div class="success-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        
        <h1 class="success-title">Application Submitted!</h1>
        <p class="success-message">
            Thank you for applying to become a vendor on Painter Near Me. 
            Your application has been received and is now under review.
        </p>
        
        <div class="success-card">
            <div class="next-steps">
                <h3>What Happens Next?</h3>
                <ol class="steps-list">
                    <li>
                        <div class="step-title">Application Review</div>
                        <div class="step-description">
                            Our team will review your application and submitted documents within 2-3 business days.
                        </div>
                    </li>
                    <li>
                        <div class="step-title">Verification Process</div>
                        <div class="step-description">
                            We may contact you for additional information or clarification if needed.
                        </div>
                    </li>
                    <li>
                        <div class="step-title">Account Approval</div>
                        <div class="step-description">
                            Once approved, you'll receive an email with login instructions and access to your vendor dashboard.
                        </div>
                    </li>
                    <li>
                        <div class="step-title">Start Selling</div>
                        <div class="step-description">
                            Set up your store profile, add products, and start selling to our network of customers.
                        </div>
                    </li>
                </ol>
            </div>
            
            <div class="contact-info">
                <h4>Need Help?</h4>
                <p>If you have any questions about your application or need assistance, please contact us:</p>
                <p>
                    <strong>Email:</strong> vendors@painter-near-me.co.uk<br>
                    <strong>Phone:</strong> +44 (0) 20 1234 5678
                </p>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="register-hub.php" class="btn btn-secondary">
                Register Another Account
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
    // Animate the success icon
    const icon = document.querySelector('.success-icon');
    
    setTimeout(() => {
        icon.style.transform = 'scale(1.1)';
        setTimeout(() => {
            icon.style.transform = 'scale(1)';
        }, 200);
    }, 500);
    
    // Animate steps
    const steps = document.querySelectorAll('.steps-list li');
    steps.forEach((step, index) => {
        step.style.opacity = '0';
        step.style.transform = 'translateX(-20px)';
        step.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        setTimeout(() => {
            step.style.opacity = '1';
            step.style.transform = 'translateX(0)';
        }, 1000 + (index * 200));
    });
});
</script>
</body>
</html>