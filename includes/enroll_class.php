<?php
session_start();
require '../config/database.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header('Location: login.php');
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_id'])) {
    $class_id = $_POST['class_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Get class details
        $stmt = $conn->prepare("SELECT c.*, u.First_Name as coach_name FROM classes c 
                               JOIN users u ON c.coach_id = u.UserID 
                               WHERE c.class_id = ?");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class) {
            throw new Exception('Class not found');
        }

        // Check if user is already enrolled
        $stmt = $conn->prepare("SELECT * FROM classenrollments WHERE class_id = ? AND user_id = ? AND status != 'cancelled'");
        $stmt->execute([$class_id, $user_id]);
        if ($stmt->fetch()) {
            $_SESSION['error_message'] = "You are already enrolled in this class.";
            header('Location: member_class.php');
            exit;
        }

        // Get user details
        $stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If class is free, directly enroll
        if ($class['price'] <= 0) {
            $stmt = $conn->prepare("INSERT INTO classenrollments (class_id, user_id, status, enrollment_date) 
                                  VALUES (?, ?, 'confirmed', NOW())");
            $stmt->execute([$class_id, $user_id]);

            $_SESSION['success_message'] = "Successfully enrolled in " . $class['class_name'];
            header('Location: member_class.php');
            exit;
        }

        // For paid classes, create PayMongo checkout session
        $client = new \GuzzleHttp\Client();

        // Store enrollment details in session
        $_SESSION['class_enrollment'] = [
            'class_id' => $class_id,
            'amount' => $class['price'],
            'class_name' => $class['class_name']
        ];

        // Create line items for checkout
        $lineItems = [
            [
                'currency' => 'PHP',
                'amount' => intval($class['price'] * 100), // Convert to cents
                'name' => $class['class_name'],
                'quantity' => 1,
                'description' => "Class with Coach " . $class['coach_name']
            ]
        ];

        // Create PayMongo checkout session
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
                        'description' => 'Class Enrollment - ' . $class['class_name'],
                        'success_url' => getCorrectUrl('includes/class_enrollment_success.php'),
                        'failure_url' => getCorrectUrl('includes/class_enrollment_failed.php'),
                        'billing' => [
                            'name' => $user['First_Name'] . ' ' . $user['Last_Name'],
                            'email' => $user['Email'],
                            'phone' => $user['Phone'] ?? ''
                        ],
                        'metadata' => [
                            'user_id' => $user_id,
                            'class_id' => $class_id
                        ]
                    ]
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        if (isset($result['data']) && isset($result['data']['attributes']['checkout_url'])) {
            // Store checkout session ID in session for verification
            $_SESSION['paymongo_checkout_id'] = $result['data']['id'];

            // Redirect to PayMongo checkout
            header('Location: ' . $result['data']['attributes']['checkout_url']);
            exit;
        } else {
            throw new Exception('Failed to create checkout session');
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error processing enrollment: " . $e->getMessage();
        header('Location: member_class.php');
        exit;
    }
} else {
    header('Location: member_class.php');
    exit;
}
