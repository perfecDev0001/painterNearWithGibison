<?php 
session_start();
require_once __DIR__ . '/core/Mailer.php';
use Core\Mailer;

// Define ADMIN_EMAIL constant if not already defined
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
}

// Initialize variables
$contact_errors = [];
$contact_success = false;
$name = '';
$email = '';
$subject = '';
$message = '';
$phone = '';
$inquiry_type = '';
$honeypot = '';
$math_question = '';
$math_answer = '';
$csrf_token = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generate math question for spam protection
if (!isset($_SESSION['math_a']) || !isset($_SESSION['math_b'])) {
    $_SESSION['math_a'] = rand(1, 9);
    $_SESSION['math_b'] = rand(1, 9);
}
$math_question = $_SESSION['math_a'] . ' + ' . $_SESSION['math_b'];
$expected_math = $_SESSION['math_a'] + $_SESSION['math_b'];

// Rate limiting check
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = __DIR__ . '/logs/contact_rate_limit.json';
    
    // Create logs directory if it doesn't exist
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    $rateLimitData = [];
    if (file_exists($rateLimitFile)) {
        $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    
    $currentTime = time();
    $timeWindow = 3600; // 1 hour
    $maxAttempts = 5; // Max 5 submissions per hour
    
    // Clean old entries
    foreach ($rateLimitData as $checkIp => $data) {
        if ($currentTime - $data['first_attempt'] > $timeWindow) {
            unset($rateLimitData[$checkIp]);
        }
    }
    
    // Check current IP
    if (!isset($rateLimitData[$ip])) {
        $rateLimitData[$ip] = [
            'attempts' => 1,
            'first_attempt' => $currentTime,
            'last_attempt' => $currentTime
        ];
    } else {
        $rateLimitData[$ip]['attempts']++;
        $rateLimitData[$ip]['last_attempt'] = $currentTime;
    }
    
    // Save updated data
    file_put_contents($rateLimitFile, json_encode($rateLimitData));
    
    return $rateLimitData[$ip]['attempts'] <= $maxAttempts;
}

// Log contact attempts
function logContactAttempt($data, $success = false) {
    $logFile = __DIR__ . '/logs/contact_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $logEntry = "[$timestamp] $status - IP: $ip - Email: {$data['email']} - Name: {$data['name']} - Type: {$data['inquiry_type']} - User-Agent: $userAgent\n";
    
    // Create logs directory if it doesn't exist
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Send auto-reply to customer
function sendAutoReply($customerEmail, $customerName, $inquiryType) {
    $mailer = new Mailer();
    
    $subject = 'Thank you for contacting Painter Near Me';
    
    $body = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
    $body .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
    $body .= '<div style="text-align: center; margin-bottom: 30px;">';
    $body .= '<h1 style="color: #00b050; margin-bottom: 10px;">Thank You for Contacting Us!</h1>';
    $body .= '</div>';
    
    $body .= '<p>Dear ' . htmlspecialchars($customerName) . ',</p>';
    $body .= '<p>Thank you for reaching out to Painter Near Me. We have received your message regarding <strong>' . htmlspecialchars($inquiryType) . '</strong> and will respond within 24 hours.</p>';
    
    $body .= '<div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    $body .= '<h3 style="color: #00b050; margin-top: 0;">What happens next?</h3>';
    $body .= '<ul style="margin: 0; padding-left: 20px;">';
    $body .= '<li>Our team will review your message within 2-4 hours</li>';
    $body .= '<li>You\'ll receive a personalized response within 24 hours</li>';
    $body .= '<li>If urgent, you can call us at <strong>0800-123-4567</strong></li>';
    $body .= '</ul>';
    $body .= '</div>';
    
    if ($inquiryType === 'quote_inquiry') {
        $body .= '<p><strong>Looking for a quote?</strong> You can also get instant quotes by visiting our <a href="https://painter-near-me.co.uk/quote.php" style="color: #00b050;">quote wizard</a>.</p>';
    }
    
    $body .= '<div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px; text-align: center; color: #666;">';
    $body .= '<p><strong>Painter Near Me</strong><br>';
    $body .= 'Email: info@painter-near-me.co.uk<br>';
    $body .= 'Phone: 0800-123-4567<br>';
    $body .= 'Web: <a href="https://painter-near-me.co.uk" style="color: #00b050;">painter-near-me.co.uk</a></p>';
    $body .= '</div>';
    
    $body .= '</div></body></html>';
    
    $altBody = "Dear $customerName,\n\nThank you for contacting Painter Near Me. We have received your message regarding $inquiryType and will respond within 24 hours.\n\nWhat happens next?\n- Our team will review your message within 2-4 hours\n- You'll receive a personalized response within 24 hours\n- If urgent, you can call us at 0800-123-4567\n\nBest regards,\nPainter Near Me Team\ninfo@painter-near-me.co.uk\n0800-123-4567";
    
    return $mailer->sendMail($customerEmail, $subject, $body, $altBody, $customerName);
}

// Send admin notification
function sendAdminNotification($formData) {
    $mailer = new Mailer();
    $adminTo = ADMIN_EMAIL;
    $subject = 'New Contact Form Message - ' . $formData['inquiry_type'];
    
    $body = '<html><body style="font-family: Arial, sans-serif;">';
    $body .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
    $body .= '<h2 style="color: #00b050; border-bottom: 2px solid #00b050; padding-bottom: 10px;">New Contact Form Submission</h2>';
    
    $body .= '<div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    $body .= '<table cellpadding="8" style="width: 100%; font-size: 14px;">';
    $body .= '<tr><td style="font-weight: bold; width: 120px;">Name:</td><td>' . htmlspecialchars($formData['name']) . '</td></tr>';
    $body .= '<tr><td style="font-weight: bold;">Email:</td><td><a href="mailto:' . htmlspecialchars($formData['email']) . '">' . htmlspecialchars($formData['email']) . '</a></td></tr>';
    
    if (!empty($formData['phone'])) {
        $body .= '<tr><td style="font-weight: bold;">Phone:</td><td><a href="tel:' . htmlspecialchars($formData['phone']) . '">' . htmlspecialchars($formData['phone']) . '</a></td></tr>';
    }
    
    $body .= '<tr><td style="font-weight: bold;">Inquiry Type:</td><td>' . htmlspecialchars($formData['inquiry_type']) . '</td></tr>';
    
    if (!empty($formData['subject'])) {
        $body .= '<tr><td style="font-weight: bold;">Subject:</td><td>' . htmlspecialchars($formData['subject']) . '</td></tr>';
    }
    
    $body .= '<tr><td style="font-weight: bold; vertical-align: top;">Message:</td><td>' . nl2br(htmlspecialchars($formData['message'])) . '</td></tr>';
    $body .= '</table>';
    $body .= '</div>';
    
    $body .= '<div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;">';
    $body .= '<h3 style="margin-top: 0; color: #00b050;">Quick Actions</h3>';
    $body .= '<p style="margin: 5px 0;"><strong>Reply:</strong> <a href="mailto:' . htmlspecialchars($formData['email']) . '?subject=Re: ' . urlencode($formData['subject'] ?: $formData['inquiry_type']) . '" style="color: #00b050;">Send Reply</a></p>';
    
    if ($formData['inquiry_type'] === 'quote_inquiry') {
        $body .= '<p style="margin: 5px 0;"><strong>Action:</strong> Follow up with quote process</p>';
    } elseif ($formData['inquiry_type'] === 'painter_application') {
        $body .= '<p style="margin: 5px 0;"><strong>Action:</strong> Review painter application</p>';
    }
    
    $body .= '</div>';
    
    $body .= '<div style="border-top: 1px solid #e5e7eb; padding-top: 15px; margin-top: 20px; font-size: 12px; color: #666;">';
    $body .= '<p><strong>Submission Details:</strong><br>';
    $body .= 'Time: ' . date('Y-m-d H:i:s') . '<br>';
    $body .= 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '<br>';
    $body .= 'User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . '</p>';
    $body .= '</div>';
    
    $body .= '</div></body></html>';
    
    $altBody = "New Contact Form Submission\n\n";
    $altBody .= "Name: {$formData['name']}\n";
    $altBody .= "Email: {$formData['email']}\n";
    if (!empty($formData['phone'])) $altBody .= "Phone: {$formData['phone']}\n";
    $altBody .= "Inquiry Type: {$formData['inquiry_type']}\n";
    if (!empty($formData['subject'])) $altBody .= "Subject: {$formData['subject']}\n";
    $altBody .= "Message: {$formData['message']}\n\n";
    $altBody .= "Submitted: " . date('Y-m-d H:i:s');
    
    return $mailer->sendMail($adminTo, $subject, $body, $altBody);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $inquiry_type = trim($_POST['inquiry_type'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');
    $math_answer = trim($_POST['math_answer'] ?? '');
    $csrf_token = trim($_POST['csrf_token'] ?? '');
    
    // Check rate limiting
    if (!checkRateLimit()) {
        $contact_errors['rate_limit'] = 'Too many submissions. Please wait before trying again.';
    }
    
    // CSRF protection
    if ($csrf_token !== $_SESSION['csrf_token']) {
        $contact_errors['csrf'] = 'Security token mismatch. Please try again.';
    }
    
    // Honeypot check (spam protection)
    if ($honeypot !== '') {
        // Spam detected, silently discard
        $contact_success = false;
        $name = $email = $subject = $message = $phone = $inquiry_type = '';
        logContactAttempt([
            'name' => $name,
            'email' => $email,
            'inquiry_type' => 'SPAM_DETECTED',
            'honeypot' => $honeypot
        ], false);
    } else {
        // Validate form fields
        if ($name === '') {
            $contact_errors['name'] = 'Please enter your name.';
        } elseif (strlen($name) < 2) {
            $contact_errors['name'] = 'Name must be at least 2 characters long.';
        } elseif (strlen($name) > 100) {
            $contact_errors['name'] = 'Name must be less than 100 characters.';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contact_errors['email'] = 'Please enter a valid email address.';
        } elseif (strlen($email) > 255) {
            $contact_errors['email'] = 'Email address is too long.';
        }
        
        if ($inquiry_type === '') {
            $contact_errors['inquiry_type'] = 'Please select an inquiry type.';
        }
        
        if ($message === '') {
            $contact_errors['message'] = 'Please enter your message.';
        } elseif (strlen($message) < 10) {
            $contact_errors['message'] = 'Message must be at least 10 characters long.';
        } elseif (strlen($message) > 2000) {
            $contact_errors['message'] = 'Message must be less than 2000 characters.';
        }
        
        // Validate phone if provided
        if (!empty($phone)) {
            $phone = preg_replace('/[^\d+\-\s\(\)]/', '', $phone);
            if (strlen($phone) < 10 || strlen($phone) > 20) {
                $contact_errors['phone'] = 'Please enter a valid phone number.';
            }
        }
        
        // Validate subject if provided
        if (!empty($subject) && strlen($subject) > 200) {
            $contact_errors['subject'] = 'Subject must be less than 200 characters.';
        }
        
        // Math question validation
        if ($math_answer === '' || intval($math_answer) !== $expected_math) {
            $contact_errors['math'] = 'Incorrect answer to the math question.';
        }
        
        // Additional spam checks
        $spamKeywords = ['viagra', 'casino', 'lottery', 'winner', 'congratulations', 'million dollars', 'inheritance'];
        $messageText = strtolower($message . ' ' . $subject . ' ' . $name);
        foreach ($spamKeywords as $keyword) {
            if (strpos($messageText, $keyword) !== false) {
                $contact_errors['spam'] = 'Message contains prohibited content.';
                break;
            }
        }
        
        // Check for suspicious patterns
        if (preg_match('/https?:\/\/[^\s]+/', $message) && $inquiry_type !== 'partnership') {
            $contact_errors['links'] = 'Links are not allowed in messages unless for partnership inquiries.';
        }
        
        // If no errors, process the form
        if (!$contact_errors) {
            $formData = [
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
                'phone' => $phone,
                'inquiry_type' => $inquiry_type
            ];
            
            // Send emails
            $adminEmailSent = sendAdminNotification($formData);
            $autoReplySent = sendAutoReply($email, $name, $inquiry_type);
            
            if ($adminEmailSent) {
                $contact_success = true;
                
                // Log successful submission
                logContactAttempt($formData, true);
                
                // Clear form data
                $name = $email = $subject = $message = $phone = $inquiry_type = '';
                
                // Regenerate math question
                unset($_SESSION['math_a'], $_SESSION['math_b']);
                $_SESSION['math_a'] = rand(1, 9);
                $_SESSION['math_b'] = rand(1, 9);
                $math_question = $_SESSION['math_a'] . ' + ' . $_SESSION['math_b'];
                $expected_math = $_SESSION['math_a'] + $_SESSION['math_b'];
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
            } else {
                $contact_errors['email_send'] = 'There was an error sending your message. Please try again or contact us directly.';
                logContactAttempt($formData, false);
            }
        } else {
            // Log failed submission
            logContactAttempt([
                'name' => $name,
                'email' => $email,
                'inquiry_type' => $inquiry_type,
                'errors' => implode(', ', $contact_errors)
            ], false);
            
            // Regenerate math question for next attempt
            $_SESSION['math_a'] = rand(1, 9);
            $_SESSION['math_b'] = rand(1, 9);
            $math_question = $_SESSION['math_a'] . ' + ' . $_SESSION['math_b'];
            $expected_math = $_SESSION['math_a'] + $_SESSION['math_b'];
        }
    }
}
?>
<?php include 'templates/header.php'; ?>
<head>
    <title>Contact | Painter Near Me</title>
    <meta name="description" content="Contact Painter Near Me for support, questions, or partnership opportunities. Get help with quotes, technical support, or business inquiries." />
    <meta name="keywords" content="contact painter near me, customer support, painting quotes help, technical support, business inquiries" />
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk",
      "email": "info@painter-near-me.co.uk",
      "telephone": "0800-123-4567",
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "0800-123-4567",
        "contactType": "customer service",
        "availableLanguage": "English",
        "areaServed": "GB"
      }
    }
    </script>
</head>
<main role="main">
  <section class="contact-hero hero contact-main">
    <h1 class="hero__title">Contact Us</h1>
    <p class="hero__subtitle">We're here to help. Reach out with any questions or feedback.</p>
    
    <div class="contact-main__container" style="max-width:600px;margin:2.5rem auto;background:#fff;border-radius:1.5rem;box-shadow:0 8px 32px rgba(0,176,80,0.10),0 1.5px 8px rgba(0,0,0,0.04);padding:2.5rem 2rem;">
      
      <?php if ($contact_success): ?>
        <div class="contact-form__success" role="status" style="background:#eaffea;border:1.5px solid #00b050;color:#008040;padding:1.2rem 1.5rem;margin-bottom:2rem;border-radius:1rem;font-size:1.15rem;text-align:center;">
          <span style="font-size:1.5rem;vertical-align:middle;">✅</span> Thank you! Your message has been sent. We'll be in touch soon.
          <div style="margin-top:0.5rem;font-size:1rem;opacity:0.9;">
            You should receive a confirmation email shortly. If urgent, call us at <strong>0800-123-4567</strong>.
          </div>
        </div>
      <?php endif; ?>
      
      <?php if ($contact_errors): ?>
        <div class="contact-form__errors" role="alert" style="background:#ffeaea;color:#b00020;border:1.5px solid #ffb3b3;border-radius:1rem;padding:1rem;margin-bottom:1rem;font-size:1.05rem;">
          <div style="font-weight:bold;margin-bottom:0.5rem;">Please correct the following errors:</div>
          <?php foreach ($contact_errors as $field => $error): ?>
            <div class="contact-form__error" style="margin-bottom:0.3rem;">• <?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      
      <form class="contact-form" method="post" action="#contact" autocomplete="off" novalidate id="contact">
        <!-- CSRF Protection -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <!-- Honeypot for spam protection -->
        <div class="contact-form__honeypot" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
          <label for="website">Website (leave blank)</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" />
        </div>
        
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-name">Name <span style="color:#b00020;">*</span></label>
          <input class="contact-form__input<?php if(isset($contact_errors['name'])) echo ' input-error'; ?>" 
                 type="text" 
                 id="contact-name" 
                 name="name" 
                 value="<?php echo htmlspecialchars($name); ?>" 
                 required 
                 maxlength="100"
                 placeholder="Your full name" />
        </div>
        
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-email">Email <span style="color:#b00020;">*</span></label>
          <input class="contact-form__input<?php if(isset($contact_errors['email'])) echo ' input-error'; ?>" 
                 type="email" 
                 id="contact-email" 
                 name="email" 
                 value="<?php echo htmlspecialchars($email); ?>" 
                 required 
                 maxlength="255"
                 placeholder="your.email@example.com" />
        </div>
        
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-phone">Phone (Optional)</label>
          <input class="contact-form__input<?php if(isset($contact_errors['phone'])) echo ' input-error'; ?>" 
                 type="tel" 
                 id="contact-phone" 
                 name="phone" 
                 value="<?php echo htmlspecialchars($phone); ?>" 
                 maxlength="20"
                 placeholder="Your phone number" />
        </div>
        
        <div class="contact-form__group">
          <label class="contact-form__label" for="inquiry-type">Inquiry Type <span style="color:#b00020;">*</span></label>
          <select class="contact-form__input<?php if(isset($contact_errors['inquiry_type'])) echo ' input-error'; ?>" 
                  id="inquiry-type" 
                  name="inquiry_type" 
                  required>
            <option value="">Select inquiry type</option>
            <option value="quote_inquiry" <?php echo $inquiry_type === 'quote_inquiry' ? 'selected' : ''; ?>>Quote Inquiry</option>
            <option value="technical_support" <?php echo $inquiry_type === 'technical_support' ? 'selected' : ''; ?>>Technical Support</option>
            <option value="account_help" <?php echo $inquiry_type === 'account_help' ? 'selected' : ''; ?>>Account Help</option>
            <option value="painter_application" <?php echo $inquiry_type === 'painter_application' ? 'selected' : ''; ?>>Painter Application</option>
            <option value="partnership" <?php echo $inquiry_type === 'partnership' ? 'selected' : ''; ?>>Partnership Opportunity</option>
            <option value="complaint" <?php echo $inquiry_type === 'complaint' ? 'selected' : ''; ?>>Complaint</option>
            <option value="feedback" <?php echo $inquiry_type === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
            <option value="media_press" <?php echo $inquiry_type === 'media_press' ? 'selected' : ''; ?>>Media/Press</option>
            <option value="other" <?php echo $inquiry_type === 'other' ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>
        
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-subject">Subject (Optional)</label>
          <input class="contact-form__input<?php if(isset($contact_errors['subject'])) echo ' input-error'; ?>" 
                 type="text" 
                 id="contact-subject" 
                 name="subject" 
                 value="<?php echo htmlspecialchars($subject); ?>" 
                 maxlength="200"
                 placeholder="Brief subject line" />
        </div>
        
        <div class="contact-form__group">
          <label class="contact-form__label" for="contact-message">Message <span style="color:#b00020;">*</span></label>
          <textarea class="contact-form__input<?php if(isset($contact_errors['message'])) echo ' input-error'; ?>" 
                    id="contact-message" 
                    name="message" 
                    rows="5" 
                    required 
                    maxlength="2000"
                    placeholder="Please provide details about your inquiry..."><?php echo htmlspecialchars($message); ?></textarea>
          <div style="font-size:0.9rem;color:#666;margin-top:0.3rem;">
            <span id="char-count">0</span>/2000 characters
          </div>
        </div>
        
        <div class="contact-form__group">
          <label class="contact-form__label" for="math-answer">Security Question: What is <?php echo htmlspecialchars($math_question); ?>? <span style="color:#b00020;">*</span></label>
          <input class="contact-form__input<?php if(isset($contact_errors['math'])) echo ' input-error'; ?>" 
                 type="text" 
                 id="math-answer" 
                 name="math_answer" 
                 value="" 
                 inputmode="numeric" 
                 pattern="[0-9]*" 
                 required 
                 autocomplete="off"
                 placeholder="Enter the answer" />
        </div>
        
        <button class="contact-form__button step__button" type="submit" id="submit-btn">
          <span class="btn-text">Send Message</span>
          <span class="btn-loading" style="display:none;">Sending...</span>
        </button>
        
        <div style="margin-top:1rem;font-size:0.9rem;color:#666;text-align:center;">
          <p>We typically respond within 24 hours. For urgent matters, call <strong>0800-123-4567</strong>.</p>
        </div>
      </form>
    </div>
  </section>
  
  <!-- Contact Information Section -->
  <section class="contact-info" style="background:#f8fafc;padding:3rem 1rem;">
    <div style="max-width:800px;margin:0 auto;text-align:center;">
      <h2 style="color:#222;margin-bottom:2rem;">Other Ways to Reach Us</h2>
      
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:2rem;margin-bottom:2rem;">
        <div style="background:white;padding:2rem;border-radius:1rem;box-shadow:0 4px 16px rgba(0,176,80,0.1);">
          <div style="font-size:2rem;color:#00b050;margin-bottom:1rem;">📞</div>
          <h3 style="color:#222;margin-bottom:0.5rem;">Phone Support</h3>
          <p style="color:#666;margin-bottom:1rem;">Monday - Friday, 9 AM - 6 PM</p>
          <a href="tel:0800-123-4567" style="color:#00b050;font-weight:600;text-decoration:none;">0800-123-4567</a>
        </div>
        
        <div style="background:white;padding:2rem;border-radius:1rem;box-shadow:0 4px 16px rgba(0,176,80,0.1);">
          <div style="font-size:2rem;color:#00b050;margin-bottom:1rem;">✉️</div>
          <h3 style="color:#222;margin-bottom:0.5rem;">Email Support</h3>
          <p style="color:#666;margin-bottom:1rem;">We respond within 24 hours</p>
          <a href="mailto:info@painter-near-me.co.uk" style="color:#00b050;font-weight:600;text-decoration:none;">info@painter-near-me.co.uk</a>
        </div>
        
        <div style="background:white;padding:2rem;border-radius:1rem;box-shadow:0 4px 16px rgba(0,176,80,0.1);">
          <div style="font-size:2rem;color:#00b050;margin-bottom:1rem;">💬</div>
          <h3 style="color:#222;margin-bottom:0.5rem;">Live Chat</h3>
          <p style="color:#666;margin-bottom:1rem;">Available during business hours</p>
          <button onclick="openLiveChat()" style="background:#00b050;color:white;border:none;padding:0.5rem 1rem;border-radius:0.5rem;cursor:pointer;">Start Chat</button>
        </div>
      </div>
      
      <div style="background:white;padding:2rem;border-radius:1rem;box-shadow:0 4px 16px rgba(0,176,80,0.1);text-align:left;">
        <h3 style="color:#222;margin-bottom:1rem;">Frequently Asked Questions</h3>
        <div style="display:grid;gap:1rem;">
          <details style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:1rem;">
            <summary style="cursor:pointer;font-weight:600;color:#00b050;">How quickly do you respond to contact forms?</summary>
            <p style="margin-top:0.5rem;color:#666;">We aim to respond to all contact form submissions within 24 hours during business days. Urgent matters are prioritized.</p>
          </details>
          
          <details style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:1rem;">
            <summary style="cursor:pointer;font-weight:600;color:#00b050;">What information should I include in my message?</summary>
            <p style="margin-top:0.5rem;color:#666;">Please include as much relevant detail as possible, including your location, project type, timeline, and any specific requirements or questions.</p>
          </details>
          
          <details style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:1rem;">
            <summary style="cursor:pointer;font-weight:600;color:#00b050;">Do you offer phone consultations?</summary>
            <p style="margin-top:0.5rem;color:#666;">Yes, we offer phone consultations for complex projects or detailed discussions. Contact us to schedule a convenient time.</p>
          </details>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include 'templates/footer.php'; ?>

<script>
// Character counter for message field
document.addEventListener('DOMContentLoaded', function() {
    const messageField = document.getElementById('contact-message');
    const charCount = document.getElementById('char-count');
    
    if (messageField && charCount) {
        function updateCharCount() {
            const count = messageField.value.length;
            charCount.textContent = count;
            
            if (count > 1800) {
                charCount.style.color = '#b00020';
            } else if (count > 1500) {
                charCount.style.color = '#ff8c00';
            } else {
                charCount.style.color = '#666';
            }
        }
        
        messageField.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial count
    }
    
    // Form submission handling
    const form = document.getElementById('contact');
    const submitBtn = document.getElementById('submit-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            // Show loading state
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
            submitBtn.disabled = true;
            
            // Re-enable after 5 seconds as fallback
            setTimeout(function() {
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                submitBtn.disabled = false;
            }, 5000);
        });
    }
    
    // Auto-focus first error field
    const firstError = document.querySelector('.input-error');
    if (firstError) {
        firstError.focus();
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Enhanced form validation
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearFieldError);
    });
    
    function validateField(e) {
        const field = e.target;
        const value = field.value.trim();
        
        // Remove existing validation messages
        const existingMsg = field.parentNode.querySelector('.field-validation-msg');
        if (existingMsg) {
            existingMsg.remove();
        }
        
        let isValid = true;
        let message = '';
        
        // Field-specific validation
        switch(field.name) {
            case 'name':
                if (!value) {
                    isValid = false;
                    message = 'Name is required';
                } else if (value.length < 2) {
                    isValid = false;
                    message = 'Name must be at least 2 characters';
                }
                break;
                
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!value) {
                    isValid = false;
                    message = 'Email is required';
                } else if (!emailRegex.test(value)) {
                    isValid = false;
                    message = 'Please enter a valid email address';
                }
                break;
                
            case 'phone':
                if (value && value.length < 10) {
                    isValid = false;
                    message = 'Please enter a valid phone number';
                }
                break;
                
            case 'message':
                if (!value) {
                    isValid = false;
                    message = 'Message is required';
                } else if (value.length < 10) {
                    isValid = false;
                    message = 'Message must be at least 10 characters';
                }
                break;
        }
        
        // Update field appearance
        if (!isValid) {
            field.classList.add('input-error');
            const msgDiv = document.createElement('div');
            msgDiv.className = 'field-validation-msg';
            msgDiv.style.cssText = 'color:#b00020;font-size:0.9rem;margin-top:0.3rem;';
            msgDiv.textContent = message;
            field.parentNode.appendChild(msgDiv);
        } else {
            field.classList.remove('input-error');
        }
    }
    
    function clearFieldError(e) {
        const field = e.target;
        field.classList.remove('input-error');
        const existingMsg = field.parentNode.querySelector('.field-validation-msg');
        if (existingMsg) {
            existingMsg.remove();
        }
    }
});

// Live chat functionality (placeholder)
function openLiveChat() {
    // In a real implementation, this would open a live chat widget
    alert('Live chat is currently unavailable. Please use the contact form or call us at 0800-123-4567.');
}

// Auto-hide success message after 10 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = document.querySelector('.contact-form__success');
    if (successMsg) {
        setTimeout(function() {
            successMsg.style.transition = 'opacity 0.5s ease';
            successMsg.style.opacity = '0';
            setTimeout(function() {
                successMsg.style.display = 'none';
            }, 500);
        }, 10000);
    }
});

// Prevent multiple form submissions
let formSubmitted = false;
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
        });
    }
});
</script>

<style>
/* Preserve existing styles */
.contact-form__group { margin-bottom: 1.3rem; }
.contact-form__label { font-weight: 700; color: #00b050; margin-bottom: 0.3rem; display: block; }
.contact-form__input { width: 100%; padding: 0.9rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 1.2rem; font-size: 1.1rem; transition: border-color 0.2s; }
.contact-form__input:focus { border-color: #00b050; outline: none; box-shadow: 0 0 0 2px #b6f5c2; }
.contact-form__button { margin-top: 1rem; }
.input-error { border-color: #b00020 !important; background: #ffeaea; }

/* Additional enhancements */
.contact-form__input:hover {
    border-color: #00b050;
}

.contact-form__input::placeholder {
    color: #9ca3af;
    opacity: 1;
}

.contact-form__button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.field-validation-msg {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive improvements */
@media (max-width: 768px) {
    .contact-main__container {
        margin: 1.5rem auto !important;
        padding: 1.5rem !important;
    }
    
    .contact-info > div {
        padding: 2rem 1rem !important;
    }
    
    .contact-info > div > div {
        grid-template-columns: 1fr !important;
    }
}

/* Accessibility improvements */
.contact-form__input:focus-visible {
    outline: 2px solid #00b050;
    outline-offset: 2px;
}

.contact-form__button:focus-visible {
    outline: 2px solid #ffffff;
    outline-offset: 2px;
}

/* Loading animation */
.btn-loading::after {
    content: '';
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 0.5rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>