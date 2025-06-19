<?php
require_once 'core/GibsonAuth.php';
require_once 'core/GibsonDataAccess.php';

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();

// Require login
$auth->requireLogin();

$painterId = $auth->getCurrentPainterId();
$errors = [];
$success = false;

// Fetch current data
$painter = $dataAccess->getPainterById($painterId);
if (!$painter) {
    die('Painter not found.');
}

$companyName = $painter['company_name'];
$contactName = $painter['contact_name'];
$email = $painter['email'];
$phone = $painter['phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newCompany = trim($_POST['company_name']);
    $newContact = trim($_POST['contact_name']);
    $newEmail = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $newPhone = trim($_POST['phone']);
    
    if (empty($newCompany)) $errors[] = 'Company name required';
    if (empty($newContact)) $errors[] = 'Contact name required';
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
    if (empty($newPhone)) $errors[] = 'Phone required';
    
    // Password change
    $changePassword = false;
    if (!empty($_POST['password']) || !empty($_POST['password2'])) {
        if ($_POST['password'] !== $_POST['password2']) {
            $errors[] = 'Passwords do not match';
        } elseif (strlen($_POST['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } else {
            $changePassword = true;
            $newPasswordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
    }
    
    // Email uniqueness check
    if ($newEmail !== $email) {
        $existingPainter = $dataAccess->getPainterByEmail($newEmail);
        if ($existingPainter && $existingPainter['id'] != $painterId) {
            $errors[] = 'Email already in use';
        }
    }
    
    if (empty($errors)) {
        $updateData = [
            'company_name' => $newCompany,
            'contact_name' => $newContact,
            'email' => $newEmail,
            'phone' => $newPhone
        ];
        
        if ($changePassword) {
            $updateData['password_hash'] = $newPasswordHash;
        }
        
        $result = $dataAccess->updatePainter($painterId, $updateData);
        if ($result['success']) {
            $success = true;
            
            // Send profile update confirmation email
            try {
                require_once __DIR__ . '/core/Mailer.php';
                $mailer = new Core\Mailer();
                
                $subject = 'Profile Updated Successfully - Painter Near Me';
                $message = "<h2>Profile Updated Successfully</h2>";
                $message .= "<p>Dear " . htmlspecialchars($newCompany) . ",</p>";
                $message .= "<p>Your profile has been successfully updated on Painter Near Me.</p>";
                $message .= "<h3>Updated Information:</h3>";
                $message .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $message .= "<tr><td style='font-weight:bold;'>Company Name:</td><td>" . htmlspecialchars($newCompany) . "</td></tr>";
                $message .= "<tr><td style='font-weight:bold;'>Contact Name:</td><td>" . htmlspecialchars($newContact) . "</td></tr>";
                $message .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($newEmail) . "</td></tr>";
                $message .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($newPhone) . "</td></tr>";
                if ($changePassword) {
                    $message .= "<tr><td style='font-weight:bold;'>Password:</td><td>Updated</td></tr>";
                }
                $message .= "<tr><td style='font-weight:bold;'>Updated On:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
                $message .= "</table>";
                
                if ($newEmail !== $email || $changePassword) {
                    $message .= "<h3>Security Notice</h3>";
                    if ($newEmail !== $email) {
                        $message .= "<p><strong>Your email address has been changed.</strong> If you did not make this change, please contact us immediately.</p>";
                    }
                    if ($changePassword) {
                        $message .= "<p><strong>Your password has been updated.</strong> If you did not make this change, please contact us immediately.</p>";
                    }
                }
                
                $message .= "<h3>What's Next?</h3>";
                $message .= "<p>Your updated profile information is now active. You can:</p>";
                $message .= "<ul>";
                $message .= "<li>Continue viewing and bidding on available leads</li>";
                $message .= "<li>Update your profile further if needed</li>";
                $message .= "<li>Manage your existing bids</li>";
                $message .= "</ul>";
                $message .= "<p><a href='https://painter-near-me.co.uk/dashboard.php' style='background:#00b050;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>View Dashboard</a></p>";
                $message .= "<p>Thank you for keeping your information up to date!</p>";
                $message .= "<p>Best regards,<br>The Painter Near Me Team</p>";
                
                $mailer->sendMail($newEmail, $subject, $message, strip_tags($message), $newContact);
                
                // If email changed, send notification to admin
                if ($newEmail !== $email) {
                    if (!defined('ADMIN_EMAIL')) {
                        define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
                    }
                    
                    $adminSubject = 'Painter Email Address Changed - ' . $newCompany;
                    $adminMessage = "<h2>Painter Email Address Changed</h2>";
                    $adminMessage .= "<p>A painter has changed their email address:</p>";
                    $adminMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                    $adminMessage .= "<tr><td style='font-weight:bold;'>Company:</td><td>" . htmlspecialchars($newCompany) . "</td></tr>";
                    $adminMessage .= "<tr><td style='font-weight:bold;'>Old Email:</td><td>" . htmlspecialchars($email) . "</td></tr>";
                    $adminMessage .= "<tr><td style='font-weight:bold;'>New Email:</td><td>" . htmlspecialchars($newEmail) . "</td></tr>";
                    $adminMessage .= "<tr><td style='font-weight:bold;'>Changed On:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
                    $adminMessage .= "</table>";
                    $adminMessage .= "<p>Please review this change in the admin panel if necessary.</p>";
                    
                    $mailer->sendMail(ADMIN_EMAIL, $adminSubject, $adminMessage, strip_tags($adminMessage));
                }
                
            } catch (Exception $e) {
                error_log("Failed to send profile update notification: " . $e->getMessage());
            }
            
            // Update session data
            $_SESSION['company_name'] = $newCompany;
            $_SESSION['user_email'] = $newEmail;
            
            // Update local variables
            $companyName = $newCompany;
            $contactName = $newContact;
            $email = $newEmail;
            $phone = $newPhone;
        } else {
            $errors[] = 'Update failed: ' . ($result['error'] ?? 'Unknown error');
        }
    }
}

include 'templates/header.php';
?>
<head>
          <title>Edit Profile | Painter Near Me</title>
    <meta name="description" content="Edit your painter company profile on Painter Near Me." />
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
  <div style="display:flex;gap:2.5rem;align-items:flex-start;max-width:1100px;margin:0 auto;">
    <div>
      <?php include 'templates/sidebar-painter.php'; ?>
    </div>
    <div style="flex:1;min-width:0;">
      <section class="profile-hero hero">
        <h1 class="hero__title">Edit Profile</h1>
        <p class="hero__subtitle">Update your company and contact details.</p>
      </section>
      <section class="profile-main">
        <!-- Tab Navigation -->
        <div class="profile-tabs" style="max-width:600px;margin:2.5rem auto 0;">
          <button class="profile-tab active" onclick="showTab('profile-info')" data-tab="profile-info">
            <i class="bi bi-person-fill"></i> Profile Info
          </button>
          <button class="profile-tab" onclick="showTab('payment-methods')" data-tab="payment-methods">
            <i class="bi bi-credit-card"></i> Payment Methods
          </button>
        </div>
        
        <!-- Profile Info Tab -->
        <div id="profile-info" class="profile-main__container tab-content active" style="max-width:600px;margin:0 auto 2.5rem;background:#fff;border-radius:1.5rem;box-shadow:0 8px 32px rgba(0,176,80,0.10),0 1.5px 8px rgba(0,0,0,0.04);padding:2.5rem 2rem;">
          <?php if ($success): ?>
            <div class="profile-main__success" style="background:#e6f7ea;color:#00b050;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;text-align:center;">Profile updated successfully.</div>
          <?php endif; ?>
          <?php if (!empty($errors)): ?>
            <div class="profile-main__errors" style="background:#fee2e2;color:#dc2626;padding:1rem;border-radius:0.5rem;margin-bottom:1.5rem;">
              <ul style="margin:0;padding-left:1.5rem;">
                <?php foreach ($errors as $error): ?>
                  <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <form class="profile-form" method="post" action="" novalidate>
            <div class="profile-form__group">
              <label class="profile-form__label" for="company-name">Company Name</label>
              <input class="profile-form__input" type="text" id="company-name" name="company_name" required value="<?php echo htmlspecialchars($companyName); ?>" />
            </div>
            <div class="profile-form__group">
              <label class="profile-form__label" for="contact-name">Contact Name</label>
              <input class="profile-form__input" type="text" id="contact-name" name="contact_name" required value="<?php echo htmlspecialchars($contactName); ?>" />
            </div>
            <div class="profile-form__group">
              <label class="profile-form__label" for="profile-email">Email</label>
              <input class="profile-form__input" type="email" id="profile-email" name="email" required value="<?php echo htmlspecialchars($email); ?>" />
            </div>
            <div class="profile-form__group">
              <label class="profile-form__label" for="profile-phone">Phone</label>
              <input class="profile-form__input" type="tel" id="profile-phone" name="phone" required value="<?php echo htmlspecialchars($phone); ?>" />
            </div>
            <div class="profile-form__group">
              <label class="profile-form__label" for="profile-password">New Password <span style="font-weight:400;color:#888;">(leave blank to keep current)</span></label>
              <input class="profile-form__input" type="password" id="profile-password" name="password" minlength="8" />
            </div>
            <div class="profile-form__group">
              <label class="profile-form__label" for="profile-password2">Confirm New Password</label>
              <input class="profile-form__input" type="password" id="profile-password2" name="password2" minlength="8" />
            </div>
            <button class="profile-form__button step__button" type="submit">Update Profile</button>
          </form>
        </div>
        
        <!-- Payment Methods Tab -->
        <div id="payment-methods" class="profile-main__container tab-content" style="max-width:600px;margin:0 auto 2.5rem;background:#fff;border-radius:1.5rem;box-shadow:0 8px 32px rgba(0,176,80,0.10),0 1.5px 8px rgba(0,0,0,0.04);padding:2.5rem 2rem;display:none;">
          <h3 style="margin-bottom:1.5rem;color:#00b050;">Payment Methods</h3>
          <p style="color:#666;margin-bottom:2rem;">Manage your payment methods to purchase lead access.</p>
          
          <div id="payment-methods-list">
            <!-- Payment methods will be loaded here -->
          </div>
          
          <button id="add-payment-method-btn" class="btn btn-primary" onclick="showAddPaymentForm()">
            <i class="bi bi-plus-circle"></i> Add Payment Method
          </button>
          
          <div id="add-payment-form" style="display:none;margin-top:2rem;padding:1.5rem;border:1px solid #e5e7eb;border-radius:0.5rem;background:#f9fafb;">
            <h4 style="margin-bottom:1rem;">Add New Payment Method</h4>
            <form id="payment-method-form">
              <div id="card-element" style="padding:1rem;border:1px solid #d1d5db;border-radius:0.375rem;background:white;margin-bottom:1rem;">
                <!-- Stripe Elements will create form elements here -->
              </div>
              <div id="card-errors" role="alert" style="color:#dc2626;margin-bottom:1rem;"></div>
              <div style="display:flex;gap:1rem;">
                <button type="submit" id="submit-payment-method" class="btn btn-primary">Save Payment Method</button>
                <button type="button" onclick="hideAddPaymentForm()" class="btn btn-secondary">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</main>

<script src="https://js.stripe.com/v3/"></script>
<script src="payment-management.js"></script>
<script>
// Tab functionality
function showTab(tabName) {
  // Hide all tab contents
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.style.display = 'none';
    tab.classList.remove('active');
  });
  
  // Remove active class from all tabs
  document.querySelectorAll('.profile-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected tab
  document.getElementById(tabName).style.display = 'block';
  document.getElementById(tabName).classList.add('active');
  
  // Add active class to clicked tab
  document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
  
  // Load payment methods if payment tab is selected
  if (tabName === 'payment-methods') {
    loadPaymentMethods();
  }
}

// Payment methods functionality
let stripe, elements, cardElement;

document.addEventListener('DOMContentLoaded', function() {
  // Initialize Stripe
  initializeStripe();
  
  // Check for tab parameter in URL
  const urlParams = new URLSearchParams(window.location.search);
  const tab = urlParams.get('tab');
  if (tab === 'payment') {
    showTab('payment-methods');
  }
});

async function initializeStripe() {
  try {
    const response = await fetch('/api/payment-api.php/config');
    const data = await response.json();
    
    if (data.success && data.config.stripe_publishable_key) {
      stripe = Stripe(data.config.stripe_publishable_key);
      elements = stripe.elements();
      
      cardElement = elements.create('card', {
        style: {
          base: {
            fontSize: '16px',
            color: '#424770',
            '::placeholder': {
              color: '#aab7c4',
            },
          },
        },
      });
      
      cardElement.mount('#card-element');
      
      cardElement.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
          displayError.textContent = event.error.message;
        } else {
          displayError.textContent = '';
        }
      });
    }
  } catch (error) {
    console.error('Error initializing Stripe:', error);
  }
}

function loadPaymentMethods() {
  fetch('/api/payment-api.php/payment-methods')
    .then(response => response.json())
    .then(data => {
      const container = document.getElementById('payment-methods-list');
      
      if (data.success && data.methods.length > 0) {
        let html = '<div class="payment-methods-grid">';
        
        data.methods.forEach(method => {
          html += `
            <div class="payment-method-card ${method.is_default ? 'default' : ''}">
              <div class="payment-method-info">
                <i class="bi bi-credit-card"></i>
                <div>
                  <div class="card-info">**** **** **** ${method.card_last4}</div>
                  <div class="card-brand">${method.card_brand.toUpperCase()}</div>
                  ${method.is_default ? '<span class="default-badge">Default</span>' : ''}
                </div>
              </div>
              <div class="payment-method-actions">
                ${!method.is_default ? `<button onclick="setDefaultPaymentMethod('${method.stripe_payment_method_id}')" class="btn btn-sm">Set Default</button>` : ''}
                <button onclick="removePaymentMethod('${method.stripe_payment_method_id}')" class="btn btn-sm btn-danger">Remove</button>
              </div>
            </div>
          `;
        });
        
        html += '</div>';
        container.innerHTML = html;
      } else {
        container.innerHTML = '<p style="color:#666;text-align:center;padding:2rem;">No payment methods added yet.</p>';
      }
    })
    .catch(error => {
      console.error('Error loading payment methods:', error);
    });
}

function showAddPaymentForm() {
  document.getElementById('add-payment-form').style.display = 'block';
  document.getElementById('add-payment-method-btn').style.display = 'none';
}

function hideAddPaymentForm() {
  document.getElementById('add-payment-form').style.display = 'none';
  document.getElementById('add-payment-method-btn').style.display = 'block';
  
  // Clear form
  if (cardElement) {
    cardElement.clear();
  }
  document.getElementById('card-errors').textContent = '';
}

// Handle payment method form submission
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('payment-method-form');
  if (form) {
    form.addEventListener('submit', async function(event) {
      event.preventDefault();
      
      if (!stripe || !cardElement) {
        alert('Payment system not initialized. Please refresh the page.');
        return;
      }
      
      const submitButton = document.getElementById('submit-payment-method');
      submitButton.disabled = true;
      submitButton.textContent = 'Saving...';
      
      const {token, error} = await stripe.createToken(cardElement);
      
      if (error) {
        document.getElementById('card-errors').textContent = error.message;
        submitButton.disabled = false;
        submitButton.textContent = 'Save Payment Method';
      } else {
        // Create payment method
        const {paymentMethod, error: pmError} = await stripe.createPaymentMethod({
          type: 'card',
          card: cardElement,
        });
        
        if (pmError) {
          document.getElementById('card-errors').textContent = pmError.message;
          submitButton.disabled = false;
          submitButton.textContent = 'Save Payment Method';
        } else {
          // Save payment method
          try {
            const response = await fetch('/api/payment-api.php/setup-payment-method', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                payment_method_id: paymentMethod.id,
                is_default: document.querySelectorAll('.payment-method-card').length === 0
              })
            });
            
            const result = await response.json();
            
            if (result.success) {
              hideAddPaymentForm();
              loadPaymentMethods();
              alert('Payment method added successfully!');
            } else {
              document.getElementById('card-errors').textContent = result.error;
            }
          } catch (fetchError) {
            document.getElementById('card-errors').textContent = 'Failed to save payment method.';
          }
          
          submitButton.disabled = false;
          submitButton.textContent = 'Save Payment Method';
        }
      }
    });
  }
});

function removePaymentMethod(paymentMethodId) {
  if (confirm('Are you sure you want to remove this payment method?')) {
    fetch('/api/payment-api.php/payment-method', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payment_method_id: paymentMethodId })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        loadPaymentMethods();
        alert('Payment method removed successfully!');
      } else {
        alert('Error removing payment method: ' + data.error);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error removing payment method.');
    });
  }
}

function setDefaultPaymentMethod(paymentMethodId) {
  fetch('/api/payment-api.php/setup-payment-method', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      payment_method_id: paymentMethodId,
      is_default: true
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      loadPaymentMethods();
      alert('Default payment method updated!');
    } else {
      alert('Error updating default payment method: ' + data.error);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error updating default payment method.');
  });
}
</script>

<style>
.profile-tabs {
  display: flex;
  border-bottom: 1px solid #e5e7eb;
  border-radius: 1.5rem 1.5rem 0 0;
  background: white;
  overflow: hidden;
}

.profile-tab {
  flex: 1;
  padding: 1rem 1.5rem;
  border: none;
  background: #f9fafb;
  color: #6b7280;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
}

.profile-tab:hover {
  background: #e5e7eb;
  color: #374151;
}

.profile-tab.active {
  background: white;
  color: #00b050;
  border-bottom: 2px solid #00b050;
}

.payment-methods-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 2rem;
}

.payment-method-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  background: #f9fafb;
}

.payment-method-card.default {
  border-color: #00b050;
  background: #e6f7ea;
}

.payment-method-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.payment-method-info i {
  font-size: 1.5rem;
  color: #6b7280;
}

.card-info {
  font-weight: 600;
  color: #374151;
}

.card-brand {
  font-size: 0.875rem;
  color: #6b7280;
}

.default-badge {
  background: #00b050;
  color: white;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  margin-left: 0.5rem;
}

.payment-method-actions {
  display: flex;
  gap: 0.5rem;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 600;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #00b050;
  color: white;
}

.btn-primary:hover {
  background: #009140;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover {
  background: #4b5563;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover {
  background: #dc2626;
}
</style>

<?php include 'templates/footer.php'; ?>
<style>
.profile-form__group { margin-bottom: 1.3rem; }
.profile-form__label { font-weight: 700; color: #00b050; margin-bottom: 0.3rem; display: block; }
.profile-form__input { width: 100%; padding: 0.9rem 1.1rem; border: 1.5px solid #e5e7eb; border-radius: 1.2rem; font-size: 1.1rem; transition: border-color 0.2s; }
.profile-form__input:focus { border-color: #00b050; outline: none; box-shadow: 0 0 0 2px #b6f5c2; }
.profile-form__button { margin-top: 1rem; }
</style> 