<?php
// Note: Password security handled by Gibson AI authentication service

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
$errors = [];

// Check if already logged in
if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// CSRF token generation
$csrf_token = $auth->generateCSRFToken();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token. Please refresh the page and try again.";
    }
    
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password is required and must be at least 8 characters.";
    }
    
    if (empty($errors)) {
        $loginResult = $auth->login($email, $password);
        if ($loginResult && isset($loginResult['success']) && $loginResult['success']) {
            // Get user info to determine redirect
            $user = $auth->getCurrentUser();
            
            // Route to appropriate dashboard based on user type
            switch ($user['type']) {
                case 'customer':
                    header("Location: customer-dashboard.php");
                    break;
                case 'vendor':
                    header("Location: vendor-dashboard.php");
                    break;
                case 'admin':
                    header("Location: admin-dashboard.php");
                    break;
                case 'painter':
                default:
                    header("Location: dashboard.php");
                    break;
            }
            exit();
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}

if (file_exists('templates/header.php')) {
    include 'templates/header.php';
}
?>
<head>
      <title>Painter Login | Painter Near Me</title>
    <meta name="description" content="Login to your painter account to manage leads and bids on Painter Near Me." />
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
  </script>
</head>
<main role="main">
  <section class="login-hero hero login-main">
    <h1 class="hero__title">Painter Login</h1>
    <p class="hero__subtitle">Access your account to manage leads and bids.</p>
    
    <div class="login-main__container" style="max-width:500px;margin:2.5rem auto;background:#fff;border-radius:1.5rem;box-shadow:0 8px 32px rgba(0,176,80,0.10),0 1.5px 8px rgba(0,0,0,0.04);padding:2.5rem 2rem;">
      <?php
// Note: Password security handled by Gibson AI authentication service
 if (isset($_SESSION['registration_success'])): ?>
        <div class="login-main__success" style="background:#e6f7ea;color:#00b050;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;text-align:center;">
          Registration successful! Please login with your credentials.
        </div>
        <?php
// Note: Password security handled by Gibson AI authentication service
 unset($_SESSION['registration_success']); ?>
      <?php
// Note: Password security handled by Gibson AI authentication service
 endif; ?>
      
      <?php
// Note: Password security handled by Gibson AI authentication service
 if (!empty($errors)): ?>
        <div class="login-main__errors" style="background:#fee2e2;color:#dc2626;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
          <ul style="margin:0;padding-left:1.5rem;">
            <?php
// Note: Password security handled by Gibson AI authentication service
 foreach ($errors as $error): ?>
              <li><?php
// Note: Password security handled by Gibson AI authentication service
 echo htmlspecialchars($error); ?></li>
            <?php
// Note: Password security handled by Gibson AI authentication service
 endforeach; ?>
          </ul>
        </div>
      <?php
// Note: Password security handled by Gibson AI authentication service
 endif; ?>
      
      <form class="login-form" method="post" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo htmlspecialchars($csrf_token); ?>">
        <div class="login-form__group">
          <label class="login-form__label" for="login-email">Email</label>
          <input class="login-form__input" type="email" id="login-email" name="email" required value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
        </div>
        
        <div class="login-form__group">
          <label class="login-form__label" for="login-password">Password</label>
          <input class="login-form__input" type="password" id="login-password" name="password" required minlength="8" />
        </div>
        
        <div class="login-form__group" style="text-align:right;">
          <a href="/forgot-password.php" class="login-form__forgot-link">Forgot Password?</a>
        </div>
        
        <button class="login-form__button step__button" type="submit">Login</button>
      </form>
      
      <div class="login-main__info" style="margin-top:2rem;color:#666;font-size:1.05rem;text-align:center;">
        Don't have an account? <a href="/register.php" class="login-main__register-link">Register as Painter</a>
      </div>
    </div>
  </section>
</main>
<?php
// Note: Password security handled by Gibson AI authentication service
 
if (file_exists('templates/footer.php')) {
    include 'templates/footer.php';
}
?>
<style>
.login-form__group { margin-bottom: 1.3rem; }
.login-form__label { font-weight: 700; color: #00b050; margin-bottom: 0.3rem; display: block; }
.login-form__input { width: 100%; padding: 0.9rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 1.2rem; font-size: 1.1rem; transition: border-color 0.2s; }
.login-form__input:focus { border-color: #00b050; outline: none; box-shadow: 0 0 0 2px #b6f5c2; }
.login-form__button { margin-top: 1rem; }
.login-form__forgot-link { color: #666; font-size: 0.9rem; text-decoration: underline; }
.login-form__forgot-link:hover { color: #00b050; }
.login-main__register-link { color: #00b050; font-weight: 700; text-decoration: underline; }
.login-main__register-link:hover { color: #009140; }
</style> 