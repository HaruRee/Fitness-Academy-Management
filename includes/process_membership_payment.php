<?php
require '../config/database.php';
require_once '../vendor/autoload.php';
session_start();

// Function to get correct URL for hosting environment
function getCorrectUrl($path)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Get the directory path from the current script
    $currentDir = dirname($_SERVER['PHP_SELF']);
    // Remove 'includes' from the path to get the base directory
    $baseDir = dirname($currentDir);
    // Make sure the base directory ends with a slash
    $baseDir = rtrim($baseDir, '/') . '/';

    return $protocol . $host . $baseDir . ltrim($path, '/');
}

// Load API configuration
require_once __DIR__ . '/../config/api_config.php';

// Check if we have required data
if (!isset($_POST['plan_id']) || !isset($_POST['amount']) || !isset($_POST['email'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // First, check if user already has an active subscription/plan
    $stmt = $conn->prepare("
        SELECT UserID, plan_id, membership_plan, membership_price, 
               First_Name, Last_Name, Email, Phone,
               current_sessions_remaining, membership_start_date, membership_end_date
        FROM users 
        WHERE UserID = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }    // Check if user has an active subscription/plan using correct logic
    $hasActivePlan = false;
    if ($user && (
        // Session-based membership: has sessions remaining
        ($user['current_sessions_remaining'] > 0) ||
        // Time-based membership: has valid dates and membership hasn't expired
        (!empty($user['membership_start_date']) && 
         !empty($user['membership_end_date']) && 
         $user['membership_end_date'] > $user['membership_start_date'] &&
         $user['membership_end_date'] > date('Y-m-d'))
    )) {
        $hasActivePlan = true;
    }
    
    if ($hasActivePlan) {
        throw new Exception('You already have an active membership plan. Please wait for your current plan to expire before purchasing a new one.');
    }

    // Get plan details
    $stmt = $conn->prepare("SELECT * FROM membershipplans WHERE id = ? AND is_active = 1");
    $stmt->execute([$_POST['plan_id']]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception('Invalid or inactive plan selected');
    }

    // Initialize GuzzleHTTP client
    $client = new \GuzzleHttp\Client();

    // Create line items for checkout
    $lineItems = [
        [
            'currency' => 'PHP',
            'amount' => intval($_POST['amount'] * 100), // Convert to cents
            'name' => $plan['name'],
            'quantity' => 1,
            'description' => 'Fitness Academy Membership Plan'
        ]
    ];

    // Store plan details in session for later use
    $_SESSION['membership_payment'] = [
        'plan_id' => $_POST['plan_id'],
        'amount' => $_POST['amount'],
        'plan_name' => $plan['name']
    ];

    // Create a PayMongo Checkout Session
    $response = $client->request('POST', 'https://api.paymongo.com/v1/checkout_sessions', [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            'Content-Type' => 'application/json'
        ],
        'json' => [
            'data' => [
                'attributes' => [
                    'line_items' => $lineItems,
                    'payment_method_types' => ['card', 'gcash', 'grab_pay', 'paymaya'],
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => 'Fitness Academy Membership - ' . $plan['name'],
                    'success_url' => getCorrectUrl('includes/membership_payment_success.php'),
                    'failure_url' => getCorrectUrl('includes/membership_payment_failed.php'),
                    'billing' => [
                        'name' => $user['First_Name'] . ' ' . $user['Last_Name'],
                        'email' => $user['Email'],
                        'phone' => $user['Phone'] ?? ''
                    ],
                    'metadata' => [
                        'user_id' => $user['UserID'],
                        'plan_id' => $_POST['plan_id'],
                        'amount' => $_POST['amount']
                    ]
                ]
            ]
        ]
    ]);

    $result = json_decode($response->getBody(), true);

    if (isset($result['data']) && isset($result['data']['attributes']['checkout_url'])) {
        // Store checkout session ID in session for verification
        $_SESSION['paymongo_checkout_id'] = $result['data']['id'];
        $_SESSION['selected_plan_id'] = $_POST['plan_id'];

        echo json_encode([
            'success' => true,
            'checkout_url' => $result['data']['attributes']['checkout_url']
        ]);
    } else {
        throw new Exception('Failed to create checkout session');
    }
} catch (Exception $e) {
    error_log('Payment Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
