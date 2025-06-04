<?php
// Add at the top of your file
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config/database.php';

// Set timezone for Philippines
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Initialize all statistics variables to prevent undefined errors
$totalRevenue = 0;
$totalTransactions = 0;
$successfulTransactions = 0;
$successRate = 0;
$totalPages = 0;
$transactions = [];
$paymentMethods = [];

// Initialize variables for filtering
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '30';  // Default to last 30 days

// Set date range based on selection
switch ($dateRange) {
    case '7':
        $dateFrom = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30':
        $dateFrom = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90':
        $dateFrom = date('Y-m-d', strtotime('-90 days'));
        break;
    case '180':
        $dateFrom = date('Y-m-d', strtotime('-180 days'));
        break;
    case '365':
        $dateFrom = date('Y-m-d', strtotime('-365 days'));
        break;
    case 'all':
        $dateFrom = '';  // No start date filter
        break;
    default:
        $dateRange = '30'; // Default to 30 days if invalid selection
        $dateFrom = date('Y-m-d', strtotime('-30 days'));
}

$dateTo = date('Y-m-d');  // Today as the end date
$currentPhilippinesTime = date('Y-m-d H:i:s');
$status = isset($_GET['status']) ? $_GET['status'] : '';
$method = isset($_GET['method']) ? $_GET['method'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

try {
    // Check if Payments table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($checkTable->rowCount() === 0) {
        throw new Exception("Payments table does not exist. Please create it first.");
    }

    // Build SQL query with correct column names
    $sql = "
        SELECT p.*, u.First_Name, u.Last_Name, u.Email, 
        u.membership_plan, m.name as plan_name,
        c.class_name, c.class_id, c.class_date, c.start_time, c.end_time
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.UserID
        LEFT JOIN membershipplans m ON u.plan_id = m.id
        LEFT JOIN classenrollments ce ON p.transaction_id = ce.payment_reference
        LEFT JOIN classes c ON ce.class_id = c.class_id
        WHERE 1=1
    ";

    $params = [];

    if (!empty($dateFrom)) {
        $sql .= " AND DATE(p.payment_date) >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $sql .= " AND DATE(p.payment_date) <= ?";

        // Add time to make it end of day if only date is provided
        $endOfDayDate = $dateTo . ' 23:59:59';
        $params[] = $endOfDayDate;
    }

    if (!empty($status)) {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }

    if (!empty($method)) {
        $sql .= " AND p.payment_method = ?";
        $params[] = $method;
    }

    if (!empty($search)) {
        $sql .= " AND (u.First_Name LIKE ? OR u.Last_Name LIKE ? OR u.Email LIKE ? OR p.transaction_id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Count total records for pagination
    $countSql = str_replace("p.*, u.First_Name, u.Last_Name, u.Email", "COUNT(*) as total", $sql);
    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $totalPages = ceil($totalRecords / $limit);

    // Get transactions with pagination
    $sql .= " ORDER BY p.payment_date DESC LIMIT $offset, $limit";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct payment methods for filter dropdown
    $stmt = $conn->prepare("SELECT DISTINCT payment_method FROM payments");
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Calculate summary statistics
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payments");
    $stmt->execute();
    $totalTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $successfulTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $successRate = $totalTransactions > 0 ? ($successfulTransactions / $totalTransactions) * 100 : 0;
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    // Variables already initialized to prevent undefined errors
}

// Handle export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Remove LIMIT clause for export
    $exportSql = str_replace("LIMIT $offset, $limit", "", $sql);
    $stmt = $conn->prepare($exportSql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transaction_history_' . date('Y-m-d') . '.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, ['Transaction ID', 'Member', 'Email', 'Amount', 'Date', 'Method', 'Status']);

    // Add data rows
    foreach ($exportData as $row) {
        fputcsv($output, [
            $row['id'],
            $row['First_Name'] . ' ' . $row['Last_Name'],
            $row['Email'],
            $row['amount'],
            $row['payment_date'],
            $row['payment_method'],
            $row['status']
        ]);
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Transaction History | Fitness Academy</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .main-content {
            padding: 2rem;
            flex: 1;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .page-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-outline {
            background: transparent;
            color: var(--dark-color);
            border: 1px solid #d1d5db;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .stat-label i {
            margin-right: 0.5rem;
        }

        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .filters-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.2rem;
            color: var(--dark-color);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray-color);
        }

        .form-control {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            color: var(--dark-color);
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .transactions-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .transactions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }

        .transactions-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions-table th {
            text-align: left;
            padding: 1rem 1rem;
            font-weight: 600;
            color: var(--gray-color);
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.85rem;
        }

        .transactions-table td {
            padding: 1rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.95rem;
            color: var(--dark-color);
        }

        .transactions-table tr:hover {
            background: rgba(0, 0, 0, 0.02);
        }

        .transactions-table tr:last-child td {
            border-bottom: none;
        }

        .member-name {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .member-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-color);
            font-weight: 600;
            font-size: 0.8rem;
        }

        .member-info {
            display: flex;
            flex-direction: column;
        }

        .member-fullname {
            font-weight: 500;
        }

        .member-email {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            color: var(--dark-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            border: 1px solid #d1d5db;
        }

        .pagination a:hover {
            background: #f9fafb;
        }

        .pagination a.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination .pagination-text {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: var(--gray-color);
        }

        .transaction-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: #f3f4f6;
            color: var(--gray-color);
            transition: all 0.2s;
            cursor: pointer;
        }

        .action-btn:hover {
            background: #e5e7eb;
            color: var(--dark-color);
        }

        .view-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .modal-close {
            background: transparent;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--gray-color);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .transaction-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .detail-group {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            font-size: 0.85rem;
            color: var(--gray-color);
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 500;
        }

        .json-view {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            margin-top: 1.5rem;
        }

        .json-view pre {
            margin: 0;
            white-space: pre-wrap;
        }

        .footer {
            background: white;
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        @media (max-width: 1200px) {
            .filters-form {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
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

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .page-actions {
                width: 100%;
            }

            .header-title {
                display: none;
            }

            .transactions-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 1rem;
            }

            .main-content {
                padding: 1.5rem;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }

            .pagination {
                flex-wrap: wrap;
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
            </a>
            <a href="member_list.php">
                <i class="fas fa-users"></i>
                <span>Member List</span>
            </a>
            <a href="coach_applications.php">
                <i class="fas fa-user-tie"></i>
                <span>Coach Applications</span>
            </a>
            <a href="track_payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payment Status</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>

            <div class="sidebar-menu-header">Attendance</div>
            <a href="../attendance/checkin.php">
                <i class="fas fa-camera"></i>
                <span>QR Scanner</span>
            </a>
            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
            </a>

            <div class="sidebar-menu-header">Content</div>
            <a href="admin_video_approval.php">
                <i class="fas fa-video"></i>
                <span>Video Approval</span>
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
            <a href="transaction_history.php" class="active">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
            <a href="audit_trail.php">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
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
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header -->
        <header class="header">
            <h2 class="header-title">Transaction History</h2>

            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($error)): ?>
                <div style="background-color: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    <p>Please make sure the Payments table exists in your database.</p>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1 class="page-title">Transaction History</h1>
                <div class="page-actions">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline">
                        <i class="fas fa-download"></i>
                        Export to CSV
                    </a>
                    <a href="admin_dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">₱<?= number_format($totalRevenue, 2) ?></div>
                    <div class="stat-label">
                        <i class="fas fa-money-bill-wave"></i>
                        Total Revenue
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalTransactions) ?></div>
                    <div class="stat-label">
                        <i class="fas fa-exchange-alt"></i>
                        Total Transactions
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-value"><?= number_format($successfulTransactions) ?></div>
                    <div class="stat-label">
                        <i class="fas fa-check-circle"></i>
                        Successful Payments
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-value"><?= number_format($successRate, 1) ?>%</div>
                    <div class="stat-label">
                        <i class="fas fa-chart-pie"></i>
                        Success Rate
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <h3 class="filters-title">Filter Transactions</h3>
                <form action="" method="GET" class="filters-form">
                    <div class="form-group">
                        <label class="form-label">Date Range</label>
                        <select name="date_range" class="form-control filter-control">
                            <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>Last 7 days</option>
                            <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>Last 30 days</option>
                            <option value="90" <?= $dateRange === '90' ? 'selected' : '' ?>>Last 90 days</option>
                            <option value="180" <?= $dateRange === '180' ? 'selected' : '' ?>>Last 180 days</option>
                            <option value="365" <?= $dateRange === '365' ? 'selected' : '' ?>>Last 365 days</option>
                            <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>All time</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select name="method" class="form-control filter-control">
                            <option value="">All Methods</option>
                            <?php foreach ($paymentMethods as $pm): ?>
                                <option value="<?= htmlspecialchars((string)$pm) ?>" <?= $method === $pm ? 'selected' : '' ?>>
                                    <?= ucfirst(htmlspecialchars((string)$pm)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control filter-control">
                            <option value="">All Statuses</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" id="search-input" placeholder="Name, Email, ID..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-download"></i> Export CSV
                        </a>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="transactions-card">
                <div class="transactions-header">
                    <h3 class="transactions-title">Transaction Records</h3>
                    <div>
                        <select name="limit" class="form-control" onchange="window.location.href='?<?= http_build_query(array_merge($_GET, ['limit' => ''])) ?>'+this.value">
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10 per page</option>
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25 per page</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 per page</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100 per page</option>
                        </select>
                    </div>
                </div>

                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Product/Plan</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr data-transaction-id="<?= htmlspecialchars((string)$transaction['id']) ?>">
                                    <td>
                                        <div class="member-name">
                                            <div class="member-avatar">
                                                <?= strtoupper(substr($transaction['First_Name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div class="member-info">
                                                <div class="member-fullname">
                                                    <?= htmlspecialchars((string)($transaction['First_Name'] . ' ' . $transaction['Last_Name'])) ?>
                                                </div>
                                                <div class="member-email">
                                                    <?= htmlspecialchars((string)$transaction['Email']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>₱<?= number_format($transaction['amount'], 2) ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($transaction['payment_date'])) ?></td>
                                    <td>
                                        <?php
                                        $payment_method = htmlspecialchars((string)$transaction['payment_method']);
                                        // Clean up placeholder text
                                        $payment_method = str_replace(['{checkout.payment_method}', '(test)'], ['', ''], $payment_method);

                                        // Handle common payment methods
                                        if (empty($payment_method) || $payment_method == 'test_payment' || strpos($payment_method, 'checkout') !== false) {
                                            $payment_method = 'Credit Card';
                                        }

                                        // Format the payment method with better capitalization and display
                                        if (strpos($payment_method, ' ') !== false) {
                                            // If it contains spaces, capitalize each word
                                            $payment_method = ucwords($payment_method);
                                        } else {
                                            $payment_method = ucfirst($payment_method);
                                        }

                                        // Add special styling for test payments
                                        if (strpos(strtolower($transaction['payment_method']), 'test') !== false) {
                                            echo "<span style='border-bottom: 1px dashed #666;' title='Test payment'>{$payment_method} (Test)</span>";
                                        } else {
                                            echo $payment_method;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $planInfo = '';
                                        
                                        // Check for class information first
                                        if (!empty($transaction['class_name'])) {
                                            $planInfo = 'Class: ' . htmlspecialchars($transaction['class_name']);
                                            if (!empty($transaction['class_date'])) {
                                                $planInfo .= ' on ' . date('M d, Y', strtotime($transaction['class_date']));
                                            }
                                            if (!empty($transaction['start_time'])) {
                                                $planInfo .= ' at ' . date('h:i A', strtotime($transaction['start_time']));
                                            }
                                        }
                                        // Then check for plan information from membership_plan or plan_name
                                        elseif (!empty($transaction['plan_name'])) {
                                            $planInfo = htmlspecialchars($transaction['plan_name']);
                                        } elseif (!empty($transaction['membership_plan'])) {
                                            $planInfo = htmlspecialchars($transaction['membership_plan']);
                                        }
                                        
                                        // If still empty, try to extract from payment_details JSON
                                        if (empty($planInfo) && !empty($transaction['payment_details'])) {
                                            try {
                                                $paymentDetails = json_decode($transaction['payment_details'], true);
                                                
                                                // Check for class enrollment information first
                                                if (isset($paymentDetails['type']) && $paymentDetails['type'] === 'class_enrollment') {
                                                    $planInfo = 'Class: ' . htmlspecialchars($paymentDetails['class_name'] ?? 'Unknown Class');
                                                    if (isset($paymentDetails['class_date'])) {
                                                        $planInfo .= ' on ' . date('M d, Y', strtotime($paymentDetails['class_date']));
                                                    }
                                                    if (isset($paymentDetails['class_time'])) {
                                                        $planInfo .= ' at ' . date('h:i A', strtotime($paymentDetails['class_time']));
                                                    }
                                                }
                                                // Check different possible locations of plan info in the JSON
                                                elseif (isset($paymentDetails['discount_info']['plan_name'])) {
                                                    $planInfo = $paymentDetails['discount_info']['plan_name'];
                                                } elseif (isset($paymentDetails['metadata']['plan_name'])) {
                                                    $planInfo = $paymentDetails['metadata']['plan_name'];
                                                } elseif (isset($paymentDetails['data']['attributes']['line_items'][0]['name'])) {
                                                    $planInfo = $paymentDetails['data']['attributes']['line_items'][0]['name'];
                                                } elseif (isset($paymentDetails['description'])) {
                                                    $planInfo = $paymentDetails['description'];
                                                } elseif (isset($paymentDetails['transaction_type'])) {
                                                    // Extract from transaction_type
                                                    $planInfo = ucfirst(str_replace('_', ' ', $paymentDetails['transaction_type']));
                                                } elseif (isset($paymentDetails['membership_plan'])) {
                                                    $planInfo = $paymentDetails['membership_plan'];
                                                }
                                            } catch (Exception $e) {
                                                // Silently fail and use default
                                            }
                                        }
                                        
                                        // Provide a default if still empty
                                        echo !empty($planInfo) ? htmlspecialchars($planInfo) : '<span class="text-muted">General Payment</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = 'status-pending';
                                        if ($transaction['status'] == 'completed') {
                                            $statusClass = 'status-completed';
                                        } elseif ($transaction['status'] == 'failed') {
                                            $statusClass = 'status-failed';
                                        }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= ucfirst(htmlspecialchars((string)$transaction['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="transaction-actions">
                                            <div class="action-btn" onclick="viewTransactionDetails(<?= htmlspecialchars(json_encode($transaction)) ?>)">
                                                <i class="fas fa-eye"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-search" style="font-size: 2rem; color: #d1d5db; margin-bottom: 0.5rem;"></i>
                                    <p>No transactions found matching your filters.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $startPage + 4);

                        if ($startPage > 1) {
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="pagination-text">...</span>';
                            }
                        }

                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $class = $i === $page ? 'active' : '';
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="' . $class . '">' . $i . '</a>';
                        }

                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="pagination-text">...</span>';
                            }
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; 2025 Fitness Academy. All rights reserved.</p>
        </footer>
    </div>

    <!-- Transaction Details Modal -->
    <div id="transactionDetailsModal" class="view-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Transaction Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="transaction-details">
                    <div>
                        <div class="detail-group">
                            <div class="detail-label">Transaction ID</div>
                            <div id="detail-id" class="detail-value">#12345</div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Member</div>
                            <div id="detail-member" class="detail-value">John Doe</div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Email</div>
                            <div id="detail-email" class="detail-value">john.doe@example.com</div>
                        </div>
                    </div>
                    <div>
                        <div class="detail-group">
                            <div class="detail-label">Amount</div>
                            <div id="detail-amount" class="detail-value">₱1,000.00</div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Payment Method</div>
                            <div id="detail-method" class="detail-value">GCash</div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Status</div>
                            <div id="detail-status" class="detail-value">
                                <span class="status-badge status-completed">Completed</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Product/Plan</div>
                    <div id="detail-product" class="detail-value">Premium Membership</div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Date & Time</div>
                    <div id="detail-date" class="detail-value">October 15, 2025 10:30 AM</div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Transaction Source ID</div>
                    <div id="detail-transaction-id" class="detail-value">src_gfdh348hfsdaoeh</div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Payment Details</div>
                    <div class="json-view">
                        <pre id="detail-json"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to format JSON for better display
        function syntaxHighlight(json) {
            if (!json) return "";
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function(match) {
                var cls = 'number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'key';
                        match = '<span style="color: #2563eb; font-weight: bold;">' + match + '</span>';
                    } else {
                        cls = 'string';
                        match = '<span style="color: #059669;">' + match + '</span>';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'boolean';
                    match = '<span style="color: #7c3aed;">' + match + '</span>';
                } else if (/null/.test(match)) {
                    cls = 'null';
                    match = '<span style="color: #db2777;">' + match + '</span>';
                } else {
                    match = '<span style="color: #b45309;">' + match + '</span>';
                }
                return match;
            });
        }
        
        // Dynamic filtering - automatically submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all filter controls
            const filterControls = document.querySelectorAll('.filter-control');
            filterControls.forEach(control => {
                control.addEventListener('change', function() {
                    document.querySelector('.filters-form').submit();
                });
            });
            
            // Add event listener for search input with delay
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                let typingTimer;
                const doneTypingInterval = 500; // ms
                
                // On keyup, start the countdown
                searchInput.addEventListener('keyup', function() {
                    clearTimeout(typingTimer);
                    if (this.value) {
                        typingTimer = setTimeout(function() {
                            document.querySelector('.filters-form').submit();
                        }, doneTypingInterval);
                    }
                });
                
                // Submit form when pressing enter
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.querySelector('.filters-form').submit();
                    }
                });
            }
        });

        // View transaction details
        function viewTransactionDetails(transaction) {
            document.getElementById('detail-id').textContent = '#' + transaction.id;
            document.getElementById('detail-member').textContent = (transaction.First_Name || 'Unknown') + ' ' + (transaction.Last_Name || '');
            document.getElementById('detail-email').textContent = transaction.Email || 'No email';
            document.getElementById('detail-amount').textContent = '₱' + parseFloat(transaction.amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            // Format payment method with better capitalization
            let paymentMethod = transaction.payment_method || 'Unknown';

            // Clean up placeholder text
            paymentMethod = paymentMethod.replace('{checkout.payment_method}', '');
            paymentMethod = paymentMethod.replace(/\(test\)/i, '');

            // Handle common payment methods
            if (!paymentMethod || paymentMethod.trim() === '' || paymentMethod.includes('checkout') || paymentMethod === 'test_payment') {
                paymentMethod = 'Credit Card';
            }

            // Format capitalization
            if (paymentMethod.includes(' ')) {
                // Capitalize each word if it contains spaces
                paymentMethod = paymentMethod.split(' ')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ');
            } else {
                // Otherwise just capitalize first letter
                paymentMethod = paymentMethod.charAt(0).toUpperCase() + paymentMethod.slice(1);
            }

            // Add test indicator if needed
            if (transaction.payment_method && transaction.payment_method.toLowerCase().includes('test')) {
                paymentMethod += ' (Test)';
            }

            document.getElementById('detail-method').textContent = paymentMethod;

            // Format date safely
            try {
                const date = new Date(transaction.payment_date);
                document.getElementById('detail-date').textContent = date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });
            } catch (e) {
                document.getElementById('detail-date').textContent = transaction.payment_date || 'Unknown date';
            }

            document.getElementById('detail-transaction-id').textContent = transaction.transaction_id || 'N/A';

            // Status badge
            let statusHTML = '';
            if (transaction.status === 'completed') {
                statusHTML = '<span class="status-badge status-completed">Completed</span>';
            } else if (transaction.status === 'pending') {
                statusHTML = '<span class="status-badge status-pending">Pending</span>';
            } else {
                statusHTML = '<span class="status-badge status-failed">Failed</span>';
            }
            document.getElementById('detail-status').innerHTML = statusHTML;

            // Extract and display product/plan information
            let planInfo = '';
            
            // Check for class information first
            if (transaction.class_name) {
                planInfo = 'Class: ' + transaction.class_name;
                
                if (transaction.class_date) {
                    const classDate = new Date(transaction.class_date);
                    planInfo += ' on ' + classDate.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                }
                
                if (transaction.start_time) {
                    // Handle the time format which might be just HH:MM:SS
                    const timeParts = transaction.start_time.split(':');
                    const hours = parseInt(timeParts[0]);
                    const minutes = timeParts[1];
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    const formattedHours = hours % 12 || 12;
                    planInfo += ' at ' + formattedHours + ':' + minutes + ' ' + ampm;
                }
            }
            // Check different sources for plan info
            else if (transaction.plan_name) {
                planInfo = transaction.plan_name;
            } else if (transaction.membership_plan) {
                planInfo = transaction.membership_plan;
            }
            
            // If still empty, try to extract from payment_details JSON
            if (!planInfo && transaction.payment_details) {
                try {
                    let paymentDetails = typeof transaction.payment_details === 'string' ?
                        JSON.parse(transaction.payment_details) : transaction.payment_details;
                    
                    // Check for class enrollment information first
                    if (paymentDetails.type === 'class_enrollment') {
                        planInfo = 'Class: ' + (paymentDetails.class_name || 'Unknown Class');
                        
                        if (paymentDetails.class_date) {
                            const classDate = new Date(paymentDetails.class_date);
                            planInfo += ' on ' + classDate.toLocaleDateString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric'
                            });
                        }
                        
                        if (paymentDetails.class_time) {
                            // Handle the time format
                            const timeParts = paymentDetails.class_time.split(':');
                            const hours = parseInt(timeParts[0]);
                            const minutes = timeParts[1];
                            const ampm = hours >= 12 ? 'PM' : 'AM';
                            const formattedHours = hours % 12 || 12;
                            planInfo += ' at ' + formattedHours + ':' + minutes + ' ' + ampm;
                        }
                    }
                    // Check different possible locations in the JSON
                    else if (paymentDetails.discount_info && paymentDetails.discount_info.plan_name) {
                        planInfo = paymentDetails.discount_info.plan_name;
                    } else if (paymentDetails.metadata && paymentDetails.metadata.plan_name) {
                        planInfo = paymentDetails.metadata.plan_name;
                    } else if (paymentDetails.data && 
                               paymentDetails.data.attributes && 
                               paymentDetails.data.attributes.line_items && 
                               paymentDetails.data.attributes.line_items[0]) {
                        planInfo = paymentDetails.data.attributes.line_items[0].name;
                    } else if (paymentDetails.description) {
                        planInfo = paymentDetails.description;
                    } else if (paymentDetails.transaction_type) {
                        // Extract from transaction_type
                        const transactionType = paymentDetails.transaction_type;
                        planInfo = transactionType.charAt(0).toUpperCase() + 
                                  transactionType.slice(1).replace(/_/g, ' ');
                    } else if (paymentDetails.membership_plan) {
                        planInfo = paymentDetails.membership_plan;
                    }
                } catch (e) {
                    console.error("Error parsing payment details", e);
                }
            }
            
            document.getElementById('detail-product').textContent = planInfo || 'General Payment';

            // Format JSON safely
            try {
                let paymentDetails = {};
                if (transaction.payment_details) {
                    paymentDetails = typeof transaction.payment_details === 'string' ?
                        JSON.parse(transaction.payment_details) :
                        transaction.payment_details;
                }
                document.getElementById('detail-json').innerHTML = syntaxHighlight(JSON.stringify(paymentDetails, null, 2));
            } catch (e) {
                document.getElementById('detail-json').textContent = 'Error parsing JSON: ' + e.message;
            }

            // Show modal
            document.getElementById('transactionDetailsModal').style.display = 'flex';
        }

        // Close the modal
        function closeModal() {
            document.getElementById('transactionDetailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('transactionDetailsModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
    </script>
</body>

</html>