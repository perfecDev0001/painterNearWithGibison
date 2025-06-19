<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonAIService.php';

session_start();

$auth = new GibsonAuth();
$service = new GibsonAIService();

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors = [];
$success = false;
$already_exists = false;

// Check if any admin exists
$adminCheckResponse = $service->checkAdminExists();
if ($adminCheckResponse['success'] && $adminCheckResponse['data']['exists']) {
    $already_exists = true;
}

if (!$already_exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token.';
    }
    
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $password2) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        $adminData = [
            'email' => $email,
            'password' => $password,
            'role' => 'super_admin'
        ];
        
        $createResponse = $service->createAdmin($adminData);
        if ($createResponse['success']) {
            $success = true;
        } else {
            $errors[] = $createResponse['error'] ?? 'Failed to create super admin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Setup | Painter Near Me</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="One-time admin setup for Painter Near Me marketplace." />
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
  </script>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #f7fafc 0%, #e6f7ea 100%);
      margin: 0;
      padding: 2rem 1rem;
      min-height: 100vh;
    }
    .setup-form__container { 
      max-width: 500px; 
      margin: 2.5rem auto; 
      background: #fff; 
      border-radius: 1.5rem; 
      box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04); 
      padding: 2.5rem 2rem; 
    }
    .setup-form__title {
      color: #00b050;
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 1rem;
      text-align: center;
    }
    .setup-form__subtitle {
      color: #6b7280;
      margin-bottom: 2rem;
      text-align: center;
      line-height: 1.5;
    }
    .setup-form__warning {
      color: #dc2626;
      font-weight: 600;
      background: #fee2e2;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .setup-form__group { 
      margin-bottom: 1.3rem; 
    }
    .setup-form__label { 
      font-weight: 700; 
      color: #00b050; 
      margin-bottom: 0.5rem; 
      display: block; 
      font-size: 1rem;
    }
    .setup-form__input { 
      width: 100%; 
      padding: 0.9rem 1.1rem; 
      border: 1.5px solid #e5e7eb; 
      border-radius: 0.7rem; 
      font-size: 1.1rem; 
      transition: all 0.2s;
      box-sizing: border-box;
    }
    .setup-form__input:focus { 
      border-color: #00b050; 
      outline: none; 
      box-shadow: 0 0 0 3px rgba(0, 176, 80, 0.1); 
    }
    .setup-form__button { 
      width: 100%;
      margin-top: 1.5rem; 
      background: #00b050; 
      color: #fff; 
      font-weight: 700; 
      padding: 1rem 2rem; 
      border: none; 
      border-radius: 0.7rem; 
      font-size: 1.1rem;
      cursor: pointer;
      transition: background 0.2s;
    }
    .setup-form__button:hover {
      background: #009140;
    }
    .setup-form__errors { 
      background: #fee2e2; 
      color: #dc2626; 
      padding: 1rem; 
      border-radius: 0.5rem; 
      margin-bottom: 1.5rem; 
    }
    .setup-form__success { 
      background: #e6f7ea; 
      color: #00b050; 
      padding: 1.5rem; 
      border-radius: 0.5rem; 
      margin-bottom: 1.5rem; 
      text-align: center;
      font-weight: 600;
    }
    .setup-form__success a {
      color: #00b050;
      text-decoration: underline;
      font-weight: 700;
    }
    .setup-form__already-exists {
      background: #f3f4f6;
      color: #374151;
      padding: 1.5rem;
      border-radius: 0.5rem;
      text-align: center;
      font-weight: 600;
    }
    .setup-form__links {
      text-align: center;
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid #e5e7eb;
    }
    .setup-form__links a {
      color: #00b050;
      text-decoration: none;
      font-weight: 600;
    }
    .setup-form__links a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <main role="main">
    <div class="setup-form__container">
      <h1 class="setup-form__title">Admin Setup</h1>
      <p class="setup-form__subtitle">One-time setup: Create your first super admin account.</p>
      
      <?php if (!$already_exists): ?>
        <div class="setup-form__warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          Delete this file after use for security!
        </div>
      <?php endif; ?>
      
      <?php if ($already_exists): ?>
        <div class="setup-form__already-exists">
          <i class="bi bi-check-circle-fill"></i>
          An admin already exists. Setup is disabled for security.
        </div>
        <div class="setup-form__links">
          <a href="admin-login.php">Go to Admin Login</a>
        </div>
      <?php elseif ($success): ?>
        <div class="setup-form__success">
          <i class="bi bi-check-circle-fill"></i>
          Super admin created successfully!<br>
          <a href="admin-login.php">Login here</a><br>
          <small style="color: #dc2626; font-weight: 600; margin-top: 1rem; display: block;">
            Remember to delete this file for security!
          </small>
        </div>
      <?php else: ?>
        <?php if (!empty($errors)): ?>
          <div class="setup-form__errors">
            <ul style="margin:0;padding-left:1.5rem;">
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        
        <form class="setup-form" method="post" action="" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          
          <div class="setup-form__group">
            <label class="setup-form__label" for="setup-email">Admin Email</label>
            <input class="setup-form__input" type="email" id="setup-email" name="email" required autocomplete="email" placeholder="admin@example.com" />
          </div>
          
          <div class="setup-form__group">
            <label class="setup-form__label" for="setup-password">Password</label>
            <input class="setup-form__input" type="password" id="setup-password" name="password" required minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters" />
          </div>
          
          <div class="setup-form__group">
            <label class="setup-form__label" for="setup-password2">Confirm Password</label>
            <input class="setup-form__input" type="password" id="setup-password2" name="password2" required minlength="8" autocomplete="new-password" placeholder="Confirm your password" />
          </div>
          
          <button class="setup-form__button" type="submit">Create Super Admin</button>
        </form>
        
        <div class="setup-form__links">
          <a href="index.php">← Back to Homepage</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html> 