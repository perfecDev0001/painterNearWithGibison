<?php
/**
 * Debug Version of Homepage to Identify Quote Wizard Issues
 * This file includes extensive logging and error checking
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug-homepage-errors.log');

// Function to log debug information
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= ": " . print_r($data, true);
    }
    error_log($logMessage, 3, __DIR__ . '/debug-homepage.log');
}

debugLog("DEBUG HOMEPAGE STARTED");

try {
    debugLog("Step 1: Checking file existence");
    
    // Check if bootstrap.php exists
    if (!file_exists('bootstrap.php')) {
        throw new Exception("bootstrap.php not found");
    }
    debugLog("bootstrap.php found");
    
    // Try to include bootstrap
    require_once 'bootstrap.php';
    debugLog("bootstrap.php included successfully");
    
    // Check if Wizard.php exists
    if (!file_exists('core/Wizard.php')) {
        throw new Exception("core/Wizard.php not found");
    }
    debugLog("core/Wizard.php found");
    
    require_once 'core/Wizard.php';
    debugLog("core/Wizard.php included successfully");
    
    debugLog("Step 2: Setting security headers");
    setSecurityHeaders();
    debugLog("Security headers set");
    
    debugLog("Step 3: Starting session", [
        'session_status' => session_status(),
        'session_save_path' => session_save_path(),
        'session_name' => session_name()
    ]);
    
    // Start the session for form data persistence
    if (session_status() === PHP_SESSION_NONE) {
        if (!session_start()) {
            throw new Exception("Failed to start session");
        }
        debugLog("Session started successfully", [
            'session_id' => session_id(),
            'session_data' => $_SESSION
        ]);
    } else {
        debugLog("Session already active", [
            'session_id' => session_id(),
            'session_data' => $_SESSION
        ]);
    }
    
    debugLog("Step 4: Initializing wizard");
    
    // Initialize the quote wizard
    try {
        $wizard = new Wizard();
        debugLog("Wizard initialized successfully");
    } catch (Exception $e) {
        debugLog("Wizard initialization failed", ['error' => $e->getMessage()]);
        throw $e;
    }
    
    debugLog("Step 5: Handling request", [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'GET_data' => $_GET,
        'POST_data' => $_POST
    ]);
    
    // Handle form submission and progression
    try {
        $wizard->handleRequest();
        debugLog("Request handled successfully");
    } catch (Exception $e) {
        debugLog("Request handling failed", ['error' => $e->getMessage()]);
        throw $e;
    }
    
    debugLog("Step 6: Getting wizard data");
    
    // Get current step data
    try {
        $currentStep = $wizard->getCurrentStep();
        $stepData = $wizard->getStepData();
        $progress = $wizard->getProgress();
        
        debugLog("Wizard data retrieved", [
            'currentStep' => $currentStep,
            'stepData' => $stepData,
            'progress' => $progress
        ]);
    } catch (Exception $e) {
        debugLog("Failed to get wizard data", ['error' => $e->getMessage()]);
        throw $e;
    }
    
    debugLog("Step 7: Starting HTML output");

} catch (Exception $e) {
    debugLog("FATAL ERROR", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Show user-friendly error page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Debug - Error Detected</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .error { background: #fee; border: 1px solid #fcc; padding: 20px; border-radius: 5px; }
            .debug { background: #eef; border: 1px solid #ccf; padding: 20px; border-radius: 5px; margin-top: 20px; }
            pre { white-space: pre-wrap; }
        </style>
    </head>
    <body>
        <h1>Debug Homepage - Error Detected</h1>
        <div class="error">
            <h2>Error:</h2>
            <p><strong><?php echo htmlspecialchars($e->getMessage()); ?></strong></p>
            <p>File: <?php echo htmlspecialchars($e->getFile()); ?></p>
            <p>Line: <?php echo htmlspecialchars($e->getLine()); ?></p>
        </div>
        
        <div class="debug">
            <h2>Debug Information:</h2>
            <p>Check the debug log file: <code>debug-homepage.log</code></p>
            <p>Check the error log file: <code>debug-homepage-errors.log</code></p>
            
            <h3>Environment:</h3>
            <pre><?php 
            echo "PHP Version: " . PHP_VERSION . "\n";
            echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
            echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
            echo "Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "\n";
            echo "Working Directory: " . getcwd() . "\n";
            echo "Session Save Path: " . session_save_path() . "\n";
            echo "Session Status: " . session_status() . "\n";
            ?></pre>
            
            <h3>Server Variables:</h3>
            <pre><?php print_r($_SERVER); ?></pre>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Get Free Painting Quotes | Painter Near Me</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-info { background: #e8f4f8; border: 1px solid #bee5eb; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .quote-form { background: #f8f9fa; padding: 20px; border-radius: 5px; }
        .step__input { padding: 10px; margin: 10px 0; width: 200px; }
        .step__button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Debug Homepage - Quote Wizard Working!</h1>
    
    <div class="debug-info">
        <h3>Debug Information:</h3>
        <p><strong>Current Step:</strong> <?php echo htmlspecialchars($currentStep); ?></p>
        <p><strong>Progress:</strong> <?php echo htmlspecialchars($progress); ?>%</p>
        <p><strong>Session ID:</strong> <?php echo htmlspecialchars(session_id()); ?></p>
        <p><strong>Session Data:</strong> <code><?php echo htmlspecialchars(json_encode($_SESSION)); ?></code></p>
        <p><strong>GET Data:</strong> <code><?php echo htmlspecialchars(json_encode($_GET)); ?></code></p>
        <p><strong>POST Data:</strong> <code><?php echo htmlspecialchars(json_encode($_POST)); ?></code></p>
    </div>
    
    <div class="quote-form">
        <h2>Step <?php echo $currentStep + 1; ?> of 6</h2>
        <div style="background: #ccc; height: 10px; margin-bottom: 20px;">
            <div style="background: #007bff; height: 100%; width: <?php echo $progress; ?>%;"></div>
        </div>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
            
            <?php 
            debugLog("Step 8: Rendering wizard step");
            try {
                $wizard->renderStep();
                debugLog("Step rendered successfully");
            } catch (Exception $e) {
                debugLog("Step rendering failed", ['error' => $e->getMessage()]);
                echo '<div style="color: red;">Error rendering step: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </form>
    </div>
    
    <div class="debug-info">
        <p><strong>Debug Log:</strong> Check <code>debug-homepage.log</code> for detailed execution log</p>
        <p><strong>Error Log:</strong> Check <code>debug-homepage-errors.log</code> for PHP errors</p>
    </div>
    
    <script>
        console.log('Debug homepage loaded successfully');
        console.log('Current step:', <?php echo json_encode($currentStep); ?>);
        console.log('Progress:', <?php echo json_encode($progress); ?>);
    </script>
</body>
</html>

<?php
debugLog("DEBUG HOMEPAGE COMPLETED SUCCESSFULLY");
?> 