<?php
require_once '../core/StripePaymentManager.php';

// Set content type
header('Content-Type: application/json');

// Log webhook received
error_log('Stripe webhook received');

try {
    // Get the raw POST data
    $payload = file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    if (empty($payload) || empty($sig_header)) {
        error_log('Stripe webhook: Missing payload or signature');
        http_response_code(400);
        echo json_encode(['error' => 'Missing payload or signature']);
        exit;
    }
    
    // Initialize payment manager
    $paymentManager = new StripePaymentManager();
    
    // Handle the webhook
    $result = $paymentManager->handleWebhook($payload, $sig_header);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        error_log('Stripe webhook processing failed: ' . ($result['error'] ?? 'Unknown error'));
        http_response_code(400);
        echo json_encode(['error' => $result['error'] ?? 'Processing failed']);
    }
    
} catch (Exception $e) {
    error_log('Stripe webhook exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?> 