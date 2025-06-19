<?php
// Note: Password security handled by Gibson AI authentication service

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
    header("Location: dashboard.php");
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
    
    // Check file size (dynamic based on server limits)
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

// Function to clean up uploaded files
function cleanupUploadedFiles($files) {
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token. Please refresh the page and try again.";
    }

    $company_name = trim($_POST['company_name']);
    $contact_name = trim($_POST['contact_name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($company_name)) {
        $errors[] = "Company name is required.";
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
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Document validation - BOTH documents are required
    $uploadedFiles = [];
    
    // Insurance document is REQUIRED
    if (!isset($_FILES['insurance_document']) || $_FILES['insurance_document']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Insurance certificate is required for registration.";
    } else {
        $fileErrors = validateUploadedFile($_FILES['insurance_document'], 'Insurance Document');
        $errors = array_merge($errors, $fileErrors);
        
        if (empty($fileErrors)) {
            $uploadedFiles['insurance'] = $_FILES['insurance_document'];
        }
    }
    
    // ID document is REQUIRED
    if (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "ID document is required for registration.";
    } else {
        $fileErrors = validateUploadedFile($_FILES['id_document'], 'ID Document');
        $errors = array_merge($errors, $fileErrors);
        
        if (empty($fileErrors)) {
            $uploadedFiles['id'] = $_FILES['id_document'];
        }
    }

    // Check if email already exists
    if (empty($errors)) {
        $existingPainter = $dataAccess->getPainterByEmail($email);
        if ($existingPainter) {
            $errors[] = "Email already registered";
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        $painterData = [
            'company_name' => $company_name,
            'contact_name' => $contact_name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password, // Will be hashed in createPainter
            'status' => 'active' // Set to active by default
        ];

        $result = $dataAccess->createPainter($painterData);
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
                    $safeFileName = $company_name . '_' . $docType . '_' . date('Y-m-d_H-i-s') . '.' . $extension;
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
            
            // Send welcome email to new painter
            try {
                require_once __DIR__ . '/core/Mailer.php';
                $mailer = new Core\Mailer();
                
                // Welcome email to the new painter
                $welcomeSubject = 'Welcome to Painter Near Me - Registration Successful';
                $welcomeMessage = "<h2>Welcome to Painter Near Me, " . htmlspecialchars($company_name) . "!</h2>";
                $welcomeMessage .= "<p>Thank you for registering with Painter Near Me. Your account has been successfully created.</p>";
                $welcomeMessage .= "<h3>Your Registration Details:</h3>";
                $welcomeMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Company Name:</td><td>" . htmlspecialchars($company_name) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Contact Name:</td><td>" . htmlspecialchars($contact_name) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($email) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($phone) . "</td></tr>";
                $welcomeMessage .= "</table>";
                
                if (!empty($attachments)) {
                    $welcomeMessage .= "<p><strong>Documents Submitted:</strong> Your insurance certificate and ID document have been successfully submitted and sent to our admin team for review.</p>";
                }
                
                $welcomeMessage .= "<h3>What's Next?</h3>";
                $welcomeMessage .= "<p>You can now:</p>";
                $welcomeMessage .= "<ul>";
                $welcomeMessage .= "<li>Log in to your dashboard to view available leads</li>";
                $welcomeMessage .= "<li>Submit bids on painting jobs in your area</li>";
                $welcomeMessage .= "<li>Update your profile and service details</li>";
                $welcomeMessage .= "<li>Manage your bids and communications</li>";
                $welcomeMessage .= "</ul>";
                $welcomeMessage .= "<p><a href='https://painter-near-me.co.uk/login.php' style='background:#00b050;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Login to Your Dashboard</a></p>";
                $welcomeMessage .= "<p>If you have any questions, please don't hesitate to contact us.</p>";
                $welcomeMessage .= "<p>Best regards,<br>The Painter Near Me Team</p>";
                
                $mailer->sendMail($email, $welcomeSubject, $welcomeMessage, strip_tags($welcomeMessage), $contact_name);
                
                // Admin notification email with documents
                if (!defined('ADMIN_EMAIL')) {
                    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
                }
                
                $adminSubject = 'New Painter Registration - ' . $company_name;
                $adminMessage = "<h2>New Painter Registration</h2>";
                $adminMessage .= "<p>A new painter has registered on Painter Near Me:</p>";
                $adminMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Company Name:</td><td>" . htmlspecialchars($company_name) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Contact Name:</td><td>" . htmlspecialchars($contact_name) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($email) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($phone) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Registration Date:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
                $adminMessage .= "</table>";
                
                if (!empty($attachments)) {
                    $adminMessage .= "<h3>Documents Attached:</h3>";
                    $adminMessage .= "<p>The following documents have been submitted by the painter:</p>";
                    $adminMessage .= "<ul>";
                    foreach ($attachments as $attachment) {
                        $adminMessage .= "<li>" . htmlspecialchars($attachment['name']) . "</li>";
                    }
                    $adminMessage .= "</ul>";
                    $adminMessage .= "<p><strong>Please review the documents and verify the painter's credentials.</strong></p>";
                } else {
                    $adminMessage .= "<p><em>No documents were submitted during registration.</em></p>";
                }
                
                $adminMessage .= "<p><strong>Please review the new painter in the admin panel.</strong></p>";
                
                // Send email with or without attachments
                if (!empty($attachments)) {
                    $mailer->sendMailWithAttachments(ADMIN_EMAIL, $adminSubject, $adminMessage, $attachments, strip_tags($adminMessage));
                } else {
                    $mailer->sendMail(ADMIN_EMAIL, $adminSubject, $adminMessage, strip_tags($adminMessage));
                }
                
                // Clean up temporary files
                cleanupUploadedFiles($tempFiles);
                
                // Remove temp directory if empty
                if (is_dir($tempDir) && count(scandir($tempDir)) == 2) {
                    rmdir($tempDir);
                }
                
            } catch (Exception $e) {
                error_log("Failed to send registration emails: " . $e->getMessage());
                // Clean up temporary files even if email fails
                cleanupUploadedFiles($tempFiles);
            }
            
            $_SESSION['registration_success'] = true;
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . ($result['error'] ?? 'Unknown error');
        }
    }
}

if (file_exists('templates/header.php')) {
    include 'templates/header.php';
} ?>
<head>
      <title>Register as Painter | Painter Near Me</title>
    <meta name="description" content="Painting companies: register to receive local leads and bid for painting jobs on Painter Near Me." />
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk"
    }
  </script>
</head>
<main role="main">
  <section class="register-hero hero">
    <h1 class="hero__title">Register as Painter</h1>
    <p class="hero__subtitle">Join our network and start receiving painting job leads.</p>
  </section>
  
  <section class="register-main">
    <div class="register-main__container" style="max-width:600px;margin:2.5rem auto;background:#fff;border-radius:1.5rem;box-shadow:0 8px 32px rgba(0,176,80,0.10),0 1.5px 8px rgba(0,0,0,0.04);padding:2.5rem 2rem;">
      <?php
// Note: Password security handled by Gibson AI authentication service
 if ($success): ?>
        <div class="register-main__success" style="background:#e6f7ea;color:#00b050;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;text-align:center;">
          Registration successful! Please <a href="login.php">login here</a>.
        </div>
      <?php
// Note: Password security handled by Gibson AI authentication service
 endif; ?>
      
      <?php
// Note: Password security handled by Gibson AI authentication service
 if (!empty($errors)): ?>
        <div class="register-main__errors" style="background:#fee2e2;color:#dc2626;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
          <ul style="margin:0;padding-left:1.5rem;">
            <?php
// Note: Password security handled by Gibson AI authentication service
 foreach ($errors as $error): ?>
              <li><?php
// Note: Password security handled by Gibson AI authentication service
 echo htmlspecialchars($error); ?></li>
            <?php
// Note: Password security handled by Gibson AI authentication service
 endforeach; ?>
          </ul>
        </div>
      <?php
// Note: Password security handled by Gibson AI authentication service
 endif; ?>
      
      <form class="register-form" method="post" action="" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo Core\UploadConfig::getMaxUploadSize(); ?>">
        
        <div class="register-form__group">
          <label class="register-form__label" for="company_name">Company Name</label>
          <input class="register-form__input" type="text" id="company_name" name="company_name" required value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>" />
        </div>
        
        <div class="register-form__group">
          <label class="register-form__label" for="contact_name">Contact Name</label>
          <input class="register-form__input" type="text" id="contact_name" name="contact_name" required value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo isset($_POST['contact_name']) ? htmlspecialchars($_POST['contact_name']) : ''; ?>" />
        </div>
        
        <div class="register-form__group">
          <label class="register-form__label" for="email">Email</label>
          <input class="register-form__input" type="email" id="email" name="email" required value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
        </div>
        
        <div class="register-form__group">
          <label class="register-form__label" for="phone">Phone</label>
          <input class="register-form__input" type="tel" id="phone" name="phone" required value="<?php
// Note: Password security handled by Gibson AI authentication service
 echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" />
        </div>
        
        <div class="register-form__group">
          <label class="register-form__label" for="password">Password</label>
          <input class="register-form__input" type="password" id="password" name="password" required minlength="8" />
        </div>
        
        <div class="register-form__group">
          <label class="register-form__label" for="confirm_password">Confirm Password</label>
          <input class="register-form__input" type="password" id="confirm_password" name="confirm_password" required minlength="8" />
        </div>
        
        <!-- Document Upload Section -->
        <div class="register-form__documents">
                     <h3 class="register-form__section-title">Upload Documents (Required)</h3>
           <p class="register-form__section-description">
             <strong>Both documents are required to complete your registration.</strong><br>
             Upload your insurance certificate and ID document. 
             Accepted formats: PDF, Word documents, or images (JPG, PNG, GIF). Maximum file size: <?php
// Note: Password security handled by Gibson AI authentication service
 echo Core\UploadConfig::getMaxUploadSizeMB(); ?>MB each.
           </p>
          
                     <div class="register-form__group">
             <label class="register-form__label" for="insurance_document">
               <i class="bi bi-shield-check"></i> Insurance Certificate <span class="required">*</span>
             </label>
             <input 
               class="register-form__file-input" 
               type="file" 
               id="insurance_document" 
               name="insurance_document" 
               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
               required
             />
             <div class="register-form__file-info">
               <strong>Required:</strong> Upload your public liability insurance certificate
             </div>
           </div>
           
           <div class="register-form__group">
             <label class="register-form__label" for="id_document">
               <i class="bi bi-person-badge"></i> ID Document <span class="required">*</span>
             </label>
             <input 
               class="register-form__file-input" 
               type="file" 
               id="id_document" 
               name="id_document" 
               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
               required
             />
             <div class="register-form__file-info">
               <strong>Required:</strong> Upload a copy of your driver's license, passport, or other photo ID
             </div>
           </div>
        </div>
        
        <button class="register-form__button step__button" type="submit">Register</button>
      </form>
      
      <div class="register-main__info" style="margin-top:2rem;color:#666;font-size:1.05rem;text-align:center;">
        Already have an account? <a href="/login.php" class="register-main__login-link">Login here</a>
      </div>
    </div>
  </section>
</main>
<?php
// Note: Password security handled by Gibson AI authentication service
 
if (file_exists('templates/footer.php')) {
    include 'templates/footer.php';
}
?>
<style>
.register-form__group { margin-bottom: 1.3rem; }
.register-form__label { font-weight: 700; color: #00b050; margin-bottom: 0.3rem; display: block; }
.register-form__input { width: 100%; padding: 0.9rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 1.2rem; font-size: 1.1rem; transition: border-color 0.2s; }
.register-form__input:focus { border-color: #00b050; outline: none; box-shadow: 0 0 0 2px #b6f5c2; }
.register-form__button { margin-top: 1rem; width: 100%; }
.register-main__login-link { color: #00b050; font-weight: 700; text-decoration: underline; }
.register-main__login-link:hover { color: #009140; }

/* Document Upload Styles */
.register-form__documents {
  background: #f8fffe;
  border: 2px solid #e6f7ea;
  border-radius: 1rem;
  padding: 1.5rem;
  margin: 2rem 0 1.5rem 0;
}

.register-form__section-title {
  color: #00b050;
  margin: 0 0 0.5rem 0;
  font-size: 1.2rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.register-form__section-description {
  color: #666;
  font-size: 0.9rem;
  margin-bottom: 1.5rem;
  line-height: 1.4;
}

.register-form__file-input {
  width: 100%;
  padding: 0.8rem 1rem;
  border: 2px dashed #d1fae5;
  border-radius: 0.8rem;
  background: #f0fdf4;
  font-size: 1rem;
  transition: all 0.2s;
  cursor: pointer;
}

.register-form__file-input:hover {
  border-color: #00b050;
  background: #e6f7ea;
}

.register-form__file-input:focus {
  border-color: #00b050;
  outline: none;
  box-shadow: 0 0 0 2px #b6f5c2;
}

.register-form__file-info {
  font-size: 0.85rem;
  color: #666;
  margin-top: 0.4rem;
  font-style: italic;
}

.register-form__label i {
  margin-right: 0.3rem;
}

.required {
  color: #dc2626;
  font-weight: bold;
  margin-left: 0.2rem;
}

/* Bootstrap Icons */
.bi-shield-check::before { content: "🛡️"; }
.bi-person-badge::before { content: "🆔"; }
</style>

<script>
// Get server upload limits for JavaScript validation
const MAX_UPLOAD_SIZE = <?php
// Note: Password security handled by Gibson AI authentication service
 echo Core\UploadConfig::getMaxUploadSize(); ?>;
const MAX_UPLOAD_SIZE_MB = <?php
// Note: Password security handled by Gibson AI authentication service
 echo Core\UploadConfig::getMaxUploadSizeMB(); ?>;

// File upload preview and validation
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('.register-form__file-input');
    const form = document.querySelector('.register-form');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            const label = this.previousElementSibling;
            const info = this.nextElementSibling;
            
            if (file) {
                                 // Validate file size (dynamic)
                 if (file.size > MAX_UPLOAD_SIZE) {
                     alert('File is too large. Maximum size is ' + MAX_UPLOAD_SIZE_MB + 'MB.');
                     this.value = '';
                     return;
                 }
                
                // Update display
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                info.innerHTML = `Selected: <strong>${fileName}</strong> (${fileSize} MB)`;
                info.style.color = '#00b050';
            } else {
                // Reset to original text
                info.innerHTML = info.getAttribute('data-original') || info.innerHTML;
                info.style.color = '#666';
            }
        });
        
        // Store original info text
        const info = input.nextElementSibling;
        info.setAttribute('data-original', info.innerHTML);
    });
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        const insuranceFile = document.getElementById('insurance_document').files[0];
        const idFile = document.getElementById('id_document').files[0];
        
        if (!insuranceFile) {
            alert('Please upload your insurance certificate. This document is required for registration.');
            e.preventDefault();
            return false;
        }
        
        if (!idFile) {
            alert('Please upload your ID document. This document is required for registration.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script> 