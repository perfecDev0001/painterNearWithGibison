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
        
        // Add beautiful CSS styles for the wizard summary
        echo '<style>
        /* Wizard Error Styles */
        .wizard-errors {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 2px solid #fca5a5;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.1);
        }
        
        .wizard-errors__container {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .wizard-errors__icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .wizard-errors__content {
            flex: 1;
        }
        
        .wizard-errors__message {
            color: #dc2626;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .wizard-errors__message:last-child {
            margin-bottom: 0;
        }
        
        .wizard-error-not-found {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            margin: 2rem 0;
            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.1);
        }
        
        .wizard-error-not-found__icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .wizard-error-not-found__message {
            color: #92400e;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        /* Summary Error Styles */
        .wizard-summary-errors {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 2px solid #f87171;
            border-radius: 1.5rem;
            padding: 2rem;
            margin: 2rem auto;
            max-width: 600px;
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.15);
        }
        
        .wizard-summary-errors__container {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
        }
        
        .wizard-summary-errors__icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .wizard-summary-errors__content {
            flex: 1;
        }
        
        .wizard-summary-errors__message {
            color: #dc2626;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }
        
        .wizard-summary-errors__message:last-child {
            margin-bottom: 0;
        }
        
        /* Quote Summary Section */
        .wizard-quote-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            padding: 3rem 0;
        }
        
        /* Confirmation Styles */
        .wizard-confirmation {
            max-width: 700px;
            margin: 0 auto 3rem auto;
            padding: 0 1rem;
        }
        
        .wizard-confirmation__container {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 3px solid #10b981;
            border-radius: 2rem;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(16, 185, 129, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .wizard-confirmation__container::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        .wizard-confirmation__icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            display: block;
            animation: bounce 2s ease-in-out infinite;
        }
        
        .wizard-confirmation__title {
            color: #065f46;
            font-size: 2.5rem;
            font-weight: 900;
            margin: 0 0 1rem 0;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }
        
        .wizard-confirmation__message {
            color: #047857;
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0 0 1rem 0;
            position: relative;
            z-index: 1;
        }
        
        .wizard-confirmation__email {
            color: #065f46;
            font-size: 1.1rem;
            margin: 0 0 1rem 0;
            position: relative;
            z-index: 1;
        }
        
        .wizard-confirmation__followup {
            color: #047857;
            font-size: 1.2rem;
            font-weight: 500;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        /* Summary Card Styles */
        .wizard-summary-card {
            max-width: 800px;
            margin: 0 auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 2rem;
            box-shadow: 0 25px 80px rgba(0, 176, 80, 0.15), 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 2px solid #e5e7eb;
            overflow: hidden;
            position: relative;
        }
        
        .wizard-summary-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #00b050 0%, #10b981 50%, #00b050 100%);
        }
        
        .wizard-summary-card__header {
            background: linear-gradient(135deg, #00b050 0%, #059669 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
        }
        
        .wizard-summary-card__header::after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(135deg, #00b050 0%, #059669 100%);
            clip-path: polygon(0 0, 100% 0, 100% 0, 0 100%);
        }
        
        .wizard-summary-card__icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.9;
        }
        
        .wizard-summary-card__title {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .wizard-summary-card__content {
            padding: 3rem 2rem;
        }
        
        /* Summary Table Styles */
        .wizard-summary-table {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .wizard-summary-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            align-items: center;
            padding: 1.25rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .wizard-summary-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 176, 80, 0.1);
            border-color: #00b050;
        }
        
        .wizard-summary-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            color: #00b050;
            font-size: 1.1rem;
        }
        
        .wizard-summary-label__icon {
            font-size: 1.3rem;
            opacity: 0.8;
        }
        
        .wizard-summary-value {
            color: #374151;
            font-weight: 600;
            font-size: 1.1rem;
            word-break: break-word;
        }
        
        .wizard-summary-value--email {
            color: #0ea5e9;
            font-family: monospace;
        }
        
        .wizard-summary-value--phone {
            color: #8b5cf6;
            font-family: monospace;
        }
        
        /* Contact Section Styles */
        .wizard-summary-section {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #10b981;
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .wizard-summary-section__header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #a7f3d0;
        }
        
        .wizard-summary-section__icon {
            font-size: 2rem;
        }
        
        .wizard-summary-section__title {
            color: #065f46;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
        }
        
        .wizard-summary-section .wizard-summary-row {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid #a7f3d0;
        }
        
        .wizard-summary-section .wizard-summary-row:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: #10b981;
        }
        
        /* Animations */
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.1; }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .wizard-quote-summary {
                padding: 2rem 0;
            }
            
            .wizard-confirmation {
                padding: 0 1rem;
            }
            
            .wizard-confirmation__container {
                padding: 2rem 1.5rem;
            }
            
            .wizard-confirmation__title {
                font-size: 2rem;
            }
            
            .wizard-confirmation__message {
                font-size: 1.1rem;
            }
            
            .wizard-summary-card {
                margin: 0 1rem;
            }
            
            .wizard-summary-card__header {
                padding: 2rem 1.5rem;
            }
            
            .wizard-summary-card__title {
                font-size: 1.8rem;
            }
            
            .wizard-summary-card__content {
                padding: 2rem 1.5rem;
            }
            
            .wizard-summary-row {
                grid-template-columns: 1fr;
                gap: 0.75rem;
                padding: 1rem;
            }
            
            .wizard-summary-label {
                font-size: 1rem;
            }
            
            .wizard-summary-value {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .wizard-confirmation__icon {
                font-size: 3rem;
            }
            
            .wizard-confirmation__title {
                font-size: 1.75rem;
            }
            
            .wizard-summary-card__icon {
                font-size: 2.5rem;
            }
            
            .wizard-summary-card__title {
                font-size: 1.5rem;
            }
        }
        </style>';
        
        echo '</section></main>';
        include __DIR__ . '/../templates/footer.php';
        
        // Email notification would be handled by Gibson AI or external service
        // No direct email sending here
    }
} 