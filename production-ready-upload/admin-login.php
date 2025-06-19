<?php
// Note: Password security handled by Gibson AI authentication service

// Production error handling - suppress all output to prevent header issues
if (getenv('GIBSON_DEVELOPMENT_MODE') === 'true' || $_ENV['GIBSON_DEVELOPMENT_MODE'] === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(0); // Suppress all error output in production
}

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

require_once 'core/GibsonAuth.php';

$auth = new GibsonAuth();
$errors = [];

// Check if admin is already logged in
if ($auth->isAdminLoggedIn()) {
    header('Location: admin-manage-leads.php');
    exit();
}

// CSRF token generation
$csrf_token = $auth->generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    }
    
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'Password is required and must be at least 8 characters.';
    }
    
    if (empty($errors)) {
        $loginResult = $auth->adminLogin($email, $password);
        if ($loginResult) {
            header('Location: admin-manage-leads.php');
            exit();
        } else {
            // Add debugging information in development mode
            if (getenv('GIBSON_DEVELOPMENT_MODE') === 'true') {
                $errors[] = 'Login failed for: ' . htmlspecialchars($email) . '. Check credentials: admin@painter-near-me.co.uk / admin123';
            } else {
                $errors[] = 'Invalid email or password.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Painter Near Me</title>
    <meta name="description" content="Admin login for Painter Near Me marketplace." />
    <meta property="og:title" content="Admin Login | Painter Near Me">
    <meta property="og:description" content="Admin login for Painter Near Me marketplace.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.painter-near-me.co.uk/admin-login.php">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' fill='%2300b050'/%3E%3Ctext x='8' y='12' font-family='Arial' font-size='10' fill='white' text-anchor='middle'%3EP%3C/text%3E%3C/svg%3E" type="image/svg+xml">
    <link rel="stylesheet" href="serve-asset.php?file=css/style.css">
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
<div class="admin-login">
  <main class="admin-login__main" role="main">
    <div class="admin-login__container">
      <section class="admin-login__hero admin-card">
        <img src="serve-asset.php?file=images/logo.svg" alt="Painter Near Me logo" loading="lazy" class="admin-login__logo" />
        <h1 class="admin-login__title">Admin Login</h1>
        <p class="admin-login__subtitle">Access the admin dashboard</p>
      </section>

      <section class="admin-login__form-section admin-card">
        <?php
// Note: Password security handled by Gibson AI authentication service
 if (!empty($errors)): ?>
          <div class="admin-login__alert admin-login__alert--error" role="alert">
            <ul class="admin-login__error-list">
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

        <form class="admin-login__form" method="post" action="" novalidate>
          <input type="hidden" name="csrf_token" value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo htmlspecialchars($csrf_token); ?>">
          
          <div class="admin-login__form-group">
            <label class="admin-login__label" for="admin-login-email">Email</label>
            <input 
              class="admin-login__input" 
              type="email" 
              id="admin-login-email" 
              name="email" 
              required 
              aria-required="true"
              value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
            />
          </div>

          <div class="admin-login__form-group">
            <label class="admin-login__label" for="admin-login-password">Password</label>
            <input 
              class="admin-login__input" 
              type="password" 
              id="admin-login-password" 
              name="password" 
              required 
              minlength="8"
              aria-required="true"
            />
          </div>

          <button class="admin-login__submit" type="submit">
            Login to Dashboard
          </button>
        </form>
      </section>
    </div>
  </main>
</div>

<style>
.admin-login {
  min-height: 100vh;
  background: #f7fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.admin-login__container {
  width: 100%;
  max-width: 400px;
}

.admin-login__hero {
  text-align: center;
  margin-bottom: 1.5rem;
}

.admin-login__logo {
  height: 3rem;
  width: auto;
  margin-bottom: 1.5rem;
}

.admin-login__title {
  color: #00b050;
  font-size: 2rem;
  font-weight: 800;
  margin: 0 0 0.5rem 0;
}

.admin-login__subtitle {
  color: #6b7280;
  font-size: 1.1rem;
  margin: 0;
}

.admin-login__alert {
  padding: 1rem;
  border-radius: 0.7rem;
  margin-bottom: 1.5rem;
  font-weight: 600;
}

.admin-login__alert--error {
  background: #fee2e2;
  color: #dc2626;
}

.admin-login__error-list {
  margin: 0;
  padding-left: 1.5rem;
}

.admin-login__form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.admin-login__form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.admin-login__label {
  font-weight: 700;
  color: #00b050;
}

.admin-login__input {
  padding: 0.9rem 1.1rem;
  border: 1.5px solid #e5e7eb;
  border-radius: 1.2rem;
  font-size: 1.1rem;
  transition: border-color 0.2s;
}

.admin-login__input:focus {
  border-color: #00b050;
  outline: none;
  box-shadow: 0 0 0 2px #b6f5c2;
}

.admin-login__submit {
  background: #00b050;
  color: #fff;
  font-weight: 700;
  border: none;
  border-radius: 1.2rem;
  padding: 0.9rem 0;
  font-size: 1.1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.admin-login__submit:hover {
  background: #00913d;
}

.admin-card {
  background: #fff;
  border-radius: 1.2rem;
  box-shadow: 0 4px 16px rgba(0,176,80,0.08), 0 1.5px 8px rgba(0,0,0,0.04);
  padding: 2rem;
}
</style>

</body>
</html> 