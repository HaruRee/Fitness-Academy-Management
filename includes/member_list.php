<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$members = [];
$error = '';
$success = '';

// Get all members (users with Role = 'Member')
try {
    // Ensure we only get users with the 'Member' role, no coaches, staff, or admins
    $stmt = $conn->prepare("SELECT * FROM users WHERE Role = 'Member' ORDER BY RegistrationDate DESC");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count active and inactive members
    $activeMembers = 0;
    $inactiveMembers = 0;
    $pendingMembers = 0;
    $verifiedMembers = 0;

    foreach ($members as $member) {
        if ($member['IsActive']) {
            $activeMembers++;
        } else {
            $inactiveMembers++;
        }

        if ($member['is_approved']) {
            $verifiedMembers++;
        } else {
            $pendingMembers++;
        }
    }
} catch (PDOException $e) {
    $error = "Error fetching members: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>Member List - Fitness Academy</title>
    <link rel="stylesheet" href="../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Move Bootstrap CSS after our custom styles so our class overrides take priority -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="../assets/js/auto-logout.js" defer></script>
    <style type="text/css" id="sidebar-override-styles">
        /* Bootstrap Override Styles - More specific selectors to override Bootstrap */
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

        /* Sidebar Styles - With stronger specificity to override Bootstrap */
        body .sidebar,
        html body .sidebar,
        .admin-layout .sidebar,
        div.sidebar,
        aside.sidebar,
        nav.sidebar {
            width: var(--sidebar-width) !important;
            max-width: var(--sidebar-width) !important;
            min-width: var(--sidebar-width) !important;
            background: var(--dark-color) !important;
            color: white !important;
            position: fixed !important;
            height: 100vh !important;
            top: 0 !important;
            left: 0 !important;
            overflow-y: auto !important;
            transition: all 0.3s ease !important;
            z-index: 100 !important;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1) !important;
            border-radius: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            flex-basis: var(--sidebar-width) !important;
            flex-grow: 0 !important;
            flex-shrink: 0 !important;
            display: block !important;
        }

        /* Override any Bootstrap container classes that might affect our sidebar */
        .sidebar.container,
        .sidebar.container-fluid,
        .sidebar.row,
        .sidebar.col,
        .sidebar.col-md,
        .sidebar.col-lg,
        .sidebar[class*="col-"] {
            width: var(--sidebar-width) !important;
            max-width: var(--sidebar-width) !important;
            min-width: var(--sidebar-width) !important;
            padding: 0 !important;
            margin: 0 !important;
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

        .sidebar a,
        body .sidebar a,
        html body .sidebar a {
            display: flex !important;
            align-items: center !important;
            color: rgba(255, 255, 255, 0.7) !important;
            text-decoration: none !important;
            padding: 0.75rem 1.5rem !important;
            transition: all 0.2s ease !important;
            font-size: 0.95rem !important;
            border-left: 4px solid transparent !important;
            border-radius: 0 !important;
            margin: 0 !important;
            background-image: none !important;
            background-color: transparent !important;
            outline: none !important;
            box-shadow: none !important;
            text-transform: none !important;
        }

        .sidebar a:hover,
        .sidebar a.active,
        body .sidebar a:hover,
        body .sidebar a.active,
        html body .sidebar a:hover,
        html body .sidebar a.active {
            background: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-left: 4px solid var(--secondary-color) !important;
            text-decoration: none !important;
        }

        .sidebar a i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .user-profile,
        body .user-profile,
        html body .user-profile,
        .sidebar .user-profile {
            padding: 1.5rem !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
            display: flex !important;
            align-items: center !important;
            margin-top: auto !important;
            background-color: transparent !important;
        }

        .user-profile img,
        body .user-profile img,
        .sidebar .user-profile img {
            width: 42px !important;
            height: 42px !important;
            border-radius: 50% !important;
            object-fit: cover !important;
            background: #e2e8f0 !important;
            margin-right: 0.75rem !important;
        }

        .user-info,
        body .user-info,
        .sidebar .user-info {
            flex: 1 !important;
        }

        .user-name,
        body .user-name,
        .sidebar .user-name {
            font-weight: 600 !important;
            color: white !important;
            font-size: 0.95rem !important;
        }

        .user-role,
        body .user-role,
        .sidebar .user-role {
            color: rgba(255, 255, 255, 0.6) !important;
            font-size: 0.8rem !important;
        }

        /* Stats Grid */
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
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-color);
            margin-top: 0.25rem;
        }

        /* Page Actions */
        .page-actions {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
        }

        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
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

        .form-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background: white;
            min-width: 120px;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 6px;
            border: none;
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

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .card-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .form-control {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        /* Modern Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table th,
        .table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .table th {
            background: #f9fafb;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table tbody tr:hover {
            background: #f9fafb;
        }

        .table td {
            font-size: 0.875rem;
            color: var(--dark-color);
        }

        /* Main Content Styles - With stronger specificity to override Bootstrap */
        body .main-wrapper,
        html body .main-wrapper {
            flex: 1 !important;
            margin-left: var(--sidebar-width) !important;
            display: flex !important;
            flex-direction: column !important;
            max-width: calc(100% - var(--sidebar-width)) !important;
            width: calc(100% - var(--sidebar-width)) !important;
        }

        .header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
        }

        .page-title {
            color: var(--primary-color);
            border-bottom: 2px solid var(--warning-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .card {
            transition: transform 0.3s;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .status-filter {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .active-filter {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.5) inset;
            transform: translateY(-3px);
        }

        .member-stats {
            margin-bottom: 25px;
        }

        .badge {
            margin-right: 3px;
            font-size: 0.8rem;
        }

        #sortDropdown {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            font-size: 0.9rem;
        }

        .table {
            vertical-align: middle;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 992px) {

            body .sidebar,
            html body .sidebar,
            .admin-layout .sidebar {
                width: 80px !important;
                max-width: 80px !important;
                min-width: 80px !important;
                flex-basis: 80px !important;
            }

            .sidebar-header h2,
            .sidebar a span,
            .user-info {
                display: none !important;
            }

            .sidebar a i,
            body .sidebar a i {
                margin-right: 0 !important;
            }

            .sidebar a,
            body .sidebar a,
            html body .sidebar a {
                justify-content: center !important;
            }

            .user-profile,
            body .user-profile {
                justify-content: center !important;
            }

            body .main-wrapper,
            html body .main-wrapper {
                margin-left: 80px !important;
                width: calc(100% - 80px) !important;
                max-width: calc(100% - 80px) !important;
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
        }

        .membership-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        /* Special Bootstrap Override Section - These are specific overrides for Bootstrap classes */
        .sidebar.navbar,
        .sidebar.navbar-collapse,
        .sidebar.navbar-nav,
        .sidebar.navbar-expand,
        .sidebar.navbar-expand-lg,
        .sidebar.navbar-dark,
        .sidebar.bg-dark {
            width: var(--sidebar-width) !important;
            max-width: var(--sidebar-width) !important;
            min-width: var(--sidebar-width) !important;
            flex-basis: var(--sidebar-width) !important;
        }

        /* Force the sidebar width to be exactly the same as admin_dashboard.php */
        .sidebar {
            width: 280px !important;
            max-width: 280px !important;
            min-width: 280px !important;
        }

        /* Make sure the main content area respects the sidebar width */
        @media (min-width: 993px) {
            .main-wrapper {
                width: calc(100% - 280px) !important;
                margin-left: 280px !important;
                max-width: calc(100% - 280px) !important;
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
            </a> <a href="member_list.php" class="active">
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
            </a>            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
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
            <h1 class="page-title">Member List</h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Member Stats -->
        <div class="stats-grid">
            <div class="stat-card status-filter" data-status="all" style="cursor: pointer;">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo count($members); ?></div>
                    <div class="stat-label">All Members</div>
                </div>
            </div>

            <div class="stat-card status-filter" data-status="active" style="cursor: pointer;">
                <div class="stat-icon icon-green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $activeMembers; ?></div>
                    <div class="stat-label">Active Members</div>
                </div>
            </div>

            <div class="stat-card status-filter" data-status="inactive" style="cursor: pointer;">
                <div class="stat-icon icon-red">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $inactiveMembers; ?></div>
                    <div class="stat-label">Inactive Members</div>
                </div>
            </div>
        </div>

        <!-- Actions Button and Filters -->
        <div class="page-actions">
            <div class="action-buttons">                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="fas fa-user-plus"></i> Add New Member
                </button>
            </div>
            <div class="filter-controls">
                <div class="form-group">
                    <label for="statusFilterDropdown" class="form-label"><strong>Filter by Status:</strong></label>                    <select id="statusFilterDropdown" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sortDropdown" class="form-label"><strong>Sort by:</strong></label>
                    <select id="sortDropdown" class="form-select" style="width: auto;">
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="date_asc">Registration (Oldest)</option>
                        <option value="date_desc" selected>Registration (Newest)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Members Table -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Member Management</h3>
                <div class="card-actions">
                    <div class="form-group mb-0">
                        <input type="text" id="memberSearch" class="form-control" placeholder="Search members...">
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" id="membersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Member Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member):
                            $status = $member['IsActive'] ? "active" : "inactive";
                            $approval = $member['is_approved'] ? "approved" : "pending";
                        ?>
                            <tr class="member-row" data-status="<?php echo $status; ?>" data-approval="<?php echo $approval; ?>"
                                data-user-id="<?php echo $member['UserID']; ?>">
                                <td><?php echo htmlspecialchars($member['First_Name'] . ' ' . $member['Last_Name']); ?></td>
                                <td><?php echo !empty($member['Phone']) ? htmlspecialchars($member['Phone']) : '<span class="text-muted"><i class="fas fa-phone-slash"></i> No phone number provided</span>'; ?></td>
                                <td>
                                    <?php if ($member['IsActive']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($member['RegistrationDate'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info edit-member"
                                        data-id="<?php echo $member['UserID']; ?>"
                                        data-username="<?php echo htmlspecialchars($member['Username']); ?>"
                                        data-email="<?php echo htmlspecialchars($member['Email']); ?>"
                                        data-firstname="<?php echo htmlspecialchars($member['First_Name']); ?>"
                                        data-lastname="<?php echo htmlspecialchars($member['Last_Name']); ?>"
                                        data-phone="<?php echo htmlspecialchars($member['Phone'] ?? ''); ?>"
                                        data-address="<?php echo htmlspecialchars($member['Address'] ?? ''); ?>"
                                        data-dob="<?php echo $member['DateOfBirth'] ?? ''; ?>"
                                        data-status="<?php echo $member['IsActive']; ?>"
                                        data-approved="<?php echo $member['is_approved']; ?>"
                                        data-emailconfirmed="<?php echo $member['email_confirmed'] ?? '0'; ?>"
                                        data-emergency="<?php echo htmlspecialchars($member['emergency_contact'] ?? ''); ?>"
                                        data-plan="<?php echo htmlspecialchars($member['membership_plan'] ?? ''); ?>"
                                        data-bs-toggle="modal" data-bs-target="#editMemberModal">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>        </div>
    </div>

    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addMemberModalLabel">Add New Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addMemberForm" action="user_actions.php" method="post">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="role" value="Member">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob">
                            </div>
                            <div class="col-md-6">
                                <label for="membership_plan" class="form-label">Membership Plan</label>
                                <input type="text" class="form-control" id="membership_plan" name="membership_plan">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address (Optional)</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                                    <label class="form-check-label" for="isActive">Account Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isApproved" name="isApproved" checked>
                                    <label class="form-check-label" for="isApproved">Account Approved</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="emailConfirmed" name="emailConfirmed" checked>
                                    <label class="form-check-label" for="emailConfirmed">Email Confirmed</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" form="addMemberForm">Add Member</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Member Modal -->
    <div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="editMemberModalLabel">Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editMemberForm" action="user_actions.php" method="post">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="userId" id="editUserId">
                        <input type="hidden" name="role" value="Member">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="editFirstName" name="firstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="editLastName" name="lastName" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="editUsername" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="editPhone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="editDob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="editDob" name="dob">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editMembership" class="form-label">Membership Plan</label>
                                <input type="text" class="form-control" id="editMembership" name="membership_plan">
                            </div>
                            <div class="col-md-6">
                                <label for="editEmergencyContact" class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control" id="editEmergencyContact" name="emergency_contact">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editAddress" class="form-label">Address (Optional)</label>
                            <textarea class="form-control" id="editAddress" name="address" rows="2"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editIsActive" name="isActive">
                                    <label class="form-check-label" for="editIsActive">Account Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editIsApproved" name="isApproved">
                                    <label class="form-check-label" for="editIsApproved">Account Approved</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editEmailConfirmed" name="emailConfirmed">
                                    <label class="form-check-label" for="editEmailConfirmed">Email Confirmed</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateMemberBtn" form="editMemberForm">Update Member</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to ensure sidebar styles are enforced
        function enforceSidebarStyles() {
            // Apply !important inline styles to ensure sidebar width
            $('.sidebar').css({
                'width': '280px !important',
                'max-width': '280px !important',
                'min-width': '280px !important'
            });

            // Apply correct margin to main wrapper
            $('.main-wrapper').css({
                'margin-left': '280px !important',
                'width': 'calc(100% - 280px) !important',
                'max-width': 'calc(100% - 280px) !important'
            });
        }

        $(document).ready(function() {
            // Apply sidebar fixes after DOM fully loaded
            enforceSidebarStyles();

            // Re-apply after a slight delay to ensure Bootstrap is fully applied
            setTimeout(enforceSidebarStyles, 100);
            // Check for URL parameters for status filtering
            function getUrlParameter(name) {
                name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                var results = regex.exec(location.search);
                return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
            }

            // Apply filter from URL if present
            var urlStatus = getUrlParameter('status');
            if (urlStatus) {
                $("#statusFilterDropdown").val(urlStatus);
                filterByStatus(urlStatus);
                $(".status-filter").removeClass("active-filter");
                $(".status-filter[data-status='" + urlStatus + "']").addClass("active-filter");
            }

            // Handle search functionality
            $("#memberSearch").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#membersTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });

            // Enhanced sorting functionality
            $("#sortDropdown").on("change", function() {
                var value = $(this).val();
                var $tbody = $("#membersTable tbody");
                var rows = $tbody.find("tr").toArray();

                rows.sort(function(a, b) {
                    var aVal, bVal;

                    if (value === "name_asc" || value === "name_desc") {
                        aVal = $(a).find("td:first").text().trim().toLowerCase();
                        bVal = $(b).find("td:first").text().trim().toLowerCase();

                        return value === "name_asc" ?
                            aVal.localeCompare(bVal) :
                            bVal.localeCompare(aVal);
                    } else {
                        // Parse dates for registration date sorting
                        var aDate = new Date($(a).find("td:eq(5)").text());
                        var bDate = new Date($(b).find("td:eq(5)").text());

                        return value === "date_asc" ?
                            aDate - bDate :
                            bDate - aDate;
                    }
                });

                // Reappend sorted rows
                $.each(rows, function(index, row) {
                    $tbody.append(row);
                });
            });

            // Handle status filtering
            $(".status-filter").click(function() {
                var status = $(this).data("status");
                $("#statusFilterDropdown").val(status);
                filterByStatus(status);

                // Highlight the active filter button
                $(".status-filter").removeClass("active-filter");
                $(this).addClass("active-filter");
            });

            // Handle status filtering from dropdown
            $("#statusFilterDropdown").change(function() {
                var status = $(this).val();
                filterByStatus(status);

                // Update card highlights to match
                $(".status-filter").removeClass("active-filter");
                $(".status-filter[data-status='" + status + "']").addClass("active-filter");
            });

            // Common function for filtering by status
            function filterByStatus(status) {
                if (status === "all") {
                    $(".member-row").show();
                } else if (status === "active" || status === "inactive") {
                    $(".member-row").hide();
                    $(".member-row[data-status='" + status + "']").show();
                } else if (status === "pending" || status === "approved") {
                    $(".member-row").hide();
                    $(".member-row[data-approval='" + status + "']").show();
                }
            }

            // Handle delete confirmation
            $(".delete-member").click(function(e) {
                e.preventDefault();
                var userId = $(this).data("id");
                if (confirm("Are you sure you want to delete this member? This action cannot be undone.")) {
                    window.location.href = "manage_users.php?action=delete&id=" + userId;
                }
            });

            // Handle edit member modal data population
            $(".edit-member").click(function() {
                // Debug data attributes to console
                console.log("Member data:", {
                    id: $(this).data("id"),
                    username: $(this).data("username"),
                    email: $(this).data("email"),
                    firstname: $(this).data("firstname"),
                    lastname: $(this).data("lastname"),
                    phone: $(this).data("phone"),
                    address: $(this).data("address"),
                    dob: $(this).data("dob"),
                    status: $(this).data("status"),
                    approved: $(this).data("approved"),
                    emergency: $(this).data("emergency"),
                    plan: $(this).data("plan"),
                    emailconfirmed: $(this).data("emailconfirmed")
                });

                // Set form values with proper error handling
                $("#editUserId").val($(this).data("id") || "");
                $("#editUsername").val($(this).data("username") || "");
                $("#editEmail").val($(this).data("email") || "");
                $("#editFirstName").val($(this).data("firstname") || "");
                $("#editLastName").val($(this).data("lastname") || "");
                $("#editPhone").val($(this).data("phone") || "");
                $("#editAddress").val($(this).data("address") || "");

                // Properly format the date of birth
                var dobValue = $(this).data("dob") || "";
                if (dobValue) {
                    // Make sure date is in YYYY-MM-DD format for the date input
                    var dobDate = new Date(dobValue);
                    if (!isNaN(dobDate.getTime())) {
                        var formattedDob = dobDate.toISOString().split('T')[0];
                        $("#editDob").val(formattedDob);
                    } else {
                        $("#editDob").val(dobValue);
                    }
                } else {
                    $("#editDob").val("");
                }

                $("#editIsActive").prop("checked", $(this).data("status") == 1);
                $("#editIsApproved").prop("checked", $(this).data("approved") == 1);
                $("#editEmergencyContact").val($(this).data("emergency") || "");
                $("#editMembership").val($(this).data("plan") || "");
                $("#editEmailConfirmed").prop("checked", $(this).data("emailconfirmed") == 1);
            });

            // Form validation for add member
            $("#addMemberForm").submit(function(e) {
                var password = $("#password").val();
                var confirmPassword = $("#confirmPassword").val();

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert("Passwords do not match!");
                }
            });

            // Form validation for edit member
            $("#editMemberForm").submit(function(e) {
                var firstName = $("#editFirstName").val().trim();
                var lastName = $("#editLastName").val().trim();
                var username = $("#editUsername").val().trim();
                var email = $("#editEmail").val().trim();
                var userId = $("#editUserId").val().trim();

                if (!firstName || !lastName || !username || !email) {
                    e.preventDefault();
                    alert("Please fill out all required fields!");
                    return;
                }

                if (!userId) {
                    e.preventDefault();
                    console.error("Missing user ID in edit form");
                    alert("Error: User ID is missing. Please try again or contact support.");
                    return;
                }

                // Email validation
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert("Please enter a valid email address!");
                    return;
                }
            });

            // Modal button handling
            $(".modal-footer button[type='submit']").click(function() {
                const formId = $(this).attr("form");
                if (formId) {
                    const $btn = $(this);
                    const originalText = $btn.text();

                    // Show loading state
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                    // Submit the form
                    $("#" + formId).submit();

                    // Reset button after a timeout (in case the form submission fails)
                    setTimeout(function() {
                        $btn.prop('disabled', false).text(originalText);
                    }, 5000);
                }
            });

            // Initialize status filter
            filterByStatus("all");

            // Final enforcement of sidebar styles
            $(window).on('load resize', function() {
                enforceSidebarStyles();
            });
        });
    </script>
</body>

</html>