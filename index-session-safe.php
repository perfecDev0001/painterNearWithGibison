<?php
/**
 * Session-Safe Version of Homepage for Live Server Debugging
 * This version relaxes session security to test if that's causing the issue
 */

require_once 'bootstrap.php';
require_once 'core/Wizard.php';

// Override session security settings for debugging
if (ENVIRONMENT === 'production') {
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_httponly', 0);
    ini_set('session.use_strict_mode', 0);
    ini_set('session.cookie_samesite', '');
}

// Initialize security headers (but not session-related ones)
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Start session with minimal security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    <title>Session-Safe Test | Painter Near Me</title>
    <meta name="description" content="Session-safe test version of quote wizard" />
    
    <!-- Basic favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    
    <!-- Simplified CSS -->
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .progress { background: #e0e0e0; height: 8px; border-radius: 4px; margin-bottom: 20px; }
        .progress-fill { background: #007bff; height: 100%; border-radius: 4px; transition: width 0.3s; }
        .step { text-align: center; }
        .step__input { padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; width: 200px; margin: 10px 0; }
        .step__button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 10px 5px; }
        .step__button:hover { background: #0056b3; }
        .debug-info { background: #e8f4f8; padding: 15px; margin: 20px 0; border-radius: 5px; font-size: 14px; }
        .success { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎨 Session-Safe Quote Wizard Test</h1>
            <p class="success">✅ Page loaded successfully!</p>
            <p>This version uses relaxed session security for debugging.</p>
        </div>
        
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            Step: <?php echo $currentStep + 1; ?> of 6<br>
            Progress: <?php echo $progress; ?>%<br>
            Session ID: <?php echo session_id(); ?><br>
            Environment: <?php echo ENVIRONMENT; ?><br>
            Session Status: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?>
        </div>
        
        <div class="progress">
            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
        </div>
        
        <div class="step">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
                
                <?php $wizard->renderStep(); ?>
            </form>
        </div>
        
        <div class="debug-info">
            <strong>Session Data:</strong><br>
            <pre><?php echo htmlspecialchars(json_encode($_SESSION, JSON_PRETTY_PRINT)); ?></pre>
        </div>
    </div>
</body>
</html> 