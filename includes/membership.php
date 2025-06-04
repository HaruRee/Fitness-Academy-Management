<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header('Location: login.php');
    exit;
}

require '../config/database.php';
require_once 'modules/membership_functions.php';

$plans = [];
$hasActivePlan = false;
$activePlan = null;
$userEmail = '';

try {
    // Get user's current membership status
    $stmt = $conn->prepare("
        SELECT u.*, mp.name as plan_name, mp.price, mp.features 
        FROM users u 
        LEFT JOIN membershipplans mp ON u.plan_id = mp.id 
        WHERE u.UserID = ? AND u.membership_plan IS NOT NULL
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['membership_plan']) {
        $hasActivePlan = true;
        $activePlan = [
            'plan_name' => $user['membership_plan'],
            'price' => $user['membership_price'],
            'features' => $user['features'] ?? ''
        ];
    }

    // Get all active membership plans
    $stmt = $conn->prepare("SELECT * FROM membershipplans WHERE is_active = 1 ORDER BY package_type, price");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userEmail = $user && isset($user['Email']) ? $user['Email'] : '';
} catch (PDOException $e) {
    error_log("Failed to fetch data: " . $e->getMessage());
}

// Show success message if payment was successful
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}

// Show error message if there was an error
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}

// Include header but add our own CSS/JS before closing head
ob_start();
include '../assets/format/member_header.php';
$header = ob_get_clean();

// Insert Bootstrap and other required CSS/JS before closing head tag
$bootstrapLinks = '
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                        url("../assets/images/gym-background.jpg") center/cover no-repeat fixed;
            color: white;
            min-height: 100vh;
        }

        .container {
            padding: 1.5rem 0;
            max-width: 1400px;
        }

        h2 {
            text-align: center;
            font-size: 2.2rem;
            margin-bottom: 2rem;
            color: white;
        }

        .plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 0 1rem;
        }

        .plan-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1.5rem;
            color: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ff6b6b;
            margin-bottom: 0;
        }

        .price {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0;
        }

        .sessions {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0;
        }

        .features-label {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.25rem;
        }

        .feature-item {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .select-button {
            background: #ff6b6b;
            color: white;
            padding: 0.75rem;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: auto;
        }

        .select-button:hover {
            background: #ff5252;
        }

        /* Modal Styles */
        .modal-content {
            background: rgba(30, 30, 30, 0.95);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .loading-overlay {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .spinner {
            border-color: rgba(255, 255, 255, 0.2);
            border-top-color: #ff6b6b;
        }

        .loading-text {
            color: white;
        }

        @media (min-width: 1200px) {
            .plans {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</head>';

echo str_replace('</head>', $bootstrapLinks, $header);
?>

<div class="container">
    <h2>Membership Plans</h2>

    <?php if ($hasActivePlan): ?>
        <div class="active-plan-alert">
            <div class="alert alert-info">
                <h4><i class="fas fa-info-circle"></i> Active Membership</h4>
                <p>You currently have an active membership plan:</p>
                <ul>
                    <li><strong>Plan:</strong> <?= htmlspecialchars($activePlan['plan_name']) ?></li>
                    <li><strong>Amount Paid:</strong> ₱<?= number_format($activePlan['price'], 2) ?></li>
                </ul>
                <p>Please wait for your current plan to expire before purchasing a new one.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="plans">
        <?php
        if (count($plans) > 0):
            foreach ($plans as $plan):
                // Debug output
                error_log("Processing plan: " . json_encode($plan));
        ?>
                <div class="plan-card" onclick="handlePlanCardClick(<?= htmlspecialchars(json_encode($plan)) ?>)">
                    <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                    <div class="price">₱<?= number_format($plan['price'], 2) ?></div>
                    <div class="sessions"><?= htmlspecialchars($plan['name']) ?></div>
                    <?php if (!empty($plan['features'])): ?>
                        <div class="features-label">Features:</div>
                        <div class="plan-features">
                            <?php foreach (explode('|', $plan['features']) as $feature): ?>
                                <div class="feature-item"><?= htmlspecialchars($feature) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="select-button">
                        <?php if ($hasActivePlan): ?>
                            View Details
                        <?php else: ?>
                            Select Plan
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No active membership plans available at the moment.</p>
            <?php error_log("No plans found in the database or all plans are inactive"); ?>
        <?php endif; ?>
    </div>
</div>

<!-- Active Subscription Modal -->
<div class="modal fade" id="activeSubscriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle"></i> Active Subscription
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="subscription-icon mb-3">
                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                </div>
                <h4>You Have an Active Membership</h4>
                <div class="current-plan-details">
                    <p><strong>Current Plan:</strong> <?= $hasActivePlan ? htmlspecialchars($activePlan['plan_name']) : '' ?></p>
                    <p><strong>Amount Paid:</strong> ₱<?= $hasActivePlan ? number_format($activePlan['price'], 2) : '0.00' ?></p>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    You cannot purchase a new plan while you have an active membership.
                    Please wait for your current plan to expire.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Plan Details Modal -->
<div class="modal fade" id="planDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Plan Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="plan-modal-content">
                    <!-- Content will be dynamically inserted here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay">
    <div class="spinner"></div>
    <div class="loading-text">Processing your request...</div>
</div>

<?php include '../assets/format/member_footer.php'; ?>

<script>
    let selectedPlan = null;
    const hasActivePlan = <?= json_encode($hasActivePlan) ?>;
    const userEmail = <?= json_encode($userEmail) ?>;

    function handlePlanCardClick(plan) {
        if (hasActivePlan) {
            // Show the active subscription modal instead of plan details
            const activeSubscriptionModal = new bootstrap.Modal(document.getElementById('activeSubscriptionModal'));
            activeSubscriptionModal.show();
            return; // Stop here and don't show plan details
        }

        // Only proceed to show plan details if user doesn't have active plan
        selectedPlan = plan;
        showPlanDetails(plan);
    }

    function showPlanDetails(plan) {
        // This will only run if user doesn't have an active plan
        const modalContent = document.querySelector('.plan-modal-content');
        const content = `
            <div class="plan-modal-header">
                <h3 class="plan-title">${plan.name}</h3>
            </div>
            <div class="plan-modal-body">
                <div class="summary-box">
                    <div class="summary-row">
                        <span>Duration:</span>
                        <span>${plan.name.includes('Month') ? plan.name.split(' ')[0] + ' Months' : 'Monthly'}</span>
                    </div>
                    <div class="summary-row">
                        <span>Amount:</span>
                        <span class="price-amount">₱${parseFloat(plan.price).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })}</span>
                    </div>
                </div>
                
                <div class="plan-features-list">
                    <h4>Plan Features:</h4>
                    <ul>
                        ${plan.features.split('|').map(feature => `<li>${feature.trim()}</li>`).join('')}
                    </ul>
                </div>
                
                <button onclick="handlePlanPurchase()" class="btn-pay">
                    Get Started
                </button>
            </div>
        `;

        modalContent.innerHTML = content;
        const planDetailsModal = new bootstrap.Modal(document.getElementById('planDetailsModal'));
        planDetailsModal.show();
    }

    async function handlePlanPurchase() {
        if (!selectedPlan) return;

        if (hasActivePlan) {
            // Double-check: Show active subscription modal if user somehow gets here
            const activeSubscriptionModal = new bootstrap.Modal(document.getElementById('activeSubscriptionModal'));
            activeSubscriptionModal.show();
            return;
        }

        const loadingOverlay = document.querySelector('.loading-overlay');

        try {
            loadingOverlay.classList.add('active');

            const formData = new FormData();
            formData.append('plan_id', selectedPlan.id);
            formData.append('amount', selectedPlan.price);
            formData.append('email', userEmail);

            const response = await fetch('process_membership_payment.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = result.checkout_url;
            } else {
                alert(result.error || 'An error occurred. Please try again.');
            }
        } catch (error) {
            console.error('Payment error:', error);
            alert('An error occurred while processing your payment. Please try again.');
        } finally {
            loadingOverlay.classList.remove('active');
        }
    }
</script>