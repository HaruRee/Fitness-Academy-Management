<?php

require '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load API configuration
require_once __DIR__ . '/../config/api_config.php';

session_start();

// Define current step first
$error_message = '';
$current_step = isset($_SESSION['registration_step']) ? $_SESSION['registration_step'] : 1;

// Make step navigation more robust
if (isset($_GET['step'])) {
    $requested_step = (int)$_GET['step'];

    // Allow going back to step 1 from any point
    if ($requested_step == 1) {
        $_SESSION['registration_step'] = 1;
        header("Location: register.php");
        exit;
    }

    // Allow going back to step 2 from step 3 or 4
    if ($requested_step == 2 && $current_step > 2) {
        $_SESSION['registration_step'] = 2;
        header("Location: register.php");
        exit;
    }

    // Allow going back to step 3 from step 4
    if ($requested_step == 3 && $current_step > 3) {
        $_SESSION['registration_step'] = 3;
        header("Location: register.php");
        exit;
    }
}

// Reset registration process if requested or coming from a different page
$http_referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
if (isset($_GET['reset']) || (strpos($http_referer, 'register.php') === false && !empty($http_referer))) {
    // Clear registration session variables
    unset($_SESSION['registration_step']);
    unset($_SESSION['verification_code']);
    unset($_SESSION['email']);
    unset($_SESSION['selected_plan']);
    unset($_SESSION['plan_price']);
    unset($_SESSION['plan_id']);
    unset($_SESSION['package_type']);
    unset($_SESSION['user_data']);
}

// Handle plan selection (Step 1)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_plan'])) {
    $_SESSION['selected_plan'] = $_POST['plan'];
    $_SESSION['plan_price'] = $_POST['price'];
    $_SESSION['plan_id'] = $_POST['plan_id'];
    $_SESSION['package_type'] = $_POST['package_type'];
    $_SESSION['registration_step'] = 2;
    header('Location: register.php');
    exit;
}

// Handle email submission (Step 2)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error_message = "Email already registered";
        } else {
            // Generate verification code
            $verification_code = sprintf("%06d", rand(0, 999999));
            $_SESSION['verification_code'] = $verification_code;
            $_SESSION['email'] = $email;

            // Send email function - Extracted for reuse in resend
            if (sendVerificationEmail($email, $verification_code)) {
                $_SESSION['registration_step'] = 2.5; // Verification code step
                header('Location: register.php');
                exit;
            } else {
                $error_message = "Failed to send verification code. Please try again.";
            }
        }
    }
}

// Handle resend verification code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_code'])) {
    if (isset($_SESSION['email'])) {
        // Generate new verification code
        $verification_code = sprintf("%06d", rand(0, 999999));
        $_SESSION['verification_code'] = $verification_code;

        if (sendVerificationEmail($_SESSION['email'], $verification_code)) {
            $success_message = "Verification code resent successfully!";
        } else {
            $error_message = "Failed to resend code. Please try again.";
        }
    } else {
        $error_message = "Email not found. Please start over.";
        $_SESSION['registration_step'] = 1;
    }
}

// Handle cancel button in verification step
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_verification'])) {
    $_SESSION['registration_step'] = 2; // Go back to email entry step
    header('Location: register.php');
    exit;
}

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_code'])) {
    $entered_code = $_POST['verification_code'];

    if ($entered_code == $_SESSION['verification_code']) {
        $_SESSION['registration_step'] = 3;
        header('Location: register.php');
        exit;
    } else {
        $error_message = "Invalid verification code. Please try again.";
    }
}

// Handle final registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    // Sanitize and validate all inputs
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $date_of_birth = $_POST['date_of_birth'];
    $phone = htmlspecialchars(trim($_POST['phone']));
    $address = htmlspecialchars(trim($_POST['address']));
    $emergency_contact = htmlspecialchars(trim($_POST['emergency_contact']));
    $email = $_SESSION['email']; // From verified email
    $role = 'Member';

    if (!preg_match("/^[a-zA-Z\s'-]+$/", $first_name)) {
        $error_message = "Invalid first name. Only letters, spaces, hyphens, and apostrophes are allowed.";
    } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $last_name)) {
        $error_message = "Invalid last name. Only letters, spaces, hyphens, and apostrophes are allowed.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]{3,20}$/", $username)) {
        $error_message = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
    } elseif (!strtotime($date_of_birth)) {
        $error_message = "Invalid date of birth.";
    } elseif (!empty($phone) && !preg_match("/^[0-9+\-\s]{7,20}$/", $phone)) {
        $error_message = "Invalid phone number format.";
    } elseif (!preg_match("/^[0-9+\-\s]{7,20}$/", $emergency_contact)) {
        $error_message = "Invalid emergency contact number.";    // Address field is now optional, so we removed the empty check
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[\W_]/', $password)) {
        $error_message = "Password must be at least 8 characters long and include uppercase, lowercase, numbers, and symbols.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // Check if username exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Username already exists.");
            }            // Store user data in session for later use after payment
            $_SESSION['user_data'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => $username,
                'password' => $password,
                'date_of_birth' => $date_of_birth,
                'phone' => $phone,
                'address' => $address,
                'emergency_contact' => $emergency_contact,
                'email' => $email,
                'role' => $role
            ];

            // Proceed to payment step
            $_SESSION['registration_step'] = 4;
            header('Location: register.php');
            exit;
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Function to send verification email
function sendVerificationEmail($email, $verification_code)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification';
        $mail->Body = "
            <h2>Verify Your Email</h2>
            <p>Your verification code is: <strong>{$verification_code}</strong></p>
            <p>Enter this code to complete your registration.</p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Join Now | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .navbar .logo {
            display: flex;
            align-items: center;
        }

        .navbar .logo img {
            height: 40px;
            margin-right: 10px;
        }

        .navbar .logo-text {
            font-weight: 700;
            font-size: 1.4rem;
            color: #e41e26;
            text-decoration: none;
        }

        .nav-links a {
            margin-left: 20px;
            color: #333;
            text-decoration: none;
            font-weight: 600;
        }

        .nav-links a:hover {
            color: #e41e26;
        }

        .registration-page {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Updated progress indicator styles */
        .progress-indicator {
            display: flex;
            justify-content: center;
            width: 100%;
            max-width: 700px;
            /* Slightly smaller overall width */
            margin-bottom: 25px;
            position: relative;
        }

        .progress-line {
            position: absolute;
            top: 16px;
            /* Adjusted to align with smaller circles */
            height: 2px;
            background: #ddd;
            width: 70%;
            z-index: 1;
        }

        .step-icon {
            width: 32px;
            /* Smaller from 40px */
            height: 32px;
            /* Smaller from 40px */
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            font-weight: bold;
            color: white;
            font-size: 0.9rem;
            /* Smaller text inside circle */
        }

        .step-label {
            font-size: 0.8rem;
            /* Smaller from 0.9rem */
            font-weight: 600;
            color: #666;
            text-align: center;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 33.33%;
            position: relative;
            z-index: 2;
        }

        .step-icon.active {
            background: #e41e26;
        }

        .step-icon.completed {
            background: #4CAF50;
        }

        .progress-step {
            text-decoration: none;
            color: inherit;
        }

        .progress-step.clickable {
            cursor: pointer;
        }

        .progress-step.clickable:hover .step-label {
            color: #e41e26;
        }

        .progress-step.clickable:hover .step-icon.completed {
            background-color: #3a8c3d;
        }

        .progress-step:not(.clickable) {
            cursor: default;
        }

        .registration-container {
            background: #fff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 800px;
            text-align: center;
        }

        .heading {
            margin-bottom: 2rem;
            font-size: 1.8rem;
            color: #333;
            text-align: center;
        }

        .error-message {
            color: #e41e26;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            text-align: left;
            background: rgba(228, 30, 38, 0.1);
            padding: 0.8rem;
            border-radius: 4px;
            border-left: 3px solid #e41e26;
        }

        /* Plan selection styles */
        .plans-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .plan-card {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            border-color: #e41e26;
        }

        .plan-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 15px;
            flex-grow: 0;
            color: #333;
        }

        .plan-price {
            color: #e41e26;
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .price-period {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .plan-features {
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .feature-item {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .select-button {
            background: #e41e26;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            margin-top: auto;
        }

        .select-button:hover {
            background: #c81a21;
        }

        /* Email verification styles */
        .email-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #e41e26;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #e41e26;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #c81a21;
        }

        .btn-secondary {
            background: #666;
        }

        .btn-secondary:hover {
            background: #555;
        }

        /* Verification code container */
        .verification-container {
            max-width: 400px;
            margin: 0 auto;
            text-align: center;
        }

        .verification-heading {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .verification-text {
            color: #666;
            margin-bottom: 25px;
        }

        .verification-code {
            letter-spacing: normal;
            font-size: 1.2rem;
            text-align: center;
        }

        .code-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .resend-link {
            color: #666;
            text-decoration: none;
            margin-top: 15px;
            display: inline-block;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        /* Personal information form */
        .personal-info-form {
            text-align: left;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .form-column {
            flex: 1;
            padding: 0 10px;
            min-width: 250px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }

        .checkbox-group input {
            margin-right: 10px;
        }

        .bottom-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            align-items: center;
        }

        .bottom-nav .btn {
            width: auto;
            padding: 10px 25px;
        }

        /* Package Header and Inclusions */
        .package-header {
            width: 100%;
            text-align: center;
            margin: 30px 0 20px;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .package-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.4rem;
            text-transform: uppercase;
        }

        .inclusions-section {
            margin: 40px auto;
            max-width: 700px;
            text-align: center;
            background: #f5f5f5;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .inclusions-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2rem;
            text-transform: uppercase;
        }

        .inclusions-section ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .inclusions-section li {
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #555;
        }

        .inclusions-section li:before {
            content: '• ';
            color: #e41e26;
            font-weight: bold;
        }

        /* Plan Grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            /* Force 4 equal columns */
            gap: 20px;
            width: 100%;
            max-width: 1200px;
            /* Slightly wider to accommodate 4 columns better */
            margin: 0 auto 30px;
        }

        /* Responsive Grid */
        @media (max-width: 1200px) {
            .plans-grid {
                grid-template-columns: repeat(4, 1fr);
                /* Still 4 columns */
                gap: 15px;
                /* Slightly reduce gap */
            }
        }

        @media (max-width: 992px) {
            .plans-grid {
                grid-template-columns: repeat(2, 1fr);
                /* Switch to 2 columns */
                max-width: 700px;
                /* Adjust max width for better proportions */
            }
        }

        @media (max-width: 576px) {
            .plans-grid {
                grid-template-columns: 1fr;
                /* Single column on very small screens */
                max-width: 320px;
            }
        }

        @media (max-width: 768px) {
            .registration-container {
                padding: 1.5rem;
            }

            .form-column {
                flex: 100%;
                margin-bottom: 10px;
            }
        }

        /* Payment step styles */
        .payment-step {
            max-width: 600px;
        }

        .payment-summary {
            margin-bottom: 30px;
        }

        .summary-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            font-weight: bold;
            margin-top: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .price {
            color: #e41e26;
            font-weight: bold;
        }

        /* Discount Section Styles */
        .discount-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .discount-section h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .discount-section p {
            color: #666;
            margin-bottom: 20px;
        }

        .discount-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .discount-type-selector {
            position: relative;
        }

        .discount-type-selector input[type="radio"] {
            display: none;
        }

        .discount-label {
            display: block;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .discount-label:hover {
            border-color: #e41e26;
            background: #fff5f5;
        }

        .discount-type-selector input[type="radio"]:checked+.discount-label {
            border-color: #e41e26;
            background: #fff5f5;
            color: #e41e26;
        }

        .discount-label i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 8px;
        }

        .discount-label span {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

        .discount-label small {
            color: #666;
            font-size: 0.8rem;
        }

        .file-upload-section {
            margin-top: 20px;
            padding: 20px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            text-align: center;
        }

        .file-upload-container {
            margin-bottom: 15px;
        }

        .file-upload-label {
            display: block;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background: #f5f5f5;
        }

        .file-upload-label i {
            font-size: 2rem;
            color: #e41e26;
            margin-bottom: 10px;
        }

        .file-upload-label span {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .file-upload-label small {
            color: #666;
        }

        .upload-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .upload-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 5px;
        }

        .verify-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .verify-btn:hover:not(:disabled) {
            background: #218838;
        }

        .verify-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .verification-status {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }

        .verification-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .verification-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .verification-status.loading {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .discount-row {
            color: #28a745 !important;
        }

        .discount-amount {
            color: #28a745 !important;
            font-weight: bold;
        }

        .payment-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .payment-container h3 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
            font-size: 1.4rem;
        }

        .payment-info {
            margin-bottom: 25px;
            text-align: center;
        }

        .payment-info p {
            margin-bottom: 15px;
            color: #555;
            font-size: 0.95rem;
        }

        .payment-methods-list {
            list-style-type: none;
            padding: 0;
            margin: 15px 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }

        .payment-methods-list li {
            background-color: #f9f9f9;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #eee;
            font-size: 0.9rem;
            color: #333;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .payment-methods-list li:hover {
            background-color: #f0f0f0;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .payment-methods-list li i {
            margin-right: 8px;
            color: #0056b3;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .payment-method-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
            transition: all 0.3s ease;
        }

        .payment-method-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }

        .payment-method-item img {
            margin-bottom: 10px;
        }

        .payment-method-item span {
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
        }

        #card-element {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
            background-color: #fff;
        }

        .payment-error {
            color: #e41e26;
            font-size: 0.9rem;
            margin: 10px 0 20px;
            min-height: 20px;
        }

        #payment-button {
            margin-top: 20px;
            background-color: #0056b3;
            border-color: #0056b3;
            transition: all 0.3s ease;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            border-radius: 4px;
            color: white;
        }

        #payment-button:hover {
            background-color: #004494;
            border-color: #004494;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .secure-payment {
            margin-top: 15px;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .secure-payment i {
            color: #4CAF50;
            margin-right: 5px;
            font-size: 0.9rem;
        }

        .payment-methods {
            margin-bottom: 20px;
        }

        .payment-method {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .payment-method.active {
            border-color: #e41e26;
            background-color: rgba(228, 30, 38, 0.05);
        }

        .payment-method input {
            margin-right: 15px;
        }

        .payment-method label {
            display: flex;
            align-items: center;
            width: 100%;
            cursor: pointer;
        }

        .payment-method img {
            height: 40px;
            margin-right: 15px;
            object-fit: contain;
        }

        .payment-method span {
            font-weight: 600;
        }

        /* Loading Animation Styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 5px solid #e41e26;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        .loading-text {
            color: white;
            font-size: 18px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .password-requirements {
            font-size: 0.8rem;
            padding: 10px;
            margin-top: 5px;
        }

        .password-requirements .req {
            margin-bottom: 2px;
        }

        .text-danger {
            color: #dc3545;
        }

        .text-success {
            color: #28a745;
        }

        .password-requirements .fa-check {
            color: #28a745;
        }

        /* Password strength meter styles */
        .password-strength-meter {
            margin-top: 10px;
        }

        .strength-label {
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .strength-meter {
            height: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }

        #strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }

        .strength-weak {
            width: 25%;
            background-color: #dc3545;
        }

        .strength-fair {
            width: 50%;
            background-color: #ffc107;
        }

        .strength-good {
            width: 75%;
            background-color: #17a2b8;
        }

        .strength-strong {
            width: 100%;
            background-color: #28a745;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="logo">
            <a href="../includes/index.php?reset_registration=true"><img src="../assets/images/fa_logo.png" alt="Fitness Academy"></a>
            <a href="../includes/index.php?reset_registration=true" class="logo-text">Fitness Academy</a>
        </div>
        <div class="nav-links">
            <a href="login.php?reset_registration=true">Login</a>
        </div>
    </nav>

    <div class="registration-page">
        <!-- Progress Indicator -->
        <div class="progress-indicator">
            <div class="progress-line"></div>

            <!-- Step 1 -->
            <a href="register.php?step=1" class="progress-step <?php echo $current_step > 1 ? 'clickable' : ''; ?>">
                <div class="step-icon <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo $current_step > 1 ? 'completed' : ''; ?>">
                    <?php echo $current_step <= 1 ? "1" : "<i class='fas fa-check'></i>"; ?>
                </div>
                <div class="step-label">YOUR PLAN</div>
            </a>

            <!-- Step 2 -->
            <a href="<?php echo ($current_step > 2) ? 'register.php?step=2' : 'javascript:void(0)'; ?>" class="progress-step <?php echo $current_step > 2 ? 'clickable' : ''; ?>">
                <div class="step-icon <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo $current_step > 2 ? 'completed' : ''; ?>">
                    <?php echo $current_step <= 2 || $current_step == 2.5 ? "2" : "<i class='fas fa-check'></i>"; ?>
                </div>
                <div class="step-label">YOUR DETAILS</div>
            </a>

            <!-- Step 3 (Payment) -->
            <div class="progress-step">
                <div class="step-icon <?php echo $current_step >= 4 ? 'active' : ''; ?>">
                    3
                </div>
                <div class="step-label">PAYMENT</div>
            </div>
        </div> <?php if (!empty($error_message)): ?>
            <div class="error-message" style="max-width: 800px; margin: 0 auto 20px auto; background-color: #fff3f3; border-left: 4px solid #e41e26; padding: 15px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <i class="fas fa-exclamation-circle" style="color: #e41e26;"></i>
                <span style="font-weight: 500;"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php
        // Step 1: Plan Selection
        if ($current_step == 1): ?>
            <h1 class="heading">1 - SELECT YOUR PLAN</h1>

            <?php
            // Fetch all active membership plans from the database with error handling
            try {
                // Check if table exists first
                $tableExists = false;
                $tables = $conn->query("SHOW TABLES LIKE 'membershipplans'")->fetchAll();
                foreach ($tables as $table) {
                    $tableExists = true;
                    break;
                }

                if (!$tableExists) {
                    throw new Exception("membershipplans table doesn't exist yet");
                }

                $stmt = $conn->prepare("SELECT * FROM membershipplans WHERE is_active = 1 ORDER BY package_type, sort_order");
                $stmt->execute();
                $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($plans)) {
                    echo '<div class="alert alert-warning">No membership plans found. Please contact an administrator.</div>';
                } else {
                    // Group plans by package type (using regular loops for compatibility)
                    $packageA = array();
                    $packageB = array();

                    foreach ($plans as $plan) {
                        if ($plan['package_type'] === 'A') {
                            $packageA[] = $plan;
                        } elseif ($plan['package_type'] === 'B') {
                            $packageB[] = $plan;
                        }
                    }

                    // Render Package A plans if any exist
                    if (!empty($packageA)): ?>
                        <div class="package-header">
                            <h2>PACKAGE A - GYM ACCESS</h2>
                        </div>

                        <div class="plans-grid">
                            <?php foreach ($packageA as $plan): ?>
                                <form method="POST" action="register.php">
                                    <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan['name']); ?>">
                                    <input type="hidden" name="price" value="<?php echo htmlspecialchars($plan['price']); ?>">
                                    <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan['id']); ?>">
                                    <input type="hidden" name="package_type" value="<?php echo htmlspecialchars($plan['package_type']); ?>">
                                    <div class="plan-card">
                                        <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                                        <div class="plan-price">₱<?php echo number_format($plan['price'], 0); ?></div>
                                        <?php if (!empty($plan['features'])): ?>
                                            <div class="plan-features">
                                                <?php foreach (explode('|', $plan['features']) as $feature): ?>
                                                    <div class="feature-item"><?php echo htmlspecialchars($feature); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <button type="submit" name="select_plan" class="select-button">Select</button>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Package B: Time-based -->
                    <?php if (!empty($packageB)): ?>
                        <div class="package-header">
                            <h2>PACKAGE B - GYM ACCESS</h2>
                        </div>

                        <div class="plans-grid">
                            <?php foreach ($packageB as $plan): ?>
                                <form method="POST" action="register.php">
                                    <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan['name']); ?>">
                                    <input type="hidden" name="price" value="<?php echo htmlspecialchars($plan['price']); ?>">
                                    <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan['id']); ?>">
                                    <input type="hidden" name="package_type" value="<?php echo htmlspecialchars($plan['package_type']); ?>">
                                    <div class="plan-card">
                                        <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                                        <div class="plan-price">₱<?php echo number_format($plan['price'], 0); ?></div>
                                        <?php if (!empty($plan['features'])): ?>
                                            <div class="plan-features">
                                                <?php foreach (explode('|', $plan['features']) as $feature): ?>
                                                    <div class="feature-item"><?php echo htmlspecialchars($feature); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <button type="submit" name="select_plan" class="select-button">Select</button>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        </div>
            <?php endif;
                }
            } catch (Exception $e) {
                echo '<div class="error-message">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>

            <!-- Inclusions Section -->
            <div class="inclusions-section">
                <h3>INCLUSIONS:</h3>
                <ul>
                    <li>STATIONARY BIKES</li>
                    <li>TREADMILL</li>
                    <li>OLYMPIC TYPES OF FREE WEIGHTS</li>
                    <li>STATE-OF-THE-ART FITNESS MACHINE</li>
                    <li>CLASS PROGRAMS</li>
                </ul>
            </div>
        <?php elseif ($current_step == 2): ?>
            <!-- Use registration-container only for non-plan steps -->
            <div class="registration-container">
                <h1 class="heading">2 - ENTER YOUR DETAILS</h1>
                <!-- Email verification content remains the same -->
                <div class="email-container">
                    <h2 style="margin-bottom: 20px;">Membership Summary</h2>
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 30px; background: #f9f9f9;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">

                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>Membership:</span>
                            <span><?php echo isset($_SESSION['selected_plan']) ? $_SESSION['selected_plan'] : ''; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>Price:</span>
                            <span style="color: #e41e26; font-weight: bold;">₱<?php echo isset($_SESSION['plan_price']) ? $_SESSION['plan_price'] : '0.00'; ?> <span style="color: #666; font-weight: normal;">per month</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Joining Fee:</span>
                            <span style="color: #e41e26; font-weight: bold;">₱0.00</span>
                        </div>
                    </div>

                    <h3 style="margin-bottom: 20px;">Member details</h3>
                    <form method="POST" action="register.php">
                        <div class="form-group">
                            <label for="email">Email <span style="color: #e41e26;">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>

                        <button type="submit" name="submit_email" class="btn">CHECK EMAIL</button>
                    </form>
                </div>
            </div>
        <?php elseif ($current_step == 2.5): ?>
            <!-- Use registration-container only for non-plan steps -->
            <div class="registration-container">
                <!-- Verification code content remains the same -->
                <div class="verification-container">
                    <h2 class="verification-heading">We sent you a code</h2>
                    <p class="verification-text">We sent a code to <?php echo htmlspecialchars($_SESSION['email']); ?>. Please enter it below.</p>

                    <?php if (isset($success_message)): ?>
                        <div style="color: #4CAF50; margin-bottom: 15px;">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php">
                        <div class="form-group">
                            <label for="verification_code">Code</label>
                            <input type="text" id="verification_code" name="verification_code" class="form-control verification-code" required>
                        </div>

                        <div class="code-actions">
                            <button type="submit" name="cancel_verification" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                            <button type="submit" name="verify_code" class="btn" style="flex: 1;">Check Code</button>
                        </div>
                    </form>

                    <form method="POST" action="register.php" style="margin-top: 15px;">
                        <button type="submit" name="resend_code" class="resend-link" style="background: none; border: none; cursor: pointer;">Resend Code</button>
                    </form>
                </div>
            </div>
        <?php elseif ($current_step == 3): ?>
            <div class="registration-container">
                <h1 class="heading">2 - ENTER YOUR DETAILS</h1>
                <!-- Personal info form with added fields -->
                <form method="POST" action="register.php" class="personal-info-form">
                    <div class="form-row">
                        <div class="form-column">
                            <div class="form-group">
                                <label for="first_name">First Name <span style="color: #e41e26;">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="form-control" required
                                    value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-column">
                            <div class="form-group">
                                <label for="last_name">Last Name <span style="color: #e41e26;">*</span></label>
                                <input type="text" id="last_name" name="last_name" class="form-control" required
                                    value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-column">
                            <div class="form-group">
                                <label for="username">Username <span style="color: #e41e26;">*</span></label>
                                <input type="text" id="username" name="username" class="form-control" required
                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-column">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth <span style="color: #e41e26;">*</span></label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required
                                    value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-column">
                            <div class="form-group">
                                <label for="phone">Phone Number (Optional)</label>
                                <input type="text" id="phone" name="phone" class="form-control"
                                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="form-column">
                            <div class="form-group">
                                <label for="address">Address (Optional)</label>
                                <input type="text" id="address" name="address" class="form-control"
                                    value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-column">
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact Number <span style="color: #e41e26;">*</span></label>
                                <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" required
                                    value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-column">
                            <!-- Empty column to maintain grid layout -->
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-column">
                            <div class="form-group">
                                <label for="password">Password <span style="color: #e41e26;">*</span></label>
                                <div class="password-input-wrapper" style="position: relative;">
                                    <input type="password" id="password" name="password" class="form-control" required
                                        value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>">
                                </div>
                                <small class="form-text text-muted">
                                    Password must be at least 8 characters with uppercase, lowercase, numbers, and symbols.
                                    <span class="d-block mt-1" style="font-style: italic; color: #666;">
                                        Password strength will be shown as you type
                                    </span>
                                </small>
                            </div>
                        </div>

                        <div class="form-column">
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span style="color: #e41e26;">*</span></label>
                                <div class="password-input-wrapper" style="position: relative;">
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                                        value="<?php echo isset($_POST['confirm_password']) ? htmlspecialchars($_POST['confirm_password']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <div class="checkbox-group" style="margin-bottom: 10px;">
                            <input type="checkbox" id="show_password" onclick="togglePassword()" style="cursor: pointer;">
                            <label for="show_password" style="cursor: pointer;">Show password</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">I agree to the <a href="#" style="color: #e41e26;" data-bs-toggle="modal" data-bs-target="#termsModal">terms and conditions</a></label>
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn" style="margin-top: 20px;">Continue to Payment</button>
                </form>
            </div> <?php elseif ($current_step == 4): ?>
            <div class="registration-container payment-step">
                <h1 class="heading">3 - COMPLETE PAYMENT</h1>

                <!-- Discount Section -->
                <div class="discount-section">
                    <h3><i class="fas fa-percent"></i> Apply Discount</h3>
                    <p>Upload your valid ID to automatically receive a 10% discount!</p>

                    <div class="discount-options">
                        <div class="discount-type-selector">
                            <input type="radio" id="student" name="discount_type" value="student">
                            <label for="student" class="discount-label">
                                <i class="fas fa-graduation-cap"></i>
                                <span>Student Discount</span>
                                <small>Valid student ID required</small>
                            </label>
                        </div>

                        <div class="discount-type-selector">
                            <input type="radio" id="senior" name="discount_type" value="senior">
                            <label for="senior" class="discount-label">
                                <i class="fas fa-user-clock"></i>
                                <span>Senior Citizen Discount</span>
                                <small>60 years and above</small>
                            </label>
                        </div>

                        <div class="discount-type-selector">
                            <input type="radio" id="pwd" name="discount_type" value="pwd">
                            <label for="pwd" class="discount-label">
                                <i class="fas fa-wheelchair"></i>
                                <span>PWD Discount</span>
                                <small>Valid PWD ID required</small>
                            </label>
                        </div>
                    </div>

                    <div class="file-upload-section" id="file-upload-section" style="display: none;">
                        <div class="file-upload-container">
                            <input type="file" id="discount_id" name="discount_id" accept="image/*" style="display: none;">
                            <label for="discount_id" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload your ID or drag and drop</span>
                                <small>JPG, PNG, GIF (Max 5MB)</small>
                            </label>
                            <div class="upload-preview" id="upload-preview" style="display: none;"></div>
                        </div>

                        <button type="button" id="verify-discount" class="verify-btn" disabled>
                            <i class="fas fa-search"></i> Verify Discount
                        </button>

                        <div class="verification-status" id="verification-status"></div>
                    </div>
                </div>

                <div class="payment-summary">
                    <h2>Order Summary</h2>
                    <div class="summary-box">
                        <div class="summary-row">
                            <span>Plan:</span>
                            <span><?php echo htmlspecialchars($_SESSION['selected_plan']); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Original Amount:</span>
                            <span class="price">₱<?php echo number_format($_SESSION['plan_price'], 2); ?></span>
                        </div>
                        <div class="summary-row discount-row" id="discount-row" style="display: none;">
                            <span>Discount (10%):</span>
                            <span class="discount-amount">-₱<span id="discount-amount">0.00</span></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span class="price" id="final-total">₱<?php echo number_format($_SESSION['plan_price'], 2); ?></span>
                        </div>
                    </div>
                </div><!-- Payment Options Container -->
                <div class="payment-container">
                    <h3>Checkout</h3>
                    <form method="POST" action="process_payment.php" id="payment-form">
                        <input type="hidden" name="applied_discount" id="applied_discount" value="">
                        <input type="hidden" name="discount_type" id="discount_type_hidden" value="">
                        <input type="hidden" name="discount_amount" id="discount_amount_hidden" value="0">
                        <input type="hidden" name="final_amount" id="final_amount_hidden" value="<?php echo $_SESSION['plan_price']; ?>">

                        <div class="payment-info">
                            <p>You will be redirected to a secure checkout page where you can choose from multiple payment options including:</p>
                        </div><?php if (isset($_SESSION['error_message'])): ?>
                            <div id="payment-errors" class="payment-error">
                                <?php
                                    // Clean up the error message
                                    $error_msg = str_replace(': active', '', $_SESSION['error_message']);
                                    echo htmlspecialchars($error_msg);
                                    unset($_SESSION['error_message']);
                                ?>
                            </div>
                        <?php endif; ?> <button type="submit" id="payment-button" class="btn">Proceed to Checkout ₱<?php echo number_format($_SESSION['plan_price'], 2); ?></button>
                    </form>

                    <div class="secure-payment">
                        <i class="fas fa-lock"></i> Secure payments powered by PayMongo
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div> <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h5>Fitness Academy Membership Terms and Conditions</h5>
                    <p><strong>1. Membership</strong></p>
                    <p>1.1 By joining Fitness Academy, members agree to abide by the rules, regulations, and policies outlined in this agreement.</p>
                    <p>1.2 Membership is non-transferable and can only be used by the registered member.</p>
                    <p>1.3 Members must present their membership ID when accessing the facility.</p>

                    <p><strong>2. Payment and Fees</strong></p>
                    <p>2.1 Members agree to pay all membership fees in accordance with the plan selected.</p>
                    <p>2.2 All payments are non-refundable unless otherwise stated in specific promotional terms.</p>
                    <p>2.3 For monthly plans, payments will be automatically processed on the due date.</p>

                    <p><strong>3. Cancellation Policy</strong></p>
                    <p>3.1 Members wishing to cancel their membership must provide written notice at least 30 days before the next billing cycle.</p>
                    <p>3.2 Fitness Academy reserves the right to cancel memberships for violation of policies or inappropriate behavior.</p>

                    <p><strong>4. Health and Safety</strong></p>
                    <p>4.1 Members acknowledge that they are in good physical condition and have no medical reason why they should not participate in fitness activities.</p>
                    <p>4.2 Fitness Academy is not responsible for any injury, damage, or loss incurred while using the facilities.</p>
                    <p>4.3 Members must follow all safety guidelines and proper use of equipment instructions.</p>

                    <p><strong>5. Facility Usage</strong></p>
                    <p>5.1 Operating hours are subject to change without notice.</p>
                    <p>5.2 Fitness Academy reserves the right to close the facility for maintenance, holidays, or special events.</p>
                    <p>5.3 Members agree to treat the facilities with respect and to clean equipment after use.</p>

                    <p><strong>6. Personal Data</strong></p>
                    <p>6.1 Fitness Academy will collect and store member personal information in accordance with data protection laws.</p>
                    <p>6.2 Members may opt in to receive marketing communications but can withdraw consent at any time.</p>

                    <p><strong>7. Amendments</strong></p>
                    <p>7.1 Fitness Academy reserves the right to amend these terms and conditions at any time.</p>
                    <p>7.2 Changes to terms will be communicated through email and/or posted notices within the facility.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="document.getElementById('terms').checked = true;">I Agree</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password validation feedback
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordFeedback = document.createElement('div');
            passwordFeedback.className = 'password-requirements';
            passwordFeedback.innerHTML = `
        <div class="mt-2">
            <div class="req length">8+ characters <i class="fas fa-times text-danger"></i></div>
            <div class="req uppercase">Uppercase letter <i class="fas fa-times text-danger"></i></div>
            <div class="req lowercase">Lowercase letter <i class="fas fa-times text-danger"></i></div>
            <div class="req number">Number <i class="fas fa-times text-danger"></i></div>
            <div class="req symbol">Symbol <i class="fas fa-times text-danger"></i></div>
            <div class="req match">Passwords match <i class="fas fa-times text-danger"></i></div>
        </div>
          <!-- Compact password strength indicator -->
        <div class="password-strength-meter mt-2">
            <div class="strength-meter">
                <div id="strength-bar"></div>
            </div>
        </div>
    `;

            // Insert after the password field's current helper text
            const passwordField = passwordInput.closest('.form-group');
            const existingHelperText = passwordField.querySelector('.form-text');
            if (existingHelperText) {
                existingHelperText.insertAdjacentElement('afterend', passwordFeedback);
            } else {
                passwordField.appendChild(passwordFeedback);
            }

            // Style the requirements
            const style = document.createElement('style');
            style.textContent = `
        .password-requirements {
            font-size: 0.8rem;
            padding: 10px;
            margin-top: 5px;
        }
        .password-requirements .req {
            margin-bottom: 2px;
        }
        .text-danger { color: #dc3545; }
        .text-success { color: #28a745; }
        .password-requirements .fa-check { color: #28a745; }
          /* Password strength meter styles - more subtle version */
        .password-strength-meter {
            margin-top: 6px;
        }
        .strength-meter {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }
        #strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }
        .strength-weak { width: 25%; background-color: #dc3545; }
        .strength-fair { width: 50%; background-color: #ffc107; }
        .strength-good { width: 75%; background-color: #17a2b8; }
        .strength-strong { width: 100%; background-color: #28a745; }
        .mt-2 { margin-top: 0.5rem; }
    `;
            document.head.appendChild(style);

            // Function to validate password and update UI
            function validatePassword() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                // Update requirements display
                const lengthReq = passwordFeedback.querySelector('.req.length i');
                const upperReq = passwordFeedback.querySelector('.req.uppercase i');
                const lowerReq = passwordFeedback.querySelector('.req.lowercase i');
                const numberReq = passwordFeedback.querySelector('.req.number i');
                const symbolReq = passwordFeedback.querySelector('.req.symbol i');
                const matchReq = passwordFeedback.querySelector('.req.match i');

                // Check each requirement
                if (password.length >= 8) {
                    lengthReq.className = 'fas fa-check text-success';
                } else {
                    lengthReq.className = 'fas fa-times text-danger';
                }

                if (/[A-Z]/.test(password)) {
                    upperReq.className = 'fas fa-check text-success';
                } else {
                    upperReq.className = 'fas fa-times text-danger';
                }

                if (/[a-z]/.test(password)) {
                    lowerReq.className = 'fas fa-check text-success';
                } else {
                    lowerReq.className = 'fas fa-times text-danger';
                }

                if (/[0-9]/.test(password)) {
                    numberReq.className = 'fas fa-check text-success';
                } else {
                    numberReq.className = 'fas fa-times text-danger';
                }

                if (/[\W_]/.test(password)) {
                    symbolReq.className = 'fas fa-check text-success';
                } else {
                    symbolReq.className = 'fas fa-times text-danger';
                }

                if (password && confirmPassword && password === confirmPassword) {
                    matchReq.className = 'fas fa-check text-success';
                } else {
                    matchReq.className = 'fas fa-times text-danger';
                }

                // Update password strength meter
                updatePasswordStrength(password);
            }
            // Function to update password strength meter - simplified
            function updatePasswordStrength(password) {
                const strengthBar = document.getElementById('strength-bar');

                // Remove existing strength classes
                strengthBar.className = '';

                // Calculate password strength
                let strength = 0;
                if (password.length > 0) {
                    // Basic checks
                    if (password.length >= 8) strength += 1;
                    if (/[A-Z]/.test(password)) strength += 1;
                    if (/[a-z]/.test(password)) strength += 1;
                    if (/[0-9]/.test(password)) strength += 1;
                    if (/[\W_]/.test(password)) strength += 1;

                    // Additional complexity checks
                    if (password.length >= 12) strength += 1;
                    if (/[0-9].*[0-9]/.test(password)) strength += 1; // At least 2 numbers
                    if (/[\W_].*[\W_]/.test(password)) strength += 1; // At least 2 special chars
                }

                // Set strength level
                let strengthClass;
                if (password.length === 0) {
                    strengthClass = '';
                } else if (strength < 3) {
                    strengthClass = 'strength-weak';
                } else if (strength < 5) {
                    strengthClass = 'strength-fair';
                } else if (strength < 7) {
                    strengthClass = 'strength-good';
                } else {
                    strengthClass = 'strength-strong';
                }

                // Update UI - only the bar, no text
                strengthBar.className = strengthClass;
            }

            // Add event listeners
            passwordInput.addEventListener('input', validatePassword);
            confirmPasswordInput.addEventListener('input', validatePassword);

            // Initial validation when the page loads (in case of form errors with pre-filled values)
            validatePassword();
        });

        // Function to toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const checkbox = document.getElementById('show_password');

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                confirmPasswordInput.type = "text";
            } else {
                passwordInput.type = "password";
                confirmPasswordInput.type = "password";
            }
            // Update feedback text
            const labelText = checkbox.checked ? "Hide password" : "Show password";
            document.querySelector('label[for="show_password"]').textContent = labelText;
        }

        // Discount System JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const discountTypeRadios = document.querySelectorAll('input[name="discount_type"]');
            const fileUploadSection = document.getElementById('file-upload-section');
            const discountIdInput = document.getElementById('discount_id');
            const verifyButton = document.getElementById('verify-discount');
            const verificationStatus = document.getElementById('verification-status');
            const uploadPreview = document.getElementById('upload-preview');
            const discountRow = document.getElementById('discount-row');
            const discountAmountSpan = document.getElementById('discount-amount');
            const finalTotalSpan = document.getElementById('final-total');
            const paymentButton = document.getElementById('payment-button');

            // Hidden form fields for discount data
            const appliedDiscountInput = document.getElementById('applied_discount');
            const discountTypeHidden = document.getElementById('discount_type_hidden');
            const discountAmountHidden = document.getElementById('discount_amount_hidden');
            const finalAmountHidden = document.getElementById('final_amount_hidden');

            // Original plan price from PHP session
            const originalPrice = <?php echo $_SESSION['plan_price']; ?>;
            let currentDiscountApplied = false;
            let verifiedDiscountType = '';

            // Handle discount type selection
            discountTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        fileUploadSection.style.display = 'block';
                        resetVerification();
                    }
                });
            });

            // Handle file upload
            discountIdInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Validate file type and size
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!allowedTypes.includes(file.type)) {
                        showVerificationStatus('Invalid file type. Please upload JPG, PNG, or GIF.', 'error');
                        this.value = '';
                        return;
                    }

                    if (file.size > 5 * 1024 * 1024) {
                        showVerificationStatus('File too large. Maximum size is 5MB.', 'error');
                        this.value = '';
                        return;
                    }

                    // Show file preview
                    showFilePreview(file);
                    verifyButton.disabled = false;
                } else {
                    verifyButton.disabled = true;
                    uploadPreview.style.display = 'none';
                }
            });

            // Handle verification
            verifyButton.addEventListener('click', function() {
                const selectedDiscountType = document.querySelector('input[name="discount_type"]:checked');
                const file = discountIdInput.files[0];

                if (!selectedDiscountType || !file) {
                    showVerificationStatus('Please select a discount type and upload your ID.', 'error');
                    return;
                }

                verifyDiscount(selectedDiscountType.value, file);
            });

            function showFilePreview(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    uploadPreview.innerHTML = `
                <div class="file-preview">
                    <img src="${e.target.result}" alt="Uploaded ID" style="max-width: 200px; max-height: 150px; border-radius: 4px;">
                    <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">${file.name}</p>
                </div>
            `;
                    uploadPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }

            function verifyDiscount(discountType, file) {
                showVerificationStatus('Verifying your ID...', 'loading');
                verifyButton.disabled = true;

                const formData = new FormData();
                formData.append('action', 'verify_discount');
                formData.append('discount_type', discountType);
                formData.append('discount_id', file);

                fetch('ocr_service.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.isValid) {
                            // Verification successful
                            showVerificationStatus(
                                `✓ ${capitalizeFirst(discountType)} ID verified! 10% discount applied (${data.confidence}% confidence)`,
                                'success'
                            );
                            applyDiscount(discountType);
                            verifiedDiscountType = discountType;
                        } else if (data.success && !data.isValid) {
                            // Verification failed
                            showVerificationStatus(
                                `✗ Could not verify ${discountType} ID. Please ensure your ID is clear and try again.`,
                                'error'
                            );
                            removeDiscount();
                        } else {
                            // API error
                            showVerificationStatus(
                                `Error: ${data.error || 'Verification failed. Please try again.'}`,
                                'error'
                            );
                            removeDiscount();
                        }
                    })
                    .catch(error => {
                        showVerificationStatus('Network error. Please try again.', 'error');
                        removeDiscount();
                    })
                    .finally(() => {
                        verifyButton.disabled = false;
                    });
            }

            function applyDiscount(discountType) {
                const discountPercentage = 10;
                const discountAmount = originalPrice * (discountPercentage / 100);
                const finalAmount = originalPrice - discountAmount;

                // Update UI
                discountAmountSpan.textContent = discountAmount.toFixed(2);
                finalTotalSpan.textContent = '₱' + finalAmount.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                discountRow.style.display = 'flex';

                // Update payment button
                paymentButton.innerHTML = `Proceed to Checkout ₱${finalAmount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;

                // Update hidden form fields
                appliedDiscountInput.value = 'true';
                discountTypeHidden.value = discountType;
                discountAmountHidden.value = discountAmount.toFixed(2);
                finalAmountHidden.value = finalAmount.toFixed(2);

                currentDiscountApplied = true;
            }

            function removeDiscount() {
                // Reset UI
                discountRow.style.display = 'none';
                finalTotalSpan.textContent = '₱' + originalPrice.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // Reset payment button
                paymentButton.innerHTML = `Proceed to Checkout ₱${originalPrice.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;

                // Reset hidden form fields
                appliedDiscountInput.value = '';
                discountTypeHidden.value = '';
                discountAmountHidden.value = '0';
                finalAmountHidden.value = originalPrice.toFixed(2);

                currentDiscountApplied = false;
                verifiedDiscountType = '';
            }

            function showVerificationStatus(message, type) {
                verificationStatus.textContent = message;
                verificationStatus.className = `verification-status ${type}`;
                verificationStatus.style.display = 'block';
            }

            function resetVerification() {
                verificationStatus.style.display = 'none';
                uploadPreview.style.display = 'none';
                discountIdInput.value = '';
                verifyButton.disabled = true;
                removeDiscount();
            }

            function capitalizeFirst(str) {
                return str.charAt(0).toUpperCase() + str.slice(1);
            }

            // Reset discount when discount type changes
            discountTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (currentDiscountApplied && this.value !== verifiedDiscountType) {
                        removeDiscount();
                        resetVerification();
                    }
                });
            });
        });
    </script>
</body>

</html>