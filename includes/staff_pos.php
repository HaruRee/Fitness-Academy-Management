<?php
session_start();
require_once '../config/database.php';
require_once '../config/api_config.php'; // Load API configuration
require_once 'activity_tracker.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header('Location: login.php');
    exit;
}

// Track page view activity
if (isset($_SESSION['user_id'])) {
    trackPageView($_SESSION['user_id'], 'POS System');
    
    // Run automatic deactivation check (throttled to once per hour)
    if (shouldRunAutomaticDeactivation($conn)) {
        runAutomaticDeactivation($conn);
    }
}

// Initialize variables
$membershipPlans = [];
$error = '';
$success = '';

// Get all active membership plans with new plan type fields
try {    $stmt = $conn->prepare("
        SELECT id, name, price, description, features, 
               plan_type, session_count, duration_months
        FROM membershipplans 
        WHERE is_active = 1 
        ORDER BY sort_order ASC, price ASC
    ");
    $stmt->execute();
    $membershipPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching membership plans: " . $e->getMessage();
}

// Process cash payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        $conn->beginTransaction();
          // Validate input
        $paymentType = $_POST['payment_type'] ?? 'membership';
        $requiredFields = ['customer_name', 'customer_email', 'customer_phone', 'cash_amount'];
        
        if ($paymentType === 'membership') {
            $requiredFields[] = 'membership_plan_id';
        } elseif ($paymentType === 'custom') {
            $requiredFields[] = 'custom_description';
            $requiredFields[] = 'custom_amount';
        }
        
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled.");
            }
        }
          $customerName = trim($_POST['customer_name']);
        $customerEmail = trim($_POST['customer_email']);
        $customerPhone = trim($_POST['customer_phone']);
        $cashAmount = floatval($_POST['cash_amount']);
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // Initialize variables for both payment types
        $planId = null;
        $plan = null;
        $planPrice = 0;
        $customDescription = '';
        $membershipStartDate = null;
        $membershipEndDate = null;
        $sessionsRemaining = null;
        
        if ($paymentType === 'membership') {
            $planId = intval($_POST['membership_plan_id']);
            
            // Get membership plan details
            $planStmt = $conn->prepare("SELECT * FROM membershipplans WHERE id = ? AND is_active = 1");
            $planStmt->execute([$planId]);
            $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                throw new Exception("Invalid membership plan selected.");
            }
            
            $planPrice = floatval($plan['price']);
            
            // Calculate membership dates and sessions based on plan type
            $membershipStartDate = date('Y-m-d');
            
            if ($plan['plan_type'] === 'monthly') {
                // Monthly plan - calculate end date
                $durationMonths = intval($plan['duration_months']);
                $membershipEndDate = date('Y-m-d', strtotime("+{$durationMonths} months"));
            } elseif ($plan['plan_type'] === 'session') {
                // Session-based plan - set remaining sessions
                $sessionsRemaining = intval($plan['session_count']);
            }
        } elseif ($paymentType === 'custom') {
            $customDescription = trim($_POST['custom_description']);
            $planPrice = floatval($_POST['custom_amount']);
            
            if ($planPrice <= 0) {
                throw new Exception("Custom amount must be greater than 0.");
            }
        }
        
        // Check if user with this email already exists
        $existingUserStmt = $conn->prepare("SELECT UserID, membership_plan, plan_id FROM users WHERE Email = ?");
        $existingUserStmt->execute([$customerEmail]);
        $existingUser = $existingUserStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            // Check if existing user has an active plan
            if ($existingUser['plan_id'] || $existingUser['membership_plan']) {
                throw new Exception("A user with email {$customerEmail} already has an active membership plan. Cannot create duplicate membership.");
            }
            throw new Exception("A user with email {$customerEmail} already exists in the system. Please use a different email or contact support.");
        }

        // Validate cash amount
        if ($cashAmount < $planPrice) {
            throw new Exception("Insufficient cash amount. Required: ₱" . number_format($planPrice, 2));
        }
        
        // Calculate change
        $changeAmount = $cashAmount - $planPrice;
        
        // Generate transaction ID
        $transactionId = 'POS' . date('Ymd') . rand(100000, 999999);
        
        // Generate temporary password
        $tempPassword = generateStrongPassword();
        
        // Create a unique username based on email
        $baseUsername = preg_replace('/[^a-z0-9]/i', '', explode('@', $customerEmail)[0]);
        $username = $baseUsername;
        $counter = 1;
          while (true) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE Username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() == 0) break;
            $username = $baseUsername . $counter++;
        }
          // Create payment details JSON
        $paymentDetails = json_encode([
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_address' => $customerAddress,
            'payment_type' => $paymentType,
            'membership_plan' => $paymentType === 'membership' ? $plan['name'] : null,
            'custom_description' => $paymentType === 'custom' ? $customDescription : null,
            'plan_id' => $planId,
            'amount' => $planPrice,
            'cash_received' => $cashAmount,
            'change_given' => $changeAmount,
            'processed_by' => $_SESSION['user_id'],
            'notes' => $notes,
            'transaction_type' => $paymentType === 'membership' ? 'membership_pos' : 'custom_pos',
            'username_created' => $username
        ]);        if ($paymentType === 'membership') {
            // Create the member account with membership plan
            $memberSql = "
                INSERT INTO users (
                    Username, PasswordHash, Email, First_Name, Last_Name,
                    Phone, Address, emergency_contact, Role, RegistrationDate,
                    membership_plan, plan_id, membership_price,
                    membership_start_date, membership_end_date, current_sessions_remaining,
                    IsActive, email_confirmed, account_status, last_activity_date,
                    DateOfBirth
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, 'Member', NOW(),
                    ?, ?, ?,
                    ?, ?, ?,
                    1, 1, 'active', NOW(),
                    NULL
                )
            ";
            
            // Execute member creation
            $memberStmt = $conn->prepare($memberSql);
            $memberStmt->execute([
                $username,
                password_hash($tempPassword, PASSWORD_DEFAULT),
                $customerEmail,
                explode(' ', $customerName)[0], // First name
                implode(' ', array_slice(explode(' ', $customerName), 1)), // Last name
                $customerPhone,
                $customerAddress,
                $customerPhone, // Using phone as emergency contact
                $plan['name'], // membership_plan
                $planId, // plan_id
                $planPrice, // membership_price
                $membershipStartDate, // membership_start_date
                $membershipEndDate, // membership_end_date
                $sessionsRemaining // current_sessions_remaining
            ]);
        } else {
            // For custom payments, create a basic user account without membership
            $memberSql = "
                INSERT INTO users (
                    Username, PasswordHash, Email, First_Name, Last_Name,
                    Phone, Address, emergency_contact, Role, RegistrationDate,
                    IsActive, email_confirmed, account_status, last_activity_date,
                    DateOfBirth
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, 'Member', NOW(),
                    1, 1, 'active', NOW(),
                    NULL
                )
            ";
            
            // Execute member creation
            $memberStmt = $conn->prepare($memberSql);
            $memberStmt->execute([
                $username,
                password_hash($tempPassword, PASSWORD_DEFAULT),
                $customerEmail,
                explode(' ', $customerName)[0], // First name
                implode(' ', array_slice(explode(' ', $customerName), 1)), // Last name
                $customerPhone,
                $customerAddress,                $customerPhone // Using phone as emergency contact
            ]);
        }
          $memberId = $conn->lastInsertId();
        
        // Insert payment record
        $paymentStmt = $conn->prepare("
            INSERT INTO payments (
                user_id, amount, payment_date, status, payment_method, 
                transaction_id, payment_details
            ) VALUES (
                ?, ?, NOW(), 'completed', 'cash', ?, ?
            )
        ");
        
        $paymentStmt->execute([
            $memberId,
            $planPrice,
            $transactionId,
            $paymentDetails
        ]);
        
        // Log in audit trail
        $auditSql = "INSERT INTO audit_trail (username, action, timestamp) VALUES (?, ?, NOW())";
        $auditStmt = $conn->prepare($auditSql);
        
        if ($paymentType === 'membership') {
            $action = "POS Sale: {$plan['name']} to {$customerName} - Amount: ₱{$planPrice} - Transaction: {$transactionId}";
        } else {
            $action = "POS Custom Sale: {$customDescription} to {$customerName} - Amount: ₱{$planPrice} - Transaction: {$transactionId}";
        }
        
        $auditStmt->execute([$_SESSION['user_name'] ?? 'Staff', $action]);
        
        $conn->commit();
        
        // Send email receipt with login credentials
        try {
            if ($paymentType === 'membership') {
                $emailSent = sendReceiptEmail($customerEmail, $customerName, $plan, $planPrice, $transactionId, $cashAmount, $changeAmount, $customerEmail, $tempPassword);
            } else {
                $emailSent = sendCustomReceiptEmail($customerEmail, $customerName, $customDescription, $planPrice, $transactionId, $cashAmount, $changeAmount, $customerEmail, $tempPassword);
            }
            
            // Set success message with transaction details
            $success = "Payment processed successfully! Transaction ID: {$transactionId}";
            if ($changeAmount > 0) {
                $success .= " | Change: ₱" . number_format($changeAmount, 2);
            }
            if ($emailSent) {
                $success .= " | Receipt and account details sent to email";
            } else {
                $success .= " | Email failed to send";
            }
        } catch (Exception $e) {
            // Set success message even if email fails
            $success = "Payment processed successfully! Transaction ID: {$transactionId}";
            if ($changeAmount > 0) {
                $success .= " | Change: ₱" . number_format($changeAmount, 2);
            }
            $success .= " | Email failed to send";
            error_log("Email receipt error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error processing payment: " . $e->getMessage();
    }
}

// Get recent transactions for today
$recentTransactions = [];
try {
    $stmt = $conn->prepare("
        SELECT p.transaction_id, p.payment_details, p.amount, 
               p.payment_method, p.payment_date, p.status
        FROM payments p
        WHERE DATE(p.payment_date) = CURDATE() 
        AND p.payment_method = 'cash'
        AND p.status = 'completed'
        AND JSON_EXTRACT(p.payment_details, '$.transaction_type') = 'membership_pos'
        ORDER BY p.payment_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse the JSON payment details for display
    foreach ($transactions as $transaction) {
        $details = json_decode($transaction['payment_details'], true);
        $recentTransactions[] = [
            'receipt_number' => $transaction['transaction_id'],
            'customer_name' => $details['customer_name'] ?? 'N/A',
            'membership_plan' => $details['membership_plan'] ?? 'N/A',
            'amount' => $transaction['amount'],
            'payment_method' => $transaction['payment_method'],
            'payment_date' => $transaction['payment_date'],
            'change_given' => $details['change_given'] ?? 0
        ];
    }
} catch (Exception $e) {
    // Log error but don't show to user
    error_log("Error fetching recent transactions: " . $e->getMessage());
}

// Calculate today's cash sales
$todaySales = 0;
$todayTransactions = 0;
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM payments 
        WHERE DATE(payment_date) = CURDATE() 
        AND payment_method = 'cash'
        AND status = 'completed'
        AND JSON_EXTRACT(payment_details, '$.transaction_type') = 'membership_pos'
    ");
    $stmt->execute();
    $salesData = $stmt->fetch(PDO::FETCH_ASSOC);
    $todaySales = floatval($salesData['total']);
    $todayTransactions = intval($salesData['count']);
} catch (Exception $e) {
    error_log("Error calculating today's sales: " . $e->getMessage());
}

// Function to get POS system user ID
function getPosUserId($conn) {
    static $posUserId = null;
    if ($posUserId === null) {
        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = 'pos_system'");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $posUserId = $user ? $user['UserID'] : 1; // Fallback to user ID 1 if not found
    }
    return $posUserId;
}

// Function to generate a strong random password
function generateStrongPassword($length = 10) {
    // Use only letters and numbers for cleaner, more user-friendly passwords
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    
    // Ensure at least one lowercase, one uppercase, and one number
    $password .= $chars[rand(0, 25)]; // lowercase
    $password .= $chars[rand(26, 51)]; // uppercase
    $password .= $chars[rand(52, 61)]; // number
    
    // Fill the rest with random alphanumeric chars
    for($i = strlen($password); $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars)-1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}

// Function to send receipt email with account details
function sendReceiptEmail($email, $customerName, $plan, $amount, $transactionId, $cashReceived, $changeGiven, $username = null, $password = null) {
    require_once 'email_templates.php';
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME . ' Support');

        // Content
        $mail->isHTML(true);
        
        if ($username && $password) {
            // Welcome email with credentials
            $mail->Subject = 'Welcome to Fitness Academy - Your Account is Ready!';
            
            $planDetails = [
                'name' => $plan['name'],
                'duration' => $plan['duration'] ?? 'N/A'
            ];
            
            $mail->Body = EmailTemplates::welcomeWithCredentials($customerName, $email, $password, $planDetails);
            $mail->AltBody = EmailTemplates::getPlainTextVersion(
                "Welcome to Fitness Academy! Your account details: Email: {$email}, Temporary Password: {$password}. Please log in and change your password immediately.",
                'Welcome to Fitness Academy'
            );
        }
        
        // Send additional receipt email
        $transactionDetails = [
            'id' => $transactionId,
            'plan' => $plan['name'],
            'amount' => $amount,
            'method' => 'Cash',
            'cash_received' => $cashReceived,
            'change' => $changeGiven
        ];
        
        $receiptBody = EmailTemplates::paymentReceipt($customerName, $transactionDetails);
        $receiptAltBody = EmailTemplates::getPlainTextVersion(
            "Payment Receipt - Transaction ID: {$transactionId}, Amount: ₱" . number_format($amount, 2) . ", Payment Method: Cash",
            'Payment Receipt'
        );
        
        // Send the main email (welcome or receipt)
        $mail->send();
        
        // If it was a welcome email, send a separate receipt email
        if ($username && $password) {
            $mail->clearAddresses();
            $mail->addAddress($email);
            $mail->Subject = 'Payment Receipt - Fitness Academy';
            $mail->Body = $receiptBody;
            $mail->AltBody = $receiptAltBody;
            $mail->send();
        }
          return true;
    } catch (Exception $e) {
        error_log("Email receipt error: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to send custom receipt email with account details
function sendCustomReceiptEmail($email, $customerName, $description, $amount, $transactionId, $cashReceived, $changeGiven, $username = null, $password = null) {
    require_once 'email_templates.php';
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME . ' Support');

        // Content
        $mail->isHTML(true);
        
        if ($username && $password) {
            // Welcome email with credentials for custom payment
            $mail->Subject = 'Welcome to Fitness Academy - Your Account is Ready!';
            
            $customDetails = [
                'description' => $description,
                'amount' => $amount
            ];
            
            $mail->Body = EmailTemplates::welcomeWithCredentials($customerName, $email, $password, $customDetails);
            $mail->AltBody = EmailTemplates::getPlainTextVersion(
                "Welcome to Fitness Academy! Your account details: Email: {$email}, Temporary Password: {$password}. Please log in and change your password immediately.",
                'Welcome to Fitness Academy'
            );
        }
        
        // Send receipt email for custom payment
        $transactionDetails = [
            'id' => $transactionId,
            'service' => $description,
            'amount' => $amount,
            'method' => 'Cash',
            'cash_received' => $cashReceived,
            'change' => $changeGiven
        ];
        
        $receiptBody = EmailTemplates::customPaymentReceipt($customerName, $transactionDetails);
        $receiptAltBody = EmailTemplates::getPlainTextVersion(
            "Payment Receipt - Transaction ID: {$transactionId}, Service: {$description}, Amount: ₱" . number_format($amount, 2) . ", Payment Method: Cash",
            'Payment Receipt'
        );
        
        // Send the main email (welcome or receipt)
        $mail->send();
        
        // If it was a welcome email, send a separate receipt email
        if ($username && $password) {
            $mail->clearAddresses();
            $mail->addAddress($email);
            $mail->Subject = 'Payment Receipt - Fitness Academy';
            $mail->Body = $receiptBody;
            $mail->AltBody = $receiptAltBody;
            $mail->send();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Custom email receipt error: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/auto-logout.js" defer></script>
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #ff6b6b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-color: #f3f4f6;
            --dark-color: #111827;
            --gray-color: #6b7280;
            --sidebar-width: 280px;
            --header-height: 72px;
            --border-color: #e5e7eb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: var(--dark-color);
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .sidebar-menu-header {
            padding: 0 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.75rem;
            margin-top: 1.25rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--secondary-color);
        }

        .sidebar a i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .user-profile {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            margin-top: auto;
        }

        .user-profile img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            margin-right: 0.75rem;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: white;
        }

        .user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
        }        /* POS Layout */
        .pos-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 1rem;
            height: calc(100vh - 130px);
        }

        .pos-main {
            display: flex;
            flex-direction: column;
        }

        .pos-sidebar {
            display: flex;
            flex-direction: column;
        }        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .card-body {
            padding: 1rem;
        }        /* Today's Summary */
        .summary-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-weight: 500;
        }        /* Membership Plans Grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .plan-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .plan-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }

        .plan-card.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.02);
        }        .plan-card .plan-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .plan-card .plan-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }        .plan-card .plan-description {
            color: var(--gray-color);
            font-size: 0.8rem;
            line-height: 1.5;
        }

        .plan-card .plan-type {
            margin: 0.5rem 0;
        }

        .plan-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .plan-type-badge.monthly {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .plan-type-badge.session {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }/* Form Styles */
        .form-group {
            margin-bottom: 0.75rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.85rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-control.large {
            font-size: 1.25rem;
            padding: 1rem;
            font-weight: 600;
        }

        .required {
            color: var(--danger-color);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.125rem;
            font-weight: 600;
        }        .btn i {
            margin-right: 0.5rem;
        }

        /* Payment Type Toggle */
        .payment-type-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .btn-toggle {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--gray-color);
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-toggle:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-toggle.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-toggle i {
            margin-right: 0;
        }/* Calculation Display */
        .calculation-display {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #f3f4f6;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .calc-row:last-child {
            margin-bottom: 0;
            font-weight: 600;
            font-size: 1rem;
            color: var(--primary-color);
            border-top: 1px solid #e5e7eb;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
        }

        .calc-label {
            color: var(--gray-color);
        }

        .calc-value {
            font-weight: 600;
            color: var(--dark-color);
        }/* Recent Transactions */
        .transaction-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.15s ease;
        }

        .transaction-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .transaction-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .transaction-icon i {
            font-size: 0.875rem;
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-customer {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.1rem;
            font-size: 0.9rem;
        }

        .transaction-plan {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .transaction-amount {
            font-weight: 600;
            color: var(--primary-color);
            text-align: right;
            font-size: 0.9rem;
        }

        /* Alert styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .pos-layout {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .pos-sidebar {
                order: -1;
            }
            
            .summary-stats {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h2,
            .sidebar a span,
            .user-info {
                display: none;
            }

            .sidebar a i {
                margin-right: 0;
            }

            .sidebar a {
                justify-content: center;
            }

            .user-profile {
                justify-content: center;
            }

            .main-wrapper {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .main-wrapper {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .header {
                padding: 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .plans-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-stats {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar/Navbar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Fitness Academy</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="sidebar-menu-header">Dashboard</div>
            <a href="staff_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>            <div class="sidebar-menu-header">Attendance</div>
            <a href="../attendance/attendance.php">
                <i class="fas fa-qrcode"></i>
                <span>QR Scanner</span>
            </a>
            <a href="staff_attendance.php">
                <i class="fas fa-user-check"></i>
                <span>Attendance Reports</span>
            </a>
            <div class="sidebar-menu-header">Members</div>
            <a href="staff_dashboard.php#all_members">
                <i class="fas fa-users"></i>
                <span>All Members</span>
            </a>
            <div class="sidebar-menu-header">POS</div>
            <a href="staff_pos.php" class="active">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>
            <div class="sidebar-menu-header">Account</div>
            <a href="staff_settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
        <div class="user-profile">
            <img src="../assets/images/avatar.jpg" alt="Staff" onerror="this.src='../assets/images/fa_logo.png'">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></div>
                <div class="user-role">Staff</div>
            </div>
        </div>
    </aside>
    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="header">
            <h1 class="page-title">Point of Sale System</h1>
            <div style="color: var(--gray-color); font-weight: 500;">
                <i class="fas fa-calendar"></i> <?= date('M d, Y') ?>
            </div>
        </div>

        <div class="main-content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="pos-layout">
                <!-- Main POS Area -->
                <div class="pos-main">
                    <!-- Today's Summary -->
                    <div class="summary-stats">
                        <div class="stat-box">
                            <div class="stat-value">₱<?= number_format($todaySales, 2) ?></div>
                            <div class="stat-label">Today's Sales</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= $todayTransactions ?></div>
                            <div class="stat-label">Transactions</div>
                        </div>
                    </div>

                    <!-- Membership Plans -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Select Membership Plan</h3>
                        </div>
                        <div class="card-body">                            <div class="plans-grid">
                                <?php foreach ($membershipPlans as $plan): ?>
                                    <div class="plan-card" onclick="selectPlan(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['name']) ?>', <?= $plan['price'] ?>)">
                                        <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                                        <div class="plan-price">₱<?= number_format($plan['price'], 2) ?></div>
                                        <div class="plan-type">
                                            <?php if ($plan['plan_type'] === 'monthly'): ?>
                                                <span class="plan-type-badge monthly">
                                                    <i class="fas fa-calendar-alt"></i> <?= $plan['duration_months'] ?> Month<?= $plan['duration_months'] > 1 ? 's' : '' ?>
                                                </span>
                                            <?php elseif ($plan['plan_type'] === 'session'): ?>
                                                <span class="plan-type-badge session">
                                                    <i class="fas fa-dumbbell"></i> <?= $plan['session_count'] ?> Session<?= $plan['session_count'] > 1 ? 's' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="plan-description"><?= htmlspecialchars($plan['description']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- POS Sidebar -->
                <div class="pos-sidebar">
                    <!-- Payment Form -->
                    <div class="card" style="flex: 1;">
                        <div class="card-header">
                            <h3 class="card-title">Process Payment</h3>
                        </div>
                        <div class="card-body">                            <form method="POST" id="paymentForm">
                                <input type="hidden" id="membership_plan_id" name="membership_plan_id" value="">
                                <input type="hidden" id="payment_type" name="payment_type" value="membership">
                                
                                <!-- Payment Type Toggle -->
                                <div class="form-group">
                                    <label class="form-label">Payment Type</label>
                                    <div class="payment-type-toggle">
                                        <button type="button" class="btn btn-toggle active" id="membershipToggle" onclick="togglePaymentType('membership')">
                                            <i class="fas fa-id-card"></i> Membership Plan
                                        </button>
                                        <button type="button" class="btn btn-toggle" id="customToggle" onclick="togglePaymentType('custom')">
                                            <i class="fas fa-calculator"></i> Custom Amount
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Customer Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="customer_name" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email Address <span class="required">*</span></label>
                                    <input type="email" class="form-control" name="customer_email" required>
                                </div>                                <div class="form-group">
                                    <label class="form-label">Phone Number <span class="required">*</span></label>
                                    <input type="tel" class="form-control" name="customer_phone" required placeholder="e.g., 09123456789">
                                </div>                                <div class="form-group" style="margin-bottom: 0.5rem;">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="customer_address" placeholder="Customer address">
                                </div>

                                <!-- Custom Amount Section -->
                                <div id="customAmountSection" style="display: none;">
                                    <div class="form-group">
                                        <label class="form-label">Service/Product Description <span class="required">*</span></label>
                                        <input type="text" class="form-control" name="custom_description" id="customDescription" placeholder="e.g., Personal Training Session, Equipment Rental, etc.">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Amount <span class="required">*</span></label>
                                        <input type="number" class="form-control" name="custom_amount" id="customAmount" 
                                               step="0.01" min="0" placeholder="0.00" oninput="updateCustomAmount()">
                                    </div>
                                </div>

                                <div id="selectedPlanDisplay" style="display: none;">
                                    <div class="calculation-display">
                                        <div class="calc-row">
                                            <span class="calc-label">Plan:</span>
                                            <span class="calc-value" id="displayPlanName">-</span>
                                        </div>
                                        <div class="calc-row">
                                            <span class="calc-label">Amount:</span>
                                            <span class="calc-value" id="displayPlanPrice">₱0.00</span>
                                        </div>
                                    </div>
                                </div>                                <div class="form-group">
                                    <label class="form-label">Cash Amount <span class="required">*</span></label>
                                    <input type="number" class="form-control large" name="cash_amount" id="cashAmount" 
                                           step="0.01" min="0" placeholder="0.00" oninput="calculateChange()">
                                </div>

                                <div id="changeDisplay" class="calculation-display" style="display: none;">
                                    <div class="calc-row">
                                        <span class="calc-label">Total Amount:</span>
                                        <span class="calc-value" id="totalAmount">₱0.00</span>
                                    </div>
                                    <div class="calc-row">
                                        <span class="calc-label">Cash Received:</span>
                                        <span class="calc-value" id="cashReceived">₱0.00</span>
                                    </div>
                                    <div class="calc-row">
                                        <span class="calc-label">Change:</span>
                                        <span class="calc-value" id="changeAmount">₱0.00</span>
                                    </div>
                                </div>                                <div class="form-group">
                                    <label class="form-label">Notes</label>
                                    <input type="text" class="form-control" name="notes" placeholder="Additional notes...">
                                </div>

                                <button type="submit" name="process_payment" class="btn btn-success btn-large" style="width: 100%;" id="processBtn" disabled>
                                    <i class="fas fa-credit-card"></i> Process Cash Payment
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="card" style="margin-top: 1rem;">
                        <div class="card-header">
                            <h3 class="card-title">Today's Transactions</h3>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <div class="transaction-list">
                                <?php if (!empty($recentTransactions)): ?>
                                    <?php foreach ($recentTransactions as $transaction): ?>                                        <div class="transaction-item">
                                            <div class="transaction-info">
                                                <div class="transaction-icon">
                                                    <i class="fas fa-receipt"></i>
                                                </div>
                                                <div class="transaction-details">
                                                    <div class="transaction-customer"><?= htmlspecialchars($transaction['customer_name']) ?></div>
                                                    <div class="transaction-plan">
                                                        <?= htmlspecialchars($transaction['membership_plan']) ?> · 
                                                        <span style="font-size: 0.75rem;"><?= date('h:i A', strtotime($transaction['payment_date'])) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="transaction-amount">₱<?= number_format($transaction['amount'], 2) ?><br/>
                                                <small style="font-size: 0.7rem; color: var(--gray-color);">Change: ₱<?= number_format($transaction['change_given'], 2) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>                                    <div class="transaction-item">
                                        <div class="transaction-info">
                                            <div class="transaction-icon" style="background: rgba(156, 163, 175, 0.1); color: #6b7280;">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <div class="transaction-details">
                                                <div class="transaction-customer" style="color: var(--gray-color);">No transactions today</div>
                                                <div class="transaction-plan">Process your first payment to see it here</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedPlanId = null;
        let selectedPlanPrice = 0;

        function selectPlan(planId, planName, planPrice) {
            // Remove previous selection
            document.querySelectorAll('.plan-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Add selection to clicked card
            event.currentTarget.classList.add('selected');

            // Update form data
            selectedPlanId = planId;
            selectedPlanPrice = planPrice;
            
            document.getElementById('membership_plan_id').value = planId;
            document.getElementById('displayPlanName').textContent = planName;
            document.getElementById('displayPlanPrice').textContent = '₱' + planPrice.toLocaleString('en-US', {minimumFractionDigits: 2});
            
            // Show selected plan display
            document.getElementById('selectedPlanDisplay').style.display = 'block';
            
            // Calculate change if cash amount is entered
            calculateChange();
            
            // Enable process button if form is valid
            checkFormValidity();
        }        function calculateChange() {
            const paymentType = document.getElementById('payment_type').value;
            let totalAmount = 0;
            
            if (paymentType === 'membership' && selectedPlanPrice > 0) {
                totalAmount = selectedPlanPrice;
            } else if (paymentType === 'custom') {
                totalAmount = parseFloat(document.getElementById('customAmount').value) || 0;
            }
            
            if (totalAmount <= 0) return;
            
            const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
            const change = cashAmount - totalAmount;
            
            // Update display
            document.getElementById('totalAmount').textContent = '₱' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('cashReceived').textContent = '₱' + cashAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('changeAmount').textContent = '₱' + change.toLocaleString('en-US', {minimumFractionDigits: 2});
            
            // Show change display if cash amount is entered
            if (cashAmount > 0) {
                document.getElementById('changeDisplay').style.display = 'block';
            } else {
                document.getElementById('changeDisplay').style.display = 'none';
            }
            
            // Color code the change amount
            const changeEl = document.getElementById('changeAmount');
            if (change < 0) {
                changeEl.style.color = 'var(--danger-color)';
            } else {
                changeEl.style.color = 'var(--success-color)';
            }
            
            checkFormValidity();
        }function checkFormValidity() {
            const form = document.getElementById('paymentForm');
            const processBtn = document.getElementById('processBtn');
            const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
            const paymentType = document.getElementById('payment_type').value;
            
            let isValid = false;
            
            if (paymentType === 'membership') {
                isValid = selectedPlanId && 
                          form.customer_name.value.trim() && 
                          form.customer_email.value.trim() && 
                          form.customer_phone.value.trim() && 
                          cashAmount >= selectedPlanPrice;
            } else if (paymentType === 'custom') {
                const customAmount = parseFloat(document.getElementById('customAmount').value) || 0;
                isValid = form.customer_name.value.trim() && 
                          form.customer_email.value.trim() && 
                          form.customer_phone.value.trim() && 
                          document.getElementById('customDescription').value.trim() &&
                          customAmount > 0 &&
                          cashAmount >= customAmount;
            }
            
            processBtn.disabled = !isValid;
        }

        function togglePaymentType(type) {
            const membershipToggle = document.getElementById('membershipToggle');
            const customToggle = document.getElementById('customToggle');
            const customSection = document.getElementById('customAmountSection');
            const selectedPlanDisplay = document.getElementById('selectedPlanDisplay');
            const changeDisplay = document.getElementById('changeDisplay');
            const paymentTypeInput = document.getElementById('payment_type');
            
            // Reset form state
            document.getElementById('cashAmount').value = '';
            changeDisplay.style.display = 'none';
            
            if (type === 'custom') {
                // Switch to custom mode
                membershipToggle.classList.remove('active');
                customToggle.classList.add('active');
                customSection.style.display = 'block';
                selectedPlanDisplay.style.display = 'none';
                paymentTypeInput.value = 'custom';
                
                // Clear plan selection
                document.querySelectorAll('.plan-card').forEach(card => {
                    card.classList.remove('selected');
                });
                selectedPlanId = null;
                selectedPlanPrice = 0;
                document.getElementById('membership_plan_id').value = '';
                
            } else {
                // Switch to membership mode
                customToggle.classList.remove('active');
                membershipToggle.classList.add('active');
                customSection.style.display = 'none';
                paymentTypeInput.value = 'membership';
                
                // Clear custom fields
                document.getElementById('customDescription').value = '';
                document.getElementById('customAmount').value = '';
            }
            
            checkFormValidity();
        }

        function updateCustomAmount() {
            const customAmount = parseFloat(document.getElementById('customAmount').value) || 0;
            const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
            
            if (customAmount > 0) {
                const change = cashAmount - customAmount;
                
                // Update display
                document.getElementById('totalAmount').textContent = '₱' + customAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
                document.getElementById('cashReceived').textContent = '₱' + cashAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
                document.getElementById('changeAmount').textContent = '₱' + change.toLocaleString('en-US', {minimumFractionDigits: 2});
                
                // Show change display if cash amount is entered
                if (cashAmount > 0) {
                    document.getElementById('changeDisplay').style.display = 'block';
                } else {
                    document.getElementById('changeDisplay').style.display = 'none';
                }
                
                // Color code the change amount
                const changeEl = document.getElementById('changeAmount');
                if (change < 0) {
                    changeEl.style.color = 'var(--danger-color)';
                } else {
                    changeEl.style.color = 'var(--success-color)';
                }
            } else {
                document.getElementById('changeDisplay').style.display = 'none';
            }
            
            checkFormValidity();
        }        // Add event listeners to form fields
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('paymentForm');
            const inputs = form.querySelectorAll('input[required], #customDescription, #customAmount');
            
            inputs.forEach(input => {
                input.addEventListener('input', checkFormValidity);
            });
            
            // Add specific listeners for custom amount calculations
            document.getElementById('customAmount').addEventListener('input', updateCustomAmount);
            document.getElementById('cashAmount').addEventListener('input', calculateChange);
        });

        // Auto-focus first input
        document.querySelector('input[name="customer_name"]').focus();
          // Clear form after successful payment
        <?php if ($success): ?>
        setTimeout(function() {
            document.getElementById('paymentForm').reset();
            document.getElementById('selectedPlanDisplay').style.display = 'none';
            document.getElementById('changeDisplay').style.display = 'none';
            document.getElementById('customAmountSection').style.display = 'none';
            document.querySelectorAll('.plan-card').forEach(card => {
                card.classList.remove('selected');
            });
            // Reset to membership mode
            document.getElementById('membershipToggle').classList.add('active');
            document.getElementById('customToggle').classList.remove('active');
            document.getElementById('payment_type').value = 'membership';
            selectedPlanId = null;
            selectedPlanPrice = 0;
            document.getElementById('processBtn').disabled = true;
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
