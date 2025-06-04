<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$payments = [];
$error = '';
$success = '';

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$method = isset($_GET['method']) ? $_GET['method'] : 'all';

// Build the SQL query based on filters
$sql = "SELECT p.*, u.First_Name, u.Last_Name, u.Email FROM payments p
        LEFT JOIN users u ON p.user_id = u.UserID
        WHERE 1=1";

$params = [];

if (!empty($startDate)) {
    $sql .= " AND DATE(p.payment_date) >= :start_date";
    $params[':start_date'] = $startDate;
}

if (!empty($endDate)) {
    $sql .= " AND DATE(p.payment_date) <= :end_date";
    $params[':end_date'] = $endDate;
}

if ($status !== 'all') {
    $sql .= " AND p.status = :status";
    $params[':status'] = $status;
}

if ($method !== 'all') {
    $sql .= " AND p.payment_method = :method";
    $params[':method'] = $method;
}

$sql .= " ORDER BY p.payment_date DESC";

// Get payments based on filters
try {
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_amount = 0;
    $completed_count = 0;
    $failed_count = 0;

    foreach ($payments as $payment) {
        if ($payment['status'] === 'completed') {
            $total_amount += $payment['amount'];
            $completed_count++;
        } else if ($payment['status'] === 'failed') {
            $failed_count++;
        }
    }
} catch (PDOException $e) {
    $error = "Error fetching payments: " . $e->getMessage();
}

// Get available payment methods
try {
    $stmt = $conn->prepare("SELECT DISTINCT payment_method FROM payments");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $payment_methods = ['gcash', 'credit_card', 'cash'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Payments - Fitness Academy</title>
    <link rel="stylesheet" href="../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/auto-logout.js" defer></script>
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #ff6b6b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-color: #f3f4f6;
            --dark-color: #111827;
            --gray-color: #6b7280;
            --sidebar-width: 280px;
            --header-height: 72px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: var(--dark-color);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .sidebar-menu-header {
            padding: 0 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.75rem;
            margin-top: 1.25rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--secondary-color);
        }

        .sidebar a i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .user-profile {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            margin-top: auto;
        }

        .user-profile img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            margin-right: 0.75rem;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: white;
            font-size: 0.95rem;
        }

        .user-role {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }

        /* Main Content Styles */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        /* Modern Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card.active {
            border: 2px solid var(--primary-color);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .stat-icon.icon-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .stat-icon.icon-green {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .stat-icon.icon-red {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-color);
            font-weight: 500;
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #fafafa;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Filter Controls */
        .filter-controls {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Button Styles */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-info {
            background: #06b6d4;
            color: white;
            padding: 0.5rem;
            font-size: 0.75rem;
        }

        .btn-info:hover {
            background: #0891b2;
        }

        .btn-secondary {
            background: var(--gray-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Table Styles */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            margin: 0;
        }

        .table th {
            background: #f8fafc;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--dark-color);
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--dark-color);
            font-size: 0.875rem;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        /* Badge Styles */
        .badge {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge.bg-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .badge.bg-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }

        .badge.bg-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h2,
            .sidebar a span,
            .user-info {
                display: none;
            }

            .sidebar a i {
                margin-right: 0;
            }

            .sidebar a {
                justify-content: center;
            }

            .user-profile {
                justify-content: center;
            }

            .main-wrapper {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .row {
                flex-direction: column;
            }

            .col-md {
                margin-bottom: 1rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Fitness Academy</h2>
        </div>

        <nav class="sidebar-menu">
            <div class="sidebar-menu-header">Dashboard</div>
            <a href="admin_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>

            <div class="sidebar-menu-header">Management</div>
            <a href="manage_users.php">
                <i class="fas fa-users-cog"></i>
                <span>Manage Users</span>
            </a> <a href="member_list.php">
                <i class="fas fa-users"></i>
                <span>Member List</span>
            </a>
            <a href="coach_applications.php">
                <i class="fas fa-user-tie"></i>
                <span>Coach Applications</span>
            </a>
            <a href="admin_video_approval.php">
                <i class="fas fa-video"></i>
                <span>Video Approval</span>
            </a>
            <a href="track_payments.php" class="active">
                <i class="fas fa-credit-card"></i>
                <span>Payment Status</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>

            <div class="sidebar-menu-header">Attendance</div>
            <a href="qr_scanner.php">
                <i class="fas fa-camera"></i>
                <span>QR Scanner</span>
            </a>
            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
            </a>

            <div class="sidebar-menu-header">Point of Sale</div>
            <a href="pos_system.php">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>

            <div class="sidebar-menu-header">Reports</div>
            <a href="report_generation.php">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="transaction_history.php">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>            <a href="audit_trail.php">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
            </a>

            <div class="sidebar-menu-header">Database</div>
            <a href="database_management.php">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>

            <div class="sidebar-menu-header">Account</div>
            <a href="admin_settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>

        <div class="user-profile">
            <img src="../assets/images/avatar.jpg" alt="Admin" onerror="this.src='../assets/images/fa_logo.png'">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </aside> <!-- Main Content -->
    <div class="main-wrapper">
        <div class="header">
            <h1 class="page-title">Payment Status</h1>
        </div>

        <div class="content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Payment Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card" data-status="all">
                    <div class="stat-icon icon-blue">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">₱ <?php echo number_format($total_amount, 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                <div class="stat-card" data-status="completed">
                    <div class="stat-icon icon-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $completed_count; ?></div>
                        <div class="stat-label">Completed Payments</div>
                    </div>
                </div>
                <div class="stat-card" data-status="failed">
                    <div class="stat-icon icon-red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $failed_count; ?></div>
                        <div class="stat-label">Failed Payments</div>
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="filter-controls">
                <form method="GET" action="" class="filter-form">
                    <div class="form-group">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="method" class="form-label">Payment Method</label>
                        <select class="form-select" id="method" name="method">
                            <option value="all" <?php echo $method === 'all' ? 'selected' : ''; ?>>All</option>
                            <?php foreach ($payment_methods as $payment_method): ?>
                                <option value="<?php echo $payment_method; ?>" <?php echo $method === $payment_method ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($payment_method); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            Filter Results
                        </button>
                    </div>
                </form>
            </div>

            <!-- Payments Table -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-table"></i>
                    Payment Transactions
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Transaction ID</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr data-payment-id="<?php echo $payment['id']; ?>">
                                        <td>
                                            <?php echo htmlspecialchars((string)($payment['First_Name'] . ' ' . $payment['Last_Name'])); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars((string)$payment['Email']); ?></small>
                                        </td>
                                        <td>₱ <?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo $payment['transaction_id']; ?>">
                                                <?php echo $payment['transaction_id']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($payment['status'] === 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="admin_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() { // Initialize DataTable
            $('#paymentsTable').DataTable({
                order: [
                    [4, 'desc'] // Updated column index for date sorting after removing ID column
                ],
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ]
            });

            // Handle view payment details
            $('.view-payment').click(function() {
                var paymentId = $(this).data('id');
                $('#payment-details-container').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');

                // Make an AJAX request to get payment details
                $.ajax({
                    url: 'get_payment_details.php',
                    type: 'GET',
                    data: {
                        id: paymentId
                    },
                    success: function(response) {
                        $('#payment-details-container').html(response);
                    },
                    error: function() {
                        $('#payment-details-container').html('<div class="alert alert-danger">Error loading payment details.</div>');
                    }
                });
            });
        });
    </script>
</body>

</html>