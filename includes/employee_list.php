<?php
session_start();
require_once '../config/database.php';
require_once 'activity_tracker.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Track page view activity
if (isset($_SESSION['user_id'])) {
    trackPageView($_SESSION['user_id'], 'Employee List');

    // Run automatic deactivation check (throttled to once per hour)
    if (shouldRunAutomaticDeactivation($conn)) {
        runAutomaticDeactivation($conn);
    }
}

// Initialize variables
$employees = [];
$error = '';
$success = '';

// Get all staff and coaches
try {
    $stmt = $conn->prepare("
        SELECT UserID, Username, First_Name, Last_Name, Email, Phone, Role, 
               account_status, last_activity_date, RegistrationDate, IsActive
        FROM users 
        WHERE Role IN ('Coach', 'Staff') 
        ORDER BY Role ASC, First_Name ASC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching employees: " . $e->getMessage();
}

// Get statistics
$totalEmployees = count($employees);
$activeEmployees = count(array_filter($employees, function ($emp) {
    return $emp['IsActive'] == 1;
}));
$coaches = count(array_filter($employees, function ($emp) {
    return $emp['Role'] == 'Coach';
}));
$staff = count(array_filter($employees, function ($emp) {
    return $emp['Role'] == 'Staff';
}));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee List - Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link rel="stylesheet" href="../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --border-color: #e5e7eb;
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
            position: relative;
            background: var(--dark-color);
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
        }

        .user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            height: var(--header-height);
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

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
        }

        /* Cards and Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.25rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
            font-size: 1.2rem;
        }

        .stat-icon.primary {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.secondary {
            background: rgba(255, 107, 107, 0.1);
            color: var(--secondary-color);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Table Styles */
        .table-card {
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9fafb;
        }

        .table-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .employee-table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-table th {
            background: #f9fafb;
            color: var(--gray-color);
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .employee-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .employee-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            border-radius: 9999px;
            letter-spacing: 0.03em;
        }

        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .status-inactive {
            background-color: rgba(107, 114, 128, 0.1);
            color: #374151;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            border-radius: 9999px;
            letter-spacing: 0.03em;
        }

        .role-coach {
            background-color: rgba(37, 99, 235, 0.1);
            color: #1e40af;
        }

        .role-staff {
            background-color: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }

        /* Filters */
        .filters-section {
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
        }

        .form-control {
            padding: 0.6rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.85rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-outline {
            background: transparent;
            color: var(--gray-color);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: var(--light-gray);
            color: var(--dark-color);
        }

        /* Alert styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.2);
        }

        /* Responsive Design */
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
            .main-wrapper {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .header {
                padding: 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .stats-grid {
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
            <a href="employee_list.php" class="active">
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
            <h1 class="page-title">Employee List</h1>
        </div>

        <div class="main-content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Employee Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= $totalEmployees ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value"><?= $activeEmployees ?></div>
                    <div class="stat-label">Active Employees</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-value"><?= $coaches ?></div>
                    <div class="stat-label">Coaches</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="stat-value"><?= $staff ?></div>
                    <div class="stat-label">Staff Members</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Role Filter</label>
                        <select class="form-control" id="roleFilter">
                            <option value="all">All Employees</option>
                            <option value="Coach">Coaches Only</option>
                            <option value="Staff">Staff Only</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status Filter</label>
                        <select class="form-control" id="statusFilter">
                            <option value="all">All Status</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" class="form-control" id="searchFilter" placeholder="Search by name or email...">
                    </div>

                    <div class="filter-group">
                        <button type="button" class="btn btn-outline" onclick="clearFilters()">
                            <i class="fas fa-undo"></i> Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Employee Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3 class="table-title">Employee Directory</h3>
                    <a href="register_coach.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add New Employee
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Last Activity</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $employee): ?>
                                    <tr class="employee-row"
                                        data-role="<?= htmlspecialchars($employee['Role']) ?>"
                                        data-status="<?= $employee['IsActive'] ? 'active' : 'inactive' ?>"
                                        data-search="<?= htmlspecialchars(strtolower($employee['First_Name'] . ' ' . $employee['Last_Name'] . ' ' . $employee['Email'])) ?>">
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: 600;">
                                                    <?= strtoupper(substr($employee['First_Name'], 0, 1) . substr($employee['Last_Name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--dark-color);">
                                                        <?= htmlspecialchars($employee['First_Name'] . ' ' . $employee['Last_Name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--gray-color);">
                                                        @<?= htmlspecialchars($employee['Username']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?= strtolower($employee['Role']) ?>">
                                                <?= htmlspecialchars($employee['Role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="mailto:<?= htmlspecialchars($employee['Email']) ?>" style="color: var(--primary-color); text-decoration: none;">
                                                <?= htmlspecialchars($employee['Email']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($employee['Phone'])): ?>
                                                <a href="tel:<?= htmlspecialchars($employee['Phone']) ?>" style="color: var(--primary-color); text-decoration: none;">
                                                    <?= htmlspecialchars($employee['Phone']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--gray-color);">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = $employee['IsActive'] ? 'status-active' : 'status-inactive';
                                            $statusText = $employee['IsActive'] ? 'Active' : 'Inactive';
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= $statusText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($employee['last_activity_date']): ?>
                                                <span style="color: var(--dark-color);">
                                                    <?= date('M d, Y', strtotime($employee['last_activity_date'])) ?>
                                                </span>
                                                <div style="font-size: 0.75rem; color: var(--gray-color);">
                                                    <?= date('h:i A', strtotime($employee['last_activity_date'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--gray-color);">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="color: var(--dark-color);">
                                                <?= date('M d, Y', strtotime($employee['RegistrationDate'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--gray-color);">
                                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                        <div>No employees found</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Filter functionality
        function filterEmployees() {
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
            const rows = document.querySelectorAll('.employee-row');

            rows.forEach(row => {
                const role = row.getAttribute('data-role');
                const status = row.getAttribute('data-status');
                const searchText = row.getAttribute('data-search');

                const roleMatch = roleFilter === 'all' || role === roleFilter;
                const statusMatch = statusFilter === 'all' || status === statusFilter;
                const searchMatch = searchFilter === '' || searchText.includes(searchFilter);

                if (roleMatch && statusMatch && searchMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function clearFilters() {
            document.getElementById('roleFilter').value = 'all';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('searchFilter').value = '';
            filterEmployees();
        }

        // Add event listeners
        document.getElementById('roleFilter').addEventListener('change', filterEmployees);
        document.getElementById('statusFilter').addEventListener('change', filterEmployees);
        document.getElementById('searchFilter').addEventListener('input', filterEmployees);
    </script>
</body>

</html>