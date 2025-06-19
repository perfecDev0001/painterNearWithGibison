<?php
/**
 * Gibson-Free Version of Homepage for Live Server Testing
 * This version bypasses all Gibson AI calls to test if API issues are the cause
 */

require_once 'bootstrap.php';
require_once 'core/Step.php';

// Initialize security headers
setSecurityHeaders();

// Start the session for form data persistence
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simplified wizard class without Gibson dependency
class SimpleWizard {
    public $config;
    public $currentStep;
    public $steps;
    public $formData;
    public $errors = [];

    public function __construct() {
        $this->loadConfig();
        $this->steps = $this->config['steps'];
        $this->currentStep = isset($_GET['step']) ? intval($_GET['step']) : 0;
        $this->formData = isset($_SESSION['formData']) ? $_SESSION['formData'] : [];
    }

    private function loadConfig() {
        $this->config = require __DIR__ . '/configs/painting.php';
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stepConfig = $this->steps[$this->currentStep];
            $step = new Step($stepConfig, $_POST);
            if ($step->validate()) {
                $this->formData[$this->currentStep] = $_POST;
                $_SESSION['formData'] = $this->formData;
                if ($this->currentStep < count($this->steps) - 1) {
                    header('Location: ?step=' . ($this->currentStep + 1));
                    exit;
                } else {
                    $this->showSummary();
                    return;
                }
            } else {
                $this->errors = $step->errors;
            }
        }
    }

    public function renderStep() {
        if (!empty($this->errors)) {
            echo '<div class="step__errors" role="alert">';
            foreach ($this->errors as $error) {
                echo '<div class="step__error">' . htmlspecialchars($error) . '</div>';
            }
            echo '</div>';
        }
        
        $stepFile = __DIR__ . '/steps/Step' . ($this->currentStep + 1) . '_' . $this->steps[$this->currentStep]['id'] . '.php';
        if (file_exists($stepFile)) {
            include $stepFile;
        } else {
            echo '<div class="step__error">Step file not found: ' . htmlspecialchars($stepFile) . '</div>';
        }
    }

    public function getCurrentStep() {
        return $this->currentStep;
    }

    public function getStepData() {
        return $this->formData[$this->currentStep] ?? [];
    }

    public function getProgress() {
        $totalSteps = count($this->steps);
        $progress = ($this->currentStep + 1) / $totalSteps * 100;
        return round($progress);
    }

    private function showSummary() {
        // Simple local storage without Gibson AI
        $contactData = $this->formData[5] ?? [];
        $userEmail = $contactData['email'] ?? '';
        
        // Save to local file for testing
        $leadData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'postcode' => $this->formData[0]['postcode'] ?? '',
            'request_type' => $this->formData[1]['requesttype'] ?? '',
            'job_type' => $this->formData[2]['jobtype'] ?? '',
            'property_type' => $this->formData[3]['propertytype'] ?? '',
            'description' => $this->formData[4]['projectdescription'] ?? '',
            'contact' => $contactData
        ];
        
        // Save to simple JSON file
        $logFile = __DIR__ . '/leads-no-gibson.json';
        $leads = [];
        if (file_exists($logFile)) {
            $leads = json_decode(file_get_contents($logFile), true) ?: [];
        }
        $leads[] = $leadData;
        file_put_contents($logFile, json_encode($leads, JSON_PRETTY_PRINT));
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Quote Submitted - No Gibson</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; text-align: center; }
                .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 600px; }
                .summary { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 600px; text-align: left; }
            </style>
        </head>
        <body>
            <h1>🎉 Quote Request Submitted Successfully!</h1>
            
            <div class="success">
                <h2>Thank you!</h2>
                <p>Your quote request has been received and saved locally (Gibson-free mode).</p>
                <?php if ($userEmail): ?>
                <p>Email: <strong><?php echo htmlspecialchars($userEmail); ?></strong></p>
                <?php endif; ?>
            </div>
            
            <div class="summary">
                <h3>Summary</h3>
                <?php foreach ($this->steps as $i => $stepConfig): ?>
                    <?php $data = $this->formData[$i] ?? []; ?>
                    <p><strong><?php echo htmlspecialchars($stepConfig['label']); ?>:</strong>
                    <?php if ($stepConfig['type'] === 'contact'): ?>
                        <br>&nbsp;&nbsp;Name: <?php echo htmlspecialchars($data['fullname'] ?? '-'); ?>
                        <br>&nbsp;&nbsp;Email: <?php echo htmlspecialchars($data['email'] ?? '-'); ?>
                        <br>&nbsp;&nbsp;Phone: <?php echo htmlspecialchars($data['phone'] ?? '-'); ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars(implode(', ', array_filter($data)) ?: '-'); ?>
                    <?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </div>
            
            <p><a href="index-no-gibson.php">← Submit Another Quote</a></p>
        </body>
        </html>
        <?php
        session_destroy();
        exit;
    }
}

// Initialize the simple wizard
$wizard = new SimpleWizard();

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
    <title>Gibson-Free Test | Painter Near Me</title>
    
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .progress { background: #e0e0e0; height: 8px; border-radius: 4px; margin-bottom: 20px; }
        .progress-fill { background: #28a745; height: 100%; border-radius: 4px; transition: width 0.3s; }
        .step { text-align: center; }
        .step__input { padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; width: 200px; margin: 10px 0; }
        .step__button { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 10px 5px; }
        .step__button:hover { background: #1e7e34; }
        .debug-info { background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px; font-size: 14px; }
        .success { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Gibson-Free Quote Wizard</h1>
            <p class="success">✅ Page loaded without Gibson AI!</p>
            <p>This version bypasses all external API calls for testing.</p>
        </div>
        
        <div class="debug-info">
            <strong>Test Info:</strong><br>
            Step: <?php echo $currentStep + 1; ?> of 6<br>
            Progress: <?php echo $progress; ?>%<br>
            Gibson AI: Disabled ❌<br>
            Local Storage: Enabled ✅
        </div>
        
        <div class="progress">
            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
        </div>
        
        <div class="step">
            <form method="post" action="">
                <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
                
                <?php $wizard->renderStep(); ?>
            </form>
        </div>
    </div>
</body>
</html> 