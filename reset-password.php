<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

$token = $_GET['token'] ?? '';
$error = '';
$success = false;
$show_form = true;

if (empty($token)) {
    $error = 'Invalid or missing token.';
    $show_form = false;
} else {
    // Validate token through Gibson AI
    $result = $auth->validatePasswordResetToken($token);
    
    if (!$result['valid']) {
        $error = $result['error'] ?? 'Invalid or expired token.';
        $show_form = false;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            // Reset password through Gibson AI
            $resetResult = $auth->completePasswordReset($token, $password);
            
            if ($resetResult['success']) {
                $success = true;
                $show_form = false;
            } else {
                $error = $resetResult['error'] ?? 'Failed to reset password. Please try again.';
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
    <title>Reset Password | Painter Near Me</title>
    <meta name="description" content="Set a new password for your Painter Near Me account." />
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
      }
    </script>
    <style>
      .reset-password__container { max-width: 400px; margin: 3rem auto; background: #fff; border-radius: 1.2rem; box-shadow: 0 4px 16px rgba(0,176,80,0.08),0 1.5px 8px rgba(0,0,0,0.04); padding: 2.5rem 2rem; }
      .reset-password__title { font-size: 1.5rem; font-weight: 700; color: #00b050; margin-bottom: 1.2rem; text-align: center; }
      .reset-password__form-group { margin-bottom: 1.3rem; }
      .reset-password__label { font-weight: 700; color: #00b050; margin-bottom: 0.3rem; display: block; }
      .reset-password__input { width: 100%; padding: 0.9rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 1.2rem; font-size: 1.1rem; transition: border-color 0.2s; }
      .reset-password__input:focus { border-color: #00b050; outline: none; box-shadow: 0 0 0 2px #b6f5c2; }
      .reset-password__button { width: 100%; background: #00b050; color: #fff; font-weight: 700; border: none; border-radius: 1.2rem; padding: 0.9rem 0; font-size: 1.1rem; cursor: pointer; transition: background 0.2s; }
      .reset-password__button:hover { background: #00913d; }
      .reset-password__error { background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; }
      .reset-password__success { background: #e6f7ea; color: #00b050; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; }
      .reset-password__links { text-align: center; margin-top: 1.5rem; }
      .reset-password__links a { color: #00b050; text-decoration: underline; font-weight: 600; }
    </style>
</head>
<body>
  <main role="main">
    <section class="reset-password" aria-labelledby="reset-password-title">
      <div class="reset-password__container">
        <h1 id="reset-password-title" class="reset-password__title">Reset Password</h1>
        <?php if ($success): ?>
          <div class="reset-password__success">
            Your password has been reset successfully! 
            <div class="reset-password__links">
              <a href="login.php">Login with your new password</a>
            </div>
          </div>
        <?php elseif (!empty($error)): ?>
          <div class="reset-password__error">
            <?php echo htmlspecialchars($error); ?>
            <div class="reset-password__links">
              <a href="forgot-password.php">Request a new reset link</a> | 
              <a href="login.php">Back to Login</a>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($show_form): ?>
          <form class="reset-password__form" method="post" action="" novalidate>
            <div class="reset-password__form-group">
              <label class="reset-password__label" for="password">New Password</label>
              <input class="reset-password__input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password" />
              <small style="color: #6b7280; font-size: 0.9rem;">Must be at least 8 characters long</small>
            </div>
            <div class="reset-password__form-group">
              <label class="reset-password__label" for="password2">Confirm New Password</label>
              <input class="reset-password__input" type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password" />
            </div>
            <button class="reset-password__button" type="submit">Set New Password</button>
          </form>
          <div class="reset-password__links">
            <a href="login.php">Back to Login</a>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html> 