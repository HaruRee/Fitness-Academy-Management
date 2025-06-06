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
    // Get user's current membership status - check for any active subscription/plan
    $stmt = $conn->prepare("
        SELECT u.*, mp.name as plan_name, mp.price, mp.features 
        FROM users u 
        LEFT JOIN membershipplans mp ON u.plan_id = mp.id 
        WHERE u.UserID = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);    // Check if user has an active subscription/plan
    // Any of these fields being set indicates an active plan
    if ($user && (
        !empty($user['plan_id']) || 
        !empty($user['membership_plan']) ||
        !empty($user['membership_price'])
    )) {
        $hasActivePlan = true;        $activePlan = [
            'plan_name' => $user['membership_plan'] ?? ($user['plan_name'] ?? 'Active Plan'),
            'price' => $user['membership_price'] ?? ($user['price'] ?? 0),
            'features' => $user['features'] ?? ''
        ];
    }

    // Get all active membership plans only if user doesn't have active plan
    if (!$hasActivePlan) {
        $stmt = $conn->prepare("SELECT * FROM membershipplans WHERE is_active = 1 ORDER BY price");
        $stmt->execute();
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
            background: #181818;
            color: #fff;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .container {
            padding: 1.2rem 0 1rem 0;
            max-width: 100%;
        }

        h2 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #fff;
            font-weight: 700;
            letter-spacing: 1px;
            border-bottom: 2px solid #e41e26;
            display: inline-block;
            width: 100%;
            padding-bottom: 0.3rem;
        }

        .active-plan-alert .alert {
            background: #232323;
            border: 1.5px solid #e41e26; /* Use the same red as the rest of the site */
            color: #fff;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 1px 6px rgba(0,0,0,0.08);
            padding: 0.7rem 0.9rem 0.7rem 0.9rem;
            font-size: 0.98rem;
            line-height: 1.5;
        }

        .active-plan-alert .alert-info {
            background: #232323;
            color: #fff;
            border-left: 4px solid #e41e26;
            padding-left: 0.8rem;
        }
        .active-plan-alert .alert-info h4 {
            font-size: 1.1rem;
            margin-bottom: 0.4rem;
            font-weight: 700;
            color: #e41e26;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .active-plan-alert .alert-info ul {
            margin: 0 0 0.3rem 0;
            padding-left: 1.1em;
        }
        .active-plan-alert .alert-info li {
            margin-bottom: 0.1rem;
        }
        .active-plan-alert .alert-info p {
            margin-bottom: 0.2rem;
        }
        @media (max-width: 600px) {
            .active-plan-alert .alert,
            .active-plan-alert .alert-info {
                font-size: 0.93rem;
                padding: 0.6rem 0.5rem 0.6rem 0.7rem;
            }
            .active-plan-alert .alert-info h4 {
                font-size: 1rem;
            }
        }

        .plans {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            padding: 0 0.2rem;
        }

        .plan-card {
            background: #232323;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.12);
            border: 1px solid #292929;
            padding: 1.2rem 1rem 1rem 1rem;
            color: #fff;
            width: 100%;
            max-width: 320px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
            position: relative;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }

        .plan-card:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 4px 16px rgba(255,82,82,0.10);
            border-color: #e41e26;
        }

        .plan-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #e41e26;
            margin-bottom: 0.3rem;
            letter-spacing: 0.3px;
        }

        .price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.2rem;
        }

        .sessions {
            font-size: 0.98rem;
            color: #bbb;
            margin-bottom: 0.3rem;
        }

        .features-label {
            font-size: 0.95rem;
            color: #e41e26;
            margin-bottom: 0.15rem;
            font-weight: 600;
        }

        .plan-features {
            margin-bottom: 0.3rem;
        }

        .feature-item {
            color: #eee;
            font-size: 0.93rem;
            margin-bottom: 0.12rem;
            padding-left: 0.8rem;
            position: relative;
            line-height: 1.3;
        }

        .feature-item:before {
            content: "•";
            color: #e41e26;
            position: absolute;
            left: 0;
        }        .select-button {
            background: #e41e26;
            color: #fff;
            padding: 0.5rem 0;
            border-radius: 5px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: auto;
            font-size: 1rem;
            letter-spacing: 0.3px;
            border: none;
            transition: background 0.15s;
        }

        .select-button:hover {
            background: #c71e26;
        }

        .select-button.disabled {
            background: #666;
            color: #aaa;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .select-button.disabled:hover {
            background: #666;
        }

        .plan-card.disabled {
            opacity: 0.6;
            background: #1a1a1a;
            cursor: not-allowed;
        }

        .plan-card.disabled:hover {
            transform: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .modal-content {
            background: #232323;
            color: #fff;
            border-radius: 10px;
            border: 1px solid #e41e26;
        }

        .modal-header,
        .modal-footer {
            border: none;
            background: #232323;
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .loading-overlay {
            display: none;
            position: fixed;
            z-index: 9999;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            border: 4px solid #444;
            border-top: 4px solid #e41e26;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 1s linear infinite;
            margin-bottom: 0.7rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg);}
            100% { transform: rotate(360deg);}
        }

        .loading-text {
            color: #fff;
            font-size: 1rem;
        }

        /* Responsive adjustments */
        @media (max-width: 900px) {
            .plans {
                gap: 0.7rem;
            }
            .plan-card {
                max-width: 95vw;
            }
        }
        @media (max-width: 600px) {
            .container {
                padding: 0.7rem 0 0.5rem 0;
            }
            h2 {
                font-size: 1.2rem;
                margin-bottom: 0.7rem;
                padding-bottom: 0.2rem;
            }
            .plans {
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                padding: 0;
            }
            .plan-card {
                width: 98vw;
                max-width: 99vw;
                padding: 0.8rem 0.7rem 0.7rem 0.7rem;
            }
            .plan-name { font-size: 1rem; }
            .price { font-size: 1.1rem; }
            .sessions { font-size: 0.93rem; }
            .features-label { font-size: 0.9rem; }
            .feature-item { font-size: 0.9rem; }
            .select-button { font-size: 0.95rem; padding: 0.4rem 0; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</head>';

echo str_replace('</head>', $bootstrapLinks, $header);
?>

<div class="container">
    <h2>Membership Plans</h2>    <?php if ($hasActivePlan): ?>
        <div class="active-plan-alert">
            <div class="alert alert-info">
                <h4><i class="fas fa-info-circle"></i> Active Membership</h4>
                <p>You currently have an active membership plan:</p>                <ul>
                    <li><strong>Plan:</strong> <?= htmlspecialchars($activePlan['plan_name']) ?></li>
                    <li><strong>Amount Paid:</strong> ₱<?= number_format($activePlan['price'], 2) ?></li>
                </ul>
                <p><strong>Important:</strong> You cannot select or purchase a new membership plan while you have an active subscription. Please contact our support team if you need to make changes to your current plan.</p>
            </div>
        </div>
        
        <!-- No plans will be shown if user has active subscription -->
        <div class="text-center" style="padding: 2rem; background: #232323; border-radius: 12px; margin-top: 1rem;">
            <i class="fas fa-lock" style="font-size: 3rem; color: #666; margin-bottom: 1rem;"></i>
            <h4 style="color: #ccc;">Plan Selection Disabled</h4>
            <p style="color: #999;">New plan selection is disabled while you have an active membership.</p>
        </div>
    <?php else: ?>    <div class="plans">
        <?php
        if (count($plans) > 0):
            foreach ($plans as $plan):
                // Debug output
                error_log("Processing plan: " . json_encode($plan));
                $isDisabled = $hasActivePlan ? 'disabled' : '';
                $clickHandler = $hasActivePlan ? 'handleDisabledPlanClick()' : 'handlePlanCardClick(' . htmlspecialchars(json_encode($plan)) . ')';
        ?>
                <div class="plan-card <?= $isDisabled ?>" onclick="<?= $clickHandler ?>">
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
                    <div class="select-button <?= $isDisabled ?>">
                        <?php if ($hasActivePlan): ?>
                            Not Available
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
    <?php endif; // End of hasActivePlan check ?>
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

    function handleDisabledPlanClick() {
        // Show the active subscription modal when user tries to click disabled plans
        const activeSubscriptionModal = new bootstrap.Modal(document.getElementById('activeSubscriptionModal'));
        activeSubscriptionModal.show();
    }

    function handlePlanCardClick(plan) {
        // Double check: If user has active plan, show modal and prevent any action
        if (hasActivePlan) {
            handleDisabledPlanClick();
            return;
        }

        // Only proceed to show plan details if user doesn't have active plan
        selectedPlan = plan;
        showPlanDetails(plan);
    }

    function showPlanDetails(plan) {
        // This will only run if user doesn't have an active plan
        if (hasActivePlan) {
            handleDisabledPlanClick();
            return;
        }

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
        if (!selectedPlan) {
            alert('No plan selected. Please try again.');
            return;
        }

        // Final check: Block if user has active plan
        if (hasActivePlan) {
            handleDisabledPlanClick();
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

    // Prevent any form submissions or plan selections if user has active plan
    document.addEventListener('DOMContentLoaded', function() {
        if (hasActivePlan) {
            // Disable all plan cards
            const planCards = document.querySelectorAll('.plan-card');
            planCards.forEach(card => {
                card.classList.add('disabled');
                card.style.pointerEvents = 'none';
                card.style.opacity = '0.5';
            });

            // Disable all select buttons
            const selectButtons = document.querySelectorAll('.select-button');
            selectButtons.forEach(button => {
                button.classList.add('disabled');
                button.textContent = 'Not Available';
                button.style.cursor = 'not-allowed';
            });
        }
    });
</script>