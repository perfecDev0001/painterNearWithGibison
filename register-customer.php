<?php
// Customer Registration Page
// Allows customers to register for painting services

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

$auth = new GibsonAuth();
$dataAccess = new GibsonDataAccess();
$errors = [];
$success = false;

// Check if already logged in
if ($auth->isLoggedIn()) {
    header("Location: customer-dashboard.php");
    exit();
}

// CSRF token generation
$csrf_token = $auth->generateCSRFToken();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token. Please refresh the page and try again.";
    }

    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postcode = trim($_POST['postcode']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
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
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
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
        $customerData = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'customer_name' => $first_name . ' ' . $last_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'postcode' => $postcode,
            'password' => $password,
            'user_type' => 'customer',
            'role_id' => 4 // Customer role
        ];

        $result = $auth->register($customerData);
        if ($result['success']) {
            $success = true;
            
            // Send welcome email
            try {
                require_once __DIR__ . '/core/Mailer.php';
                $mailer = new Core\Mailer();
                
                $welcomeSubject = 'Welcome to Painter Near Me - Registration Successful';
                $welcomeMessage = "<h2>Welcome to Painter Near Me, " . htmlspecialchars($first_name) . "!</h2>";
                $welcomeMessage .= "<p>Thank you for joining Painter Near Me. Your customer account has been successfully created.</p>";
                $welcomeMessage .= "<h3>Your Registration Details:</h3>";
                $welcomeMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Name:</td><td>" . htmlspecialchars($first_name . ' ' . $last_name) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($email) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($phone) . "</td></tr>";
                $welcomeMessage .= "<tr><td style='font-weight:bold;'>Address:</td><td>" . htmlspecialchars($address . ', ' . $city . ' ' . $postcode) . "</td></tr>";
                $welcomeMessage .= "</table>";
                
                $welcomeMessage .= "<h3>What's Next?</h3>";
                $welcomeMessage .= "<p>You can now:</p>";
                $welcomeMessage .= "<ul>";
                $welcomeMessage .= "<li>Post your first painting project</li>";
                $welcomeMessage .= "<li>Get quotes from verified painters</li>";
                $welcomeMessage .= "<li>Manage your projects and communications</li>";
                $welcomeMessage .= "<li>Leave reviews for completed work</li>";
                $welcomeMessage .= "</ul>";
                $welcomeMessage .= "<p><a href='https://painter-near-me.co.uk/login.php' style='background:#00b050;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Login to Your Account</a></p>";
                $welcomeMessage .= "<p>If you have any questions, please don't hesitate to contact us.</p>";
                $welcomeMessage .= "<p>Best regards,<br>The Painter Near Me Team</p>";
                
                $mailer->sendMail($email, $welcomeSubject, $welcomeMessage, strip_tags($welcomeMessage), $first_name . ' ' . $last_name);
                
                // Admin notification
                if (!defined('ADMIN_EMAIL')) {
                    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@painter-near-me.co.uk');
                }
                
                $adminSubject = 'New Customer Registration - ' . $first_name . ' ' . $last_name;
                $adminMessage = "<h2>New Customer Registration</h2>";
                $adminMessage .= "<p>A new customer has registered on Painter Near Me:</p>";
                $adminMessage .= "<table cellpadding='6' style='font-size:1.1rem;'>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Name:</td><td>" . htmlspecialchars($first_name . ' ' . $last_name) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Email:</td><td>" . htmlspecialchars($email) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Phone:</td><td>" . htmlspecialchars($phone) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Address:</td><td>" . htmlspecialchars($address . ', ' . $city . ' ' . $postcode) . "</td></tr>";
                $adminMessage .= "<tr><td style='font-weight:bold;'>Registration Date:</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
                $adminMessage .= "</table>";
                $adminMessage .= "<p><strong>Customer is ready to post projects and request quotes.</strong></p>";
                
                $mailer->sendMail(ADMIN_EMAIL, $adminSubject, $adminMessage, strip_tags($adminMessage));
                
            } catch (Exception $e) {
                error_log("Failed to send customer registration emails: " . $e->getMessage());
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Customer Registration | Painter Near Me</title>
    <meta name="description" content="Register as a customer to get quotes from verified painters in your area." />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .customer-registration {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .customer-registration__header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .customer-registration__title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .customer-registration__subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .customer-registration__form {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0,176,80,0.10), 0 1.5px 8px rgba(0,0,0,0.04);
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
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #00b050;
            box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
        }
        
        .form-input.error {
            border-color: #e74c3c;
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
        
        @media (max-width: 768px) {
            .customer-registration__form {
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
    <div class="customer-registration">
        <a href="register-hub.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Registration Options
        </a>
        
        <div class="customer-registration__header">
            <h1 class="customer-registration__title">Customer Registration</h1>
            <p class="customer-registration__subtitle">
                Join thousands of customers who found their perfect painter
            </p>
        </div>
        
        <form method="POST" class="customer-registration__form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="form-group form-group--half">
                <div>
                    <label for="first_name" class="form-label">First Name *</label>
                    <input type="text" id="first_name" name="first_name" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="last_name" class="form-label">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email Address *</label>
                <input type="email" id="email" name="email" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone" class="form-label">Phone Number *</label>
                <input type="tel" id="phone" name="phone" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="address" class="form-label">Address *</label>
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
            
            <button type="submit" class="form-button">
                Create Customer Account
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
    const form = document.querySelector('.customer-registration__form');
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
    const inputs = form.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
});
</script>
</body>
</html>