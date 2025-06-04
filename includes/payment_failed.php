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
        // Localhost or other hosting - include gym1 folder
        return $protocol . $host . '/gym1/' . ltrim($path, '/');
    }
}

// Log the callback data
error_log('Payment Failed Callback - GET: ' . json_encode($_GET));
error_log('Payment Failed Callback - SESSION: ' . json_encode($_SESSION));

// Load API configuration
require_once __DIR__ . '/../config/api_config.php';

// For test mode, auto-redirect to success page
if (strpos(PAYMONGO_SECRET_KEY, 'test') !== false && isset($_SESSION['paymongo_checkout_id'])) {
    error_log('TEST MODE DETECTED: Redirecting failed payment to success page');
    header("Location: " . getCorrectUrl('includes/payment_success.php'));
    exit;
}

// Check if there's a receipt in the session
if (isset($_SESSION['payment_receipt'])) {
    // If we have a receipt, the payment might have succeeded but callback failed
    error_log('Payment has receipt but failed callback - checking status');

    try {
        if (isset($_SESSION['paymongo_checkout_id'])) {
            $client = new \GuzzleHttp\Client();
            $sessionResponse = $client->request('GET', "https://api.paymongo.com/v1/checkout_sessions/{$_SESSION['paymongo_checkout_id']}", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
                ]
            ]);

            $sessionData = json_decode($sessionResponse->getBody(), true);
            if (
                isset($sessionData['data']['attributes']['status']) &&
                in_array($sessionData['data']['attributes']['status'], ['completed', 'paid', 'succeeded'])
            ) {
                // Payment actually succeeded - redirect to success
                header("Location: " . getCorrectUrl('includes/payment_success.php'));
                exit;
            }
        }
    } catch (Exception $e) {
        error_log('Error checking payment status: ' . $e->getMessage());
    }
}

// If we get here, payment truly failed
$_SESSION['error_message'] = "Your payment was not completed. If you received a payment receipt, please contact support with your receipt details.";

// Redirect back to payment page
$_SESSION['registration_step'] = 4;
header("Location: " . getCorrectUrl('includes/register.php'));
exit;
