<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Member') {
    header('Location: login.php');
    exit;
}

require '../config/database.php';

$user_id = $_SESSION['user_id'];

// Get enrolled classes for user
$sql_enrolled = "
SELECT c.*, ce.status as enrollment_status, ce.payment_status, ce.payment_amount,
       CONCAT(u.First_Name, ' ', u.Last_Name) as coach_name, c.requirements
FROM classes c
JOIN classenrollments ce ON c.class_id = ce.class_id
JOIN users u ON c.coach_id = u.UserID
WHERE ce.user_id = ? AND ce.status != 'cancelled' AND c.is_active = 1
ORDER BY c.class_date, c.start_time";

$stmt = $conn->prepare($sql_enrolled);
$stmt->execute([$user_id]);
$enrolled_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available classes user is NOT enrolled in
$sql_available = "
SELECT c.*, CONCAT(u.First_Name, ' ', u.Last_Name) as coach_name, c.requirements
FROM classes c
JOIN users u ON c.coach_id = u.UserID
WHERE c.is_active = 1 
AND c.class_date >= CURDATE()
AND c.class_id NOT IN (
    SELECT class_id FROM classenrollments 
    WHERE user_id = ? AND status != 'cancelled'
)
ORDER BY c.class_date, c.start_time";

$stmt2 = $conn->prepare($sql_available);
$stmt2->execute([$user_id]);
$available_classes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

function formatPrice($price)
{
    return $price > 0 ? '₱' . number_format($price, 2) : 'Free';
}

function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'confirmed':
            return 'bg-success';
        case 'waitlisted':
            return 'bg-warning text-dark';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>

<?php include '../assets/format/member_header.php'; ?>

<div class="container mt-4 mb-5">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Enrolled Classes Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="fas fa-dumbbell text-primary me-2"></i>
                My Enrolled Classes
            </h5>
        </div>
        <div class="card-body">
            <?php if (count($enrolled_classes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Class Name</th>
                                <th>Coach</th>
                                <th>Date & Time</th>
                                <th>Difficulty</th>
                                <th>Requirements</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolled_classes as $class): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($class['class_name']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars($class['class_description']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($class['coach_name']) ?></td>
                                    <td>
                                        <div><?= date('F j, Y', strtotime($class['class_date'])) ?></div>
                                        <div class="text-muted small">
                                            <?= date('g:i A', strtotime($class['start_time'])) ?> -
                                            <?= date('g:i A', strtotime($class['end_time'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= htmlspecialchars($class['difficulty_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($class['requirements'] ?: 'No special requirements') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($class['price'] > 0): ?>
                                            <span class="badge <?= $class['payment_status'] === 'completed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= $class['payment_status'] === 'completed' ? 'Paid' : 'Payment Pending' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Free Class</span>
                                        <?php endif; ?>
                                        <span class="badge <?= getStatusBadgeClass($class['enrollment_status']) ?>">
                                            <?= ucfirst($class['enrollment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (strtotime($class['class_date']) > time()): ?>
                                            <form method="post" action="drop_class.php" class="d-inline" onsubmit="return confirm('Are you sure you want to drop out from this class?');">
                                                <input type="hidden" name="class_id" value="<?= $class['class_id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times-circle"></i> Drop Out
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times text-muted mb-3" style="font-size: 2.5rem;"></i>
                    <p class="text-muted mb-0">You are not enrolled in any classes yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available Classes Section -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="fas fa-list text-primary me-2"></i>
                Available Classes
            </h5>
        </div>
        <div class="card-body">
            <?php if (count($available_classes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Class Name</th>
                                <th>Coach</th>
                                <th>Date & Time</th>
                                <th>Difficulty</th>
                                <th>Requirements</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_classes as $class): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($class['class_name']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars($class['class_description']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($class['coach_name']) ?></td>
                                    <td>
                                        <div><?= date('F j, Y', strtotime($class['class_date'])) ?></div>
                                        <div class="text-muted small">
                                            <?= date('g:i A', strtotime($class['start_time'])) ?> -
                                            <?= date('g:i A', strtotime($class['end_time'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= htmlspecialchars($class['difficulty_level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($class['requirements'] ?: 'No special requirements') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $class['price'] > 0 ? 'bg-warning text-dark' : 'bg-success' ?>">
                                            <?= formatPrice($class['price']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="enroll_class.php" class="d-inline">
                                            <input type="hidden" name="class_id" value="<?= $class['class_id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <?= $class['price'] > 0 ? 'Enroll (₱' . number_format($class['price'], 2) . ')' : 'Enroll Free' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times text-muted mb-3" style="font-size: 2.5rem;"></i>
                    <p class="text-muted mb-0">No available classes for enrollment at this time.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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

    .bg-danger {
        background-color: #dc3545 !important;
    }

    .btn-primary {
        background-color: #e41e26;
        border: none;
        padding: 8px 16px;
        font-weight: 500;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background-color: #c41920;
    }

    .alert {
        background-color: #2d2d2d;
        border: 1px solid #3d3d3d;
        color: #fff;
    }

    .alert-success {
        background-color: rgba(25, 135, 84, 0.2);
        border-color: #198754;
    }

    .alert-danger {
        background-color: rgba(220, 53, 69, 0.2);
        border-color: #dc3545;
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

        .btn-sm {
            font-size: 12px;
            padding: 6px 12px;
        }
    }
</style>

<?php include '../assets/format/member_footer.php'; ?>