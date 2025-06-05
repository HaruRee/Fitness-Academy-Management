<?php
require '../config/database.php';
require_once '../vendor/autoload.php';
session_start();

// Function to get correct URL for hosting environment
function getCorrectUrl($path)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Check if we're on InfinityFree hosting or localhost
    if (strpos($host, '.ct.ws') !== false || strpos($host, '.infinityfreeapp.com') !== false || strpos($host, '.epizy.com') !== false || strpos($host, '.rf.gd') !== false) {
        // InfinityFree hosting - files are in the root directory structure
        return $protocol . $host . '/' . ltrim($path, '/');
    } else {
        // Localhost or other hosting - remove gym1 folder
        return $protocol . $host . '/' . ltrim($path, '/');
    }
}

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log the callback data
error_log('Payment Success Callback - GET: ' . json_encode($_GET));
error_log('Payment Success Callback - SESSION: ' . json_encode($_SESSION));

// PayMongo API credentials
define('PAYMONGO_SECRET_KEY', 'sk_test_8zaUvLB9QjiQFbDwjugyzMst');

// Check if we have the checkout session ID and required session data
if (!isset($_SESSION['paymongo_checkout_id']) || !isset($_SESSION['user_data'])) {
    $_SESSION['error_message'] = "Missing payment data. Please start over.";
    header("Location: " . getCorrectUrl('includes/register.php?reset=true'));
    exit;
}

try {
    // We're in test mode, so let's be more permissive with test payments
    $isTestMode = true; // Since we're using test keys

    // Get checkout session ID from session
    $checkoutSessionId = $_SESSION['paymongo_checkout_id'];
    error_log('Using checkout ID: ' . $checkoutSessionId);

    // For test mode, we can proceed with registration directly
    if ($isTestMode) {
        error_log('TEST MODE: Proceeding with registration without strict payment verification');
        $proceedWithRegistration = true;

        // Get payment method type from the session data or use default value
        // First try to get from the checkout session
        try {
            $client = new \GuzzleHttp\Client();
            $sessionResponse = $client->request('GET', "https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
                ]
            ]);

            $checkoutData = json_decode($sessionResponse->getBody(), true);
            error_log('Checkout Data: ' . json_encode($checkoutData));

            // Try to extract the payment method used
            if (isset($checkoutData['data']['attributes']['payment_method_used'])) {
                $paymentMethodType = $checkoutData['data']['attributes']['payment_method_used'] . ' (test)';
            } else {
                // Fallback based on selected payment methods
                $paymentMethods = $checkoutData['data']['attributes']['payment_method_types'] ?? ['card'];
                $paymentMethodType = $paymentMethods[0] . ' (test)';
            }
        } catch (Exception $e) {
            error_log('Error fetching checkout session: ' . $e->getMessage());
            $paymentMethodType = 'card (test)'; // Default fallback
        }

        $sessionData = [
            'test_mode' => true,
            'payment_method' => $paymentMethodType
        ];
        $paymentIntentId = $checkoutSessionId;
    } else {
        // Initialize GuzzleHTTP client for production mode
        $client = new \GuzzleHttp\Client();

        // Check the checkout session status
        $sessionResponse = $client->request('GET', "https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}", [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ]
        ]);

        $sessionData = json_decode($sessionResponse->getBody(), true);
        error_log('Checkout Session Data: ' . json_encode($sessionData));

        // Get session status considering multiple paths and payment methods
        $paymentIntent = $sessionData['data']['attributes']['payment_intent'] ?? null;
        $sessionStatus = $paymentIntent['status'] ?? $sessionData['data']['attributes']['status'] ?? null;
        $paymentMethodType = $sessionData['data']['attributes']['payment_method_used'] ?? 'online payment';

        error_log('Session Status: ' . $sessionStatus);
        error_log('Payment Method: ' . $paymentMethodType);
        error_log('Full Session Data: ' . json_encode($sessionData));

        // For Billease and other payment methods, check both session status and payment status
        $validStatuses = ['succeeded', 'paid', 'processing', 'awaiting_payment_method', 'payment_completed', 'completed'];

        // Check if payment should be considered successful
        $paymentSuccessful = false;

        if (in_array($sessionStatus, $validStatuses)) {
            $paymentSuccessful = true;
        } elseif (
            isset($sessionData['data']['attributes']['status']) &&
            $sessionData['data']['attributes']['status'] === 'completed'
        ) {
            $paymentSuccessful = true;
        } elseif (
            $paymentMethodType === 'billease' &&
            isset($sessionData['data']['attributes']['billing'])
        ) {
            // Special handling for Billease payments
            error_log('Billease payment detected - proceeding with registration');
            $paymentSuccessful = true;
        }

        if ($paymentSuccessful) {
            $proceedWithRegistration = true;
        } else {
            $proceedWithRegistration = false;
        }
    }

    if ($proceedWithRegistration) {
        // Payment is successful or we're in test mode, proceed with user registration

        // Start database transaction
        $conn->beginTransaction();

        // Get user data from session
        $userData = $_SESSION['user_data'];

        // Hash the password
        $hashed_password = password_hash($userData['password'], PASSWORD_DEFAULT);

        // Generate email token
        $email_token = bin2hex(random_bytes(32));        // Insert user into database
        $stmt = $conn->prepare("INSERT INTO users (
            Username, PasswordHash, Role, First_Name, Last_Name, Email, DateOfBirth, 
            Phone, Address, emergency_contact, is_approved, email_confirmed, email_token, 
            membership_plan, membership_price, plan_id, package_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $userData['username'],
            $hashed_password,
            $userData['role'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['email'],
            $userData['date_of_birth'],
            $userData['phone'],
            $userData['address'],
            $userData['emergency_contact'],
            $email_token,
            $_SESSION['selected_plan'],
            $_SESSION['plan_price'],
            $_SESSION['plan_id'],
            $_SESSION['package_type']
        ]);

        $user_id = $conn->lastInsertId();

        // Get the payment ID if available
        $paymentIntentId = $sessionData['data']['attributes']['payment_intent']['id'] ?? $checkoutSessionId;

        // Get the final amount to charge (considering discounts)
        $finalAmount = $_SESSION['final_amount'] ?? $_SESSION['plan_price'];
        $originalAmount = $_SESSION['plan_price'];

        // Handle discount information
        $discountData = [];
        if (isset($_SESSION['discount_applied']) && $_SESSION['discount_applied'] === true) {
            $discountData = [
                'discount_applied' => true,
                'discount_type' => $_SESSION['discount_type'],
                'discount_amount' => $_SESSION['discount_amount'],
                'original_amount' => $originalAmount,
                'final_amount' => $finalAmount,
                'discount_percentage' => 10
            ];

            // Save discount record to database
            try {
                $discountStmt = $conn->prepare("INSERT INTO user_discounts (
                    user_id, user_email, discount_type, discount_percentage, 
                    original_amount, discount_amount, final_amount, applied_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

                $discountStmt->execute([
                    $user_id,
                    $userData['email'],
                    $_SESSION['discount_type'],
                    10.00,
                    $originalAmount,
                    $_SESSION['discount_amount'],
                    $finalAmount
                ]);

                error_log("Discount record saved for user {$user_id}: {$_SESSION['discount_type']} discount");
            } catch (Exception $e) {
                error_log("Error saving discount record: " . $e->getMessage());
                // Don't fail the whole process for discount record error
            }
        }        // Store payment record with final amount
        $stmt = $conn->prepare("INSERT INTO payments (
            user_id, amount, payment_date, status, payment_method, 
            transaction_id, payment_details
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?)");

        // Merge discount data with session data for payment details
        $paymentDetails = $sessionData;
        if (!empty($discountData)) {
            $paymentDetails['discount_info'] = $discountData;
        }

        $stmt->execute([
            $user_id,
            $finalAmount, // Use final amount after discount
            'completed',
            $paymentMethodType,
            $paymentIntentId,
            json_encode($paymentDetails)
        ]);

        // Log payment in audit trail
        $payment_id = $conn->lastInsertId();
        $audit_stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (?, ?, NOW())");

        // Create a more descriptive audit entry including discount info
        $paymentDetails = "payment completed: ₱" . number_format($finalAmount, 2);
        if (isset($_SESSION['discount_applied']) && $_SESSION['discount_applied'] === true) {
            $paymentDetails .= " (originally ₱" . number_format($originalAmount, 2) .
                " with " . $_SESSION['discount_type'] . " 10% discount of ₱" .
                number_format($_SESSION['discount_amount'], 2) . ")";
        }
        $paymentDetails .= " for " . $_SESSION['selected_plan'] .
            " membership via " . $paymentMethodType;

        $audit_stmt->execute([
            $userData['username'],
            $paymentDetails
        ]);

        // Commit the transaction
        $conn->commit();

        // Clear session data including discount information
        unset($_SESSION['registration_step']);
        unset($_SESSION['verification_code']);
        unset($_SESSION['email']);
        unset($_SESSION['selected_plan']);
        unset($_SESSION['plan_price']);
        unset($_SESSION['plan_id']);
        unset($_SESSION['package_type']);
        unset($_SESSION['user_data']);
        unset($_SESSION['paymongo_checkout_id']);
        unset($_SESSION['discount_applied']);
        unset($_SESSION['discount_type']);
        unset($_SESSION['discount_amount']);
        unset($_SESSION['original_amount']);
        unset($_SESSION['final_amount']);

        // Set success message and redirect to success page
        $_SESSION['registration_success'] = true;

        // Updated redirection logic
        if ($proceedWithRegistration) {
            // Construct the full URL to ensure correct redirection
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];

            // Check if we're on InfinityFree hosting or localhost
            if (strpos($host, '.ct.ws') !== false || strpos($host, '.infinityfreeapp.com') !== false || strpos($host, '.epizy.com') !== false || strpos($host, '.rf.gd') !== false) {
                // InfinityFree hosting - files are in the root/includes directory
                $redirectUrl = $protocol . $host . '/includes/registration_success.php';
            } else {
                // Localhost or other hosting - remove gym1 folder
                $redirectUrl = $protocol . $host . '/includes/registration_success.php';
            }

            header("Location: " . $redirectUrl);
            exit;
        } else {
            $_SESSION['error_message'] = "Registration error: Payment verification failed.";
            header("Location: " . getCorrectUrl('includes/register.php'));
            exit;
        }
    } else {
        // Payment not successful
        throw new Exception("Payment was not successful: payment verification failed");
    }
} catch (Exception $e) {
    error_log("Payment Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Registration error: " . $e->getMessage();
    header("Location: " . getCorrectUrl('includes/register.php'));
    exit;
}
