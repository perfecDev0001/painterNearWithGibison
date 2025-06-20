<?php
/**
 * Main Landing Page for Painter Near Me
 * Displays the quote form wizard for customers to request painting services
 * With fallback support for when Gibson AI is unavailable
 */

// Error handling for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

try {
    // Try to load bootstrap
    require_once 'bootstrap.php';
    
    // Check if Gibson AI is enabled and available
    $gibsonEnabled = (getenv('GIBSON_ENABLED') === 'true' || $_ENV['GIBSON_ENABLED'] === 'true');
    $useWizard = false;
    
    if ($gibsonEnabled) {
        try {
            require_once 'core/Wizard.php';
            $useWizard = true;
        } catch (Exception $e) {
            error_log('Wizard loading failed: ' . $e->getMessage());
            $useWizard = false;
        }
    }
    
    // Initialize security headers
    if (function_exists('setSecurityHeaders')) {
        setSecurityHeaders();
    }
    
    // Start the session for form data persistence
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if ($useWizard) {
        // Use the full wizard system
        $wizard = new Wizard();
        $wizard->handleRequest();
        $currentStep = $wizard->getCurrentStep();
        $stepData = $wizard->getStepData();
        $progress = $wizard->getProgress();
    } else {
        // Use simple form fallback
        $currentStep = 1;
        $stepData = [];
        $progress = 0;
        
        // Handle simple form submission
        $form_success = false;
        $form_errors = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postcode = trim($_POST['postcode'] ?? '');
            $job_type = trim($_POST['job_type'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $csrf_token = trim($_POST['csrf_token'] ?? '');
            
            // Basic validation
            if ($csrf_token !== $_SESSION['csrf_token']) {
                $form_errors[] = 'Security token mismatch. Please try again.';
            }
            
            if (empty($postcode)) {
                $form_errors[] = 'Please enter your postcode.';
            }
            
            if (empty($job_type)) {
                $form_errors[] = 'Please select a job type.';
            }
            
            if (empty($description)) {
                $form_errors[] = 'Please describe your project.';
            }
            
            if (empty($form_errors)) {
                // Log the submission
                $log_entry = date('Y-m-d H:i:s') . " - Quote request: $postcode, $job_type, " . substr($description, 0, 100) . "\n";
                @file_put_contents('logs/quotes.log', $log_entry, FILE_APPEND | LOCK_EX);
                
                $form_success = true;
                
                // Clear form data
                $_POST = [];
            }
        }
    }
    
} catch (Exception $e) {
    // Log the error
    error_log('Index page error: ' . $e->getMessage());
    
    // Fallback to simple mode
    $useWizard = false;
    $currentStep = 1;
    $stepData = [];
    $progress = 0;
    
    // Start session manually
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Free Painting Quotes | Painter Near Me</title>
    <meta name="description" content="Get free quotes from verified local painters. Compare prices, read reviews, and hire the best painter for your project. Quick and easy quote process." />
    <meta name="keywords" content="painter quotes, house painting, interior painting, exterior painting, decorators, local painters" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://painter-near-me.co.uk/" />
    <meta property="og:title" content="Get Free Painting Quotes | Painter Near Me" />
    <meta property="og:description" content="Get free quotes from verified local painters. Compare prices and hire the best painter for your project." />
    <meta property="og:image" content="/assets/images/og-image.jpg" />

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="https://painter-near-me.co.uk/" />
    <meta property="twitter:title" content="Get Free Painting Quotes | Painter Near Me" />
    <meta property="twitter:description" content="Get free quotes from verified local painters. Compare prices and hire the best painter for your project." />
    <meta property="twitter:image" content="/assets/images/og-image.jpg" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <?php if (file_exists('serve-asset.php')): ?>
        <link rel="stylesheet" href="serve-asset.php?file=css/style.css">
    <?php else: ?>
        <!-- Fallback inline styles -->
        <style>
            :root {
                --primary-color: #00b050;
                --primary-dark: #009140;
                --text-dark: #222;
                --text-light: #666;
                --bg-light: #f8fafc;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: var(--text-dark);
            }
            
            .hero {
                background: linear-gradient(120deg, var(--primary-color) 0%, var(--primary-dark) 100%);
                color: white;
                padding: 4rem 0;
                text-align: center;
            }
            
            .hero h1 {
                font-size: 3rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }
            
            .hero p {
                font-size: 1.25rem;
                opacity: 0.9;
                margin-bottom: 2rem;
            }
            
            .quote-form {
                background: white;
                border-radius: 1rem;
                box-shadow: 0 8px 32px rgba(0,176,80,0.15);
                padding: 2rem;
                margin-top: -3rem;
                position: relative;
                z-index: 10;
            }
            
            .form-label {
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 0.5rem;
            }
            
            .form-control, .form-select {
                border: 2px solid #e5e7eb;
                border-radius: 0.75rem;
                padding: 0.75rem 1rem;
                font-size: 1rem;
                transition: border-color 0.2s ease;
            }
            
            .form-control:focus, .form-select:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
            }
            
            .btn-primary {
                background: var(--primary-color);
                border: none;
                border-radius: 0.75rem;
                padding: 0.875rem 2rem;
                font-weight: 600;
                font-size: 1.1rem;
                transition: all 0.2s ease;
            }
            
            .btn-primary:hover {
                background: var(--primary-dark);
                transform: translateY(-2px);
            }
            
            .features {
                padding: 4rem 0;
                background: var(--bg-light);
            }
            
            .feature-card {
                background: white;
                border-radius: 1rem;
                padding: 2rem;
                text-align: center;
                box-shadow: 0 4px 16px rgba(0,176,80,0.1);
                height: 100%;
                transition: transform 0.2s ease;
            }
            
            .feature-card:hover {
                transform: translateY(-5px);
            }
            
            .feature-icon {
                font-size: 3rem;
                color: var(--primary-color);
                margin-bottom: 1rem;
            }
            
            .testimonials {
                padding: 4rem 0;
            }
            
            .testimonial {
                background: white;
                border-radius: 1rem;
                padding: 2rem;
                box-shadow: 0 4px 16px rgba(0,176,80,0.1);
                margin-bottom: 2rem;
            }
            
            .testimonial-text {
                font-style: italic;
                font-size: 1.1rem;
                margin-bottom: 1rem;
            }
            
            .testimonial-author {
                font-weight: 600;
                color: var(--primary-color);
            }
            
            .alert {
                border-radius: 0.75rem;
                border: none;
                padding: 1rem 1.5rem;
            }
            
            .alert-success {
                background: #eaffea;
                color: #008040;
                border: 2px solid var(--primary-color);
            }
            
            .alert-danger {
                background: #ffeaea;
                color: #b00020;
                border: 2px solid #ffb3b3;
            }
            
            .navbar {
                background: white !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .navbar-brand {
                font-weight: 700;
                color: var(--primary-color) !important;
            }
            
            .nav-link {
                color: var(--text-dark) !important;
                font-weight: 500;
            }
            
            .nav-link:hover {
                color: var(--primary-color) !important;
            }
            
            footer {
                background: var(--text-dark);
                color: white;
                padding: 3rem 0 2rem;
                margin-top: 4rem;
            }
            
            @media (max-width: 768px) {
                .hero h1 {
                    font-size: 2rem;
                }
                
                .quote-form {
                    margin: 1rem;
                    margin-top: -2rem;
                }
            }
        </style>
    <?php endif; ?>
    
    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "description": "Professional painting service marketplace connecting customers with verified local painters",
        "url": "https://painter-near-me.co.uk",
        "telephone": "+44-800-123-4567",
        "address": {
            "@type": "PostalAddress",
            "addressCountry": "GB"
        },
        "serviceArea": {
            "@type": "Place",
            "name": "United Kingdom"
        },
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Painting Services",
            "itemListElement": [
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Interior Painting",
                        "description": "Professional interior painting services"
                    }
                },
                {
                    "@type": "Offer", 
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Exterior Painting",
                        "description": "Professional exterior painting services"
                    }
                }
            ]
        }
    }
    </script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-brush"></i> Painter Near Me
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="how-it-works.php">How it Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer-dashboard.php">My Projects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main role="main" style="padding-top: 76px;">
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <h1>Find Local Painters</h1>
                <p>Get free quotes from verified painters in your area</p>
            </div>
        </section>

        <!-- Quote Form Section -->
        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="quote-form">
                            <?php if ($useWizard): ?>
                                <!-- Full Wizard System -->
                                <form class="quote-form" method="post" action="" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
                                    
                                    <div class="quote-form__container">
                                        <?php $wizard->renderStep(); ?>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Simple Fallback Form -->
                                <?php if (isset($form_success) && $form_success): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle"></i>
                                        <strong>Thank you!</strong> Your quote request has been submitted. 
                                        We'll connect you with local painters within 24 hours.
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($form_errors) && !empty($form_errors)): ?>
                                    <div class="alert alert-danger">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <strong>Please fix the following errors:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($form_errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="postcode" class="form-label">Your Postcode</label>
                                        <input type="text" class="form-control" id="postcode" name="postcode" 
                                               placeholder="Enter your postcode" required
                                               value="<?php echo htmlspecialchars($_POST['postcode'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="job_type" class="form-label">Type of Painting Job</label>
                                        <select class="form-select" id="job_type" name="job_type" required>
                                            <option value="">Select job type</option>
                                            <option value="interior" <?php echo ($_POST['job_type'] ?? '') === 'interior' ? 'selected' : ''; ?>>Interior Painting</option>
                                            <option value="exterior" <?php echo ($_POST['job_type'] ?? '') === 'exterior' ? 'selected' : ''; ?>>Exterior Painting</option>
                                            <option value="both" <?php echo ($_POST['job_type'] ?? '') === 'both' ? 'selected' : ''; ?>>Interior & Exterior</option>
                                            <option value="commercial" <?php echo ($_POST['job_type'] ?? '') === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="description" class="form-label">Project Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" 
                                                  placeholder="Describe your painting project..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Get Free Quotes
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features">
            <div class="container">
                <h2 id="features-title" class="text-center mb-5">Why Choose Painter Near Me?</h2>
                
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <h3>Trusted & Verified</h3>
                            <p>All painters are background checked, insured, and have verified customer reviews.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                            <h3>Save Time</h3>
                            <p>Get multiple quotes instantly instead of calling painters individually.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-currency-pound"></i>
                            </div>
                            <h3>Save Money</h3>
                            <p>Compare competitive quotes to find the best value for your project.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="testimonials">
            <div class="container">
                <h2 class="text-center mb-5">What Our Customers Say</h2>
                
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="testimonial">
                            <p class="testimonial-text">"Found an excellent painter within hours. The quality was outstanding and the price was fair. Highly recommend!"</p>
                            <cite class="testimonial-author">- Sarah J., London</cite>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="testimonial">
                            <p class="testimonial-text">"Professional service from start to finish. The painter was punctual, tidy, and did amazing work on our living room."</p>
                            <cite class="testimonial-author">- Michael P., Manchester</cite>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="testimonial">
                            <p class="testimonial-text">"Great platform for finding reliable painters. Saved me time and money compared to other methods."</p>
                            <cite class="testimonial-author">- Emma L., Birmingham</cite>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-brush"></i> Painter Near Me</h5>
                    <p>Connecting customers with trusted local painters across the UK.</p>
                </div>
                <div class="col-md-6">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="how-it-works.php" class="text-light">How it Works</a></li>
                        <li><a href="contact.php" class="text-light">Contact Us</a></li>
                        <li><a href="privacy-policy.php" class="text-light">Privacy Policy</a></li>
                        <li><a href="terms.php" class="text-light">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col text-center">
                    <p>&copy; <?php echo date('Y'); ?> Painter Near Me. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (file_exists('serve-asset.php')): ?>
        <script src="serve-asset.php?file=js/main.js" defer></script>
    <?php endif; ?>
    
    <script>
        // Basic functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success message after 10 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s ease';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 10000);
            }
            
            // Form validation for simple form
            const form = document.querySelector('form[method="post"]');
            if (form && !form.classList.contains('quote-form')) {
                form.addEventListener('submit', function(e) {
                    const postcode = document.getElementById('postcode');
                    const jobType = document.getElementById('job_type');
                    const description = document.getElementById('description');
                    
                    if (postcode && jobType && description) {
                        if (!postcode.value.trim() || !jobType.value || !description.value.trim()) {
                            e.preventDefault();
                            alert('Please fill in all required fields.');
                            return false;
                        }
                        
                        // Simple postcode validation
                        const postcodeRegex = /^[A-Z]{1,2}[0-9][A-Z0-9]? ?[0-9][A-Z]{2}$/i;
                        if (!postcodeRegex.test(postcode.value.trim())) {
                            e.preventDefault();
                            alert('Please enter a valid UK postcode.');
                            return false;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>