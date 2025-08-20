<?php
require_once __DIR__ . '/Step.php';
require_once __DIR__ . '/GibsonDataAccess.php';

class Wizard {
    public $config;
    public $currentStep;
    public $steps;
    public $formData;
    public $errors = [];
    private $dataAccess;

    public function __construct() {
        $this->loadConfig();
        $this->steps = $this->config['steps'];
        $this->currentStep = isset($_GET['step']) ? intval($_GET['step']) : 0;
        $this->formData = isset($_SESSION['formData']) ? $_SESSION['formData'] : [];
        $this->dataAccess = new GibsonDataAccess();
        
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function loadConfig() {
        // Default to painting config for now
        $this->config = require __DIR__ . '/../configs/painting.php';
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
                !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                $this->errors[] = 'Invalid security token. Please refresh the page and try again.';
                return;
            }
            
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
                    session_destroy();
                    exit;
                }
            } else {
                $this->errors = $step->errors;
            }
        }
    }

    public function renderStep() {
        if (!empty($this->errors)) {
            echo '<div class="wizard-errors" role="alert">';
            echo '<div class="wizard-errors__container">';
            echo '<div class="wizard-errors__icon">⚠️</div>';
            echo '<div class="wizard-errors__content">';
            foreach ($this->errors as $error) {
                echo '<div class="wizard-errors__message">' . htmlspecialchars($error) . '</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        $stepFile = __DIR__ . '/../steps/Step' . ($this->currentStep + 1) . '_' . $this->steps[$this->currentStep]['id'] . '.php';
        if (file_exists($stepFile)) {
            $wizard = $this; // Make wizard instance available to step file
            $stepData = $this->getStepData(); // Make step data available
            include $stepFile;
        } else {
            echo '<div class="wizard-error-not-found">';
            echo '<div class="wizard-error-not-found__icon">🔍</div>';
            echo '<div class="wizard-error-not-found__message">Step file not found: ' . htmlspecialchars($stepFile) . '</div>';
            echo '</div>';
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

    private function getStepIcon($stepId) {
        $icons = [
            'Postcode' => '📍',
            'RequestType' => '🎨',
            'JobType' => '🏢',
            'PropertyType' => '🏠',
            'ProjectDescription' => '✏️',
            'ContactDetails' => '👤'
        ];
        
        return $icons[$stepId] ?? '📝';
    }

    private function showSummary() {
        $errors = [];
        
        // Gather data from formData using lowercase keys
        $postcode = $this->formData[0]['postcode'] ?? '';
        $requestType = $this->formData[1]['requesttype'] ?? '';
        $jobType = $this->formData[2]['jobtype'] ?? '';
        $propertyType = $this->formData[3]['propertytype'] ?? '';
        $projectDescription = $this->formData[4]['projectdescription'] ?? '';
        $contactData = $this->formData[5] ?? [];
        $customer_name = $contactData['fullname'] ?? '';
        $customer_email = $contactData['email'] ?? '';
        $customer_phone = $contactData['phone'] ?? '';
        
        // Compose job_title and job_description for Gibson AI
        $job_title = $jobType;
        $job_description = $projectDescription;
        $location = $postcode;
        $status = 'open';
        
        if ($customer_name && $customer_email && $job_title && $job_description && $location) {
            $leadData = [
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'job_title' => $job_title,
                'job_description' => $job_description,
                'location' => $location,
                'status' => $status
            ];
            
            $result = $this->dataAccess->createLead($leadData);
            if (!$result['success']) {
                $errors[] = 'Failed to save lead: ' . ($result['error'] ?? 'Unknown error');
            }
        } else {
            $errors[] = 'Missing required lead data.';
        }
        
        if (!empty($errors)) {
            echo '<div class="wizard-summary-errors">';
            echo '<div class="wizard-summary-errors__container">';
            echo '<div class="wizard-summary-errors__icon">❌</div>';
            echo '<div class="wizard-summary-errors__content">';
            foreach ($errors as $error) {
                echo '<div class="wizard-summary-errors__message">' . htmlspecialchars($error) . '</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        include __DIR__ . '/../templates/header.php';
        echo '<main role="main"><section class="wizard-quote-summary">';
        
        // Confirmation message
        $contactData = $this->formData[5] ?? [];
        $userEmail = $contactData['email'] ?? '';
        echo '<div class="wizard-confirmation">';
        echo '<div class="wizard-confirmation__container">';
        echo '<div class="wizard-confirmation__icon">🎉</div>';
        echo '<div class="wizard-confirmation__content">';
        echo '<h2 class="wizard-confirmation__title">Thank You!</h2>';
        echo '<p class="wizard-confirmation__message">Your quote request has been received successfully.</p>';
        if ($userEmail) {
            echo '<p class="wizard-confirmation__email">A confirmation email has been sent to <strong>' . htmlspecialchars($userEmail) . '</strong></p>';
        }
        echo '<p class="wizard-confirmation__followup">We\'ll connect you with qualified painters in your area within 24 hours!</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Enhanced summary card
        echo '<div class="wizard-summary-card">';
        echo '<div class="wizard-summary-card__header">';
        echo '<div class="wizard-summary-card__icon">📋</div>';
        echo '<h2 class="wizard-summary-card__title">Quote Request Summary</h2>';
        echo '</div>';
        echo '<div class="wizard-summary-card__content">';
        echo '<div class="wizard-summary-table">';
        
        foreach ($this->steps as $i => $stepConfig) {
            $data = isset($this->formData[$i]) ? $this->formData[$i] : [];
            if ($stepConfig['type'] === 'contact') {
                echo '<div class="wizard-summary-section">';
                echo '<div class="wizard-summary-section__header">';
                echo '<div class="wizard-summary-section__icon">👤</div>';
                echo '<h3 class="wizard-summary-section__title">' . htmlspecialchars($stepConfig['label']) . '</h3>';
                echo '</div>';
                echo '<div class="wizard-summary-row">';
                echo '<span class="wizard-summary-label">Name:</span>';
                echo '<span class="wizard-summary-value">' . htmlspecialchars($data['fullname'] ?? '-') . '</span>';
                echo '</div>';
                echo '<div class="wizard-summary-row">';
                echo '<span class="wizard-summary-label">Email:</span>';
                echo '<span class="wizard-summary-value wizard-summary-value--email">' . htmlspecialchars($data['email'] ?? '-') . '</span>';
                echo '</div>';
                echo '<div class="wizard-summary-row">';
                echo '<span class="wizard-summary-label">Phone:</span>';
                echo '<span class="wizard-summary-value wizard-summary-value--phone">' . htmlspecialchars($data['phone'] ?? '-') . '</span>';
                echo '</div>';
                echo '</div>';
            } else {
                $value = trim(implode(", ", array_filter($data)));
                if ($value === '') {
                    $value = '-';
                }
                
                // Get appropriate icon for each step
                $stepIcon = $this->getStepIcon($stepConfig['id']);
                
                echo '<div class="wizard-summary-row">';
                echo '<span class="wizard-summary-label">';
                echo '<span class="wizard-summary-label__icon">' . $stepIcon . '</span>';
                echo htmlspecialchars($stepConfig['label']) . ':';
                echo '</span>';
                echo '<span class="wizard-summary-value">' . htmlspecialchars($value) . '</span>';
                echo '</div>';
            }
        }
        echo '</div>'; // Close wizard-summary-table
        echo '</div>'; // Close wizard-summary-card__content
        echo '</div>'; // Close wizard-summary-card
        
        // JSON-LD LocalBusiness schema (example)
        $contactData = $this->formData[5] ?? [];
        echo '<script type="application/ld+json">';
        echo json_encode([
            "@context" => "https://schema.org",
            "@type" => "LocalBusiness",
                            "name" => "Painter-near-me.co.uk",
                "url" => "https://painter-near-me.co.uk",
            "address" => [
                "@type" => "PostalAddress",
                "postalCode" => $this->formData[0]['postcode'] ?? '',
            ],
            "telephone" => $contactData['phone'] ?? '',
            "email" => $contactData['email'] ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '</script>';
        
        // Load external CSS for wizard summary styles (better performance)
        echo '<link rel="stylesheet" href="serve-asset.php?file=css/wizard-summary.css">';
        
        echo '</section></main>';
        include __DIR__ . '/../templates/footer.php';
        
        // Email notification would be handled by Gibson AI or external service
        // No direct email sending here
    }
} 