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

// Check if we have the checkout session ID and required session data
if (!isset($_SESSION['paymongo_checkout_id']) || !isset($_SESSION['membership_payment'])) {
    $_SESSION['error_message'] = "Missing payment data. Please try again.";
    header("Location: " . getCorrectUrl('includes/membership.php'));
    exit;
}

try {
    // Get checkout session ID from session
    $checkoutSessionId = $_SESSION['paymongo_checkout_id'];
    error_log('Using checkout ID: ' . $checkoutSessionId);

    // Initialize GuzzleHTTP client
    $client = new \GuzzleHttp\Client();

    // Verify the payment status
    $sessionResponse = $client->request('GET', "https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}", [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ]
    ]);

    $sessionData = json_decode($sessionResponse->getBody(), true);
    $paymentStatus = $sessionData['data']['attributes']['payment_intent']['status'] ?? $sessionData['data']['attributes']['status'] ?? null;    // Check if payment is successful
    $validStatuses = ['succeeded', 'paid', 'payment_completed', 'completed', 'active'];
    if (!in_array($paymentStatus, $validStatuses)) {
        throw new Exception('Payment verification failed. Status: ' . $paymentStatus);
    }// Start transaction
    $conn->beginTransaction();

    // Get membership payment details
    $membershipData = $_SESSION['membership_payment'];
    $userId = $_SESSION['user_id'];
    $planId = $membershipData['plan_id'];
    $amount = $membershipData['amount'];    // Double-check user doesn't already have an active plan (security measure)
    $stmt = $conn->prepare("
        SELECT plan_id, membership_plan, membership_price,
               current_sessions_remaining, membership_start_date, membership_end_date
        FROM users 
        WHERE UserID = ?
    ");
    $stmt->execute([$userId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user has an active plan using consistent logic
    $hasActivePlan = false;
    if ($existingUser && (
        // Session-based membership: has sessions remaining
        ($existingUser['current_sessions_remaining'] > 0) ||
        // Time-based membership: has valid dates and membership hasn't expired
        (!empty($existingUser['membership_start_date']) && 
         !empty($existingUser['membership_end_date']) && 
         $existingUser['membership_end_date'] > $existingUser['membership_start_date'] &&
         $existingUser['membership_end_date'] > date('Y-m-d'))
    )) {
        $hasActivePlan = true;
    }
    
    if ($hasActivePlan) {
        throw new Exception('User already has an active membership plan. Cannot process duplicate payment.');
    }

    // Calculate membership duration based on plan
    $stmt = $conn->prepare("SELECT * FROM membershipplans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);    // Set start date and handle plan type-specific logic
    $startDate = date('Y-m-d');
    $endDate = null;
    $sessionsRemaining = null;
    
    if ($plan['plan_type'] === 'monthly') {
        // For monthly plans, use duration_months field
        $duration = intval($plan['duration_months']);
        $endDate = date('Y-m-d', strtotime("+{$duration} months"));
    } elseif ($plan['plan_type'] === 'session') {
        // For session plans, set sessions_remaining
        $sessionsRemaining = intval($plan['session_count']);
        // Setting a default end date of 1 year for session-based plans
        $endDate = date('Y-m-d', strtotime("+12 months"));
    } else {
        // Fallback to extracting duration from plan name (legacy support)
        preg_match('/(\d+)\s*(?:Month|Months)/', $plan['name'], $matches);
        $duration = isset($matches[1]) ? intval($matches[1]) : 1; // Default to 1 month
        $endDate = date('Y-m-d', strtotime("+{$duration} months"));
    }

    // Insert into memberships table
    $stmt = $conn->prepare("
        INSERT INTO memberships (
            user_id, plan_id, start_date, end_date, 
            amount_paid, payment_method, payment_reference,
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");

    $stmt->execute([
        $userId,
        $planId,
        $startDate,
        $endDate,
        $amount,
        $sessionData['data']['attributes']['payment_method_used'] ?? 'online',
        $checkoutSessionId
    ]);    // Update user's membership details with correct start and end dates
    $stmt = $conn->prepare("
        UPDATE users SET 
        membership_plan = ?,
        membership_price = ?,
        plan_id = ?,
        membership_start_date = ?,
        membership_end_date = ?,
        current_sessions_remaining = ?
        WHERE UserID = ?
    ");

    // Set sessions remaining if it's a session-based plan
    $sessionsRemaining = null;
    if ($plan['plan_type'] === 'session') {
        $sessionsRemaining = intval($plan['session_count']);
    }

    $stmt->execute([
        $plan['name'],
        $amount,
        $planId,
        $startDate,
        $endDate,
        $sessionsRemaining,
        $userId
    ]);// Insert into payments table
    $stmt = $conn->prepare("
        INSERT INTO payments (
            user_id, amount, payment_date, payment_method,
            transaction_id, payment_details, status
        ) VALUES (?, ?, NOW(), ?, ?, ?, 'completed')
    ");

    $paymentDetails = json_encode([
        'type' => 'membership',
        'plan_id' => $planId,
        'plan_name' => $plan['name']
    ]);

    $stmt->execute([
        $userId,
        $amount,
        $sessionData['data']['attributes']['payment_method_used'] ?? 'online',
        $checkoutSessionId,
        $paymentDetails
    ]);

    // Commit transaction
    $conn->commit();

    // Clear payment session data
    unset($_SESSION['paymongo_checkout_id']);
    unset($_SESSION['membership_payment']);
    unset($_SESSION['selected_plan_id']);

    // Set success message
    $_SESSION['success_message'] = "Payment successful! Your membership has been activated.";

    // Redirect back to membership page
    header("Location: " . getCorrectUrl('includes/membership.php'));
    exit;
} catch (Exception $e) {
    // Rollback transaction if started
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Payment Success Error: ' . $e->getMessage());
    $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
    header("Location: " . getCorrectUrl('includes/membership.php'));
    exit;
}
