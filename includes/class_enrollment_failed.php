<?php
session_start();

// Check payment type from URL parameter
$paymentType = $_GET['type'] ?? 'class';

// Clear payment session data
unset($_SESSION['paymongo_checkout_id']);

if ($paymentType === 'subscription') {
    unset($_SESSION['subscription_payment']);
    // Set error message
    $_SESSION['error_message'] = "Subscription payment was not completed. Please try again.";
    // Redirect to courses page
    header('Location: member_online_courses.php');
} else {
    unset($_SESSION['class_enrollment']);
    // Set error message
    $_SESSION['error_message'] = "Payment was not completed. Please try again.";
    // Redirect back to classes page
    header('Location: member_class.php');
}

exit;
