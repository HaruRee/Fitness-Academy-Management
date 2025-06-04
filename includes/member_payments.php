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
    if (!$data) return 'N/A';

    switch ($data['type']) {
        case 'class_enrollment':
            return "Class Enrollment: " . $data['class_name'];
        case 'membership':
            return "Membership Plan: " . ($data['plan_name'] ?? 'Standard');
        case 'video_subscription':
            return "Video Course: " . ($data['video_title'] ?? 'Unknown');
        default:
            return ucfirst(str_replace('_', ' ', $data['type']));
    }
}
?>

<div class="container">
    <h2>My Payments</h2>
    <style>
        body {
            background-color: #1a1a1a;
            color: #fff;
        }

        .container {
            max-width: 1200px;
            padding: 20px;
        }

        .card {
            background-color: #2d2d2d;
            border: none;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .card-header {
            background-color: #2d2d2d;
            border-bottom: 1px solid #3d3d3d;
            padding: 20px;
        }

        .card-header h5 {
            color: #fff;
            font-size: 18px;
            font-weight: 500;
            margin: 0;
        }

        .card-header i {
            color: #e41e26;
        }

        .card-body {
            padding: 0;
        }

        .table {
            color: #fff;
            margin: 0;
        }

        .table> :not(caption)>*>* {
            background-color: #2d2d2d;
            color: #fff;
            padding: 15px 20px;
        }

        .table thead th {
            background-color: #252525;
            color: #999;
            font-weight: 500;
            border-bottom: 1px solid #3d3d3d;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            border-bottom: 1px solid #3d3d3d;
        }

        .table tbody tr:last-child {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background-color: #333;
        }

        .badge {
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
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
        }

        .text-muted {
            color: #999 !important;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #666;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .table {
                font-size: 14px;
            }

            .badge {
                font-size: 11px;
                padding: 4px 8px;
            }
        }
    </style>
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

<?php
include '../assets/format/member_footer.php';
?>