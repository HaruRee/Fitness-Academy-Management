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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Coach | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">    <style>
        :root {
            --primary-color: #dc2626;
            --primary-dark: #b91c1c;
            --text-color: #f3f4f6;
            --text-light: #d1d5db;
            --bg-dark: #0f0f0f;
            --card-bg: #1f1f1f;
            --border-color: #374151;
            --success-color: #10b981;
            --danger-color: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            margin: 0;
            color: var(--text-color);
            line-height: 1.5;
        }

        .container {
            max-width: 500px;
            margin: 30px auto;
            padding: 15px;
        }        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        .subscription-header {
            margin-bottom: 22px;
            text-align: center;
        }

        .subscription-header i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 12px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .subscription-header h1 {
            font-size: 1.5rem;
            margin: 0 0 5px;
            color: var(--text-color);
            font-weight: 600;
        }        .coach-info {
            background: #2d2d2d;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 22px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .coach-info:hover {
            border-color: var(--primary-color);
            background: #333;
        }

        .coach-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .subscription-price {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--text-color);
            margin: 5px 0;
        }

        .subscription-price-note {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 4px;
        }

        .benefits-list {
            margin-bottom: 25px;
        }

        .benefits-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            color: var(--text-light);
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            font-weight: 500;
        }

        .benefit-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
            font-size: 0.95rem;
            transition: transform 0.15s ease;
        }

        .benefit-item:hover {
            transform: translateX(3px);
        }

        .benefit-item i {
            color: var(--success-color);
            margin-right: 10px;
            font-size: 0.95rem;
            flex-shrink: 0;
            margin-top: 4px;
        }
          .benefit-item span {
            color: var(--text-light);
        }

        .payment-info {
            margin-bottom: 22px;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            background-color: #2d2d2d;
            border: 1px solid var(--border-color);
        }

        .payment-info p {
            margin: 0 0 12px;
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .payment-methods {
            display: flex;
            justify-content: center;
            margin-bottom: 5px;
            gap: 10px;
            flex-wrap: wrap;
        }        .payment-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #3d3d3d;
            border-radius: 8px;
            color: var(--text-light);
            transition: all 0.2s ease;
        }

        .payment-icon:hover {
            background: #4d4d4d;
            transform: translateY(-2px);
            color: var(--primary-color);
        }

        .payment-icon i {
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #fff;
            box-shadow: 0 2px 4px rgba(228, 30, 38, 0.2);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(228, 30, 38, 0.25);
        }

        .btn-primary:active {
            transform: translateY(0);
        }        .btn-secondary {
            background-color: transparent;
            color: var(--text-light);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: #2d2d2d;
            border-color: var(--primary-color);
            color: var(--text-color);
        }

        .btn i {
            margin-right: 8px;
        }

        /* Button loading state */
        .btn-primary.loading {
            background-color: #f0888b;
            pointer-events: none;
        }

        .btn-primary.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
            right: 18px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: flex-start;
        }

        .alert i {
            margin-right: 10px;
            margin-top: 2px;
        }        .alert-success {
            background-color: #1a2f1a;
            border-left: 4px solid var(--success-color);
            color: #a3e6a3;
        }

        .alert-danger {
            background-color: #2f1a1a;
            border-left: 4px solid var(--danger-color);
            color: #f5a3a3;
        }        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-light);
            text-decoration: none;
            margin-bottom: 15px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 6px 10px;
            border-radius: 6px;
        }

        .back-link:hover {
            color: var(--primary-color);
            background-color: rgba(220, 38, 38, 0.1);
        }

        .back-link i {
            margin-right: 6px;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .container {
                padding: 10px;
                margin: 15px auto;
            }

            .card {
                padding: 20px;
                border-radius: 10px;
            }

            .subscription-header i {
                font-size: 1.8rem;
            }

            .subscription-header h1 {
                font-size: 1.3rem;
            }

            .subscription-price {
                font-size: 1.4rem;
            }

            .btn {
                padding: 12px;
            }
        }        /* High-contrast mode for accessibility */
        @media (prefers-contrast: more) {
            :root {
                --primary-color: #ff4444;
                --primary-dark: #cc3333;
                --text-color: #ffffff;
                --text-light: #e5e5e5;
                --bg-dark: #000000;
                --card-bg: #222222;
                --border-color: #888888;
                --success-color: #00ff00;
                --danger-color: #ff0000;
            }
            
            .card {
                box-shadow: 0 0 0 1px #ffffff;
            }
            
            .alert {
                border: 1px solid currentColor;
            }
            
            .btn-primary {
                border: 1px solid #ffffff;
            }
            
            .btn-secondary {
                border: 1px solid #ffffff;
            }
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
                    <h1>Premium Subscription</h1>
                </div>

                <div class="coach-info">
                    <div class="coach-name"><?= htmlspecialchars($coach['First_Name'] . ' ' . $coach['Last_Name']) ?></div>
                    <div class="subscription-price">₱<?= number_format($price, 2) ?></div>
                    <div class="subscription-price-note">per month, cancel anytime</div>
                </div>

                <div class="benefits-list">
                    <div class="benefits-title">Subscription includes:</div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Unlimited access to all premium videos</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Regular new content updates</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>HD video quality on all devices</span>
                    </div>
                </div>

                <form method="POST" id="subscriptionForm" class="payment-form">
                    <input type="hidden" name="action" value="create_checkout">

                    <div class="payment-info">
                        <p>Payment processed securely via PayMongo</p>
                        <div class="payment-methods">
                            <div class="payment-icon" title="Credit/Debit Card"><i class="fas fa-credit-card"></i></div>
                            <div class="payment-icon" title="GCash"><i class="fas fa-mobile-alt"></i></div>
                            <div class="payment-icon" title="PayMaya"><i class="fas fa-wallet"></i></div>
                            <div class="payment-icon" title="GrabPay"><i class="fas fa-car"></i></div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" id="subscribeBtn" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Subscribe for ₱<?= number_format($price, 2) ?>
                        </button>
                        <a href="member_online_courses.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add loading state to button when form is submitted
        document.getElementById('subscriptionForm')?.addEventListener('submit', function() {
            const button = document.getElementById('subscribeBtn');
            if (button) {
                button.classList.add('loading');
                button.innerHTML = '<i class="fas fa-lock"></i> Processing...';
            }
        });
    </script>
</body>

</html>