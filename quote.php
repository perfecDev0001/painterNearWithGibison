<?php
// quote.php - Entry point for the quote engine

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

session_start();

require_once __DIR__ . '/core/Wizard.php';

// Try to load wizard, fallback if issues
try {
    $wizard = new Wizard();
    $wizard->handleRequest();
} catch (Exception $e) {
    error_log("Quote wizard error: " . $e->getMessage());
    
    // Fallback to simple quote form
    include 'templates/header.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Get Quote | Painter Near Me</title>
        <meta name="description" content="Get free quotes from professional painters near you." />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "LocalBusiness",
          "name": "Painter Near Me",
          "url": "https://painter-near-me.co.uk"
        }
        </script>
    </head>
    <body>
        <main role="main">
            <section class="quote-hero hero">
                <h1 class="hero__title">Get Your Free Quote</h1>
                <p class="hero__subtitle">Connect with professional painters in your area</p>
            </section>
            
            <section class="quote-form-section">
                <div class="quote-form-container">
                    <form class="quote-form" action="/" method="post">
                        <div class="quote-form__group">
                            <label class="quote-form__label" for="postcode">Your Postcode</label>
                            <input class="quote-form__input" type="text" id="postcode" name="postcode" placeholder="Enter your postcode" required>
                        </div>
                        
                        <div class="quote-form__group">
                            <label class="quote-form__label" for="job_type">Type of Painting Job</label>
                            <select class="quote-form__input" id="job_type" name="job_type" required>
                                <option value="">Select job type</option>
                                <option value="interior">Interior Painting</option>
                                <option value="exterior">Exterior Painting</option>
                                <option value="both">Interior & Exterior</option>
                                <option value="commercial">Commercial</option>
                            </select>
                        </div>
                        
                        <div class="quote-form__group">
                            <label class="quote-form__label" for="description">Project Description</label>
                            <textarea class="quote-form__input" id="description" name="description" rows="4" placeholder="Describe your painting project..." required></textarea>
                        </div>
                        
                        <button class="quote-form__submit" type="submit">Get Free Quotes</button>
                    </form>
                </div>
            </section>
        </main>
        
        <style>
        .quote-hero {
            background: linear-gradient(120deg, #00b050 0%, #009140 100%);
            color: white;
            padding: 3rem 1rem;
            text-align: center;
        }
        .hero__title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
        }
        .hero__subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .quote-form-section {
            padding: 3rem 1rem;
            background: #f8f9fa;
        }
        .quote-form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .quote-form {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 16px rgba(0,176,80,0.1);
        }
        .quote-form__group {
            margin-bottom: 1.5rem;
        }
        .quote-form__label {
            display: block;
            font-weight: 700;
            color: #00b050;
            margin-bottom: 0.5rem;
        }
        .quote-form__input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
        }
        .quote-form__input:focus {
            border-color: #00b050;
            outline: none;
        }
        .quote-form__submit {
            background: #00b050;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
        }
        .quote-form__submit:hover {
            background: #009140;
        }
        </style>
    </body>
    </html>
    <?php
    include 'templates/footer.php';
}
?> 