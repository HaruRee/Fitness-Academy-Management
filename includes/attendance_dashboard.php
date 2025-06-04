<?php
// Attendance Dashboard for Admin
session_start();
require_once '../config/database.php';

// Set timezone to Manila, Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Get filter parameters
$date_range_filter = $_GET['date_range'] ?? 'all_time';
$user_type_filter = $_GET['user_type'] ?? 'all';
$attendance_type_filter = $_GET['attendance_type'] ?? 'all';

// Calculate date range based on filter
$date_conditions = [];
$date_params = [];

switch ($date_range_filter) {
    case 'today':
        $date_conditions[] = "DATE(ar.check_in_time) = CURDATE()";
        break;
    case 'last_7_days':
        $date_conditions[] = "ar.check_in_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'last_30_days':
        $date_conditions[] = "ar.check_in_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'last_90_days':
        $date_conditions[] = "ar.check_in_time >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        break;
    case 'last_365_days':
        $date_conditions[] = "ar.check_in_time >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
        break;
    case 'all_time':
    default:
        // No date filter, show all records
        break;
}

// Build query based on filters
$where_conditions = [];
$params = [];

// Add date range conditions
if (!empty($date_conditions)) {
    $where_conditions = array_merge($where_conditions, $date_conditions);
    $params = array_merge($params, $date_params);
}

if ($user_type_filter !== 'all') {
    $where_conditions[] = "ar.user_type = ?";
    $params[] = $user_type_filter;
}

if ($attendance_type_filter !== 'all') {
    $where_conditions[] = "ar.attendance_type = ?";
    $params[] = $attendance_type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get attendance records
$stmt = $conn->prepare("
    SELECT 
        ar.*,
        u.First_Name,
        u.Last_Name,
        u.Email,
        scanner.First_Name as Scanner_First_Name,
        scanner.Last_Name as Scanner_Last_Name,
        CASE 
            WHEN ar.session_id IS NOT NULL THEN ats.session_name
            ELSE NULL
        END as session_name,
        ar.time_out as check_out_time
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.UserID
    LEFT JOIN users scanner ON ar.scanned_by_user_id = scanner.UserID
    LEFT JOIN attendance_sessions ats ON ar.session_id = ats.id
    $where_clause
    ORDER BY ar.check_in_time DESC
");
$stmt->execute($params);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for the selected date range
$stats_where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_checkins,
        COUNT(DISTINCT user_id) as unique_users,
        SUM(CASE WHEN user_type = 'Member' THEN 1 ELSE 0 END) as member_checkins,
        SUM(CASE WHEN user_type = 'Coach' THEN 1 ELSE 0 END) as coach_checkins,
        SUM(CASE WHEN attendance_type = 'gym_entry' THEN 1 ELSE 0 END) as gym_entries,
        SUM(CASE WHEN attendance_type = 'class_session' THEN 1 ELSE 0 END) as class_attendances
    FROM attendance_records ar
    $stats_where_clause
");
$stats_stmt->execute($params);
$daily_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get coach work hours for the selected date range
$coach_where_conditions = ['ar.user_type = ?'];
$coach_params = ['Coach'];

// Add the same date and other filters for coach data
if (!empty($date_conditions)) {
    $coach_where_conditions = array_merge($coach_where_conditions, $date_conditions);
    $coach_params = array_merge($coach_params, $date_params);
}

$coach_where_clause = 'WHERE ' . implode(' AND ', $coach_where_conditions);

$coach_hours_stmt = $conn->prepare("
    SELECT 
        u.First_Name,
        u.Last_Name,
        COUNT(DISTINCT ar.session_id) as classes_taught,
        COUNT(CASE WHEN ar.attendance_type = 'gym_entry' THEN 1 END) as shift_checkins,
        ar.check_in_time,
        ar.time_out as check_out_time,
        TIMESTAMPDIFF(MINUTE, ar.check_in_time, ar.time_out) as duration_minutes
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.UserID
    $coach_where_clause
    AND ar.time_out IS NOT NULL
    GROUP BY ar.user_id, u.First_Name, u.Last_Name, ar.check_in_time, ar.time_out
    ORDER BY u.First_Name, u.Last_Name, ar.check_in_time DESC
");
$coach_hours_stmt->execute($coach_params);
$coach_hours = $coach_hours_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the date range label for display
$date_range_label = 'All Time';
switch ($date_range_filter) {
    case 'today':
        $date_range_label = 'Today (' . date('M j, Y') . ')';
        break;
    case 'last_7_days':
        $date_range_label = 'Last 7 Days';
        break;
    case 'last_30_days':
        $date_range_label = 'Last 30 Days';
        break;
    case 'last_90_days':
        $date_range_label = 'Last 90 Days';
        break;
    case 'last_365_days':
        $date_range_label = 'Last 365 Days';
        break;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard | Fitness Academy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
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
            background: rgba(0, 0, 0, 0.2);
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

        .section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .section h2 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-item label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-color);
        }

        .filter-item select,
        .filter-item input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            font-weight: 600;
            color: var(--gray-color);
            background: #f9fafb;
        }

        tr:hover {
            background: #f9fafb;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }        .badge-member {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .badge-coach {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-staff {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }

        .badge-gym {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .badge-class {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }

        .badge-work_shift {
            background: rgba(139, 69, 19, 0.1);
            color: #8B4513;
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
            .filters {
                flex-direction: column;
            }

            .filter-item {
                min-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 1rem;
            }

            .main-content {
                padding: 1.5rem;
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
            <div class="sidebar-menu-header">DASHBOARD</div>
            <a href="admin_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>

            <div class="sidebar-menu-header">MANAGEMENT</div>
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
            <a href="track_payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payment Status</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>            <div class="sidebar-menu-header">ATTENDANCE</div>            <a href="../attendance/checkin.php">
                <i class="fas fa-sign-in-alt"></i>
                <span>Check In</span>
            </a>
            <a href="../attendance/checkout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Check Out</span>
            </a>
            <a href="attendance_dashboard.php" class="active">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
            </a>

            <div class="sidebar-menu-header">POINT OF SALE</div>
            <a href="pos_system.php">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>

            <div class="sidebar-menu-header">REPORTS</div>
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

            <div class="sidebar-menu-header">DATABASE</div>
            <a href="database_management.php">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>

            <div class="sidebar-menu-header">ACCOUNT</div>
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
        <header class="header">
            <h1>Attendance Dashboard</h1>            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            </div>
        </header>

        <main class="main-content">
            <div class="welcome-header">
                <p>Track and manage gym attendance records.</p>
            </div>            <div class="section">
                <h2>Attendance Statistics (<?php echo $date_range_label; ?>)</h2>
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <h3 style="color: var(--gray-color); font-size: 0.9rem; margin-bottom: 0.5rem;">Total Check-ins</h3>
                        <div style="font-size: 1.8rem; font-weight: 600; color: var(--dark-color);"><?php echo $daily_stats['total_checkins'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <h3 style="color: var(--gray-color); font-size: 0.9rem; margin-bottom: 0.5rem;">Unique Users</h3>
                        <div style="font-size: 1.8rem; font-weight: 600; color: var(--dark-color);"><?php echo $daily_stats['unique_users'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <h3 style="color: var(--gray-color); font-size: 0.9rem; margin-bottom: 0.5rem;">Member Check-ins</h3>
                        <div style="font-size: 1.8rem; font-weight: 600; color: var(--dark-color);"><?php echo $daily_stats['member_checkins'] ?? 0; ?></div>
                    </div>
                    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <h3 style="color: var(--gray-color); font-size: 0.9rem; margin-bottom: 0.5rem;">Coach Check-ins</h3>
                        <div style="font-size: 1.8rem; font-weight: 600; color: var(--dark-color);"><?php echo $daily_stats['coach_checkins'] ?? 0; ?></div>
                    </div>
                </div>                <form method="GET" action="">
                <div class="filters">
                    <div class="filter-item">
                        <label for="date_range">Date Range</label>
                        <select id="date_range" name="date_range" onchange="this.form.submit()">
                            <option value="all_time" <?php echo $date_range_filter === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $date_range_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="last_7_days" <?php echo $date_range_filter === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="last_30_days" <?php echo $date_range_filter === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="last_90_days" <?php echo $date_range_filter === 'last_90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="last_365_days" <?php echo $date_range_filter === 'last_365_days' ? 'selected' : ''; ?>>Last 365 Days</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="user_type">User Type</label>
                        <select id="user_type" name="user_type" onchange="this.form.submit()">
                            <option value="all" <?php echo $user_type_filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="Member" <?php echo $user_type_filter === 'Member' ? 'selected' : ''; ?>>Members</option>
                            <option value="Coach" <?php echo $user_type_filter === 'Coach' ? 'selected' : ''; ?>>Coaches</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="attendance_type">Attendance Type</label>
                        <select id="attendance_type" name="attendance_type" onchange="this.form.submit()">
                            <option value="all" <?php echo $attendance_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="gym_entry" <?php echo $attendance_type_filter === 'gym_entry' ? 'selected' : ''; ?>>Gym Entry</option>
                            <option value="class_session" <?php echo $attendance_type_filter === 'class_session' ? 'selected' : ''; ?>>Class Session</option>
                        </select>
                    </div>
                </div>
                </form>

                <div class="table-container">
                    <table>                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Name</th>
                                <th>User Type</th>
                                <th>Status</th>
                            </tr>
                        </thead><tbody>                            <?php if (empty($attendance_records)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem;">
                                        <div style="color: var(--gray-color);">
                                            <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                            <strong>No attendance records found for <?php echo $date_range_label; ?></strong>
                                            <br><br>
                                            <small>Try selecting a different date range using the dropdown above, or check if there are any recent check-ins.</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('g:i:s A', strtotime($record['check_in_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['First_Name'] . ' ' . $record['Last_Name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($record['user_type']); ?>">
                                                <?php echo $record['user_type']; ?>
                                            </span>
                                        </td>                                        <td>
                                            <span class="badge badge-<?php echo $record['time_out'] ? 'class' : 'gym'; ?>">
                                                <?php echo $record['time_out'] ? 'Checked out' : 'Checked in'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>            <div class="section">
                <h2>Coach Work Summary (<?php echo $date_range_label; ?>)</h2>
                <div class="table-container">
                    <table>                        <thead>
                            <tr>
                                <th>Coach Name</th>
                                <th>Shift Check-ins</th>
                                <th>Check in time</th>
                                <th>Check out time</th>
                                <th>Estimated Hours</th>
                            </tr>
                        </thead><tbody>
                            <?php if (empty($coach_hours)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem;">
                                        <div style="color: var(--gray-color);">
                                            <i class="fas fa-user-tie" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                            <strong>No coach attendance records found for <?php echo $date_range_label; ?></strong>
                                            <br><br>
                                            <small>Coach work hours will appear here once coaches check in for shifts or classes.</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($coach_hours as $coach): ?>                                    <?php
                                    $estimated_hours = 0;
                                    if (isset($coach['duration_minutes']) && $coach['duration_minutes'] > 0) {
                                        $estimated_hours = round($coach['duration_minutes'] / 60, 1);
                                    } elseif ($coach['check_in_time'] && $coach['check_out_time']) {
                                        // Fallback calculation if duration_minutes is not available
                                        $first = new DateTime($coach['check_in_time']);
                                        $last = new DateTime($coach['check_out_time']);
                                        $estimated_hours = round($last->diff($first)->h + ($last->diff($first)->i / 60), 1);
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($coach['First_Name'] . ' ' . $coach['Last_Name']); ?></td>
                                        <td><?php echo $coach['shift_checkins']; ?></td>                                        <td><?php echo $coach['check_in_time'] ? date('M d, g:i A', strtotime($coach['check_in_time'])) : '-'; ?></td>
                                        <td><?php echo $coach['check_out_time'] ? date('M d, g:i A', strtotime($coach['check_out_time'])) : '-'; ?></td>
                                        <td><?php echo $estimated_hours; ?>h</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>    <script>
        // Handle form submission for filters
        document.querySelectorAll('select').forEach(element => {
            element.addEventListener('change', function() {
                const dateRange = document.getElementById('date_range').value;
                const userType = document.getElementById('user_type').value;
                const attendanceType = document.getElementById('attendance_type').value;

                window.location.href = `attendance_dashboard.php?date_range=${dateRange}&user_type=${userType}&attendance_type=${attendanceType}`;
            });
        });
    </script>
</body>

</html>