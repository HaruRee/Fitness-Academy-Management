<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header('Location: login.php');
    exit;
}

require '../config/database.php';

$user_id = $_SESSION['user_id'];

$payments = [];

try {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch payments: " . $e->getMessage());
}

include '../assets/format/member_header.php';

function formatPaymentDetails($details)
{
    if (empty($details)) return 'N/A';

    $data = json_decode($details, true);
    if (!$data || !is_array($data) || !isset($data['type'])) return ' ';

    switch ($data['type']) {
        case 'class_enrollment':
            return "Class Enrollment: " . ($data['class_name'] ?? 'Unknown');
        case 'membership':
            return "Membership Plan: " . ($data['plan_name'] ?? 'Standard');
        case 'video_subscription':
            return "Video Course: " . ($data['video_title'] ?? 'Unknown');
        default:
            return ucfirst(str_replace('_', ' ', $data['type']));
    }
}
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <div class="payments-header mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-credit-card text-danger me-3 fs-3"></i>
                        <div>
                            <h2 class="mb-1 text-white fw-bold">Payment History</h2>
                            <p class="mb-0 text-muted">Track all your payments and transactions</p>
                        </div>
                    </div>
                </div>                <div class="payments-container">
                    <?php if (count($payments) > 0): ?>
                        <div class="table-responsive">
                            <table class="payments-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr class="payment-row">
                                            <td class="date-cell">
                                                <div class="date-wrapper">
                                                    <div class="date-main"><?= date('M j, Y', strtotime($payment['payment_date'])) ?></div>
                                                    <div class="date-time"><?= date('g:i A', strtotime($payment['payment_date'])) ?></div>
                                                </div>
                                            </td>
                                            <td class="amount-cell">
                                                <div class="amount-wrapper">
                                                    <span class="amount-value">₱<?= number_format($payment['amount'], 2) ?></span>
                                                </div>
                                            </td>
                                            <td class="type-cell">
                                                <div class="type-wrapper">
                                                    <i class="fas fa-tag"></i>
                                                    <span><?= formatPaymentDetails($payment['payment_details']) ?></span>
                                                </div>
                                            </td>
                                            <td class="method-cell">
                                                <div class="method-wrapper">
                                                    <i class="fas fa-credit-card"></i>
                                                    <span><?= ucfirst($payment['payment_method']) ?></span>
                                                </div>
                                            </td>
                                            <td class="status-cell">
                                                <span class="status-badge status-<?= strtolower($payment['status']) ?>">
                                                    <i class="fas fa-<?= $payment['status'] === 'completed' ? 'check-circle' : ($payment['status'] === 'pending' ? 'clock' : 'exclamation-circle') ?>"></i>
                                                    <?= ucfirst($payment['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?><div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <h3>No Payments Yet</h3>
                            <p>You haven't made any payments yet. Start by enrolling in classes or purchasing memberships!</p>
                            <div class="empty-actions">
                                <a href="member_class.php" class="btn btn-danger me-2">
                                    <i class="fas fa-dumbbell me-2"></i>Browse Classes
                                </a>
                                <a href="membership.php" class="btn btn-outline-danger">
                                    <i class="fas fa-star me-2"></i>View Memberships
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
:root {
    --primary-red: #d62328;
    --dark-bg: #1a1a1a;
    --darker-bg: #121212;
    --card-bg: #2d2d2d;
    --border-color: #404040;
    --text-primary: #ffffff;
    --text-secondary: #b3b3b3;
    --text-muted: #888888;
    --shadow-light: rgba(255, 255, 255, 0.1);
    --shadow-dark: rgba(0, 0, 0, 0.5);
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
}

body {
    background: linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
    color: var(--text-primary);
    font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
}

.main-content {
    background: transparent;
    min-height: calc(100vh - 140px);
    padding: 2rem 0;
}

.payments-container {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 0;
    box-shadow: 0 10px 30px var(--shadow-dark);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}

.payments-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-red), #ff4449, var(--primary-red));
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
    border-radius: 0 0 16px 16px;
}

.payments-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    margin: 0;
}

.payments-table thead th {
    background: linear-gradient(135deg, #333333, #404040);
    color: var(--text-primary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.85rem;
    padding: 1.25rem 1rem;
    border-bottom: 2px solid var(--primary-red);
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
}

.payments-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid var(--border-color);
}

.payments-table tbody tr:hover {
    background: rgba(214, 35, 40, 0.08);
    transform: scale(1.002);
}

.payments-table tbody tr:last-child {
    border-bottom: none;
}

.payments-table td {
    padding: 1rem;
    vertical-align: middle;
    color: var(--text-primary);
}

.date-cell {
    min-width: 140px;
}

.date-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date-main {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.date-time {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.amount-cell {
    min-width: 120px;
    text-align: right;
}

.amount-wrapper {
    display: flex;
    justify-content: flex-end;
}

.amount-value {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--primary-red);
    white-space: nowrap;
}

.type-cell {
    min-width: 200px;
    max-width: 250px;
}

.type-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.type-wrapper i {
    color: var(--primary-red);
    font-size: 0.85rem;
    flex-shrink: 0;
}

.type-wrapper span {
    color: var(--text-secondary);
    font-size: 0.9rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.method-cell {
    min-width: 100px;
}

.method-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.method-wrapper i {
    color: var(--primary-red);
    font-size: 0.85rem;
}

.method-wrapper span {
    color: var(--text-secondary);
    font-size: 0.9rem;
    text-transform: capitalize;
}

.status-cell {
    min-width: 120px;
    text-align: center;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.status-completed {
    background: linear-gradient(135deg, var(--success-color), #34d058);
    color: white;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.status-pending {
    background: linear-gradient(135deg, var(--warning-color), #ffca2c);
    color: #212529;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
}

.status-failed {
    background: linear-gradient(135deg, var(--danger-color), #e74c3c);
    color: white;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-icon i {
    font-size: 4rem;
    color: var(--primary-red);
    opacity: 0.7;
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    color: var(--text-primary);
    margin-bottom: 1rem;
    font-weight: 600;
}

.empty-state p {
    margin-bottom: 2rem;
    color: var(--text-secondary);
}

.empty-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn {
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-danger {
    background: linear-gradient(135deg, var(--primary-red), #ff4449);
    border: none;
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(214, 35, 40, 0.4);
}

.btn-outline-danger {
    border: 2px solid var(--primary-red);
    color: var(--primary-red);
    background: transparent;
}

.btn-outline-danger:hover {
    background: var(--primary-red);
    color: white;
    transform: translateY(-2px);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .payments-table thead th {
        padding: 1rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .payments-table td {
        padding: 0.75rem;
    }
    
    .type-cell {
        max-width: 180px;
    }
}

@media (max-width: 768px) {
    .payments-container {
        margin: 1rem;
        border-radius: 12px;
    }
    
    .table-responsive {
        border-radius: 0 0 12px 12px;
    }
    
    .payments-table thead th {
        padding: 0.75rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .payments-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .date-cell {
        min-width: 100px;
    }
    
    .amount-cell {
        min-width: 90px;
    }
    
    .amount-value {
        font-size: 1rem;
    }
    
    .type-cell {
        min-width: 150px;
        max-width: 150px;
    }
    
    .method-cell {
        min-width: 80px;
    }
    
    .status-cell {
        min-width: 100px;
    }
    
    .status-badge {
        font-size: 0.7rem;
        padding: 0.4rem 0.6rem;
    }
    
    .empty-state {
        padding: 3rem 1rem;
    }
    
    .empty-icon i {
        font-size: 3rem;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 100%;
        max-width: 300px;
    }
}

@media (max-width: 480px) {
    .payments-table {
        font-size: 0.85rem;
    }
    
    .payments-table thead th {
        padding: 0.6rem 0.4rem;
    }
    
    .payments-table td {
        padding: 0.6rem 0.4rem;
    }
    
    .date-main {
        font-size: 0.8rem;
    }
    
    .date-time {
        font-size: 0.7rem;
    }
    
    .amount-value {
        font-size: 0.9rem;
    }
    
    .type-wrapper span,
    .method-wrapper span {
        font-size: 0.8rem;
    }
    
    .status-badge {
        font-size: 0.65rem;
        padding: 0.35rem 0.5rem;
    }
}
</style>

<?php
include '../assets/format/member_footer.php';
?>