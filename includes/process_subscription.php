<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';

// Check if logged in and is member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header("Location: login.php");
    exit;
}

$member_id = $_SESSION['user_id'];
$coach_id = $_GET['coach_id'] ?? null;
$price = $_GET['price'] ?? null;

if (!$coach_id || !$price) {
    header("Location: member_online_courses.php");
    exit;
}

// Function to get correct URL for hosting environment
function getCorrectUrl($path)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $currentDir = dirname($_SERVER['PHP_SELF']);
    $baseDir = dirname($currentDir);
    $baseDir = rtrim($baseDir, '/') . '/';
    return $protocol . $host . $baseDir . ltrim($path, '/');
}

// Load API configuration
require_once __DIR__ . '/../config/api_config.php';

// Get coach details
$stmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE UserID = ? AND Role = 'Coach'");
$stmt->execute([$coach_id]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    header("Location: member_online_courses.php");
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT UserID, First_Name, Last_Name, Email, Phone FROM users WHERE UserID = ?");
$stmt->execute([$member_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: member_online_courses.php");
    exit;
}

// Check if already subscribed
$stmt = $conn->prepare("
    SELECT id FROM coach_subscriptions 
    WHERE member_id = ? AND coach_id = ? AND status = 'active'
");
$stmt->execute([$member_id, $coach_id]);
if ($stmt->fetch()) {
    header("Location: member_online_courses.php?already_subscribed=1");
    exit;
}

$success_message = '';
$error_message = '';

// Handle PayMongo checkout creation
if ($_POST && $_POST['action'] === 'create_checkout') {
    try {
        // Validate minimum amount (PayMongo requires minimum of ₱20.00)
        if ($price < 20) {
            throw new Exception('This subscription price (₱' . number_format($price, 2) . ') is below the payment processor minimum of ₱20.00. Please contact the coach to update their pricing or choose a different subscription option.');
        }
        
        // Initialize GuzzleHTTP client
        $client = new \GuzzleHttp\Client();

        // Create line items for checkout
        $lineItems = [
            [
                'currency' => 'PHP',
                'amount' => intval($price * 100), // Convert to cents
                'name' => 'Premium Subscription - ' . $coach['First_Name'] . ' ' . $coach['Last_Name'],
                'quantity' => 1,
                'description' => 'Monthly subscription to premium video courses'
            ]
        ];

        // Store subscription details in session for later use
        $_SESSION['subscription_payment'] = [
            'coach_id' => $coach_id,
            'price' => $price,
            'coach_name' => $coach['First_Name'] . ' ' . $coach['Last_Name']
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
                        'description' => 'Premium Subscription - ' . $coach['First_Name'] . ' ' . $coach['Last_Name'],
                        'success_url' => getCorrectUrl('includes/class_enrollment_success.php?type=subscription'),
                        'failure_url' => getCorrectUrl('includes/class_enrollment_failed.php?type=subscription'),
                        'billing' => [
                            'name' => $user['First_Name'] . ' ' . $user['Last_Name'],
                            'email' => $user['Email'],
                            'phone' => $user['Phone'] ?? ''
                        ],
                        'metadata' => [
                            'user_id' => $user['UserID'],
                            'coach_id' => $coach_id,
                            'subscription_price' => $price,
                            'type' => 'subscription'
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
        error_log('Subscription Payment Error: ' . $e->getMessage());
        $error_message = "Error processing subscription: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Subscribe to Coach | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }

        .card {
            background: #fff;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .subscription-header {
            margin-bottom: 30px;
        }

        .subscription-header i {
            font-size: 3rem;
            color: #e41e26;
            margin-bottom: 20px;
        }

        .subscription-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: #333;
        }

        .coach-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .coach-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #e41e26;
            margin-bottom: 10px;
        }

        .subscription-price {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }

        .benefits-list {
            text-align: left;
            margin-bottom: 30px;
        }

        .benefits-list h3 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(228, 30, 38, 0.05);
            border-radius: 8px;
        }

        .benefit-item i {
            color: #28a745;
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .payment-form {
            text-align: left;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #e41e26;
        }

        .payment-info {
            margin-bottom: 30px;
            text-align: center;
        }

        .payment-info p {
            margin-bottom: 20px;
            color: #666;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            background: rgba(228, 30, 38, 0.05);
            border-radius: 10px;
            border: 2px solid transparent;
        }

        .payment-option i {
            font-size: 1.5rem;
            color: #e41e26;
            margin-bottom: 8px;
        }

        .payment-option span {
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(45deg, #e41e26, #ff6b6b);
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #c71e24, #ff5252);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: #fff;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: #e41e26;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .back-link i {
            margin-right: 8px;
        }

        .back-link:hover {
            color: #c71e24;
        }
    </style>
</head>

<body>
    <?php include '../assets/format/member_header.php'; ?>

    <div class="container">
        <a href="member_online_courses.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Courses
        </a>

        <div class="card">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php elseif ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php else: ?>
                <div class="subscription-header">
                    <i class="fas fa-crown"></i>
                    <h1>Subscribe to Premium Content</h1>
                </div>

                <div class="coach-info">
                    <div class="coach-name"><?= htmlspecialchars($coach['First_Name'] . ' ' . $coach['Last_Name']) ?></div>
                    <div class="subscription-price">₱<?= number_format($price, 2) ?>/month</div>
                </div>

                <div class="benefits-list">
                    <h3>What You Get:</h3>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Unlimited access to all premium video courses</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>New content added regularly</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>HD video quality and mobile access</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Cancel anytime</span>
                    </div>
                </div>

                <form method="POST" class="payment-form">
                    <input type="hidden" name="action" value="create_checkout">

                    <div class="payment-info">
                        <p>Click below to proceed to secure PayMongo checkout. You can pay using:</p>
                        <div class="payment-options">
                            <div class="payment-option">
                                <i class="fas fa-credit-card"></i>
                                <span>Credit/Debit Cards</span>
                            </div>
                            <div class="payment-option">
                                <i class="fas fa-mobile-alt"></i>
                                <span>GCash</span>
                            </div>
                            <div class="payment-option">
                                <i class="fas fa-wallet"></i>
                                <span>PayMaya</span>
                            </div>
                            <div class="payment-option">
                                <i class="fas fa-car"></i>
                                <span>GrabPay</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-lock"></i> Proceed to Secure Checkout - ₱<?= number_format($price, 2) ?>
                    </button>

                    <a href="member_online_courses.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add any additional JavaScript here if needed
    </script>
</body>

</html>