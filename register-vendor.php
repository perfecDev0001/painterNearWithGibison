<?php
// Vendor Registration Page
// Allows vendors to register to sell painting supplies

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

// Error handling
if (getenv('GIBSON_DEVELOPMENT_MODE') === 'true' || $_ENV['GIBSON_DEVELOPMENT_MODE'] === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(0);
}

require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';
require_once 'core/UploadConfig.php';

// Initialize upload configuration
Core\UploadConfig::initialize();

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();
$errors = [];
$success = false;

// Check if already logged in
if ($auth->isLoggedIn()) {
    header("Location: vendor-dashboard.php");
    exit();
}

// CSRF token generation
$csrf_token = $auth->generateCSRFToken();

// Function to validate uploaded files
function validateUploadedFile($file, $fieldName) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $maxSizeMB = Core\UploadConfig::getMaxUploadSizeMB();
                $errors[] = "$fieldName: File is too large. Maximum size is {$maxSizeMB}MB.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "$fieldName: File was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = "$fieldName: No file was uploaded.";
                break;
            default:
                $errors[] = "$fieldName: Upload error occurred.";
        }
        return $errors;
    }
    
    // Check file size
    $maxSize = Core\UploadConfig::getMaxUploadSize();
    $maxSizeMB = Core\UploadConfig::getMaxUploadSizeMB();
    
    if ($file['size'] > $maxSize) {
        $errors[] = "$fieldName: File is too large. Maximum size is {$maxSizeMB}MB.";
    }
    
    // Check file type
    $allowedTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "$fieldName: Invalid file type. Please upload PDF, Word document, or image files only.";
    }
    
    return $errors;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token. Please refresh the page and try again.";
    }

    $business_name = trim($_POST['business_name']);
    $contact_name = trim($_POST['contact_name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postcode = trim($_POST['postcode']);
    $business_type = trim($_POST['business_type']);
    $tax_number = trim($_POST['tax_number']);
    $website = trim($_POST['website']);
    $description = trim($_POST['description']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($business_name)) {
        $errors[] = "Business name is required.";
    }
    if (empty($contact_name)) {
        $errors[] = "Contact name is required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    if (empty($city)) {
        $errors[] = "City is required.";
    }
    if (empty($postcode)) {
        $errors[] = "Postcode is required.";
    }
    if (empty($business_type)) {
        $errors[] = "Business type is required.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Document validation - Business license is required
    $uploadedFiles = [];
    
    if (!isset($_FILES['business_license']) || $_FILES['business_license']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Business license document is required for vendor registration.";
    } else {
        $fileErrors = validateUploadedFile($_FILES['business_license'], 'Business License');
        $errors = array_merge($errors, $fileErrors);
        
        if (empty($fileErrors)) {
            $uploadedFiles['business_license'] = $_FILES['business_license'];
        }
    }

    // Check if email already exists
    if (empty($errors)) {
        $existingUser = $dataAccess->getUserByEmail($email);
        if ($existingUser) {
            $errors[] = "Email already registered. Please use a different email or try logging in.";
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        $vendorData = [
            'business_name' => $business_name,
            'contact_name' => $contact_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'postcode' => $postcode,
            'business_type' => $business_type,
            'tax_number' => $tax_number,
            'website' => $website,
            'description' => $description,
            'password' => $password,
            'user_type' => 'vendor',
            'role_id' => 5, // Vendor role
            'status' => 'pending' // Vendors need approval
        ];

        $result = $auth->register($vendorData);
        if ($result['success']) {
            $success = true;
            
            // Process document uploads and send emails
            $attachments = [];
            $tempFiles = [];
            
            if (!empty($uploadedFiles)) {
                // Create temporary directory if it doesn't exist
                $tempDir = __DIR__ . '/temp_uploads';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                
                foreach ($uploadedFiles as $docType => $file) {
                    $originalName = $file['name'];
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $safeFileName = $business_name . '_' . $docType . '_' . date('Y-m-d_H-i-s') . '.' . $extension;
                    $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $safeFileName);
                    $tempPath = $tempDir . '/' . $safeFileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $tempPath)) {
                        $attachments[] = [
                            'path' => $tempPath,
                            'name' => $originalName
                        ];
                        $tempFiles[] = $tempPath;
                    }
                }
            }
            
            // Send welcome email
            try {
                require_once __DIR__ . '/core/Mailer.php';
                $mailer = new Core\Mailer();
                
                $welcomeSubject = 'Welcome to Painter Near Me - Vendor Application Received';
                $welcomeMessage = "<h2>Welcome to Painter Near Me, " . htmlspecialchars($business_name) . "!</h2>";
                $welcomeMessage .= "<p>Thank you for applying to become a vendor on Painter Near Me. Your application has been received and is under review.</p>";
                $welcomeMessage .= "<h3>Your Application Details:</h3>";
                $welcomeMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Business Name:</td><td>" . htmlspecialchars($business_name) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Contact Name:</td><td>" . htmlspecialchars($contact_name) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($email) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($phone) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Business Type:</td><td>" . htmlspecialchars($business_type) . "</td></tr>";
                $welcomeMessage .= "</table>";
                
                $welcomeMessage .= "<h3>What's Next?</h3>";
                $welcomeMessage .= "<p><strong>Application Review:</strong> Our team will review your application and documents within 2-3 business days.</p>";
                $welcomeMessage .= "<p><strong>Account Approval:</strong> Once approved, you'll receive an email with login instructions and access to your vendor dashboard.</p>";
                $welcomeMessage .= "<p><strong>Getting Started:</strong> After approval, you can:</p>";
                $welcomeMessage .= "<ul>";
                $welcomeMessage .= "<li>Add your products to our marketplace</li>";
                $welcomeMessage .= "<li>Set up your store profile</li>";
                $welcomeMessage .= "<li>Configure payment and shipping options</li>";
                $welcomeMessage .= "<li>Start selling to our network of painters and customers</li>";
                $welcomeMessage .= "</ul>";
                
                $welcomeMessage .= "<p>If you have any questions during the review process, please don't hesitate to contact us.</p>";
                $welcomeMessage .= "<p>Best regards,<br>The Painter Near Me Team</p>";
                
                $mailer->sendMail($email, $welcomeSubject, $welcomeMessage, strip_tags($welcomeMessage), $contact_name);
                
                // Admin notification email with documents
                if (!defined('ADMIN_EMAIL')) {
                    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
                }
                
                $adminSubject = 'New Vendor Application - ' . $business_name;
                $adminMessage = "<h2>New Vendor Application</h2>";
                $adminMessage .= "<p>A new vendor has applied to join Painter Near Me:</p>";
                $adminMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Business Name:</td><td>" . htmlspecialchars($business_name) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Contact Name:</td><td>" . htmlspecialchars($contact_name) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($email) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($phone) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Business Type:</td><td>" . htmlspecialchars($business_type) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Tax Number:</td><td>" . htmlspecialchars($tax_number) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Website:</td><td>" . htmlspecialchars($website) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Address:</td><td>" . htmlspecialchars($address . ', ' . $city . ' ' . $postcode) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Application Date:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
                $adminMessage .= "</table>";
                
                if (!empty($description)) {
                    $adminMessage .= "<h3>Business Description:</h3>";
                    $adminMessage .= "<p>" . nl2br(htmlspecialchars($description)) . "</p>";
                }
                
                if (!empty($attachments)) {
                    $adminMessage .= "<h3>Documents Attached:</h3>";
                    $adminMessage .= "<ul>";
                    foreach ($attachments as $attachment) {
                        $adminMessage .= "<li>" . htmlspecialchars($attachment['name']) . "</li>";
                    }
                    $adminMessage .= "</ul>";
                }
                
                $adminMessage .= "<p><strong>Please review the vendor application and approve/reject accordingly.</strong></p>";
                
                // Send email with attachments
                if (!empty($attachments)) {
                    $mailer->sendMailWithAttachments(ADMIN_EMAIL, $adminSubject, $adminMessage, $attachments, strip_tags($adminMessage));
                } else {
                    $mailer->sendMail(ADMIN_EMAIL, $adminSubject, $adminMessage, strip_tags($adminMessage));
                }
                
                // Clean up temporary files
                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
                
                // Remove temp directory if empty
                if (is_dir($tempDir) && count(scandir($tempDir)) == 2) {
                    rmdir($tempDir);
                }
                
            } catch (Exception $e) {
                error_log("Failed to send vendor registration emails: " . $e->getMessage());
            }
            
            $_SESSION['vendor_application_success'] = true;
            header("Location: vendor-application-success.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . ($result['error'] ?? 'Unknown error');
        }
    }
}

if (file_exists('templates/header.php')) {
    include 'templates/header.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Vendor Registration | Painter Near Me</title>
    <meta name="description" content="Register as a vendor to sell painting supplies and products on Painter Near Me marketplace." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .vendor-registration {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .vendor-registration__header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .vendor-registration__title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .vendor-registration__subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .vendor-registration__form {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04);
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .form-section__title {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group--half {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #00b050;
            box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
        }
        
        .form-input.error, .form-select.error, .form-textarea.error {
            border-color: #e74c3c;
        }
        
        .form-file {
            padding: 0.5rem;
        }
        
        .form-button {
            width: 100%;
            background: linear-gradient(135deg, #00b050, #00d460);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-button:hover {
            background: linear-gradient(135deg, #009640, #00c050);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,176,80,0.3);
        }
        
        .error-messages {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .error-messages ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #c33;
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }
        
        .login-link a {
            color: #00b050;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #7f8c8d;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .back-link:hover {
            color: #00b050;
        }
        
        .back-link i {
            margin-right: 0.5rem;
        }
        
        .required-note {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #2d5a2d;
        }
        
        @media (max-width: 768px) {
            .vendor-registration__form {
                padding: 2rem;
            }
            
            .form-group--half {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main role="main">
    <div class="vendor-registration">
        <a href="register-hub.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Registration Options
        </a>
        
        <div class="vendor-registration__header">
            <h1 class="vendor-registration__title">Vendor Registration</h1>
            <p class="vendor-registration__subtitle">
                Join our marketplace and sell your painting products to thousands of customers
            </p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="vendor-registration__form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="required-note">
                <strong>Note:</strong> All vendor applications are subject to review and approval. 
                You will receive an email notification once your application has been processed.
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Business Information -->
            <div class="form-section">
                <h2 class="form-section__title">Business Information</h2>
                
                <div class="form-group">
                    <label for="business_name" class="form-label">Business Name *</label>
                    <input type="text" id="business_name" name="business_name" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group form-group--half">
                    <div>
                        <label for="contact_name" class="form-label">Contact Name *</label>
                        <input type="text" id="contact_name" name="contact_name" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['contact_name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="business_type" class="form-label">Business Type *</label>
                        <select id="business_type" name="business_type" class="form-select" required>
                            <option value="">Select Business Type</option>
                            <option value="manufacturer" <?php echo ($_POST['business_type'] ?? '') === 'manufacturer' ? 'selected' : ''; ?>>Manufacturer</option>
                            <option value="distributor" <?php echo ($_POST['business_type'] ?? '') === 'distributor' ? 'selected' : ''; ?>>Distributor</option>
                            <option value="retailer" <?php echo ($_POST['business_type'] ?? '') === 'retailer' ? 'selected' : ''; ?>>Retailer</option>
                            <option value="wholesaler" <?php echo ($_POST['business_type'] ?? '') === 'wholesaler' ? 'selected' : ''; ?>>Wholesaler</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group form-group--half">
                    <div>
                        <label for="tax_number" class="form-label">Tax/VAT Number</label>
                        <input type="text" id="tax_number" name="tax_number" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['tax_number'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="website" class="form-label">Website</label>
                        <input type="url" id="website" name="website" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Business Description</label>
                    <textarea id="description" name="description" class="form-textarea" 
                              placeholder="Tell us about your business, products, and what makes you unique..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="form-section">
                <h2 class="form-section__title">Contact Information</h2>
                
                <div class="form-group form-group--half">
                    <div>
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address" class="form-label">Business Address *</label>
                    <input type="text" id="address" name="address" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group form-group--half">
                    <div>
                        <label for="city" class="form-label">City *</label>
                        <input type="text" id="city" name="city" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="postcode" class="form-label">Postcode *</label>
                        <input type="text" id="postcode" name="postcode" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['postcode'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="form-section">
                <h2 class="form-section__title">Required Documents</h2>
                
                <div class="form-group">
                    <label for="business_license" class="form-label">Business License/Registration *</label>
                    <input type="file" id="business_license" name="business_license" class="form-input form-file" 
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" required>
                    <small style="color: #7f8c8d;">Upload your business license, registration certificate, or incorporation documents (PDF, Word, or Image files only)</small>
                </div>
            </div>
            
            <!-- Account Security -->
            <div class="form-section">
                <h2 class="form-section__title">Account Security</h2>
                
                <div class="form-group form-group--half">
                    <div>
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               minlength="8" required>
                    </div>
                    <div>
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               minlength="8" required>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="form-button">
                Submit Vendor Application
            </button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
    </div>
</main>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.vendor-registration__form');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    // Password confirmation validation
    function validatePasswords() {
        if (passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
    
    passwordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        validatePasswords();
        
        if (!form.checkValidity()) {
            e.preventDefault();
            
            // Add error styling to invalid fields
            const invalidFields = form.querySelectorAll(':invalid');
            invalidFields.forEach(field => {
                field.classList.add('error');
            });
        }
    });
    
    // Remove error styling on input
    const inputs = form.querySelectorAll('.form-input, .form-select, .form-textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
});
</script>
</body>
</html>