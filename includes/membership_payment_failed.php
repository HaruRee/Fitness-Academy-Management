<?php
require '../config/database.php';
require_once '../vendor/autoload.php';
session_start();

// Function to get correct URL for hosting environment
function getCorrectUrl($path)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    if (strpos($host, '.ct.ws') !== false || strpos($host, '.infinityfreeapp.com') !== false || strpos($host, '.epizy.com') !== false || strpos($host, '.rf.gd') !== false) {
        return $protocol . $host . '/' . ltrim($path, '/');
    } else {
        return $protocol . $host . '/' . ltrim($path, '/');
    }
}

// Log the failure
if (isset($_SESSION['paymongo_checkout_id'])) {
    error_log("Payment failed for checkout session: " . $_SESSION['paymongo_checkout_id']);
}

// Clear payment session data
unset($_SESSION['paymongo_checkout_id']);
unset($_SESSION['membership_payment_data']);

// Set error message
$_SESSION['error_message'] = "Payment was not completed. Please try again or contact support if the problem persists.";

// Redirect back to membership page
header("Location: " . getCorrectUrl('includes/membership.php'));
exit;
