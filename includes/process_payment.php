<?php
require '../config/database.php';
require_once '../vendor/autoload.php';

// Set longer session timeout for payment process
ini_set('session.gc_maxlifetime', 3600); // 1 hour
session_set_cookie_params(3600); // 1 hour

session_start();

// Function to get correct URL for hosting environment
function getCorrectUrl($path)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Check if we're on InfinityFree hosting or localhost
    if (strpos($host, '.ct.ws') !== false || strpos($host, '.infinityfreeapp.com') !== false || strpos($host, '.epizy.com') !== false || strpos($host, '.rf.gd') !== false) {
        return $protocol . $host . '/' . ltrim($path, '/');
    } else {
        return $protocol . $host . '/' . ltrim($path, '/');
    }
}

// Load API configuration
require_once __DIR__ . '/../config/api_config.php';

// Check if we have required session data
if (!isset($_SESSION['user_data']) || !isset($_SESSION['plan_price'])) {
    error_log('Missing session data in process_payment - user_data: ' . (isset($_SESSION['user_data']) ? 'present' : 'missing'));
    error_log('Missing session data in process_payment - plan_price: ' . (isset($_SESSION['plan_price']) ? 'present' : 'missing'));
    error_log('All session keys in process_payment: ' . json_encode(array_keys($_SESSION)));
    
    $_SESSION['error_message'] = "Missing payment data. Please try again.";
    header("Location: " . getCorrectUrl('includes/register.php'));
    exit;
}

try {
    // Enhanced logging at the beginning of the process
    error_log('Starting payment process with session data: ' . json_encode($_SESSION));

    // Handle discount data from form
    $originalAmount = $_SESSION['plan_price'];
    $finalAmount = $originalAmount;
    $discountApplied = false;
    $discountType = '';
    $discountAmount = 0;

    // Check if discount was applied
    if (isset($_POST['applied_discount']) && $_POST['applied_discount'] === 'true') {
        $discountApplied = true;
        $discountType = $_POST['discount_type'] ?? '';
        $discountAmount = floatval($_POST['discount_amount'] ?? 0);
        $finalAmount = floatval($_POST['final_amount'] ?? $originalAmount);

        // Store discount data in session for later use
        $_SESSION['discount_applied'] = true;
        $_SESSION['discount_type'] = $discountType;
        $_SESSION['discount_amount'] = $discountAmount;
        $_SESSION['original_amount'] = $originalAmount;
        $_SESSION['final_amount'] = $finalAmount;

        error_log("Discount applied: {$discountType}, Amount: {$discountAmount}, Final: {$finalAmount}");
    } else {
        // Clear any existing discount data
        unset($_SESSION['discount_applied']);
        unset($_SESSION['discount_type']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['original_amount']);
        $_SESSION['final_amount'] = $originalAmount;
    }

    // Get the payment amount (use final amount after discount)
    $amount = intval($finalAmount * 100); // Convert to cents as required by PayMongo

    // Get the full domain for success and failure URLs
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Check if we're on InfinityFree hosting or localhost
    if (strpos($host, '.ct.ws') !== false || strpos($host, '.infinityfreeapp.com') !== false || strpos($host, '.epizy.com') !== false || strpos($host, '.rf.gd') !== false) {
        // InfinityFree hosting - files are in the root/includes directory
        $baseUrl = $protocol . $host . '/includes/';
    } else {
        // Localhost or other hosting - include gym1 folder
        $baseUrl = $protocol . $host . '/includes/';
    }

    // Initialize GuzzleHTTP client for API requests
    $client = new \GuzzleHttp\Client();

    // Create description with discount info if applicable
    $planDescription = $_SESSION['selected_plan'] . ' Membership';
    if ($discountApplied) {
        $planDescription .= ' (with ' . ucfirst($discountType) . ' 10% discount)';
    }

    // Create line items for checkout
    $lineItems = [
        [
            'currency' => 'PHP',
            'amount' => $amount,
            'name' => $planDescription,
            'quantity' => 1,
            'description' => 'Fitness Academy Membership Plan'
        ]
    ];

    // Prepare metadata
    $metadata = [
        'test_mode' => 'true',
        'user_email' => $_SESSION['user_data']['email'],
        'original_amount' => number_format($originalAmount, 2),
        'final_amount' => number_format($finalAmount, 2)
    ];

    if ($discountApplied) {
        $metadata['discount_applied'] = 'true';
        $metadata['discount_type'] = $discountType;
        $metadata['discount_amount'] = number_format($discountAmount, 2);
        $metadata['discount_percentage'] = '10';
    }

    // Create a PayMongo Checkout Session
    $checkoutResponse = $client->request('POST', 'https://api.paymongo.com/v1/checkout_sessions', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            'Content-Type' => 'application/json'
        ],
        'json' => [
            'data' => [
                'attributes' => [
                    'line_items' => $lineItems,
                    'payment_method_types' => ['card', 'gcash', 'grab_pay', 'paymaya', 'dob', 'billease'], // All supported payment methods
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => 'Fitness Academy Membership',
                    'success_url' => $baseUrl . 'payment_success.php',
                    'failure_url' => $baseUrl . 'payment_failed.php',
                    'billing' => [
                        'name' => $_SESSION['user_data']['first_name'] . ' ' . $_SESSION['user_data']['last_name'],
                        'email' => $_SESSION['user_data']['email'],
                        'phone' => $_SESSION['user_data']['phone']
                    ],
                    // Add metadata to help with tracking including discount info
                    'metadata' => $metadata
                ]
            ]
        ]
    ]);

    $checkoutData = json_decode($checkoutResponse->getBody(), true);

    // Debug - log the response
    error_log('PayMongo Checkout Response: ' . json_encode($checkoutData));    // Check if checkout session was created successfully
    if (isset($checkoutData['data']) && isset($checkoutData['data']['id']) && isset($checkoutData['data']['attributes']['checkout_url'])) {        // Save the checkout session ID in session for later verification
        $_SESSION['paymongo_checkout_id'] = $checkoutData['data']['id'];
        
        // Ensure session data persistence by explicitly saving important data
        $_SESSION['payment_in_progress'] = true;
        $_SESSION['payment_timestamp'] = time();
        
        // Store payment session data in database as backup
        try {
            $stmt = $conn->prepare("
                INSERT INTO payment_sessions (
                    checkout_id, user_email, user_data, plan_data, created_at
                ) VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                user_data = VALUES(user_data),
                plan_data = VALUES(plan_data),
                created_at = NOW()
            ");
            
            $planData = json_encode([
                'plan_id' => $_SESSION['plan_id'] ?? null,
                'selected_plan' => $_SESSION['selected_plan'] ?? null,
                'plan_price' => $_SESSION['plan_price'] ?? null,
                'plan_type' => $_SESSION['plan_type'] ?? null,
                'final_amount' => $finalAmount,
                'discount_applied' => $discountApplied,
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount
            ]);
            
            $stmt->execute([
                $checkoutData['data']['id'],
                $_SESSION['user_data']['email'],
                json_encode($_SESSION['user_data']),
                $planData
            ]);
            
            error_log('Payment session data backed up to database for checkout ID: ' . $checkoutData['data']['id']);
        } catch (Exception $e) {
            error_log('Failed to backup payment session data: ' . $e->getMessage());
            // Don't fail the process if backup fails
        }
        
        // Log session state for debugging
        error_log('Session data stored for payment: ' . json_encode([
            'checkout_id' => $_SESSION['paymongo_checkout_id'],
            'user_email' => $_SESSION['user_data']['email'] ?? 'not set',
            'plan_price' => $_SESSION['plan_price'] ?? 'not set',
            'plan_id' => $_SESSION['plan_id'] ?? 'not set'
        ]));

        // Get the checkout URL from the response
        $checkoutUrl = $checkoutData['data']['attributes']['checkout_url'];

        // Redirect the user to the PayMongo Checkout page
        header("Location: " . $checkoutUrl);
        exit;
    } else {
        throw new Exception("Failed to create checkout session: " . json_encode($checkoutData));
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Payment error: " . $e->getMessage();
    header("Location: " . getCorrectUrl('includes/register.php'));
    exit;
}
