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
                    return;
                }
            } else {
                $this->errors = $step->errors;
            }
        }
        $this->renderStep();
    }

    private function renderStep() {
        include __DIR__ . '/../templates/header.php';
        include __DIR__ . '/../templates/progress.php';
        if (!empty($this->errors)) {
            echo '<div class="step__errors" role="alert">';
            foreach ($this->errors as $error) {
                echo '<div class="step__error">' . htmlspecialchars($error) . '</div>';
            }
            echo '</div>';
        }
        $stepFile = __DIR__ . '/../steps/Step' . ($this->currentStep + 1) . '_' . $this->steps[$this->currentStep]['id'] . '.php';
        if (file_exists($stepFile)) {
            // Buffer the step form
            ob_start();
            include $stepFile;
            $stepForm = ob_get_clean();
            // Place Back and Next buttons inside the form, in a .step__actions panel
            $actions = '<div class="step__actions">';
            if ($this->currentStep > 0) {
                $actions .= '<button type="button" class="step__button step__button--back" onclick="window.location.search=\'step=' . ($this->currentStep - 1) . '\'" aria-label="Go to previous step">&#8592; Back</button>';
            }
            // Extract the submit button
            if (preg_match('/(<button[^>]*type="submit"[^>]*>.*?<\/button>)/is', $stepForm, $matches)) {
                $submitBtn = $matches[1];
                $formWithoutSubmit = preg_replace('/<button[^>]*type="submit"[^>]*>.*?<\/button>/is', '', $stepForm, 1);
            } else {
                $submitBtn = '';
                $formWithoutSubmit = $stepForm;
            }
            $actions .= $submitBtn;
            $actions .= '</div>';
            // Insert actions panel before </form>
            $stepFormWithActions = preg_replace('/<\/form>/', $actions . '</form>', $formWithoutSubmit, 1);
            echo $stepFormWithActions;
        }
        include __DIR__ . '/../templates/footer.php';
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
            echo '<div class="quote-summary__errors" style="background:#fee2e2;color:#dc2626;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;text-align:center;">'.implode('<br>', array_map('htmlspecialchars', $errors)).'</div>';
        }
        
        include __DIR__ . '/../templates/header.php';
        echo '<main role="main"><section class="quote-summary">';
        
        // Confirmation message
        $contactData = $this->formData[5] ?? [];
        $userEmail = $contactData['email'] ?? '';
        echo '<div class="quote-confirmation" style="background:#eaffea;border:1.5px solid #00b050;color:#008040;padding:1.2rem 1.5rem;margin-bottom:2rem;border-radius:1rem;font-size:1.15rem;max-width:600px;margin-left:auto;margin-right:auto;text-align:center;">';
        echo '<span style="font-size:1.5rem;vertical-align:middle;">✅</span> Thank you! Your quote request has been received.';
        if ($userEmail) {
            echo '<br>A confirmation email has been sent to <strong>' . htmlspecialchars($userEmail) . '</strong>.';
        }
        echo '<br>We\'ll be in touch soon!';
        echo '</div>';
        
        // Improved summary card
        echo '<div class="quote-summary__card" style="background:linear-gradient(135deg,#fff 80%,#eaffea 100%);max-width:600px;margin:2.5rem auto 0 auto;padding:2.8rem 2.2rem 2.2rem 2.2rem;border-radius:2rem;box-shadow:0 12px 40px rgba(0,176,80,0.13),0 2px 12px rgba(0,0,0,0.06);font-size:1.18rem;border:1.5px solid #e5e7eb;">';
        echo '<h2 style="color:#00b050;font-size:2rem;font-weight:900;margin-top:0;margin-bottom:2.2rem;text-align:center;letter-spacing:-1px;">Summary</h2>';
        echo '<div class="quote-summary__table" style="display:grid;grid-template-columns:1fr 2fr;gap:1.1rem 1.5rem;align-items:center;">';
        
        foreach ($this->steps as $i => $stepConfig) {
            $data = isset($this->formData[$i]) ? $this->formData[$i] : [];
            if ($stepConfig['type'] === 'contact') {
                echo '<div style="font-weight:700;color:#00b050;grid-column:1/3;margin-top:1.2rem;">' . htmlspecialchars($stepConfig['label']) . ':</div>';
                echo '<div style="color:#222;">Name:</div><div style="font-weight:600;">' . htmlspecialchars($data['fullname'] ?? '-') . '</div>';
                echo '<div style="color:#222;">Email:</div><div style="font-weight:700;color:#008040;word-break:break-all;">' . htmlspecialchars($data['email'] ?? '-') . '</div>';
                echo '<div style="color:#222;">Phone:</div><div style="font-weight:700;color:#008040;">' . htmlspecialchars($data['phone'] ?? '-') . '</div>';
            } else {
                $value = trim(implode(", ", array_filter($data)));
                if ($value === '') {
                    $value = '-';
                }
                echo '<div style="font-weight:700;color:#00b050;">' . htmlspecialchars($stepConfig['label']) . ':</div><div style="color:#222;">' . htmlspecialchars($value) . '</div>';
            }
        }
        echo '</div>';
        echo '</div>';
        
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
        
        echo '</section></main>';
        include __DIR__ . '/../templates/footer.php';
        
        // Email notification would be handled by Gibson AI or external service
        // No direct email sending here
    }
} 