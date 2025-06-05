<?php
session_start();
require '../config/database.php';
date_default_timezone_set('Asia/Manila');

// Ensure only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

// Initialize variables for filtering
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$offset = ($page - 1) * $limit;

try {
    // Build SQL query with filters
    $sql = "SELECT id, username, action, timestamp FROM audit_trail WHERE 1=1";
    $params = [];

    if (!empty($dateFrom)) {
        $sql .= " AND DATE(timestamp) >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $sql .= " AND DATE(timestamp) <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }

    if (!empty($search)) {
        $sql .= " AND (username LIKE ? OR action LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($action)) {
        $sql .= " AND action LIKE ?";
        $params[] = "%$action%";
    }

    // Count total records for pagination
    $countSql = str_replace("id, username, action, timestamp", "COUNT(*) as total", $sql);
    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $totalPages = ceil($totalRecords / $limit);

    // Get audit trails with pagination
    $sql .= " ORDER BY timestamp DESC LIMIT $offset, $limit";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $audit_trails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct actions for filter dropdown
    $stmt = $conn->prepare("SELECT DISTINCT LEFT(action, LOCATE(' ', action)) as action_type FROM audit_trail");
    $stmt->execute();
    $action_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Clean up action types
    $action_types = array_map(function ($item) {
        return trim($item);
    }, array_filter($action_types));
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail | Fitness Academy</title>
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
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
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
            color: var(--dark-color);
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
        }

        .btn-reset {
            background: #f3f4f6;
            color: var(--gray-color);
        }

        .btn-reset:hover {
            background: #e5e7eb;
            color: var(--dark-color);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .data-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-color);
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            color: var(--dark-color);
            font-size: 0.95rem;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background: #f9fafb;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 6px;
            background: white;
            color: var(--dark-color);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: #f3f4f6;
        }

        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .footer {
            background: white;
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-login {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .badge-register {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-update {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .badge-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .badge-payment {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-color);
        }

        .empty-state i {
            font-size: 3rem;
            color: #e5e7eb;
            margin-bottom: 1rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
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
            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
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
            </a>            <a href="coach_applications.php">
                <i class="fas fa-user-tie"></i>
                <span>Coach Applications</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>            <div class="sidebar-menu-header">Attendance</div>            <a href="../attendance/checkin.php">
                <i class="fas fa-sign-in-alt"></i>
                <span>Check In</span>
            </a>
            <a href="../attendance/checkout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Check Out</span>
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
            <a href="transaction_history.php">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>            <a href="audit_trail.php" class="active">
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
            <div class="header-title">Audit Trail</div>
            <div class="header-actions">
                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($error)): ?>
                <div style="background-color: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?> <div class="page-header">
                <h1 class="page-title">System Audit Trail</h1>
                <div class="page-actions">
                    <a href="audit_trail.php?action=payment" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i> View Payment Activities
                    </a>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-card">
                <form action="" method="GET" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        <small class="form-text text-muted">Current time in Manila: <?= date('h:i:s A') ?></small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Action Type</label>
                        <select name="action" class="form-control">
                            <option value="">All Actions</option>
                            <?php foreach ($action_types as $type): ?>
                                <?php if (!empty($type)): ?>
                                    <option value="<?= htmlspecialchars($type) ?>" <?= $action === $type ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst($type)) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search username or action..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter Results
                        </button>
                        <a href="audit_trail.php" class="btn btn-reset">
                            <i class="fas fa-sync-alt"></i> Reset Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Data Table -->
            <div class="data-card">
                <?php if (!empty($audit_trails)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Action</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_trails as $trail): ?> <tr>
                                    <td><?= htmlspecialchars($trail['username']) ?></td>
                                    <td>
                                        <?php
                                        $action = strtolower($trail['action']);
                                        $badge_class = 'badge-update'; // Default

                                        if (strpos($action, 'login') !== false) {
                                            $badge_class = 'badge-login';
                                        } elseif (strpos($action, 'register') !== false) {
                                            $badge_class = 'badge-register';
                                        } elseif (strpos($action, 'delete') !== false) {
                                            $badge_class = 'badge-delete';
                                        } elseif (strpos($action, 'payment') !== false) {
                                            $badge_class = 'badge-payment';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= htmlspecialchars($trail['action']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y h:i A', strtotime($trail['timestamp'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No audit records found.</p>
                        <p>Try adjusting your filters or check back later.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&action=<?= urlencode($action) ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&action=<?= urlencode($action) ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);

                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }

                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <li class="page-item">
                            <a class="page-link <?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&action=<?= urlencode($action) ?>&search=<?= urlencode($search) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&action=<?= urlencode($action) ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $totalPages ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&action=<?= urlencode($action) ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>&copy; 2025 Fitness Academy. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>