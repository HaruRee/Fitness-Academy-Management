<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/gym1/includes/admin_dashboard.php
session_start();
require '../config/database.php';
require 'activity_tracker.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Track page view activity
if (isset($_SESSION['user_id'])) {
    trackPageView($_SESSION['user_id'], 'Admin Dashboard');

    // Run automatic deactivation check (throttled to once per hour)
    if (shouldRunAutomaticDeactivation($conn)) {
        runAutomaticDeactivation($conn);
    }
}

// Initialize statistics variables to avoid undefined errors
$active_users = 0;
$new_users = 0;
$total_payments = 0;
$today_payments = 0;
$total_members = 0;
$active_members = 0;
$recent_audit_trails = [];
$recent_transactions = [];
$sales_summary = [];
$error = null;

// Get user stats
try {
    // Total active users
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE is_approved = 1");
    $stmt->execute();
    $active_users = $stmt->fetchColumn();

    // Total members
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE Role = 'Member'");
    $stmt->execute();
    $total_members = $stmt->fetchColumn();

    // Active members
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE Role = 'Member' AND IsActive = 1");
    $stmt->execute();
    $active_members = $stmt->fetchColumn();

    // New registrations this month
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE MONTH(RegistrationDate) = MONTH(CURRENT_DATE()) AND YEAR(RegistrationDate) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $new_users = $stmt->fetchColumn();

    // Total payments
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $total_payments = $stmt->fetchColumn();

    // Recent payments 
    $stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE status = 'completed' AND DATE(payment_date) = CURDATE()");
    $stmt->execute();
    $today_payments = $stmt->fetchColumn();

    // Recent audit trails (limited to 6)
    $stmt = $conn->prepare("SELECT id, username, action, timestamp FROM audit_trail ORDER BY timestamp DESC LIMIT 6");
    $stmt->execute();
    $recent_audit_trails = $stmt->fetchAll(PDO::FETCH_ASSOC);    // Recent transactions from the last 3 days
    $stmt = $conn->prepare("
        SELECT p.id, u.First_Name, u.Last_Name, p.amount, p.payment_date, p.status, p.payment_method
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.UserID
        WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get a summary of the last 3 days of sales
    $stmt = $conn->prepare("
        SELECT DATE(payment_date) as sale_date, SUM(amount) as daily_total, COUNT(*) as transaction_count
        FROM payments 
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
        AND status = 'completed'
        GROUP BY DATE(payment_date)
        ORDER BY sale_date DESC
    ");
    $stmt->execute();
    $sales_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Admin Dashboard | Fitness Academy</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-search {
            display: flex;
            align-items: center;
        }

        .header-search input {
            border: none;
            background: #f3f4f6;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border-radius: 8px;
            width: 300px;
            font-size: 0.9rem;
            color: var(--dark-color);
        }

        .header-search i {
            position: absolute;
            left: 3rem;
            color: var(--gray-color);
        }        .header-actions {
            display: flex;
            align-items: center;
        }

        .main-content {
            padding: 2rem;
            flex: 1;
        }

        .welcome-header {
            margin-bottom: 2rem;
        }

        .welcome-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .welcome-header p {
            color: var(--gray-color);
            font-size: 1rem;
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
            display: flex;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .icon-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .icon-green {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .icon-orange {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .icon-red {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .stat-icon i {
            font-size: 1.8rem;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
        }        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
            max-width: 100%;
            justify-content: center;
        }.chart-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            height: 100%;
            width: 100%;
            min-height: 480px;
        }.chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }        .chart-actions select {
            background: #f3f4f6;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            color: var(--gray-color);
            font-size: 0.9rem;
            cursor: pointer;
        }

        /* AI Predictions Styles */        .ai-predictions {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .prediction-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .prediction-header .fas.fa-brain {
            color: var(--primary-color);
        }

        .prediction-loader {
            margin-left: auto;
        }

        .prediction-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .prediction-text {
            color: var(--dark-color);
            font-size: 0.9rem;
            font-weight: 500;
        }.prediction-insights {
            margin-top: 0.25rem;
        }

        .insights-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }        .insights-list li {
            color: var(--gray-color);
            font-size: 0.85rem;
            padding: 0.15rem 0;
            position: relative;
            padding-left: 1rem;
        }
        
        .insights-list li strong {
            color: #1d4ed8;
            font-weight: 600;
        }.insights-list li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-weight: bold;
        }

        /* Responsive adjustments for AI predictions */
        @media (max-width: 768px) {            .ai-predictions {
                margin-top: 0.5rem;
                padding: 0.75rem;
            }

            .prediction-main {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .confidence-badge {
                align-self: flex-end;
            }
        }

        /* Animation for loading state */
        .ai-predictions.loading {
            opacity: 0.7;
        }

        .prediction-loader .fa-spinner {
            color: var(--primary-color);
        }

        .activity-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .activity-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .activity-actions a {
            display: inline-flex;
            align-items: center;
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .activity-actions a:hover {
            background: #1e3a8a;
        }

        .activity-actions a i {
            margin-right: 0.5rem;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .activity-icon i {
            color: var(--gray-color);
            font-size: 1rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .activity-text strong {
            font-weight: 600;
            color: var(--dark-color);
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .transaction-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transaction-table th {
            text-align: left;
            padding: 1rem 0.5rem;
            font-weight: 600;
            color: var(--gray-color);
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.85rem;
        }

        .transaction-table td {
            padding: 1rem 0.5rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.95rem;
            color: var(--dark-color);
        }

        .transaction-table tr:hover {
            background: rgba(0, 0, 0, 0.02);
        }

        .transaction-table tr:last-child td {
            border-bottom: none;
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

        .view-all {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .view-all-btn {
            background: #f3f4f6;
            color: var(--gray-color);
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .view-all-btn:hover {
            background: #e5e7eb;
            color: var(--dark-color);
        }

        .footer {
            background: white;
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .sales-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .no-data-message {
            text-align: center;
            padding: 2rem;
            color: var(--gray-color);
        }

        .error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .prediction-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .refresh-predictions {
            background: transparent;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .refresh-predictions:hover {
            background: rgba(59, 130, 246, 0.1);
            transform: rotate(15deg);
        }

        .refresh-predictions i {
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .chart-card {
                min-height: 400px;
                padding: 1.5rem;
            }

            .chart-container {
                height: 280px;
            }

            .sales-summary {
                flex-direction: column;
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
        }

        @media (max-width: 768px) {
            .header-search input {
                width: 200px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 1rem;
            }

            .header-search {
                display: none;
            }

            .main-content {
                padding: 1.5rem;
            }
        }

        /* Large screens optimization */
        @media (min-width: 1400px) {
            .chart-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2.5rem;
                max-width: 1400px;
                margin: 0 auto 2rem auto;
            }

            .chart-card {
                min-height: 520px;
                padding: 2.5rem;
            }

            .chart-container {
                height: 350px;
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
            <a href="admin_dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>            <div class="sidebar-menu-header">Management</div>
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
            <a href="admin_video_approval.php">
                <i class="fas fa-video"></i>
                <span>Video Approval</span>
            </a>
            <a href="products.php">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a><div class="sidebar-menu-header">Reports</div>
            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
            </a>
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
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            </div>
        </header>        <!-- Main Content -->
        <main class="main-content">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['pos_redirect_message'])): ?>
                <div class="success-message" style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <i class="fas fa-info-circle"></i>
                    <?= htmlspecialchars($_SESSION['pos_redirect_message']) ?>
                    <span style="display: block; margin-top: 0.5rem; font-size: 0.9rem;">
                        Staff can now access the POS system from their dashboard for streamlined operations.
                    </span>
                </div>
                <?php unset($_SESSION['pos_redirect_message']); ?>
            <?php endif; ?>

            <div class="welcome-header">
                <h1>Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>!</h1>
                <p>Here's what's happening with your gym today.</p>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= number_format($active_users) ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-green">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= number_format($new_users) ?></div>
                        <div class="stat-label">New Registrations This Month</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-orange">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">₱<?= number_format($total_payments, 2) ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-red">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= number_format($today_payments) ?></div>
                        <div class="stat-label">Payments Today</div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Membership Growth</h3>
                        <div class="chart-actions">
                            <select id="membershipTimeRange">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                            </select>
                        </div>
                    </div>                    <div class="chart-container">
                        <canvas id="membershipChart"></canvas>
                    </div>
                      <!-- AI Predictions Section for Membership -->
                    <div class="ai-predictions" id="membershipPredictions">
                        <div class="prediction-header">
                            <i class="fas fa-brain"></i>
                            <span>AI Insights</span>
                            <div class="prediction-actions">
                                <button class="refresh-predictions" title="Get fresh insights" data-target="membership">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <div class="prediction-loader" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                        </div>
                        <div class="prediction-content">
                            <div class="prediction-main">
                                <span class="prediction-text">Loading predictions...</span>
                            </div>
                            <div class="prediction-insights">
                                <ul class="insights-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Revenue Analysis</h3>
                        <div class="chart-actions">
                            <select id="revenueTimeRange">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                      <!-- AI Predictions Section for Revenue -->
                    <div class="ai-predictions" id="revenuePredictions">
                        <div class="prediction-header">
                            <i class="fas fa-brain"></i>
                            <span>AI Insights</span>
                            <div class="prediction-actions">
                                <button class="refresh-predictions" title="Get fresh insights" data-target="revenue">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <div class="prediction-loader" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                        </div>
                        <div class="prediction-content">
                            <div class="prediction-main">
                                <span class="prediction-text">Loading predictions...</span>
                            </div>
                            <div class="prediction-insights">
                                <ul class="insights-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3 class="activity-title">Recent Transactions</h3>
                    <div class="activity-actions">
                        <a href="transaction_history.php">
                            <i class="fas fa-history"></i> View All Transactions
                        </a>
                    </div>
                </div>

                <!-- 3-Day Sales Summary -->
                <div class="sales-summary">
                    <?php if (!empty($sales_summary)): ?>
                        <?php foreach ($sales_summary as $summary): ?>
                            <div class="stat-card" style="flex: 1; margin: 0;">
                                <div class="stat-info">
                                    <div class="stat-value">₱<?= number_format($summary['daily_total'], 2) ?></div>
                                    <div class="stat-label">
                                        <?= date('M d, Y', strtotime($summary['sale_date'])) ?>
                                        <span style="color: var(--success-color); font-size: 0.85rem; margin-left: 0.5rem;">
                                            <?= $summary['transaction_count'] ?> transaction<?= $summary['transaction_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="stat-card" style="flex: 1; margin: 0;">
                            <div class="stat-info">
                                <div class="stat-value">₱0.00</div>
                                <div class="stat-label">No recent sales</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($recent_transactions)): ?> <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)(($transaction['First_Name'] ?? 'Unknown') . ' ' . ($transaction['Last_Name'] ?? ''))) ?></td>
                                    <td>₱<?= number_format($transaction['amount'], 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($transaction['payment_date'])) ?></td>
                                    <td><?= ucfirst(htmlspecialchars((string)$transaction['payment_method'])) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'status-pending';
                                        if ($transaction['status'] == 'completed') {
                                            $statusClass = 'status-completed';
                                        } elseif ($transaction['status'] == 'failed') {
                                            $statusClass = 'status-failed';
                                        }
                                        ?> <span class="status-badge <?= $statusClass ?>">
                                            <?= ucfirst(htmlspecialchars((string)$transaction['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data-message">
                        <i class="fas fa-receipt" style="font-size: 3rem; color: #e5e7eb; margin-bottom: 1rem;"></i>
                        <p>No transactions found in the last 3 days.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3 class="activity-title">Recent Activity</h3>
                    <div class="activity-actions">
                        <a href="audit_trail.php">
                            <i class="fas fa-clipboard-list"></i> View Full Audit Trail
                        </a>
                    </div>
                </div>

                <ul class="activity-list">
                    <?php if (!empty($recent_audit_trails)): ?>
                        <?php foreach ($recent_audit_trails as $trail): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <strong><?= htmlspecialchars($trail['username']) ?></strong>
                                        <?= htmlspecialchars($trail['action']) ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= date('M d, Y h:i A', strtotime($trail['timestamp'])) ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div class="activity-content">
                                <div class="activity-text">No recent activities found.</div>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; 2025 Fitness Academy. All rights reserved.</p>
        </footer>
    </div>

    <script>
        // Fetch membership data from the server
        async function fetchMembershipData(days) {
            try {
                const response = await fetch(`../api/get_membership_data.php?days=${days}`);
                if (!response.ok) {
                    return getDefaultMembershipData(days);
                }
                return await response.json();
            } catch (error) {
                console.error('Error fetching membership data:', error);
                return getDefaultMembershipData(days);
            }
        }

        // Get default membership data for demo
        function getDefaultMembershipData(days) {
            if (days === 7) {
                return {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    data: [3, 5, 2, 6, 4, 8, 5]
                };
            } else if (days === 90) {
                return {
                    labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    data: [5, 10, 15, 12, 18, 22, 25, 30, 35]
                };
            } else {
                // 30 days default
                return {
                    labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
                    data: [5, 10, 15, 12, 18, 22, 25, 30]
                };
            }
        }

        // Fetch revenue data from the server
        async function fetchRevenueData(days) {
            try {
                const response = await fetch(`../api/get_revenue_data.php?days=${days}`);
                if (!response.ok) {
                    return getDefaultRevenueData(days);
                }
                return await response.json();
            } catch (error) {
                console.error('Error fetching revenue data:', error);
                return getDefaultRevenueData(days);
            }
        }

        // Get default revenue data for demo
        function getDefaultRevenueData(days) {
            if (days === 7) {
                return {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    data: [12000, 15000, 10000, 20000, 25000, 30000, 20000]
                };
            } else if (days === 90) {
                return {
                    labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    data: [25000, 35000, 30000, 42000, 38000, 55000, 60000, 75000, 90000]
                };
            } else {
                // 30 days default
                return {
                    labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
                    data: [25000, 35000, 30000, 40000, 45000, 55000, 60000, 70000]
                };
            }
        }

        // Initialize charts
        let membershipChart;
        let revenueChart;

        async function initializeCharts() {
            // Initial data
            const membershipData = await fetchMembershipData(30);
            const revenueData = await fetchRevenueData(30);

            // Membership Chart
            const membershipCtx = document.getElementById('membershipChart').getContext('2d');
            membershipChart = new Chart(membershipCtx, {
                type: 'line',
                data: {
                    labels: membershipData.labels,
                    datasets: [{
                        label: 'New Members',
                        data: membershipData.data,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: revenueData.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: revenueData.data,
                        backgroundColor: '#10b981',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Update charts based on time range selection
        document.getElementById('membershipTimeRange').addEventListener('change', async function() {
            const days = parseInt(this.value);
            const data = await fetchMembershipData(days);

            membershipChart.data.labels = data.labels;
            membershipChart.data.datasets[0].data = data.data;
            membershipChart.update();
        });

        document.getElementById('revenueTimeRange').addEventListener('change', async function() {
            const days = parseInt(this.value);
            const data = await fetchRevenueData(days);

            revenueChart.data.labels = data.labels;
            revenueChart.data.datasets[0].data = data.data;
            revenueChart.update();
        });        // Initialize everything when the document loads
        document.addEventListener('DOMContentLoaded', initializeCharts);        // AI Predictions functionality
        async function fetchPredictions() {
            const membershipLoader = document.querySelector('#membershipPredictions .prediction-loader');
            const revenueLoader = document.querySelector('#revenuePredictions .prediction-loader');
            const membershipContainer = document.getElementById('membershipPredictions');
            const revenueContainer = document.getElementById('revenuePredictions');
            
            try {
                // Show loaders and add loading class
                membershipLoader.style.display = 'block';
                revenueLoader.style.display = 'block';
                membershipContainer.classList.add('loading');
                revenueContainer.classList.add('loading');

                const response = await fetch('../api/get_predictions.php');
                if (!response.ok) throw new Error('Failed to fetch predictions');
                
                const result = await response.json();
                
                if (result.success) {
                    updatePredictionDisplay('membershipPredictions', result.predictions.membership);
                    updatePredictionDisplay('revenuePredictions', result.predictions.revenue);
                } else {
                    throw new Error(result.error || 'Failed to generate predictions');
                }
            } catch (error) {
                console.error('Error fetching predictions:', error);
                // Show error message
                updatePredictionDisplay('membershipPredictions', {
                    prediction: 'Unable to generate predictions',
                    confidence: 'low',
                    insights: ['Prediction service temporarily unavailable']
                });
                updatePredictionDisplay('revenuePredictions', {
                    prediction: 'Unable to generate predictions', 
                    confidence: 'low',
                    insights: ['Prediction service temporarily unavailable']
                });
            } finally {
                // Hide loaders and remove loading class
                membershipLoader.style.display = 'none';
                revenueLoader.style.display = 'none';
                membershipContainer.classList.remove('loading');
                revenueContainer.classList.remove('loading');
            }
        }        function updatePredictionDisplay(elementId, prediction) {
            const element = document.getElementById(elementId);
            const textElement = element.querySelector('.prediction-text');
            const insightsList = element.querySelector('.insights-list');

            // Update prediction text
            textElement.textContent = prediction.prediction || 'No prediction available';

            // Update insights
            insightsList.innerHTML = '';
            if (prediction.insights && Array.isArray(prediction.insights)) {
                prediction.insights.forEach(insight => {
                    const li = document.createElement('li');
                    // Clean up any incorrect formatting first
                    let cleanedText = insight.replace(/\*+/g, function(match) {
                        return match.length % 2 === 0 ? match : match + '*';
                    });
                    
                    // Convert markdown-style bold (**text**) to HTML bold
                    // Handle nested and multiple bold sections
                    let formattedText = '';
                    let bold = false;
                    let lastIndex = 0;
                    let regex = /\*\*/g;
                    let match;
                    
                    while ((match = regex.exec(cleanedText)) !== null) {
                        // Add the text before this match
                        formattedText += cleanedText.substring(lastIndex, match.index);
                        // Add the appropriate opening or closing tag
                        formattedText += bold ? '</strong>' : '<strong>';
                        // Toggle bold state
                        bold = !bold;
                        // Update last index
                        lastIndex = match.index + 2;
                    }
                    
                    // Add any remaining text
                    formattedText += cleanedText.substring(lastIndex);
                    
                    // Make sure we close any open tags
                    if (bold) formattedText += '</strong>';
                    
                    li.innerHTML = formattedText;
                    insightsList.appendChild(li);
                });
            }
        }        // Load predictions on page load and refresh every 5 minutes
        document.addEventListener('DOMContentLoaded', () => {
            fetchPredictions();
            
            // Set up refresh interval
            setInterval(fetchPredictions, 5 * 60 * 1000); // Refresh every 5 minutes
            
            // Set up refresh button click handlers
            document.querySelectorAll('.refresh-predictions').forEach(button => {
                button.addEventListener('click', function() {
                    // Add spinning animation to the refresh icon
                    const icon = this.querySelector('i');
                    icon.classList.add('fa-spin');
                    
                    // Fetch fresh predictions
                    fetchPredictions().finally(() => {
                        // Remove spinning animation after 1 second
                        setTimeout(() => {
                            icon.classList.remove('fa-spin');
                        }, 1000);
                    });
                });
            });
        });

        // Add click handlers for chart time range changes to update predictions
        document.getElementById('membershipTimeRange').addEventListener('change', () => {
            setTimeout(fetchPredictions, 1000); // Wait for chart to update first
        });

        document.getElementById('revenueTimeRange').addEventListener('change', () => {
            setTimeout(fetchPredictions, 1000); // Wait for chart to update first
        });
    </script>
</body>

</html>