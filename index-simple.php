<?php
// Simple homepage for testing - bypasses quote wizard issues
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Free Painting Quotes | Painter Near Me</title>
    <meta name="description" content="Get free quotes from verified local painters. Compare prices, read reviews, and hire the best painter for your project." />
    <link rel="stylesheet" href="serve-asset.php?file=css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header" role="banner">
        <nav class="nav" role="navigation">
            <div class="nav__container">
                <a href="/" class="nav__logo">
                    <img src="serve-asset.php?file=images/logo.svg" alt="Painter Near Me" width="180" height="40">
                </a>
                <ul class="nav__links">
                    <li><a href="how-it-works.php" class="nav__link">How It Works</a></li>
                    <li><a href="login.php" class="nav__link">Painter Login</a></li>
                    <li><a href="register.php" class="nav__link nav__link--cta">Join as Painter</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main role="main">
        <section class="hero">
            <div class="hero__container">
                <div class="hero__content">
                    <h1 class="hero__title">Find Trusted Local Painters</h1>
                    <p class="hero__subtitle">Get free quotes from verified painters in your area. Compare prices, read reviews, and hire with confidence.</p>
                    
                    <div class="hero__stats">
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
                    
                    <!-- Simple Quote Button instead of wizard -->
                    <div class="hero__cta">
                        <a href="quote.php" class="btn btn--primary btn--large">Get Free Quote</a>
                        <p class="hero__disclaimer">
                            <small>* Free quotes with no obligation. Average response time: 2 hours</small>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features">
            <div class="features__container">
                <h2 class="features__title">Why Choose Painter Near Me?</h2>
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
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer__container">
            <div class="footer__grid">
                <div class="footer__section">
                    <h3 class="footer__title">For Consumers</h3>
                    <ul class="footer__links">
                        <li><a href="/">Home</a></li>
                        <li><a href="how-it-works.php">How it Works</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer__section">
                    <h3 class="footer__title">For Companies</h3>
                    <ul class="footer__links">
                        <li><a href="register.php">Register as Painter</a></li>
                        <li><a href="login.php">Login</a></li>
                    </ul>
                </div>
                <div class="footer__section">
                    <h3 class="footer__title">Legal</h3>
                    <ul class="footer__links">
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms</a></li>
                        <li><a href="cookie-policy.php">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer__bottom">
                <p>© <?php echo date('Y'); ?> - Painter-near-me.co.uk. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html> 