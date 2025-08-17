<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Wizard</title>
    <meta name="description" content="Get free painter quotes, compare local companies, and save up to 40% on your painting project. Trusted by 1M+ clients.">
    <meta property="og:title" content="Painter Near Me - Compare Quotes & Save">
    <meta property="og:description" content="Get free painter quotes, compare local companies, and save up to 40% on your painting project. Trusted by 1M+ clients.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.painter-near-me.co.uk/">
    <meta property="og:image" content="assets/images/logo.svg">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' fill='%2300b050'/%3E%3Ctext x='8' y='12' font-family='Arial' font-size='10' fill='white' text-anchor='middle'%3EP%3C/text%3E%3C/svg%3E" type="image/svg+xml">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="serve-asset.php?file=css/style.css">
</head>
<body>
<header class="header" role="banner">
    <nav class="nav" role="navigation" aria-label="Main navigation">
        <div class="nav__container">
            <a class="nav__logo" href="/" aria-label="Home">
                        <img src="serve-asset.php?file=images/logo.svg" alt="Painter Near Me logo" loading="lazy" />
        Painter Near Me
            </a>
            <button class="nav__toggle" aria-label="Open menu" onclick="document.querySelector('.nav__links').classList.toggle('nav__links--open')">&#9776;</button>
            <ul class="nav__links">
                <li><a class="nav__link" href="/how-it-works.php">How it Works</a></li>
                <li><a class="nav__link" href="/customer-dashboard.php">My Projects</a></li>
                <li><a class="nav__link" href="/contact.php">Contact</a></li>
            </ul>
            <div class="nav__links" id="navLinks">
                <a href="/#testimonials" class="nav__link">Testimonials</a>
            </div>
        </div>
    </nav>
</header>

<script>
// Mobile nav toggle
const navToggle = document.querySelector('.nav__toggle');
const navLinks = document.getElementById('navLinks');
if(navToggle && navLinks) {
  navToggle.addEventListener('click', function() {
    const open = navLinks.classList.toggle('nav__links--open');
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}
</script> 