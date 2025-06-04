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

<div class="payments-wrapper">
    <div class="payments-card">
        <h2 class="payments-title">My Payments</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Payment Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <div><?= date('F j, Y', strtotime($payment['payment_date'])) ?></div>
                                <div class="text-muted small"><?= date('g:i A', strtotime($payment['payment_date'])) ?></div>
                            </td>
                            <td>
                                <strong>₱<?= number_format($payment['amount'], 2) ?></strong>
                            </td>
                            <td>
                                <?= formatPaymentDetails($payment['payment_details']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $payment['status'] === 'completed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= ucfirst($payment['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?= ucfirst($payment['payment_method']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
body {
    background-color: #1a1a1a;
    color: #fff;
}
.payments-wrapper {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: 80vh;
    width: 100vw;
}
.payments-card {
    background-color: #232323;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.18);
    width: 90vw;
    max-width: 1600px;
    margin: 40px 0;
    padding: 32px 32px 32px 32px;
}
.payments-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #fff;
    letter-spacing: 1px;
}
.payments-underline {
    width: 80px;
    height: 4px;
    background: #e41e26;
    border-radius: 2px;
    margin-bottom: 24px;
}
.table {
    color: #fff;
    background: transparent;
    margin: 0;
    font-size: 1.08rem;
    width: 100%;
}
.table thead th {
    background-color: #181818;
    color: #e41e26;
    font-weight: 600;
    border-bottom: 2px solid #3d3d3d;
    text-transform: uppercase;
    font-size: 15px;
    letter-spacing: 0.5px;
    padding: 18px 12px;
}
.table tbody tr {
    border-bottom: 1px solid #333;
    transition: background 0.2s;
}
.table tbody tr:last-child {
    border-bottom: none;
}
.table tbody tr:hover {
    background-color: #292929;
}
.table td {
    padding: 18px 12px;
    vertical-align: middle;
}
.badge {
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    letter-spacing: 0.3px;
}
.bg-success {
    background-color: #198754 !important;
}
.bg-warning {
    background-color: #ffc107 !important;
    color: #000 !important;
}
.bg-info {
    background-color: #0dcaf0 !important;
    color: #000 !important;
}
.text-muted {
    color: #999 !important;
}
@media (max-width: 1200px) {
    .payments-card {
        width: 98vw;
        padding: 16px 4px;
    }
    .payments-title {
        font-size: 1.3rem;
    }
    .payments-underline {
        width: 40px;
        height: 3px;
    }
    .table thead th, .table td {
        padding: 12px 6px;
        font-size: 13px;
    }
    .badge {
        font-size: 12px;
        padding: 6px 8px;
    }
}
@media (max-width: 768px) {
    .payments-card {
        width: 100vw;
        padding: 8px 2px;
    }
    .payments-title {
        font-size: 1.1rem;
    }
    .payments-underline {
        width: 30px;
        height: 2px;
    }
    .table thead th, .table td {
        padding: 8px 2px;
        font-size: 11px;
    }
    .badge {
        font-size: 10px;
        padding: 4px 6px;
    }
}
</style>

<?php
include '../assets/format/member_footer.php';
?>