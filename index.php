<?php
/**
 * Main Landing Page for Painter Near Me
 * Displays the quote form wizard for customers to request painting services
 * With robust Gibson AI integration and fallback support
 */

// Production error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Initialize variables
$useWizard = false;
$currentStep = 1;
$stepData = [];
$progress = 0;
$form_success = false;
$form_errors = [];
$gibson_status = 'unknown';

try {
    // Load bootstrap with error handling
    require_once 'bootstrap.php';
    
    // Initialize security headers
    if (function_exists('setSecurityHeaders')) {
        setSecurityHeaders();
    }
    
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Fast Gibson AI connectivity check with caching
    $gibsonEnabled = (getenv('GIBSON_ENABLED') !== 'false' && $_ENV['GIBSON_ENABLED'] !== 'false');
    
    if ($gibsonEnabled) {
        // Check cached Gibson status first (avoid API calls on every page load)
        $cacheFile = __DIR__ . '/cache/gibson_status.cache';
        $cacheValid = false;
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            $cacheValid = ($cacheData && (time() - $cacheData['timestamp']) < 300); // 5 minute cache
        }
        
        if ($cacheValid) {
            $gibson_status = $cacheData['status'];
            if ($gibson_status === 'connected') {
                require_once 'core/Wizard.php';
                $wizard = new Wizard();
                $wizard->handleRequest();
                $currentStep = $wizard->getCurrentStep();
                $stepData = $wizard->getStepData();
                $progress = $wizard->getProgress();
                $useWizard = true;
            }
        } else {
            try {
                // Set very short timeout for connectivity test
                $originalTimeout = ini_get('default_socket_timeout');
                ini_set('default_socket_timeout', 2); // Reduced to 2 seconds
                
                // Try to load and test Gibson AI
                require_once 'core/GibsonAIService.php';
                $gibson = new GibsonAIService();
                
                // Quick connectivity test with minimal timeout
                $testResult = $gibson->makeApiCallPublic('/v1/-/role', null, 'GET');
                
                if ($testResult['success'] || $testResult['http_code'] === 200) {
                    $gibson_status = 'connected';
                    
                    // Load the full wizard system
                    require_once 'core/Wizard.php';
                    $wizard = new Wizard();
                    $wizard->handleRequest();
                    $currentStep = $wizard->getCurrentStep();
                    $stepData = $wizard->getStepData();
                    $progress = $wizard->getProgress();
                    $useWizard = true;
                    
                } else {
                    $gibson_status = 'api_error';
                    error_log('Gibson AI API test failed: ' . ($testResult['error'] ?? 'Unknown error'));
                }
                
                // Cache the result
                if (!file_exists(dirname($cacheFile))) {
                    mkdir(dirname($cacheFile), 0755, true);
                }
                file_put_contents($cacheFile, json_encode([
                    'status' => $gibson_status,
                    'timestamp' => time()
                ]));
                
                // Restore original timeout
                ini_set('default_socket_timeout', $originalTimeout);
                
            } catch (Exception $e) {
                $gibson_status = 'connection_failed';
                error_log('Gibson AI connection failed: ' . $e->getMessage());
                
                // Cache the failed status
                if (!file_exists(dirname($cacheFile))) {
                    mkdir(dirname($cacheFile), 0755, true);
                }
                file_put_contents($cacheFile, json_encode([
                    'status' => 'connection_failed',
                    'timestamp' => time()
                ]));
                
                // Restore timeout on error
                if (isset($originalTimeout)) {
                    ini_set('default_socket_timeout', $originalTimeout);
                }
            }
        }
    } else {
        $gibson_status = 'disabled';
    }
    
    // If Gibson AI is not working, use simple form fallback
    if (!$useWizard) {
        // Handle simple form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postcode = trim($_POST['postcode'] ?? '');
            $job_type = trim($_POST['job_type'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $csrf_token = trim($_POST['csrf_token'] ?? '');
            
            // Basic validation
            if ($csrf_token !== $_SESSION['csrf_token']) {
                $form_errors[] = 'Security token mismatch. Please try again.';
            }
            
            if (empty($name)) {
                $form_errors[] = 'Please enter your name.';
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $form_errors[] = 'Please enter a valid email address.';
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
                // Try to save to Gibson AI if available
                $saved_to_gibson = false;
                
                if ($gibson_status === 'connected' && isset($gibson)) {
                    try {
                        $leadData = [
                            'customer_name' => $name,
                            'customer_email' => $email,
                            'customer_phone' => $phone,
                            'postcode' => $postcode,
                            'job_type' => $job_type,
                            'description' => $description,
                            'status' => 'pending',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $result = $gibson->createJobLead($leadData);
                        if ($result['success']) {
                            $saved_to_gibson = true;
                        }
                    } catch (Exception $e) {
                        error_log('Failed to save to Gibson AI: ' . $e->getMessage());
                    }
                }
                
                // Always log locally as backup
                $log_entry = date('Y-m-d H:i:s') . " - Quote request: $name, $email, $postcode, $job_type, " . 
                           substr($description, 0, 100) . ($saved_to_gibson ? ' [GIBSON: YES]' : ' [GIBSON: NO]') . "\n";
                @file_put_contents('logs/quotes.log', $log_entry, FILE_APPEND | LOCK_EX);
                
                $form_success = true;
                
                // Clear form data
                $_POST = [];
            }
        }
    }
    
} catch (Exception $e) {
    // Log the error and continue with fallback
    error_log('Index page critical error: ' . $e->getMessage());
    $gibson_status = 'critical_error';
    $useWizard = false;
    
    // Ensure session is started
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
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Critical CSS - Load immediately -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
    <!-- Non-critical CSS - Load asynchronously -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"></noscript>
    
    <!-- Critical performance optimization -->
    <script>
        // Preload critical resources immediately
        (function() {
            var link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'script';
            link.href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';
            document.head.appendChild(link);
        })();
    </script>
    
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
            
            .alert-info {
                background: #e3f2fd;
                color: #1565c0;
                border: 2px solid #42a5f5;
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
            
            .system-status {
                font-size: 0.85rem;
                color: #666;
                text-align: center;
                margin-top: 1rem;
                padding: 0.5rem;
                background: #f8f9fa;
                border-radius: 0.5rem;
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
                                <!-- Full Gibson AI Wizard System -->
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle"></i> <strong>Advanced Quote System Active</strong> - 
                                    Complete our detailed wizard for the most accurate quotes.
                                </div>
                                
                                <form class="" method="post" action="?step=<?php echo $currentStep; ?>" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
                                    
                                    <div class="quote-form__container">
                                        <?php $wizard->renderStep(); ?>
                                    </div>
                                </form>
                                
                            <?php else: ?>
                                <!-- Simple Fallback Form -->
                                <?php if ($gibson_status !== 'connected'): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-info-circle"></i> <strong>Quick Quote Mode</strong> - 
                                        Our advanced system is temporarily unavailable, but you can still request quotes using this form.
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($form_success): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle"></i>
                                        <strong>Thank you!</strong> Your quote request has been submitted successfully. 
                                        We'll connect you with local painters within 24 hours.
                                        <div class="mt-2">
                                            <small>You should receive a confirmation email shortly<?php if (!empty($email)): ?> at <?php echo htmlspecialchars($email); ?><?php endif; ?></small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($form_errors)): ?>
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
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Your Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   placeholder="Enter your full name" required
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   placeholder="your.email@example.com" required
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   placeholder="Your phone number (optional)"
                                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="postcode" class="form-label">Your Postcode *</label>
                                            <input type="text" class="form-control" id="postcode" name="postcode" 
                                                   placeholder="Enter your postcode" required
                                                   value="<?php echo htmlspecialchars($_POST['postcode'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="job_type" class="form-label">Type of Painting Job *</label>
                                        <select class="form-select" id="job_type" name="job_type" required>
                                            <option value="">Select job type</option>
                                            <option value="interior" <?php echo ($_POST['job_type'] ?? '') === 'interior' ? 'selected' : ''; ?>>Interior Painting</option>
                                            <option value="exterior" <?php echo ($_POST['job_type'] ?? '') === 'exterior' ? 'selected' : ''; ?>>Exterior Painting</option>
                                            <option value="both" <?php echo ($_POST['job_type'] ?? '') === 'both' ? 'selected' : ''; ?>>Interior & Exterior</option>
                                            <option value="commercial" <?php echo ($_POST['job_type'] ?? '') === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                                            <option value="decorating" <?php echo ($_POST['job_type'] ?? '') === 'decorating' ? 'selected' : ''; ?>>Decorating</option>
                                            <option value="wallpaper" <?php echo ($_POST['job_type'] ?? '') === 'wallpaper' ? 'selected' : ''; ?>>Wallpaper Hanging</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="description" class="form-label">Project Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" 
                                                  placeholder="Please describe your painting project in detail..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                        <div class="form-text">Include details like room sizes, current condition, preferred colors, timeline, etc.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Get Free Quotes
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- System Status (for debugging) -->
                            <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
                                <div class="system-status">
                                    System Status: <?php echo ucfirst(str_replace('_', ' ', $gibson_status)); ?>
                                    <?php if ($useWizard): ?>
                                        | Wizard: Active | Step: <?php echo $currentStep; ?>
                                    <?php else: ?>
                                        | Mode: Simple Form
                                    <?php endif; ?>
                                </div>
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

    <!-- Scripts - Load asynchronously for better performance -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <?php if (file_exists('serve-asset.php')): ?>
        <script src="serve-asset.php?file=js/main.js" defer></script>
    <?php endif; ?>
    
    <!-- Preload critical JavaScript -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" as="script">
    
    <script>
        // Enhanced functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success message after 15 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.transition = 'opacity 0.5s ease';
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 15000);
            }
            
            // Enhanced form validation - only for simple form, not wizard
            const form = document.querySelector('form[method="post"]');
            const isWizardForm = form && form.querySelector('.quote-form__container');
            
            if (form && !isWizardForm) {
                form.addEventListener('submit', function(e) {
                    const name = document.getElementById('name');
                    const email = document.getElementById('email');
                    const postcode = document.getElementById('postcode');
                    const jobType = document.getElementById('job_type');
                    const description = document.getElementById('description');
                    
                    let isValid = true;
                    let errors = [];
                    
                    // Validate required fields - only if elements exist
                    if (name && !name.value.trim()) {
                        errors.push('Name is required');
                        isValid = false;
                    }
                    
                    if (email && !email.value.trim()) {
                        errors.push('Email is required');
                        isValid = false;
                    } else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                        errors.push('Please enter a valid email address');
                        isValid = false;
                    }
                    
                    if (postcode && !postcode.value.trim()) {
                        errors.push('Postcode is required');
                        isValid = false;
                    } else if (postcode && postcode.value.trim()) {
                        // UK postcode validation
                        const postcodeRegex = /^[A-Z]{1,2}[0-9][A-Z0-9]? ?[0-9][A-Z]{2}$/i;
                        if (!postcodeRegex.test(postcode.value.trim())) {
                            errors.push('Please enter a valid UK postcode');
                            isValid = false;
                        }
                    }
                    
                    if (jobType && !jobType.value) {
                        errors.push('Please select a job type');
                        isValid = false;
                    }
                    
                    if (description && !description.value.trim()) {
                        errors.push('Project description is required');
                        isValid = false;
                    } else if (description && description.value.trim().length < 20) {
                        errors.push('Please provide a more detailed description (at least 20 characters)');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fix the following errors:\n\n• ' + errors.join('\n• '));
                        return false;
                    }
                    
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';
                    }
                });
            }
            
            // Character counter for description - only for simple form
            if (!isWizardForm) {
                const description = document.getElementById('description');
                if (description) {
                    const counter = document.createElement('div');
                    counter.className = 'form-text text-end';
                    counter.style.fontSize = '0.8rem';
                    description.parentNode.appendChild(counter);
                    
                    function updateCounter() {
                        const length = description.value.length;
                        counter.textContent = length + ' characters';
                        
                        if (length < 20) {
                            counter.style.color = '#dc3545';
                        } else if (length < 50) {
                            counter.style.color = '#fd7e14';
                        } else {
                            counter.style.color = '#198754';
                        }
                    }
                    
                    description.addEventListener('input', updateCounter);
                    updateCounter();
                }
            }
        });
    </script>
</body>
</html>