<?php
session_start();
require '../config/database.php';
require 'activity_tracker.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header('Location: login.php');
    exit;
}

// Track page view activity
if (isset($_SESSION['user_id'])) {
    trackPageView($_SESSION['user_id'], 'Staff Dashboard');
}

// Initialize statistics variables
$active_members = 0;
$pending_payments = 0;
$recent_transactions = [];
$recent_audit_trails = [];
$error = null;

try {
    // Active members
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE Role = 'Member' AND IsActive = 1");
    $stmt->execute();
    $active_members = $stmt->fetchColumn();

    // Pending payments
    $stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
    $stmt->execute();
    $pending_payments = $stmt->fetchColumn();

    // Recent transactions (last 3 days)
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

    // Recent audit trails (limited to 6)
    $stmt = $conn->prepare("SELECT id, username, action, timestamp FROM audit_trail ORDER BY timestamp DESC LIMIT 6");
    $stmt->execute();
    $recent_audit_trails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Staff Dashboard | Fitness Academy</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
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
            font-size: 0.85rem;
            color: var(--gray-color);
        }

        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            height: var(--header-height);
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .header-search {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            border-radius: 6px;
            padding: 0.5rem 1rem;
        }

        .header-search input {
            border: none;
            background: transparent;
            outline: none;
            margin-left: 0.5rem;
            font-size: 1rem;
        }

        .header-search i {
            color: var(--gray-color);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;        }
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            background: #f3f4f6;
        }

        .welcome-header {
            margin-bottom: 2rem;
        }

        .welcome-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-header p {
            color: var(--gray-color);
        }

        .stats-grid {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex: 1;
            transition: box-shadow 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
        }

        .icon-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        .icon-green {
            background: #d1fae5;
            color: #10b981;
        }

        .icon-orange {
            background: #fef3c7;
            color: #f59e0b;
        }

        .icon-red {
            background: #fee2e2;
            color: #ef4444;
        }

        .stat-info {}

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.95rem;
        }

        .activity-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
        }

        .activity-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .activity-title {
            font-size: 1.15rem;
            font-weight: 600;
        }

        .activity-actions a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: color 0.2s;
        }

        .activity-actions a:hover {
            color: var(--secondary-color);
        }

        .activity-actions a i {
            font-size: 1rem;
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--primary-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.98rem;
            margin-bottom: 0.2rem;
        }

        .activity-text strong {
            color: var(--primary-color);
        }

        .activity-time {
            color: var(--gray-color);
            font-size: 0.85rem;
        }

        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }

        .transaction-table th,
        .transaction-table td {
            padding: 0.75rem 1rem;
            text-align: left;
        }

        .transaction-table th {
            background: #f3f4f6;
            color: var(--gray-color);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .transaction-table tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25em 0.75em;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-completed {
            background: #d1fae5;
            color: #10b981;
        }

        .status-pending {
            background: #fef3c7;
            color: #f59e0b;
        }

        .status-failed {
            background: #fee2e2;
            color: #ef4444;
        }

        .no-data-message {
            text-align: center;
            color: var(--gray-color);
            padding: 2rem 0;
        }

        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .footer {
            text-align: center;
            color: var(--gray-color);
            font-size: 0.95rem;
            padding: 1.5rem 0 0.5rem 0;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                flex-direction: column;
                gap: 1.5rem;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                padding: 1rem;
            }

            .activity-card {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .main-wrapper {
                margin-left: 70px;
            }

            .sidebar-header h2,
            .sidebar-menu-header,
            .sidebar a span,
            .user-info {
                display: none;
            }

            .sidebar a {
                justify-content: center;
            }

            .user-profile {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 0.5rem;
            }

            .main-content {
                padding: 0.5rem;
            }

            .activity-card {
                padding: 0.5rem;
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
            <a href="staff_dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>
            <div class="sidebar-menu-header">Attendance</div>
            <a href="staff_attendance.php">
                <i class="fas fa-user-check"></i>
                <span>Attendance</span>
            </a>
            <div class="sidebar-menu-header">Members</div>
            <a href="#all_members">
                <i class="fas fa-users"></i>
                <span>All Members</span>
            </a>
            <div class="sidebar-menu-header">POS</div>
            <a href="staff_pos.php">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>
            <div class="sidebar-menu-header">Account</div>
            <a href="staff_settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
        <div class="user-profile">
            <img src="../assets/images/avatar.jpg" alt="Staff" onerror="this.src='../assets/images/fa_logo.png'">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></div>
                <div class="user-role">Staff</div>
            </div>
        </div>
    </aside>
    <!-- Main Content -->
    <div class="main-wrapper">
        <header class="header">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></span>
            </div>
        </header>
        <main class="main-content">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <div class="welcome-header">
                <h1>Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?>!</h1>
                <p>Here's your gym overview for today.</p>
            </div>
            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= number_format($active_members) ?></div>
                        <div class="stat-label">Active Members</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-orange">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= number_format($pending_payments) ?></div>
                        <div class="stat-label">Pending Payments</div>
                    </div>
                </div>
            </div>

            <!-- All Members Section -->
            <div class="activity-card" id="all_members">
                <div class="activity-header">
                    <h3 class="activity-title">All Members</h3>
                </div>
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("SELECT First_Name, Last_Name, IsActive FROM users WHERE Role = 'Member'");
                        $stmt->execute();
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $member) {
                            echo "<tr>
                                <td>" . htmlspecialchars($member['First_Name'] . ' ' . $member['Last_Name']) . "</td>
                                <td>" . ($member['IsActive'] ? 'Active' : 'Inactive') . "</td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity -->
            <!--
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
                                    <i class="fas fa-user"></i>
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
            -->
        </main>
        <footer class="footer">
            <p>&copy; 2025 Fitness Academy. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>