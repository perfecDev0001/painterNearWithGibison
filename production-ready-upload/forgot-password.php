<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

$email = '';
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Use Gibson AI password reset
        $result = $auth->resetPassword($email);
        
        // Send password reset email
        try {
            require_once __DIR__ . '/core/Mailer.php';
            $mailer = new Core\Mailer();
            
            // Generate a password reset token (in production, this should be stored in database)
            $resetToken = bin2hex(random_bytes(32));
            $resetLink = 'https://painter-near-me.co.uk/reset-password.php?token=' . $resetToken . '&email=' . urlencode($email);
            
            $subject = 'Password Reset Request - Painter Near Me';
            $message = "<h2>Password Reset Request</h2>";
            $message .= "<p>Hello,</p>";
            $message .= "<p>We received a request to reset the password for your Painter Near Me account associated with this email address.</p>";
            $message .= "<p>If you made this request, please click the link below to reset your password:</p>";
            $message .= "<p><a href='" . $resetLink . "' style='background:#00b050;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;margin:10px 0;'>Reset Your Password</a></p>";
            $message .= "<p>Alternatively, you can copy and paste this link into your browser:</p>";
            $message .= "<p style='word-break:break-all;background:#f5f5f5;padding:10px;border-radius:5px;font-family:monospace;'>" . $resetLink . "</p>";
            $message .= "<p><strong>This link will expire in 24 hours for your security.</strong></p>";
            $message .= "<p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>";
            $message .= "<p>For security reasons, if you continue to receive these emails, please contact us immediately.</p>";
            $message .= "<h3>Need Help?</h3>";
            $message .= "<p>If you're having trouble with the password reset process, please contact us:</p>";
            $message .= "<ul>";
            $message .= "<li>Email: info@painter-near-me.co.uk</li>";
            $message .= "<li>Visit: <a href='https://painter-near-me.co.uk/contact.php'>Contact Us</a></li>";
            $message .= "</ul>";
            $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
            
            $mailer->sendMail($email, $subject, $message, strip_tags($message));
            
            // In production, you would also store the reset token in the database
            // For now, we'll use session storage (not recommended for production)
            session_start();
            $_SESSION['reset_tokens'][$resetToken] = [
                'email' => $email,
                'expires' => time() + (24 * 60 * 60) // 24 hours
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
        }
        
        // Always show success for security (don't reveal if email exists)
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Painter Near Me</title>
    <meta name="description" content="Reset your password for Painter Near Me." />
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Painter Near Me",
        "url": "https://painter-near-me.co.uk"
      }
    </script>
    <style>
      .forgot-password__container { max-width: 400px; margin: 3rem auto; background: #fff; border-radius: 1.2rem; box-shadow: 0 4px 16px rgba(0,176,80,0.08),0 1.5px 8px rgba(0,0,0,0.04); padding: 2.5rem 2rem; }
      .forgot-password__title { font-size: 1.5rem; font-weight: 700; color: #00b050; margin-bottom: 1.2rem; text-align: center; }
      .forgot-password__form-group { margin-bottom: 1.3rem; }
      .forgot-password__label { font-weight: 700; color: #00b050; margin-bottom: 0.3rem; display: block; }
      .forgot-password__input { width: 100%; padding: 0.9rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 1.2rem; font-size: 1.1rem; transition: border-color 0.2s; }
      .forgot-password__input:focus { border-color: #00b050; outline: none; box-shadow: 0 0 0 2px #b6f5c2; }
      .forgot-password__button { width: 100%; background: #00b050; color: #fff; font-weight: 700; border: none; border-radius: 1.2rem; padding: 0.9rem 0; font-size: 1.1rem; cursor: pointer; transition: background 0.2s; }
      .forgot-password__button:hover { background: #00913d; }
      .forgot-password__error { background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; }
      .forgot-password__success { background: #e6f7ea; color: #00b050; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; }
    </style>
</head>
<body>
  <main role="main">
    <section class="forgot-password" aria-labelledby="forgot-password-title">
      <div class="forgot-password__container">
        <h1 id="forgot-password-title" class="forgot-password__title">Forgot Password</h1>
        <?php if ($success): ?>
          <div class="forgot-password__success">
            If this email is registered, a password reset link will be sent to your inbox.
            <br><br>
            <a href="login.php" style="color:#00b050;font-weight:700;text-decoration:underline;">Return to Login</a>
          </div>
        <?php else: ?>
          <?php if (!empty($error)): ?>
            <div class="forgot-password__error"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          <form class="forgot-password__form" method="post" action="" novalidate>
            <div class="forgot-password__form-group">
              <label class="forgot-password__label" for="email">Email Address</label>
              <input class="forgot-password__input" type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autocomplete="email" />
            </div>
            <button class="forgot-password__button" type="submit">Send Reset Link</button>
          </form>
          <div style="text-align:center;margin-top:1.5rem;">
            <a href="login.php" style="color:#666;text-decoration:underline;">Back to Login</a>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html> 