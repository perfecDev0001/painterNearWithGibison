<?php
require_once 'core/GibsonDataAccess.php';

$dataAccess = new GibsonDataAccess();
$errors = [];
$success = false;

// Anti-spam measures
$honeypot = isset($_POST['website']) ? $_POST['website'] : '';
$math_answer = isset($_POST['math_answer']) ? trim($_POST['math_answer']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = filter_var(trim($_POST['customer_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $job_description = trim($_POST['job_description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    // Validation
    if (!empty($honeypot)) {
        $errors[] = 'Spam detected.';
    }
    if ($math_answer !== '7') {
        $errors[] = 'Incorrect anti-spam answer.';
    }
    if (empty($customer_name)) {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (empty($job_title)) {
        $errors[] = 'Job title is required.';
    }
    if (empty($job_description)) {
        $errors[] = 'Job description is required.';
    }
    if (empty($location)) {
        $errors[] = 'Location is required.';
    }
    
    if (empty($errors)) {
        $leadData = [
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'job_title' => $job_title,
            'job_description' => $job_description,
            'location' => $location,
            'status' => 'open',
            'assigned_painter_id' => null
        ];
        
        $result = $dataAccess->createLead($leadData);
        if ($result['success']) {
            $success = true;
            
            // Send notification email using proper Mailer class
            // Define ADMIN_EMAIL constant if not already defined
            if (!defined('ADMIN_EMAIL')) {
                define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
            }
            
            require_once __DIR__ . '/core/Mailer.php';
            
            $adminEmail = ADMIN_EMAIL;
            $subject = 'New Painting Job Posted - ' . $job_title;
            $message = "<h2>New Painting Job Posted</h2>";
            $message .= "<p>A new painting job has been posted on Painter Near Me:</p>";
            $message .= "<table cellpadding='6' style='font-size:1.1rem;'>";
            $message .= "<tr><td style='font-weight:bold;'>Customer:</td><td>" . htmlspecialchars($customer_name) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($customer_email) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($customer_phone) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Job Title:</td><td>" . htmlspecialchars($job_title) . "</td></tr>";
            $message .= "<tr><td style='font-weight:bold;'>Location:</td><td>" . htmlspecialchars($location) . "</td></tr>";
            $message .= "</table>";
            $message .= "<h3>Description:</h3>";
            $message .= "<p>" . nl2br(htmlspecialchars($job_description)) . "</p>";
            $message .= "<p><strong>View and manage this lead in the admin panel.</strong></p>";
            
            // Send email notification using Mailer class
            try {
                $mailer = new Core\Mailer();
                $mailer->sendMail($adminEmail, $subject, $message, strip_tags($message));
                
                // Send acknowledgment email to customer
                $customerSubject = 'Job Posted Successfully - ' . $job_title;
                $customerMessage = "<h2>Thank you for posting your painting job!</h2>";
                $customerMessage .= "<p>Dear " . htmlspecialchars($customer_name) . ",</p>";
                $customerMessage .= "<p>Your painting job has been successfully posted on Painter Near Me and is now visible to local painters.</p>";
                $customerMessage .= "<h3>Your Job Details:</h3>";
                $customerMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $customerMessage .= "<tr><td style='font-weight:bold;'>Job Title:</td><td>" . htmlspecialchars($job_title) . "</td></tr>";
                $customerMessage .= "<tr><td style='font-weight:bold;'>Location:</td><td>" . htmlspecialchars($location) . "</td></tr>";
                $customerMessage .= "<tr><td style='font-weight:bold;'>Posted Date:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
                $customerMessage .= "</table>";
                $customerMessage .= "<h3>Description:</h3>";
                $customerMessage .= "<p>" . nl2br(htmlspecialchars($job_description)) . "</p>";
                $customerMessage .= "<h3>What Happens Next?</h3>";
                $customerMessage .= "<p>Local painters will now be able to view your job and submit bids. You can expect to receive bids within the next few days.</p>";
                $customerMessage .= "<p>When painters submit bids, you will receive notifications with their details and quoted prices.</p>";
                $customerMessage .= "<p><strong>Track Your Project:</strong> Access your customer dashboard to manage your project and view all bids: <a href='https://painter-near-me.co.uk/customer-dashboard.php' style='color:#00b050;font-weight:600;'>View Dashboard</a></p>";
                $customerMessage .= "<p>We recommend comparing multiple bids before making your decision.</p>";
                $customerMessage .= "<h3>Need Help?</h3>";
                $customerMessage .= "<p>If you have any questions about your job posting or need assistance, please don't hesitate to contact us:</p>";
                $customerMessage .= "<ul>";
                $customerMessage .= "<li>Email: info@painter-near-me.co.uk</li>";
                $customerMessage .= "<li>Visit: <a href='https://painter-near-me.co.uk/contact.php'>Contact Us</a></li>";
                $customerMessage .= "</ul>";
                $customerMessage .= "<p>Thank you for choosing Painter Near Me!</p>";
                $customerMessage .= "<p>Best regards,<br>The Painter Near Me Team</p>";
                
                $mailer->sendMail($customer_email, $customerSubject, $customerMessage, strip_tags($customerMessage), $customer_name);
                
            } catch (Exception $e) {
                error_log("Failed to send job posting notification: " . $e->getMessage());
            }
        }
    }
}

include 'templates/header.php';
?>
<head>
  <title>Post a Painting Job | Painter Near Me</title>
  <meta name="description" content="Post your painting job and get free quotes from local painters in your area." />
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Painter Near Me",
      "url": "https://painter-near-me.co.uk",
      "description": "Connect with local painters for your painting projects"
    }
  </script>
</head>
<main role="main">
  <section class="postjob-hero hero postjob-main">
    <h1 class="hero__title">Post a Painting Job</h1>
    <p class="hero__subtitle">Describe your painting project and get free quotes from qualified local painters.</p>
    
    <div class="postjob-main__container">
      <?php if ($success): ?>
        <div class="postjob-main__success" role="alert">
          <i class="bi bi-check-circle-fill"></i>
          <strong>Success!</strong> Your painting job has been posted successfully. 
          Local painters will review your project and contact you with quotes soon.
          <div class="postjob-main__success-actions">
            <a href="customer-dashboard.php" class="postjob-main__success-link postjob-main__success-link--primary">📊 View Dashboard</a>
            <a href="index.php" class="postjob-main__success-link">← Back to Homepage</a>
            <a href="post-job.php" class="postjob-main__success-link">Post Another Job</a>
          </div>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($errors)): ?>
        <div class="postjob-main__errors" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <strong>Please fix the following errors:</strong>
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      
      <?php if (!$success): ?>
        <form class="postjob-form" method="post" action="" novalidate>
          <!-- Honeypot for spam protection -->
          <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true" />
          
          <div class="postjob-form__section">
            <h2 class="postjob-form__section-title">Your Contact Information</h2>
            
            <div class="postjob-form__group">
              <label class="postjob-form__label" for="customer-name">Your Name *</label>
              <input class="postjob-form__input" type="text" id="customer-name" name="customer_name" required 
                     value="<?php echo isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''; ?>" 
                     placeholder="Enter your full name" />
            </div>
            
            <div class="postjob-form__group">
              <label class="postjob-form__label" for="customer-email">Email Address *</label>
              <input class="postjob-form__input" type="email" id="customer-email" name="customer_email" required 
                     value="<?php echo isset($_POST['customer_email']) ? htmlspecialchars($_POST['customer_email']) : ''; ?>" 
                     placeholder="your.email@example.com" />
            </div>
            
            <div class="postjob-form__group">
              <label class="postjob-form__label" for="customer-phone">Phone Number</label>
              <input class="postjob-form__input" type="tel" id="customer-phone" name="customer_phone" 
                     value="<?php echo isset($_POST['customer_phone']) ? htmlspecialchars($_POST['customer_phone']) : ''; ?>" 
                     placeholder="Your phone number (optional)" />
            </div>
          </div>
          
          <div class="postjob-form__section">
            <h2 class="postjob-form__section-title">Job Details</h2>
            
            <div class="postjob-form__group">
              <label class="postjob-form__label" for="job-title">Job Title *</label>
              <input class="postjob-form__input" type="text" id="job-title" name="job_title" required 
                     value="<?php echo isset($_POST['job_title']) ? htmlspecialchars($_POST['job_title']) : ''; ?>" 
                     placeholder="e.g., Interior house painting, Exterior wall painting" />
            </div>
            
            <div class="postjob-form__group">
              <label class="postjob-form__label" for="location">Location *</label>
              <input class="postjob-form__input" type="text" id="location" name="location" required 
                     value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" 
                     placeholder="City, postcode, or area" />
            </div>
            
            <div class="postjob-form__group">
              <label class="postjob-form__label" for="job-description">Job Description *</label>
              <textarea class="postjob-form__input postjob-form__textarea" id="job-description" name="job_description" rows="5" required 
                        placeholder="Describe your painting project in detail. Include room sizes, surfaces to be painted, preferred colors, timeline, and any special requirements."><?php echo isset($_POST['job_description']) ? htmlspecialchars($_POST['job_description']) : ''; ?></textarea>
              <small class="postjob-form__hint">The more details you provide, the more accurate quotes you'll receive.</small>
            </div>
          </div>
          
          <div class="postjob-form__section">
            <h2 class="postjob-form__section-title">Security Check</h2>
            
            <div class="postjob-form__group">
              <label class="postjob-form__label" for="math-answer">Anti-spam verification: What is 3 + 4? *</label>
              <input class="postjob-form__input" type="text" id="math-answer" name="math_answer" required 
                     placeholder="Enter the answer" maxlength="2" />
            </div>
          </div>
          
          <div class="postjob-form__actions">
            <button class="postjob-form__button" type="submit">
              <i class="bi bi-plus-circle-fill"></i>
              Post My Painting Job
            </button>
            <p class="postjob-form__disclaimer">
              By posting this job, you agree to be contacted by qualified painters. 
              Your information will not be shared with third parties.
            </p>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include 'templates/footer.php'; ?>

<style>
.postjob-main__container {
  max-width: 700px;
  margin: 2.5rem auto;
  background: #fff;
  border-radius: 1.5rem;
  box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04);
  padding: 2.5rem 2rem;
}

.postjob-main__success {
  background: linear-gradient(135deg, #e6f7ea 0%, #d1f2d9 100%);
  color: #00b050;
  padding: 2rem;
  border-radius: 1rem;
  margin-bottom: 2rem;
  text-align: center;
  border: 2px solid #b6f5c2;
}

.postjob-main__success i {
  font-size: 2rem;
  display: block;
  margin-bottom: 1rem;
}

.postjob-main__success-actions {
  margin-top: 1.5rem;
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.postjob-main__success-link {
  color: #00b050;
  text-decoration: none;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border: 1px solid #00b050;
  border-radius: 0.5rem;
  transition: all 0.2s;
}

.postjob-main__success-link:hover {
  background: #00b050;
  color: white;
}

.postjob-main__success-link--primary {
  background: #00b050;
  color: white;
  font-weight: 700;
}

.postjob-main__success-link--primary:hover {
  background: #00913d;
  color: white;
}

.postjob-main__errors {
  background: #fee2e2;
  color: #dc2626;
  padding: 1.5rem;
  border-radius: 0.7rem;
  margin-bottom: 2rem;
  border: 2px solid #fca5a5;
}

.postjob-main__errors i {
  font-size: 1.2rem;
  margin-right: 0.5rem;
}

.postjob-main__errors ul {
  margin: 0.5rem 0 0 1.5rem;
  padding: 0;
}

.postjob-form__section {
  margin-bottom: 2.5rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid #e5e7eb;
}

.postjob-form__section:last-of-type {
  border-bottom: none;
  margin-bottom: 1.5rem;
}

.postjob-form__section-title {
  color: #00b050;
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: 1.5rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e6f7ea;
}

.postjob-form__group {
  margin-bottom: 1.5rem;
}

.postjob-form__label {
  font-weight: 700;
  color: #00b050;
  margin-bottom: 0.5rem;
  display: block;
  font-size: 1rem;
}

.postjob-form__input {
  width: 100%;
  padding: 1rem 1.2rem;
  border: 2px solid #e5e7eb;
  border-radius: 0.7rem;
  font-size: 1.1rem;
  transition: all 0.2s;
  box-sizing: border-box;
  font-family: inherit;
}

.postjob-form__input:focus {
  border-color: #00b050;
  outline: none;
  box-shadow: 0 0 0 3px rgba(0, 176, 80, 0.1);
}

.postjob-form__textarea {
  resize: vertical;
  min-height: 120px;
}

.postjob-form__hint {
  color: #6b7280;
  font-size: 0.9rem;
  margin-top: 0.5rem;
  display: block;
}

.postjob-form__actions {
  text-align: center;
  margin-top: 2rem;
}

.postjob-form__button {
  background: linear-gradient(135deg, #00b050 0%, #009140 100%);
  color: white;
  font-weight: 700;
  font-size: 1.2rem;
  padding: 1.2rem 2.5rem;
  border: none;
  border-radius: 0.7rem;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 16px rgba(0, 176, 80, 0.3);
}

.postjob-form__button:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 176, 80, 0.4);
}

.postjob-form__button i {
  margin-right: 0.5rem;
}

.postjob-form__disclaimer {
  color: #6b7280;
  font-size: 0.9rem;
  margin-top: 1rem;
  line-height: 1.4;
}

@media (max-width: 768px) {
  .postjob-main__container {
    margin: 1rem;
    padding: 1.5rem 1rem;
  }
  
  .postjob-main__success-actions {
    flex-direction: column;
    align-items: center;
  }
  
  .postjob-form__button {
    width: 100%;
    padding: 1rem;
    font-size: 1.1rem;
  }
}
</style> 