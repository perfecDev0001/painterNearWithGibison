<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Painter Near Me - Get Free Quotes'; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Get free painter quotes, compare local companies, and save up to 40% on your painting project. Trusted by 1M+ clients.'; ?>">
    <meta name="keywords" content="painter quotes, house painting, interior painting, exterior painting, decorators, local painters" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://painter-near-me.co.uk<?php echo $_SERVER['REQUEST_URI'] ?? '/'; ?>" />
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title : 'Painter Near Me - Compare Quotes & Save'; ?>" />
    <meta property="og:description" content="<?php echo isset($page_description) ? $page_description : 'Get free painter quotes, compare local companies, and save up to 40% on your painting project. Trusted by 1M+ clients.'; ?>" />
    <meta property="og:image" content="/assets/images/og-image.jpg" />

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="https://painter-near-me.co.uk<?php echo $_SERVER['REQUEST_URI'] ?? '/'; ?>" />
    <meta property="twitter:title" content="<?php echo isset($page_title) ? $page_title : 'Painter Near Me - Compare Quotes & Save'; ?>" />
    <meta property="twitter:description" content="<?php echo isset($page_description) ? $page_description : 'Get free painter quotes, compare local companies, and save up to 40% on your painting project.'; ?>" />
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
    <link rel="stylesheet" href="serve-asset.php?file=css/style.css">
    
    <?php if (isset($structured_data)): ?>
    <script type="application/ld+json">
    <?php echo json_encode($structured_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
    </script>
    <?php endif; ?>
    
    <style>
    /* Consistent navbar styles */
    .navbar {
        background: white !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .navbar-brand {
        font-weight: 700;
        color: #00b050 !important;
    }

    .nav-link {
        color: #222 !important;
        font-weight: 500;
    }

    .nav-link:hover {
        color: #00b050 !important;
    }

    .nav-link.active {
        color: #00b050 !important;
        font-weight: 600;
    }
    </style>
</head>
<body>
    <!-- Navigation - Bootstrap navbar for consistency -->
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
                        <a class="nav-link<?php echo (strpos($_SERVER['REQUEST_URI'], 'how-it-works') !== false) ? ' active' : ''; ?>" href="how-it-works.php">How it Works</a>
                    </li>
                    <?php
                    // Check if user is logged in
                    session_start();
                    $isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['painter_id']) || isset($_SESSION['customer_id']) || isset($_SESSION['vendor_id']) || isset($_SESSION['admin_id']);
                    
                    if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false) ? ' active' : ''; ?>" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link<?php echo (strpos($_SERVER['REQUEST_URI'], 'register') !== false) ? ' active' : ''; ?>" href="register-hub.php">Join Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo (strpos($_SERVER['REQUEST_URI'], 'login') !== false) ? ' active' : ''; ?>" href="login.php">Login</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo (strpos($_SERVER['REQUEST_URI'], 'contact') !== false) ? ' active' : ''; ?>" href="contact.php">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Add padding to account for fixed navbar -->
    <div style="padding-top: 76px;"></div> 