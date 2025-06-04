<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';
$revenueData = [];
$membershipData = [];

// Get date filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Current date

// Get revenue data by date range
try {
    $stmt = $conn->prepare("
        SELECT DATE(payment_date) as date, SUM(amount) as daily_revenue, COUNT(*) as transactions
        FROM payments 
        WHERE status = 'completed'
        AND DATE(payment_date) BETWEEN :start_date AND :end_date
        GROUP BY DATE(payment_date)
        ORDER BY date
    ");

    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the revenue data for the chart
    $dates = [];
    $revenue = [];
    $transactions = [];

    foreach ($revenueData as $row) {
        $dates[] = date('M d', strtotime($row['date']));
        $revenue[] = $row['daily_revenue'];
        $transactions[] = $row['transactions'];
    }
} catch (PDOException $e) {
    $error = "Error fetching revenue data: " . $e->getMessage();
}

// Get membership data by plan
try {
    $stmt = $conn->prepare("
        SELECT mp.name as plan_name, COUNT(u.UserID) as member_count
        FROM users u
        JOIN membershipplans mp ON mp.id = u.plan_id
        WHERE u.plan_id IS NOT NULL
        GROUP BY mp.name
        ORDER BY member_count DESC
    ");

    $stmt->execute();
    $membershipData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the membership data for the chart
    $planNames = [];
    $memberCounts = [];

    foreach ($membershipData as $row) {
        $planNames[] = $row['plan_name'];
        $memberCounts[] = $row['member_count'];
    }
} catch (PDOException $e) {
    $error = "Error fetching membership data: " . $e->getMessage();
}

// Get registration data by month
try {
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(RegistrationDate, '%Y-%m') as month, 
               COUNT(*) as registrations,
               SUM(CASE WHEN Role = 'Member' THEN 1 ELSE 0 END) as members,
               SUM(CASE WHEN Role = 'Coach' THEN 1 ELSE 0 END) as coaches
        FROM users
        WHERE RegistrationDate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(RegistrationDate, '%Y-%m')
        ORDER BY month
    ");

    $stmt->execute();
    $registrationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the registration data for the chart
    $months = [];
    $registrations = [];
    $members = [];
    $coaches = [];

    foreach ($registrationData as $row) {
        $months[] = date('M Y', strtotime($row['month'] . '-01'));
        $registrations[] = $row['registrations'];
        $members[] = $row['members'];
        $coaches[] = $row['coaches'];
    }
} catch (PDOException $e) {
    $error = "Error fetching registration data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Fitness Academy</title>
    <link rel="stylesheet" href="../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
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

        .stat-icon.icon-orange {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
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

        .stat-subtitle {
            font-size: 0.75rem;
            color: var(--gray-color);
            margin-top: 0.25rem;
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

        .form-control {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
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

        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-outline-success {
            background: transparent;
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .btn-outline-success:hover {
            background: var(--success-color);
            color: white;
        }

        .btn-outline-info {
            background: transparent;
            color: #06b6d4;
            border: 1px solid #06b6d4;
        }

        .btn-outline-info:hover {
            background: #06b6d4;
            color: white;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem 0;
        }

        /* Report Generation Grid */
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 1rem;
            }

            .chart-container {
                height: 250px;
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
            <a href="track_payments.php">
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
            <a href="report_generation.php" class="active">
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
            <h1 class="page-title">Analytics & Reports</h1>
        </div>

        <div class="content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Date Filter Controls -->
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i>
                            Update Reports
                        </button>
                    </div>
                </form>
            </div>

            <!-- Revenue Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">₱ <?php echo number_format(array_sum($revenue ?? [0]), 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-subtitle"><?php echo count($revenueData); ?> days</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-value"><?php echo array_sum($transactions ?? [0]); ?></div>
                    <div class="stat-label">Total Transactions</div>
                    <div class="stat-subtitle">completed payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-orange">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">₱ <?php
                                                $totalTransactions = array_sum($transactions ?? [0]);
                                                $avgTransaction = $totalTransactions > 0 ? array_sum($revenue ?? [0]) / $totalTransactions : 0;
                                                echo number_format($avgTransaction, 2);
                                                ?></div>
                    <div class="stat-label">Average Transaction</div>
                    <div class="stat-subtitle">per payment</div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    Revenue Trend Analysis
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- Membership Distribution -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-pie-chart"></i>
                        Membership Distribution
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="membershipChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Registration Trend -->
                <div class="content-card">
                    <div class="card-header">
                        <i class="fas fa-user-plus"></i>
                        Registration Trend
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="registrationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Generation -->
            <div class="content-card">
                <div class="card-header">
                    <i class="fas fa-file-export"></i>
                    Generate Reports
                </div>
                <div class="card-body">
                    <div class="report-grid">
                        <a href="generate_report.php?type=revenue&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Revenue Report
                        </a>
                        <a href="generate_report.php?type=membership" class="btn btn-outline-success">
                            <i class="fas fa-users"></i>
                            Membership Report
                        </a>
                        <a href="generate_report.php?type=attendance" class="btn btn-outline-info">
                            <i class="fas fa-calendar-check"></i>
                            Attendance Report
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueChartCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueChartCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates ?? []); ?>,
                datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode($revenue ?? []); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Transactions',
                        data: <?php echo json_encode($transactions ?? []); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Transactions'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Membership Chart
        const membershipChartCtx = document.getElementById('membershipChart').getContext('2d');
        const membershipChart = new Chart(membershipChartCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($planNames ?? []); ?>,
                datasets: [{
                    data: <?php echo json_encode($memberCounts ?? []); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Registration Chart
        const registrationChartCtx = document.getElementById('registrationChart').getContext('2d');
        const registrationChart = new Chart(registrationChartCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months ?? []); ?>,
                datasets: [{
                        label: 'Total Registrations',
                        data: <?php echo json_encode($registrations ?? []); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Members',
                        data: <?php echo json_encode($members ?? []); ?>,
                        backgroundColor: 'rgba(255, 206, 86, 0.7)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Coaches',
                        data: <?php echo json_encode($coaches ?? []); ?>,
                        backgroundColor: 'rgba(153, 102, 255, 0.7)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Registrations'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>