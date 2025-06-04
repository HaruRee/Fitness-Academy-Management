<?php
session_start();
require '../config/database.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member' || !isset($_SESSION['paymongo_checkout_id'])) {
    header('Location: member_class.php');    exit;
}

// Load API configuration
require_once __DIR__ . '/../config/api_config.php';

try {
    // Get checkout session ID from session
    $checkoutSessionId = $_SESSION['paymongo_checkout_id'];

    // Initialize GuzzleHTTP client
    $client = new \GuzzleHttp\Client();

    // Verify the payment status
    $sessionResponse = $client->request('GET', "https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}", [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ]
    ]);

    $sessionData = json_decode($sessionResponse->getBody(), true);
    $paymentStatus = $sessionData['data']['attributes']['payment_intent']['status'] ?? $sessionData['data']['attributes']['status'] ?? null;

    // Check if payment is successful - include 'active' status for class enrollments
    $validStatuses = ['succeeded', 'paid', 'payment_completed', 'completed', 'active'];
    if (!in_array($paymentStatus, $validStatuses)) {
        throw new Exception('Payment verification failed. Status: ' . $paymentStatus);
    }

    // Check payment type from URL parameter
    $paymentType = $_GET['type'] ?? 'class';
    
    if ($paymentType === 'subscription') {
        // Handle subscription payment
        if (!isset($_SESSION['subscription_payment'])) {
            throw new Exception('Subscription payment data not found in session');
        }
        
        $subscriptionData = $_SESSION['subscription_payment'];
        $userId = $_SESSION['user_id'];
        $coachId = $subscriptionData['coach_id'];
        $price = $subscriptionData['price'];
        
        // Check if user is already subscribed to this coach
        $stmt = $conn->prepare("
            SELECT id FROM coach_subscriptions 
            WHERE member_id = ? AND coach_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId, $coachId]);
        if ($stmt->fetch()) {
            throw new Exception('You are already subscribed to this coach');
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Insert subscription record
        $stmt = $conn->prepare("
            INSERT INTO coach_subscriptions (
                member_id, coach_id, subscription_price, status, 
                start_date, end_date, payment_id, created_at, updated_at
            ) VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $userId,
            $coachId,
            $price,
            $checkoutSessionId
        ]);
        
        // Insert into payments table
        $stmt = $conn->prepare("
            INSERT INTO payments (
                user_id, amount, payment_date, status, payment_method,
                transaction_id, payment_details, created_at
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $price,
            'completed',
            $sessionData['data']['attributes']['payment_method_used'] ?? 'online',
            $checkoutSessionId,
            json_encode([
                'type' => 'subscription',
                'coach_id' => $coachId,
                'coach_name' => $subscriptionData['coach_name']
            ])
        ]);

        // Commit transaction
        $conn->commit();

        // Clear payment session data
        unset($_SESSION['paymongo_checkout_id']);
        unset($_SESSION['subscription_payment']);

        // Set success message
        $_SESSION['success_message'] = "Subscription successful! You now have access to premium content from " . $subscriptionData['coach_name'];

        // Redirect to courses page
        header('Location: member_online_courses.php');
        exit;
        
    } else {
        // Handle class enrollment payment (existing logic)
        if (!isset($_SESSION['class_enrollment'])) {
            throw new Exception('Class enrollment data not found in session');
        }
        
        $enrollmentData = $_SESSION['class_enrollment'];
        $userId = $_SESSION['user_id'];
        $classId = $enrollmentData['class_id'];
        $amount = $enrollmentData['amount'];

        // Start transaction
        $conn->beginTransaction();

        // Check if class is still available (not full)
        $stmt = $conn->prepare("
            SELECT c.*, COUNT(ce.id) as enrolled_count 
            FROM classes c 
            LEFT JOIN classenrollments ce ON c.class_id = ce.class_id 
            WHERE c.class_id = ? 
            GROUP BY c.class_id
        ");
        $stmt->execute([$classId]);
        $classInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        // If class has a capacity limit, check if it's full
        if ($classInfo['capacity'] && $classInfo['enrolled_count'] >= $classInfo['capacity']) {
            throw new Exception('Sorry, this class is now full.');
        }

        // Insert into classenrollments
        $stmt = $conn->prepare("
            INSERT INTO classenrollments (
                class_id, user_id, status, enrollment_date, 
                payment_amount, payment_reference, payment_status
            ) VALUES (?, ?, 'confirmed', NOW(), ?, ?, 'completed')
        ");

        $stmt->execute([
            $classId,
            $userId,
            $amount,
            $checkoutSessionId
        ]);

        // Insert into payments table
        $stmt = $conn->prepare("
            INSERT INTO payments (
                user_id, amount, payment_date, status, payment_method,
                transaction_id, payment_details, created_at
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $amount,
            'completed',
            $sessionData['data']['attributes']['payment_method_used'] ?? 'online',
            $checkoutSessionId,
            json_encode([
                'type' => 'class_enrollment',
                'class_id' => $classId,
                'class_name' => $enrollmentData['class_name']
            ])
        ]);

        // Commit transaction
        $conn->commit();

        // Clear payment session data
        unset($_SESSION['paymongo_checkout_id']);
        unset($_SESSION['class_enrollment']);

        // Set success message
        $_SESSION['success_message'] = "Payment successful! You are now enrolled in " . $enrollmentData['class_name'];

        // Redirect back to classes page
        header('Location: member_class.php');
        exit;
    }
} catch (Exception $e) {
    // Rollback transaction if started
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Class Enrollment Payment Error: ' . $e->getMessage());
    $_SESSION['error_message'] = "Error processing enrollment: " . $e->getMessage();
    header('Location: member_class.php');
    exit;
}
