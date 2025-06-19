<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../core/StripePaymentManager.php';
require_once '../core/GibsonAuth.php';

$auth = new GibsonAuth();
$paymentManager = new StripePaymentManager();

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';
$endpoint = trim($path, '/');

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);

// Check authentication for most endpoints
$requiresAuth = !in_array($endpoint, ['config', 'webhook']);
if ($requiresAuth) {
    if (!$auth->isPainterLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    $painterId = $_SESSION['painter_id'] ?? null;
}

try {
    switch ("$method:$endpoint") {
        
        case 'POST:setup-payment-method':
            // Setup payment method for painter
            $paymentMethodId = $input['payment_method_id'] ?? '';
            $isDefault = $input['is_default'] ?? false;
            
            if (empty($paymentMethodId)) {
                throw new Exception('Payment method ID is required');
            }
            
            $result = $paymentManager->savePaymentMethod($painterId, $paymentMethodId, $isDefault);
            echo json_encode($result);
            break;
            
        case 'GET:payment-methods':
            // Get painter's payment methods
            $methods = $paymentManager->getPainterPaymentMethods($painterId);
            echo json_encode(['success' => true, 'methods' => $methods]);
            break;
            
        case 'DELETE:payment-method':
            // Remove payment method
            $paymentMethodId = $input['payment_method_id'] ?? '';
            
            if (empty($paymentMethodId)) {
                throw new Exception('Payment method ID is required');
            }
            
            $result = $paymentManager->removePaymentMethod($painterId, $paymentMethodId);
            echo json_encode($result);
            break;
            
        case 'POST:purchase-lead':
            // Purchase access to a lead
            $leadId = $input['lead_id'] ?? '';
            $paymentMethodId = $input['payment_method_id'] ?? null;
            
            if (empty($leadId)) {
                throw new Exception('Lead ID is required');
            }
            
            $result = $paymentManager->processLeadPayment($painterId, $leadId, $paymentMethodId);
            echo json_encode($result);
            break;
            
        case 'GET:lead-access':
            // Check if painter has access to specific lead
            $leadId = $_GET['lead_id'] ?? '';
            
            if (empty($leadId)) {
                throw new Exception('Lead ID is required');
            }
            
            $hasAccess = $paymentManager->painterHasLeadAccess($painterId, $leadId);
            echo json_encode(['success' => true, 'has_access' => $hasAccess]);
            break;
            
        case 'GET:payment-history':
            // Get painter's payment history
            $dataAccess = new GibsonDataAccess();
            
            $result = $dataAccess->query(
                "SELECT lp.*, l.job_title, l.location, la.accessed_at
                 FROM lead_payments lp
                 JOIN leads l ON lp.lead_id = l.id
                 LEFT JOIN lead_access la ON lp.id = la.payment_id
                 WHERE lp.painter_id = ?
                 ORDER BY lp.created_at DESC
                 LIMIT 50",
                [$painterId]
            );
            
            $payments = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $payments[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'payments' => $payments]);
            break;
            
        case 'POST:confirm-payment':
            // Confirm payment intent (for 3D Secure)
            $paymentIntentId = $input['payment_intent_id'] ?? '';
            
            if (empty($paymentIntentId)) {
                throw new Exception('Payment intent ID is required');
            }
            
            \Stripe\Stripe::setApiKey($paymentManager->getStripeSecretKey());
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            
            if ($paymentIntent->status === 'succeeded') {
                // Payment confirmed, update database
                $dataAccess = new GibsonDataAccess();
                $dataAccess->query(
                    "UPDATE lead_payments SET payment_status = 'succeeded' WHERE stripe_payment_intent_id = ?",
                    [$paymentIntentId]
                );
                
                // Grant access
                $paymentResult = $dataAccess->query(
                    "SELECT id, lead_id, painter_id FROM lead_payments WHERE stripe_payment_intent_id = ?",
                    [$paymentIntentId]
                );
                
                if ($paymentResult && $paymentResult->num_rows > 0) {
                    $payment = $paymentResult->fetch_assoc();
                    $paymentManager->grantLeadAccess($payment['lead_id'], $payment['painter_id'], $payment['id']);
                    $paymentManager->updateLeadPaymentCount($payment['lead_id']);
                }
            }
            
            echo json_encode([
                'success' => true,
                'status' => $paymentIntent->status,
                'access_granted' => $paymentIntent->status === 'succeeded'
            ]);
            break;
            
        case 'GET:config':
            // Get Stripe publishable key and payment config
            $dataAccess = new GibsonDataAccess();
            $configResult = $dataAccess->query(
                "SELECT config_key, config_value FROM payment_config 
                 WHERE config_key IN ('stripe_publishable_key', 'default_lead_price', 'payment_enabled')"
            );
            
            $config = [];
            if ($configResult && $configResult->num_rows > 0) {
                while ($row = $configResult->fetch_assoc()) {
                    $config[$row['config_key']] = $row['config_value'];
                }
            }
            
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'GET:analytics':
            // Get payment analytics (admin only)
            if (!$auth->isAdminLoggedIn()) {
                throw new Exception('Admin access required');
            }
            
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $analytics = $paymentManager->getPaymentAnalytics($startDate, $endDate);
            echo json_encode(['success' => true, 'analytics' => $analytics]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Payment API error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 