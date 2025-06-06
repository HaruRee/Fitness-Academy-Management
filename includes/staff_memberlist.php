<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
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
    <title>Member List | Fitness Academy</title>
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

        /* Sidebar Styles - with important flags to override any conflicts */
        .sidebar {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid;
        }

        .stat-card.active {
            border-left-color: var(--success-color);
        }

        .stat-card.inactive {
            border-left-color: var(--danger-color);
        }

        .stat-card.pending {
            border-left-color: var(--warning-color);
        }

        .stat-card.verified {
            border-left-color: var(--primary-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .members-table {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .members-table h2 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
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
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .badge-verified {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .badge-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px !important;
                max-width: 80px !important;
                min-width: 80px !important;
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
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }

            .main-content {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
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
            <a href="staff_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>

            <div class="sidebar-menu-header">Attendance</div>
            <a href="staff_attendance.php">
                <i class="fas fa-user-check"></i>
                <span>Attendance</span>
            </a>

            <div class="sidebar-menu-header">Members</div>
            <a href="staff_memberlist.php" class="active">
                <i class="fas fa-users"></i>
                <span>Member List</span>
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
            <h1>Member List</h1>
            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></span>
            </div>
        </header>

        <main class="main-content">
            <div class="welcome-header">
                <h1>Member Management</h1>
                <p>View and manage gym members.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card active">
                    <div class="stat-value"><?= $activeMembers ?></div>
                    <div class="stat-label">Active Members</div>
                </div>
                <div class="stat-card inactive">
                    <div class="stat-value"><?= $inactiveMembers ?></div>
                    <div class="stat-label">Inactive Members</div>
                </div>
                <div class="stat-card verified">
                    <div class="stat-value"><?= $verifiedMembers ?></div>
                    <div class="stat-label">Verified Members</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-value"><?= $pendingMembers ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
            </div>

            <!-- Members Table -->
            <div class="members-table">
                <h2>All Members</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Verification</th>
                                <th>Membership</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($members)): ?>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($member['UserID']) ?></td>
                                        <td><?= htmlspecialchars($member['First_Name'] . ' ' . $member['Last_Name']) ?></td>
                                        <td><?= htmlspecialchars($member['Email']) ?></td>
                                        <td><?= htmlspecialchars($member['Phone'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y', strtotime($member['RegistrationDate'])) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $member['IsActive'] ? 'active' : 'inactive' ?>">
                                                <?= $member['IsActive'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $member['is_approved'] ? 'verified' : 'pending' ?>">
                                                <?= $member['is_approved'] ? 'Verified' : 'Pending' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($member['membership_type'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 2rem; color: var(--gray-color);">
                                        <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                        No members found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
