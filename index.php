<?php
/**
 * Main Landing Page for Painter Near Me
 * Displays the quote form wizard for customers to request painting services
 */

require_once 'bootstrap.php';
require_once 'core/Wizard.php';

// Initialize security headers
setSecurityHeaders();

// Start the session for form data persistence
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize the quote wizard
$wizard = new Wizard();

// Handle form submission and progression
$wizard->handleRequest();

// Get current step data
$currentStep = $wizard->getCurrentStep();
$stepData = $wizard->getStepData();
$progress = $wizard->getProgress();

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
    <link rel="stylesheet" href="serve-asset.php?file=css/style.css">
    
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
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "1247"
        }
    }
    </script>
    
    <!-- Performance optimized loading -->
    <link rel="preload" href="serve-asset.php?file=css/style.css" as="style">
    <link rel="dns-prefetch" href="//api.gibsonai.com">
</head>
<body>
    <!-- Skip link for accessibility -->
    <a href="#main-content" class="skip-to-content">Skip to main content</a>
    
    <!-- Header -->
    <header class="header" role="banner">
        <nav class="nav" role="navigation" aria-label="Main navigation">
            <div class="nav__container">
                <a href="/" class="nav__logo" aria-label="Painter Near Me - Home">
                    <img src="serve-asset.php?file=images/logo.svg" alt="Painter Near Me" width="180" height="40" loading="eager">
                </a>
                
                <button class="nav__toggle" aria-label="Toggle navigation menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                
                <ul class="nav__links" role="list">
                    <li><a href="how-it-works.php" class="nav__link">How It Works</a></li>
                    <li><a href="login.php" class="nav__link">Painter Login</a></li>
                    <li><a href="register.php" class="nav__link nav__link--cta">Join as Painter</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main id="main-content" role="main">
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero__container">
                <div class="hero__content">
                    <h1 class="hero__title">Find Trusted Local Painters</h1>
                    <p class="hero__subtitle">Get free quotes from verified painters in your area. Compare prices, read reviews, and hire with confidence.</p>
                    
                    <div class="hero__stats" role="region" aria-label="Service statistics">
                        <div class="hero__stat">
                            <span class="hero__stat-number">2,500+</span>
                            <span class="hero__stat-text">Verified Painters</span>
                        </div>
                        <div class="hero__stat">
                            <span class="hero__stat-number">15,000+</span>
                            <span class="hero__stat-text">Happy Customers</span>
                        </div>
                        <div class="hero__stat">
                            <span class="hero__stat-number">4.8★</span>
                            <span class="hero__stat-text">Average Rating</span>
                        </div>
                    </div>
                    
                    <p class="hero__disclaimer">
                        <small>* Free quotes with no obligation. Average response time: 2 hours</small>
                    </p>
                </div>
                
                <!-- Quote Form -->
                <div class="hero__form">
                    <?php include 'templates/progress.php'; ?>
                    
                    <form class="quote-form" method="post" action="" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
                        
                        <div class="quote-form__container">
                            <?php $wizard->renderStep(); ?>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features" role="region" aria-labelledby="features-title">
            <div class="features__container">
                <h2 id="features-title" class="features__title">Why Choose Painter Near Me?</h2>
                
                <div class="features__grid">
                    <article class="feature">
                        <div class="feature__icon">
                            <img src="serve-asset.php?file=images/trusted.svg" alt="" width="64" height="64" loading="lazy">
                        </div>
                        <h3 class="feature__title">Trusted & Verified</h3>
                        <p class="feature__text">All painters are background checked, insured, and have verified customer reviews.</p>
                    </article>
                    
                    <article class="feature">
                        <div class="feature__icon">
                            <img src="serve-asset.php?file=images/save-time.svg" alt="" width="64" height="64" loading="lazy">
                        </div>
                        <h3 class="feature__title">Save Time</h3>
                        <p class="feature__text">Get multiple quotes instantly instead of calling painters individually.</p>
                    </article>
                    
                    <article class="feature">
                        <div class="feature__icon">
                            <img src="serve-asset.php?file=images/save-money.svg" alt="" width="64" height="64" loading="lazy">
                        </div>
                        <h3 class="feature__title">Save Money</h3>
                        <p class="feature__text">Compare competitive quotes to find the best value for your project.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="testimonials" role="region" aria-labelledby="testimonials-title">
            <div class="testimonials__container">
                <h2 id="testimonials-title" class="section__title">What Our Customers Say</h2>
                
                <div class="testimonials__grid">
                    <blockquote class="testimonial">
                        <p class="testimonial__text">"Found an excellent painter within hours. The quality was outstanding and the price was fair. Highly recommend!"</p>
                        <cite class="testimonial__author">- Sarah J., London</cite>
                    </blockquote>
                    
                    <blockquote class="testimonial">
                        <p class="testimonial__text">"Professional service from start to finish. The painter was punctual, tidy, and did amazing work on our living room."</p>
                        <cite class="testimonial__author">- Michael P., Manchester</cite>
                    </blockquote>
                    
                    <blockquote class="testimonial">
                        <p class="testimonial__text">"Great platform for finding reliable painters. Saved me time and money compared to other methods."</p>
                        <cite class="testimonial__author">- Emma L., Birmingham</cite>
                    </blockquote>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php include 'templates/footer.php'; ?>

    <!-- Scripts -->
    <script src="serve-asset.php?file=js/main.js" defer></script>
    
    <!-- Performance monitoring (inline for faster execution) -->
    <script>
        // Basic performance monitoring that runs immediately
        if ('performance' in window) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData && perfData.loadEventEnd > 3000) {
                        console.warn('Page load time exceeded 3 seconds');
                    }
                }, 0);
            });
        }
    </script>
    
    <!-- Service Worker for offline capability -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').catch(function() {
                    // Service worker registration failed - not critical
                });
            });
        }
    </script>
</body>
</html> 