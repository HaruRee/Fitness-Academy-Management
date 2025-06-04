<?php
session_start();
require '../config/database.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle application approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if ($action === 'approve' || $action === 'reject') {
        try {
            $conn->beginTransaction();

            $status = ($action === 'approve') ? 'approved' : 'rejected';

            // If approving, create a user account first
            if ($action === 'approve') {
                // Get application details
                $stmt = $conn->prepare("SELECT * FROM coach_applications WHERE id = ?");
                $stmt->execute([$application_id]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);                if ($application) {
                    // Use the existing first_name and last_name from database
                    $first_name = $application['first_name'];
                    $last_name = $application['last_name'];

                    // Generate username from email (part before @)
                    $username = explode('@', $application['email'])[0];

                    // Check if username already exists, if so, add number suffix
                    $original_username = $username;
                    $counter = 1;
                    while (true) {
                        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = ?");
                        $stmt->execute([$username]);
                        if ($stmt->rowCount() == 0) break;
                        $username = $original_username . $counter;
                        $counter++;
                    }

                    // Generate a temporary password (user should change this)
                    $temp_password = bin2hex(random_bytes(8)); // 16 character random password
                    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);                    // Create user account
                    $stmt = $conn->prepare("
                        INSERT INTO users (Username, PasswordHash, Email, Role, First_Name, Last_Name, Phone, Address, DateOfBirth, IsActive, is_approved, email_confirmed) 
                        VALUES (?, ?, ?, 'Coach', ?, ?, ?, ?, ?, 1, 1, 1)
                    ");
                    try {
                        $stmt->execute([
                            $username,
                            $password_hash,
                            $application['email'],
                            $first_name,
                            $last_name,
                            $application['phone'],
                            $application['address'],
                            $application['birthdate']
                        ]);
                    } catch (PDOException $e) {
                        // If UserID field error, try with explicit UserID
                        if (strpos($e->getMessage(), 'UserID') !== false) {
                            // Get next available UserID
                            $userIdStmt = $conn->prepare("SELECT COALESCE(MAX(UserID), 0) + 1 as next_id FROM users");
                            $userIdStmt->execute();
                            $nextUserId = $userIdStmt->fetch(PDO::FETCH_ASSOC)['next_id'];
                            
                            $stmtWithId = $conn->prepare("
                                INSERT INTO users (UserID, Username, PasswordHash, Email, Role, First_Name, Last_Name, Phone, Address, DateOfBirth, IsActive, is_approved, email_confirmed) 
                                VALUES (?, ?, ?, 'Coach', ?, ?, ?, ?, ?, 1, 1, 1)
                            ");
                            $stmtWithId->execute([
                                $nextUserId,
                                $username,
                                $password_hash,
                                $application['email'],
                                $first_name,
                                $last_name,
                                $application['phone'],
                                $application['address'],
                                $application['birthdate']
                            ]);
                        } else {
                            throw $e; // Re-throw if it's a different error
                        }
                    }

                    $user_id = $conn->lastInsertId();

                    // Update admin notes to include login credentials
                    $admin_notes .= "\n\nUser account created:\nUsername: {$username}\nTemporary Password: {$temp_password}\n(Please inform the coach to change their password upon first login)";
                }
            }

            // Update application status
            $stmt = $conn->prepare("
                UPDATE coach_applications 
                SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $admin_notes, $_SESSION['user_id'], $application_id]);

            $conn->commit();

            if ($action === 'approve') {
                $message = "Application approved successfully! Coach account has been created.";
            } else {
                $message = "Application has been rejected successfully!";
            }
            $messageType = 'success';

            // Log the action in audit trail
            $action_text = ($action === 'approve') ? 'approved coach application and created user account' : 'rejected coach application';
            $stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (?, ?, NOW())");
            $stmt->execute([$_SESSION['user_name'], $action_text]);
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = 'An error occurred while processing the application.';
            $messageType = 'error';
            error_log("Coach application processing error: " . $e->getMessage());
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get applications with pagination
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

try {
    // Count total applications
    $count_sql = "SELECT COUNT(*) FROM coach_applications $where_clause";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_applications = $stmt->fetchColumn();

    // Get applications
    $sql = "
        SELECT ca.*, u.Username as reviewed_by_name 
        FROM coach_applications ca
        LEFT JOIN users u ON ca.reviewed_by = u.UserID
        $where_clause
        ORDER BY ca.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = ceil($total_applications / $limit);

    // Get statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM coach_applications
    ";
    $stmt = $conn->prepare($stats_sql);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching applications: " . $e->getMessage();
    $applications = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Applications - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            --info-color: #3498db;
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
            margin-bottom: 0.25rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Main Content */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-info {
            background: var(--info-color);
            color: white;
        }

        .btn-secondary {
            background: var(--gray-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
            border-color: rgba(39, 174, 96, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border-color: rgba(231, 76, 60, 0.2);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-left: 4px solid;
        }

        .stat-card.pending {
            border-left-color: var(--warning-color);
        }

        .stat-card.approved {
            border-left-color: var(--success-color);
        }

        .stat-card.rejected {
            border-left-color: var(--danger-color);
        }

        .stat-card.total {
            border-left-color: var(--info-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filters */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(228, 30, 38, 0.1);
        }

        /* Applications Table */
        .applications-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .applications-table {
            width: 100%;
            border-collapse: collapse;
        }

        .applications-table th,
        .applications-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .applications-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
        }

        .applications-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }

        .status-approved {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .status-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-color);
        }

        .close:hover {
            color: var(--dark-color);
        }

        .application-details {
            display: grid;
            gap: 1rem;
        }

        .detail-item {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 1rem;
        }

        .detail-label {
            font-weight: 600;
            color: var(--gray-color);
        }

        .detail-value {
            color: var(--dark-color);
        }        .resume-link {
            color: var(--primary-color);
            text-decoration: none;
        }

        .resume-link:hover {
            text-decoration: underline;
        }

        /* Resume Modal specific styles */
        #resumeModal .modal-content {
            max-width: 900px;
            max-height: 90vh;
        }

        #resumeViewer {
            max-height: 600px;
            overflow-y: auto;
        }

        #resumeViewer iframe {
            min-height: 500px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: var(--dark-color);
        }

        .pagination a:hover,
        .pagination a.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Responsive */
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

            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-wrapper {
                margin-left: 0;
                padding: 1rem;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .applications-table {
                font-size: 0.8rem;
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
            <a href="coach_applications.php" class="active">
                <i class="fas fa-user-tie"></i>
                <span>Coach Applications</span>
            </a>
            <a href="admin_video_approval.php">
                <i class="fas fa-video"></i>
                <span>Video Approval</span>
            </a>
            <a href="track_payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payment Status</span>
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
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="header">
            <h1 class="page-title">Coach Applications</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-value"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-value"><?= $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-value"><?= $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Applications</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" class="form-control"
                        placeholder="Name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="applications-card">
            <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <i class="fas fa-user-tie" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                    <p style="color: var(--gray-color); font-size: 1.1rem;">No coach applications found.</p>
                </div>
            <?php else: ?>
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></td>
                                <td><?= htmlspecialchars($app['email']) ?></td>
                                <td><?= htmlspecialchars($app['phone']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $app['status'] ?>">
                                        <?= ucfirst($app['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($app['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewApplication(<?= htmlspecialchars(json_encode($app)) ?>)"
                                            class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <button onclick="processApplication(<?= $app['id'] ?>, 'approve')"
                                                class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button onclick="processApplication(<?= $app['id'] ?>, 'reject')"
                                                class="btn btn-danger btn-sm">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>"
                                class="<?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Application Details</h2>
                <button class="close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div id="applicationDetails" class="application-details">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Process Application Modal -->
    <div id="processModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="processModalTitle">Process Application</h2>
                <button class="close" onclick="closeModal('processModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" id="processApplicationId" name="application_id">
                <input type="hidden" id="processAction" name="action">

                <div class="form-group">
                    <label for="admin_notes">Admin Notes (Optional)</label>
                    <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3"
                        placeholder="Add any notes about this decision..."></textarea>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeModal('processModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" id="processSubmitBtn" class="btn">Confirm</button>
                </div>
            </form>        </div>
    </div>

    <!-- Resume Viewer Modal -->
    <div id="resumeModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title" id="resumeTitle">Resume</h2>
                <button class="close" onclick="closeModal('resumeModal')">&times;</button>
            </div>
            <div id="resumeViewer" style="padding: 20px;">
                <!-- Resume content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function viewApplication(app) {
            const details = document.getElementById('applicationDetails');
            details.innerHTML = `                <div class="detail-item">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value">${app.first_name} ${app.last_name}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${app.email}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">${app.phone}</span>
                </div>                <div class="detail-item">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value">${app.address}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date of Birth:</span>
                    <span class="detail-value">${app.birthdate ? new Date(app.birthdate).toLocaleDateString('en-US', {
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric'
                    }) : 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Coach License:</span>
                    <span class="detail-value">${app.license_number || 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Experience:</span>
                    <span class="detail-value">${app.experience || 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Specialization:</span>
                    <span class="detail-value">${app.specialization || 'Not provided'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Motivation:</span>
                    <span class="detail-value">${app.why_coach || 'Not provided'}</span>
                </div>                <div class="detail-item">
                    <span class="detail-label">Resume:</span>
                    <span class="detail-value">
                        ${app.resume_path 
                            ? `<button onclick="viewResume('${app.resume_path}', '${app.first_name} ${app.last_name}')" class="resume-link" style="background:none;border:none;color:#e41e26;cursor:pointer;text-decoration:underline;">
                                 <i class="fas fa-eye"></i> View Resume
                               </button>`
                            : 'No resume uploaded'
                        }
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-${app.status}">${app.status.charAt(0).toUpperCase() + app.status.slice(1)}</span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Applied:</span>
                    <span class="detail-value">${new Date(app.created_at).toLocaleDateString('en-US', {
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</span>
                </div>
                ${app.admin_notes ? `
                    <div class="detail-item">
                        <span class="detail-label">Admin Notes:</span>
                        <span class="detail-value">${app.admin_notes}</span>
                    </div>
                ` : ''}
                ${app.reviewed_by_name ? `
                    <div class="detail-item">
                        <span class="detail-label">Reviewed By:</span>
                        <span class="detail-value">${app.reviewed_by_name}</span>
                    </div>
                ` : ''}
                ${app.reviewed_at ? `
                    <div class="detail-item">
                        <span class="detail-label">Reviewed:</span>
                        <span class="detail-value">${new Date(app.reviewed_at).toLocaleDateString('en-US', {
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}</span>
                    </div>
                ` : ''}
            `;

            document.getElementById('viewModal').style.display = 'block';
        }

        function processApplication(applicationId, action) {
            document.getElementById('processApplicationId').value = applicationId;
            document.getElementById('processAction').value = action;

            const title = document.getElementById('processModalTitle');
            const submitBtn = document.getElementById('processSubmitBtn');

            if (action === 'approve') {
                title.textContent = 'Approve Application';
                submitBtn.textContent = 'Approve';
                submitBtn.className = 'btn btn-success';
            } else {
                title.textContent = 'Reject Application';
                submitBtn.textContent = 'Reject';
                submitBtn.className = 'btn btn-danger';
            }

            document.getElementById('processModal').style.display = 'block';
        }        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function viewResume(resumePath, applicantName) {
            const resumeModal = document.getElementById('resumeModal');
            const resumeTitle = document.getElementById('resumeTitle');
            const resumeViewer = document.getElementById('resumeViewer');
            
            resumeTitle.textContent = `Resume - ${applicantName}`;
            
            // Get file extension to determine how to display
            const fileExtension = resumePath.split('.').pop().toLowerCase();
            const resumeUrl = `../uploads/coach_resumes/${resumePath}`;
            
            if (fileExtension === 'pdf') {
                resumeViewer.innerHTML = `<iframe src="${resumeUrl}" style="width:100%;height:500px;border:none;"></iframe>`;
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                resumeViewer.innerHTML = `<img src="${resumeUrl}" style="width:100%;height:auto;border-radius:8px;" alt="Resume">`;
            } else {
                resumeViewer.innerHTML = `
                    <div style="text-align:center;padding:40px;">
                        <i class="fas fa-file-alt" style="font-size:48px;color:#ccc;margin-bottom:16px;"></i>
                        <p>This file format cannot be previewed directly.</p>
                        <a href="${resumeUrl}" target="_blank" class="btn btn-primary">
                            <i class="fas fa-download"></i> Download to View
                        </a>
                    </div>
                `;
            }
            
            resumeModal.style.display = 'block';
        }        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const processModal = document.getElementById('processModal');
            const resumeModal = document.getElementById('resumeModal');

            if (event.target === viewModal) {
                viewModal.style.display = 'none';
            }
            if (event.target === processModal) {
                processModal.style.display = 'none';
            }
            if (event.target === resumeModal) {
                resumeModal.style.display = 'none';
            }
        }
    </script>
</body>

</html>