<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

// Get coach information
$coach_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ? AND Role = 'Coach'");
$stmt->execute([$coach_id]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    header("Location: logout.php");
    exit;
}

// Get today's date
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$currentTime = date('H:i:s');

// Get today's classes
$stmt = $conn->prepare("
    SELECT c.*, 
           COUNT(ce.id) AS enrolled_members
    FROM classes c
    LEFT JOIN classenrollments ce ON c.class_id = ce.class_id
    WHERE c.coach_id = ? 
    AND c.class_date = ? 
    GROUP BY c.class_id
    ORDER BY c.start_time
");
$stmt->execute([$coach_id, $today]);
$todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming classes (next 7 days)
$stmt = $conn->prepare("
    SELECT c.*, 
           COUNT(ce.id) AS enrolled_members
    FROM classes c
    LEFT JOIN classenrollments ce ON c.class_id = ce.class_id
    WHERE c.coach_id = ? 
    AND c.class_date > ? 
    AND c.class_date <= DATE_ADD(?, INTERVAL 7 DAY)
    GROUP BY c.class_id
    ORDER BY c.class_date, c.start_time
");
$stmt->execute([$coach_id, $today, $today]);
$upcomingClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned clients
$stmt = $conn->prepare("
    SELECT u.*, 
           MIN(ce.enrollment_date) AS enrollment_date,
           MAX(c.class_date) AS last_class_date
    FROM users u
    JOIN classenrollments ce ON u.UserID = ce.user_id
    JOIN classes c ON ce.class_id = c.class_id
    WHERE c.coach_id = ? 
    GROUP BY u.UserID
    ORDER BY last_class_date DESC
");
$stmt->execute([$coach_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total class count
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_classes
    FROM classes
    WHERE coach_id = ?
");
$stmt->execute([$coach_id]);
$totalClasses = $stmt->fetch(PDO::FETCH_ASSOC)['total_classes'];

// Get total clients count
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.UserID) as total_clients
    FROM users u
    JOIN classenrollments ce ON u.UserID = ce.user_id
    JOIN classes c ON ce.class_id = c.class_id
    WHERE c.coach_id = ?
");
$stmt->execute([$coach_id]);
$totalClients = $stmt->fetch(PDO::FETCH_ASSOC)['total_clients'];

// Get recent notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$coach_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update last login time
$stmt = $conn->prepare("UPDATE users SET LastLogin = NOW() WHERE UserID = ?");
$stmt->execute([$coach_id]);

// Get recent announcements by this coach
$stmt = $conn->prepare("
    SELECT announcement, created_at 
    FROM coach_announcements 
    WHERE coach_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$coach_id]);
$recentAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format time from 24-hour to 12-hour format
function formatTime($time)
{
    return date("g:i A", strtotime($time));
}

// Function to check if a class is currently active
function isClassActive($classDate, $startTime, $endTime)
{
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');

    return ($classDate == $today &&
        $currentTime >= $startTime &&
        $currentTime <= $endTime);
}

// Function to get class status label
function getClassStatusLabel($classDate, $startTime, $endTime)
{
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');

    if ($classDate < $today || ($classDate == $today && $currentTime > $endTime)) {
        return '<span class="badge badge-completed">Completed</span>';
    } elseif ($classDate == $today && $currentTime >= $startTime && $currentTime <= $endTime) {
        return '<span class="badge badge-active">Active Now</span>';
    } elseif ($classDate == $today && $currentTime < $startTime) {
        return '<span class="badge badge-upcoming">Today</span>';
    } else {
        return '<span class="badge badge-scheduled">Scheduled</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Dashboard | Fitness Academy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --primary-color: #e41e26;
            --secondary-color: #333333;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --gray-color: #6c757d;
            --bg-light: #f5f5f5;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, .075);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, .1);
            --shadow-lg: 0 8px 16px rgba(0, 0, 0, .15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            min-height: 100vh;
        }

        /* Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background-color: #222;
            color: #fff;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            transition: all 0.3s;
            box-shadow: var(--shadow-md);
            z-index: 100;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s;
        }

        /* Header */
        .header {
            background-color: #fff;
            box-shadow: var(--shadow-sm);
            padding: 15px 30px;
            position: sticky;
            top: 0;
            z-index: 99;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h2 {
            font-weight: 700;
            color: #333;
        }

        .header-right {
            display: flex;
            align-items: center;
        }

        .user-dropdown {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .user-dropdown:hover {
            background-color: #f1f1f1;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
        }

        .user-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 600;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        /* Sidebar Logo */
        .sidebar-header {
            padding: 20px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }

        .sidebar-header h3 {
            color: #fff;
            font-weight: 700;
            font-size: 1.2rem;
        }

        /* Sidebar Navigation */
        .sidebar-nav {
            padding: 15px 0;
        }

        .nav-section {
            margin-bottom: 10px;
        }

        .nav-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            padding: 10px 25px;
            letter-spacing: 1px;
        }

        .nav-item {
            display: block;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 500;
            border-left: 5px solid transparent;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }

        .nav-item i {
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-item.active {
            background-color: rgba(228, 30, 38, 0.2);
            color: #fff;
            border-left-color: var(--primary-color);
        }

        /* Dashboard Content */
        .content {
            padding: 30px;
        }

        .welcome-banner {
            background: linear-gradient(45deg, #e41e26, #ff6b6b);
            color: #fff;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--gray-color);
            text-transform: uppercase;
        }

        .dashboard-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 20px;
        }

        .card-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Class Schedule Table */
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .schedule-table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: #333;
        }

        .schedule-table tr:last-child td {
            border-bottom: none;
        }

        .schedule-table tr:hover td {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-active {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .badge-upcoming {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .badge-completed {
            background-color: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }

        .badge-scheduled {
            background-color: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }

        /* Client List */
        .client-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .client-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .client-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .client-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .client-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .client-info {
            flex: 1;
        }

        .client-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .client-detail {
            font-size: 0.9rem;
            color: var(--gray-color);
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #c81a21;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        .btn-sm {
            padding: 6px 15px;
            font-size: 0.85rem;
        }

        /* Notifications */
        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .notification-icon.info {
            background-color: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }

        .notification-icon.success {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .notification-icon.warning {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .notification-content {
            flex: 1;
        }

        .notification-text {
            margin-bottom: 5px;
        }        .notification-time {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        /* Alert styles for announcements */
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        /* Announcement item styles */
        .announcement-item {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .announcement-item:hover {
            border-left-color: var(--accent-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Empty states */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 1.1rem;
            color: #999;
        }

        /* Responsive design */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                overflow: visible;
            }

            .sidebar-header h3,
            .nav-section-title,
            .nav-item span {
                display: none;
            }

            .nav-item {
                padding: 15px;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .nav-item i {
                margin-right: 0;
                font-size: 1.3rem;
            }

            .main-content {
                margin-left: 80px;
            }

            .sidebar:hover {
                width: 280px;
            }

            .sidebar:hover .sidebar-header h3,
            .sidebar:hover .nav-section-title,
            .sidebar:hover .nav-item span {
                display: block;
            }

            .sidebar:hover .nav-item {
                padding: 15px 25px;
                justify-content: flex-start;
            }

            .sidebar:hover .nav-item i {
                margin-right: 15px;
                font-size: 1.1rem;
            }
        }

        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }

            .client-list {
                grid-template-columns: 1fr;
            }

            .schedule-table {
                display: block;
                overflow-x: auto;
            }

            .user-name,
            .user-role {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 15px;
            }

            .content {
                padding: 20px 15px;
            }

            .welcome-banner {
                padding: 20px;
            }

            .welcome-banner h2 {
                font-size: 1.5rem;
            }

            .welcome-banner p {
                font-size: 1rem;
            }

            .section-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/images/fa_logo.png" alt="Fitness Academy">
                <h3>Fitness Academy</h3>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="coach_dashboard.php" class="nav-item active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Classes</div>
                    <a href="coach_my_classes.php" class="nav-item">
                        <i class="fas fa-dumbbell"></i>
                        <span>My Classes</span>
                    </a>
                    <a href="coach_class_schedule.php" class="nav-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Class Schedule</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Members</div>
                    <a href="coach_my_clients.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>My Clients</span>
                    </a>
                    <a href="coach_progress_tracking.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Progress Tracking</span>
                    </a>
                </div>                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
                    </a>
                    <a href="#announcements" class="nav-item" onclick="scrollToAnnouncements()">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcements</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="coach_my_profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <h2>Coach Dashboard</h2>
                </div>

                <div class="header-right">
                    <div class="user-dropdown">
                        <div class="user-info">                            <div class="user-image">                                <?php if (!empty($coach['ProfileImage'])): ?>
                                    <?php
                                    // Handle both path formats - full path for coaches, filename only for members
                                    $profileImageSrc = $coach['ProfileImage'];
                                    if (strpos($profileImageSrc, '../') !== 0) {
                                        // If it doesn't start with ../, assume it's just a filename and add the path
                                        $profileImageSrc = '../uploads/' . $profileImageSrc;
                                    }
                                    ?>
                                    <img src="<?= htmlspecialchars($profileImageSrc) ?>" alt="Profile">
                                <?php else: ?>
                                    <img src="../assets/images/avatar.jpg" alt="Profile">
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="user-name"><?= htmlspecialchars($coach['First_Name'] . ' ' . $coach['Last_Name']) ?></div>
                                <div class="user-role">Coach</div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <h2>Welcome, <?= htmlspecialchars($coach['First_Name']) ?>!</h2>
                    <p><?= date('l, F j, Y') ?> · Have a great day ahead!</p>
                </div>

                <!-- Quick Actions Section -->
                <div class="dashboard-section">
                    <div class="section-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <!-- QR Code Section -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-qrcode"></i> My QR Code</h3>
                            </div>
                            <div class="card-body" style="text-align: center;">
                                <p style="color: var(--gray-color); margin-bottom: 20px;">Generate your personal QR code for gym entrance/exit scanners</p>
                                <button class="btn btn-primary" onclick="generateStaticQR()" style="display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 15px 30px; font-size: 16px;">
                                    <i class="fas fa-qrcode"></i>
                                    Generate My QR Code
                                </button>
                                
                                <!-- QR Code Display -->
                                <div id="qrDisplay" style="display: none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                                    <h4 style="margin-bottom: 15px;">Your Attendance QR Code</h4>
                                    <div id="qrCodeContainer" style="margin: 20px auto;">
                                        <!-- QR code will be generated here -->
                                    </div>
                                    <div id="qrMessage" style="margin-bottom: 15px; font-weight: 600; color: var(--primary-color);"></div>
                                    <p style="color: var(--gray-color); font-size: 0.9rem; margin-bottom: 15px;">
                                        <i class="fas fa-info-circle"></i> Show this QR code at gym entrance/exit scanners
                                    </p>
                                    <button class="btn btn-sm" onclick="hideQRDisplay()">Close</button>
                                </div>
                            </div>                        </div>

                        <!-- Announcements Section -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bullhorn"></i> Make Announcement</h3>
                            </div>
                            <div class="card-body">
                                <p style="color: var(--gray-color); margin-bottom: 20px;">Share important updates with your members</p>
                                <form id="announcementForm" onsubmit="addAnnouncement(event)">
                                    <textarea 
                                        id="announcementText" 
                                        name="announcement" 
                                        placeholder="Enter your announcement here..." 
                                        maxlength="500" 
                                        required
                                        style="width: 100%; min-height: 100px; padding: 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-family: inherit; font-size: 14px; line-height: 1.5; resize: vertical; margin-bottom: 15px;"
                                    ></textarea>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                        <small id="charCount" style="color: var(--gray-color);">0/500 characters</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">
                                        <i class="fas fa-bullhorn"></i>
                                        Post Announcement
                                    </button>
                                </form>
                                
                                <!-- Success/Error Messages -->
                                <div id="announcementMessage" style="display: none; margin-top: 15px; padding: 10px; border-radius: 6px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Announcements -->
                    <div id="announcements" class="dashboard-section">
                        <h3 class="section-title">
                            <i class="fas fa-bullhorn"></i> My Recent Announcements
                        </h3>                        <div class="card">
                            <div class="card-body">
                                <div id="announcementsList">
                                    <?php if (count($recentAnnouncements) > 0): ?>
                                        <?php foreach ($recentAnnouncements as $announcement): ?>
                                            <div class="announcement-item" style="padding: 15px; border: 1px solid #e1e5e9; border-radius: 8px; margin-bottom: 15px; background: #f8f9fa;">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                                    <span style="font-size: 0.9rem; color: var(--gray-color);">
                                                        <i class="fas fa-clock"></i> <?= date('M d, Y g:i A', strtotime($announcement['created_at'])) ?>
                                                    </span>
                                                </div>
                                                <p style="margin: 0; color: var(--text-color); line-height: 1.5;"><?= htmlspecialchars($announcement['announcement']) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-bullhorn"></i>
                                            <p>No announcements yet. Create your first announcement above!</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Stats -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <i class="fas fa-dumbbell"></i>
                        <div class="stat-value"><?= $totalClasses ?></div>
                        <div class="stat-label">Total Classes</div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <div class="stat-value"><?= $totalClients ?></div>
                        <div class="stat-label">Total Clients</div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-calendar-day"></i>
                        <div class="stat-value"><?= count($todayClasses) ?></div>
                        <div class="stat-label">Today's Classes</div>
                    </div>

                    <div class="stat-card">
                        <i class="fas fa-calendar-check"></i>
                        <div class="stat-value"><?= count($upcomingClasses) ?></div>
                        <div class="stat-label">Upcoming Classes</div>
                    </div>
                </div>

                <!-- Today's Classes -->
                <div class="dashboard-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-day"></i> Today's Classes
                    </h3>

                    <div class="card">
                        <div class="card-body">
                            <?php if (count($todayClasses) > 0): ?>
                                <div class="table-responsive">
                                    <table class="schedule-table">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Time</th>
                                                <th>Enrolled</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($todayClasses as $class): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($class['class_name']) ?></td>
                                                    <td><?= formatTime($class['start_time']) ?> - <?= formatTime($class['end_time']) ?></td>
                                                    <td><?= $class['enrolled_members'] ?> members</td>
                                                    <td><?= getClassStatusLabel($class['class_date'], $class['start_time'], $class['end_time']) ?></td>
                                                    <td>
                                                        <a href="view_class.php?id=<?= $class['class_id'] ?>" class="btn btn-sm btn-outline">View</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No classes scheduled for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Classes -->
                <div class="dashboard-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt"></i> Upcoming Classes
                    </h3>

                    <div class="card">
                        <div class="card-body">
                            <?php if (count($upcomingClasses) > 0): ?>
                                <div class="table-responsive">
                                    <table class="schedule-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Class</th>
                                                <th>Time</th>
                                                <th>Enrolled</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcomingClasses as $class): ?>
                                                <tr>
                                                    <td><?= date('M d', strtotime($class['class_date'])) ?> (<?= date('D', strtotime($class['class_date'])) ?>)</td>
                                                    <td><?= htmlspecialchars($class['class_name']) ?></td>
                                                    <td><?= formatTime($class['start_time']) ?> - <?= formatTime($class['end_time']) ?></td>
                                                    <td><?= $class['enrolled_members'] ?> members</td>
                                                    <td>
                                                        <a href="view_class.php?id=<?= $class['class_id'] ?>" class="btn btn-sm btn-outline">View</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No upcoming classes scheduled</p>
                                </div>
                            <?php endif; ?>
                        </div>                        <div class="card-footer">
                            <a href="coach_my_classes.php" class="btn btn-outline">View All Classes</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Clients -->
                <div class="dashboard-section">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i> Recent Clients
                    </h3>

                    <div class="card">
                        <div class="card-body">
                            <?php if (count($clients) > 0): ?>
                                <div class="client-list">
                                    <?php
                                    // Display only up to 3 recent clients
                                    $recentClients = array_slice($clients, 0, 6);
                                    foreach ($recentClients as $client):
                                    ?>
                                        <div class="client-card">                                            <div class="client-avatar">
                                                <?php if (!empty($client['ProfileImage'])): ?>
                                                    <img src="../uploads/profile_images/<?= htmlspecialchars($client['ProfileImage']) ?>" alt="<?= htmlspecialchars($client['First_Name']) ?>">
                                                <?php else: ?>
                                                    <img src="../assets/images/avatar.jpg" alt="<?= htmlspecialchars($client['First_Name']) ?>">
                                                <?php endif; ?>
                                            </div>
                                            <div class="client-info">
                                                <div class="client-name"><?= htmlspecialchars($client['First_Name'] . ' ' . $client['Last_Name']) ?></div>
                                                <div class="client-detail">Last class: <?= date('M d', strtotime($client['last_class_date'])) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-friends"></i>
                                    <p>No clients assigned yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($clients) > 6): ?>
                            <div class="card-footer">
                                <a href="my_clients.php" class="btn btn-outline">View All Clients</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Notifications -->
                <div class="dashboard-section">
                    <h3 class="section-title">
                        <i class="fas fa-bell"></i> Recent Notifications
                    </h3>

                    <div class="card">
                        <div class="card-body">
                            <?php if (count($notifications) > 0): ?>
                                <div class="notification-list">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item">
                                            <?php if ($notification['type'] == 'info'): ?>
                                                <div class="notification-icon info">
                                                    <i class="fas fa-info"></i>
                                                </div>
                                            <?php elseif ($notification['type'] == 'success'): ?>
                                                <div class="notification-icon success">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="notification-icon warning">
                                                    <i class="fas fa-exclamation"></i>
                                                </div>
                                            <?php endif; ?>

                                            <div class="notification-content">
                                                <div class="notification-text"><?= htmlspecialchars($notification['message']) ?></div>
                                                <div class="notification-time">
                                                    <?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No notifications at this time</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let qrLibraryLoaded = false;
        let qrLibraryPromise = null;

        // Load QR library with multiple fallbacks
        function loadQRLibrary() {
            if (qrLibraryPromise) {
                return qrLibraryPromise;
            }

            qrLibraryPromise = new Promise((resolve, reject) => {
                // If already loaded
                if (typeof QRCode !== 'undefined') {
                    qrLibraryLoaded = true;
                    resolve();
                    return;
                }

                // Try different QR code libraries with working CDNs
                let attempts = [
                    // QRCode.js library with toCanvas method
                    {
                        url: 'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',
                        type: 'qrcode-npm'
                    },
                    // Alternative QRCode.js
                    {
                        url: 'https://unpkg.com/qrcode@1.5.3/build/qrcode.min.js',
                        type: 'qrcode-npm'
                    },
                    // QRCode-generator (different API)
                    {
                        url: 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js',
                        type: 'qrcode-generator'
                    }
                ];

                let currentAttempt = 0;

                function tryLoadScript() {
                    if (currentAttempt >= attempts.length) {
                        console.error('All QR library sources failed. Initializing fallback...');
                        initializeFallbackQR();
                        resolve(); // Still resolve so the system can continue with fallback
                        return;
                    }

                    const attempt = attempts[currentAttempt];
                    console.log(`Attempting to load QR library from: ${attempt.url} (type: ${attempt.type})`);
                    const script = document.createElement('script');
                    script.src = attempt.url;

                    script.onload = () => {
                        console.log('Script loaded successfully from:', attempt.url);
                        setTimeout(() => {
                            if (attempt.type === 'qrcode-npm') {
                                // For npm qrcode library, check for QRCode.toCanvas
                                if (typeof QRCode !== 'undefined' && QRCode.toCanvas) {
                                    qrLibraryLoaded = true;
                                    resolve();
                                    return;
                                } else if (typeof window.QRCode !== 'undefined') {
                                    window.QRCode = window.QRCode;
                                    if (window.QRCode.toCanvas) {
                                        qrLibraryLoaded = true;
                                        resolve();
                                        return;
                                    }
                                }
                            } else if (attempt.type === 'qrcode-generator') {
                                // For qrcode-generator, create a QRCode.toCanvas wrapper
                                if (typeof qrcode !== 'undefined') {
                                    window.QRCode = window.QRCode || {};
                                    window.QRCode.toCanvas = function(canvas, text, options, callback) {
                                        try {
                                            const qr = qrcode(4, 'M');
                                            qr.addData(text);
                                            qr.make();
                                            const modules = qr.modules;
                                            if (!modules || !modules.length) {
                                                throw new Error('QR modules not generated properly');
                                            }
                                            const size = options.width || 200;
                                            canvas.width = size;
                                            canvas.height = size;
                                            const ctx = canvas.getContext('2d');
                                            ctx.fillStyle = options.color?.light || '#FFFFFF';
                                            ctx.fillRect(0, 0, size, size);
                                            ctx.fillStyle = options.color?.dark || '#000000';
                                            const moduleCount = modules.length;
                                            const cellSize = size / moduleCount;
                                            for (let row = 0; row < moduleCount; row++) {
                                                for (let col = 0; col < moduleCount; col++) {
                                                    if (modules[row] && modules[row][col]) {
                                                        ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
                                                    }
                                                }
                                            }
                                            if (typeof callback === 'function') callback();
                                        } catch (err) {
                                            if (typeof callback === 'function') callback(err);
                                        }
                                    };
                                    qrLibraryLoaded = true;
                                    resolve();
                                    return;
                                }
                            }
                            currentAttempt++;
                            tryLoadScript();
                        }, 300);
                    };

                    script.onerror = (error) => {
                        console.error('Failed to load script from:', attempt.url, error);
                        currentAttempt++;
                        tryLoadScript();
                    };

                    // Add timeout for slow networks
                    setTimeout(() => {
                        if (!qrLibraryLoaded && currentAttempt < attempts.length) {
                            console.log('Timeout loading from:', attempt.url);
                            script.remove(); // Remove the script that timed out
                            currentAttempt++;
                            tryLoadScript();
                        }
                    }, 8000); // Increased timeout to 8 seconds

                    document.head.appendChild(script);
                }

                tryLoadScript();
            });

            return qrLibraryPromise;
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, starting QR library load...');
            loadQRLibrary().then(() => {
                console.log('QR library ready!');
            }).catch(error => {
                console.error('Failed to load QR library:', error);
                // Fallback is already initialized in the promise if all sources fail
                console.log('Using fallback QR generator');
            });
        });

        // Fallback QR generator for when CDN libraries fail
        function initializeFallbackQR() {
            console.log('Initializing fallback QR generator...');
            window.QRCode = {
                toCanvas: function(canvas, text, options, callback) {
                    try {
                        console.log('Using fallback QR generator for text:', text);
                        // Simple QR-like visual representation
                        const ctx = canvas.getContext('2d');
                        const size = options.width || 200;
                        canvas.width = size;
                        canvas.height = size;

                        // Create a simple grid pattern based on text hash
                        const hash = simpleHash(text);
                        const gridSize = 25;
                        const cellSize = size / gridSize;

                        // Clear canvas with light color
                        ctx.fillStyle = options.color?.light || '#FFFFFF';
                        ctx.fillRect(0, 0, size, size);

                        // Draw pattern based on text hash
                        ctx.fillStyle = options.color?.dark || '#000000';
                        for (let row = 0; row < gridSize; row++) {
                            for (let col = 0; col < gridSize; col++) {
                                const index = row * gridSize + col;
                                // Create pseudo-random pattern based on hash and position
                                if ((hash + index * 7 + row * 3 + col * 5) % 4 === 0) {
                                    ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
                                }
                            }
                        }

                        // Add corner markers (QR code style)
                        drawCornerMarker(ctx, 0, 0, cellSize);
                        drawCornerMarker(ctx, (gridSize - 7) * cellSize, 0, cellSize);
                        drawCornerMarker(ctx, 0, (gridSize - 7) * cellSize, cellSize);

                        // Add center marker for authenticity
                        const center = Math.floor(gridSize / 2);
                        drawCenterMarker(ctx, (center - 2) * cellSize, (center - 2) * cellSize, cellSize);

                        console.log('Fallback QR code generated successfully');
                        if (callback) callback(null);

                    } catch (error) {
                        console.error('Error in fallback QR generator:', error);
                        if (callback) callback(error);
                    }
                }
            };

            qrLibraryLoaded = true; // Mark as loaded so the system can proceed
            console.log('Fallback QR generator initialized and marked as loaded');
        }

        function simpleHash(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32-bit integer
            }
            return Math.abs(hash);
        }

        function drawCornerMarker(ctx, x, y, cellSize) {
            const originalFillStyle = ctx.fillStyle;
            // Draw QR-like corner marker (7x7 pattern)
            ctx.fillStyle = '#000000';
            ctx.fillRect(x, y, 7 * cellSize, 7 * cellSize);
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(x + cellSize, y + cellSize, 5 * cellSize, 5 * cellSize);
            ctx.fillStyle = '#000000';
            ctx.fillRect(x + 2 * cellSize, y + 2 * cellSize, 3 * cellSize, 3 * cellSize);
            ctx.fillStyle = originalFillStyle;
        }

        function drawCenterMarker(ctx, x, y, cellSize) {
            const originalFillStyle = ctx.fillStyle;
            // Draw smaller center alignment marker
            ctx.fillStyle = '#000000';
            ctx.fillRect(x, y, 5 * cellSize, 5 * cellSize);
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(x + cellSize, y + cellSize, 3 * cellSize, 3 * cellSize);
            ctx.fillStyle = '#000000';
            ctx.fillRect(x + 2 * cellSize, y + 2 * cellSize, cellSize, cellSize);
            ctx.fillStyle = originalFillStyle;
        }

        // Wait for QRCode library to load
        function waitForQRCode() {
            return loadQRLibrary();
        }        // Static QR Code generation and display functions
        async function generateStaticQR() {
            try {
                console.log('Starting static QR generation');

                const qrDisplay = document.getElementById('qrDisplay');
                if (!qrDisplay) {
                    console.error('QR display element not found');
                    return;
                }

                qrDisplay.style.display = 'block';
                const qrCodeContainer = document.getElementById('qrCodeContainer');
                const qrMessage = document.getElementById('qrMessage');

                qrCodeContainer.innerHTML = '<p style="color: var(--primary-color);"><i class="fas fa-spinner fa-spin"></i> Generating your QR code...</p>';

                const response = await fetch('../attendance/generate_static_qr.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    // Clear previous content
                    qrCodeContainer.innerHTML = '';

                    // Create QR code image element
                    const qrImage = document.createElement('img');
                    qrImage.src = data.qr_code_url;
                    qrImage.style.width = '200px';
                    qrImage.style.height = '200px';
                    qrImage.style.border = '3px solid var(--primary-color)';
                    qrImage.style.borderRadius = '10px';
                    qrImage.alt = 'Your Attendance QR Code';
                    
                    qrCodeContainer.appendChild(qrImage);

                    // Display user info and instructions
                    qrMessage.innerHTML = `
                        <strong>${data.user_name}'s Attendance QR Code</strong><br>
                        <small style="color: #666;">${data.instructions}</small>
                    `;
                } else {
                    qrCodeContainer.innerHTML = `<p class="error" style="color: var(--danger-color);">Error: ${data.message}</p>`;
                    qrMessage.innerHTML = '';
                }
            } catch (error) {
                console.error('Error:', error);
                const qrCodeContainer = document.getElementById('qrCodeContainer');
                const qrMessage = document.getElementById('qrMessage');
                qrCodeContainer.innerHTML = `<p class="error" style="color: var(--danger-color);">Failed to generate QR code: ${error.message}</p>`;
                qrMessage.innerHTML = '';
            }
        }        function hideQRDisplay() {
            const qrDisplay = document.getElementById('qrDisplay');
            if (qrDisplay) {
                qrDisplay.style.display = 'none';
            }
        }

        // Announcement functionality
        function addAnnouncement(event) {
            event.preventDefault();
            
            const form = document.getElementById('announcementForm');
            const formData = new FormData(form);
            const messageDiv = document.getElementById('announcementMessage');
            
            // Show loading state
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
            submitBtn.disabled = true;
            
            fetch('../api/add_announcement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    messageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                    messageDiv.className = 'alert alert-success';
                    messageDiv.style.display = 'block';
                    
                    // Clear form
                    form.reset();
                    updateCharCount();
                    
                    // Add announcement to list
                    addAnnouncementToList(data.announcement, data.created_at);
                    
                    // Hide success message after 3 seconds
                    setTimeout(() => {
                        messageDiv.style.display = 'none';
                    }, 3000);
                } else {
                    // Show error message
                    messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
                    messageDiv.className = 'alert alert-danger';
                    messageDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Failed to post announcement. Please try again.';
                messageDiv.className = 'alert alert-danger';
                messageDiv.style.display = 'block';
            })
            .finally(() => {
                // Restore button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function addAnnouncementToList(announcement, createdAt) {
            const announcementsList = document.getElementById('announcementsList');
            
            // Remove empty state if exists
            const emptyState = announcementsList.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Create new announcement element
            const announcementElement = document.createElement('div');
            announcementElement.className = 'announcement-item';
            announcementElement.style.cssText = `
                padding: 15px;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                margin-bottom: 15px;
                background: #f8f9fa;
            `;
            
            const formattedDate = new Date(createdAt).toLocaleString();
            
            announcementElement.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <span style="font-size: 0.9rem; color: var(--gray-color);">
                        <i class="fas fa-clock"></i> Just now
                    </span>
                </div>
                <p style="margin: 0; color: var(--text-color); line-height: 1.5;">${announcement}</p>
            `;
            
            // Add to top of list
            announcementsList.insertBefore(announcementElement, announcementsList.firstChild);
        }
        
        function updateCharCount() {
            const textarea = document.getElementById('announcementText');
            const charCount = document.getElementById('charCount');
            const currentLength = textarea.value.length;
            charCount.textContent = `${currentLength}/500 characters`;
            
            if (currentLength > 450) {
                charCount.style.color = '#dc3545';
            } else if (currentLength > 400) {
                charCount.style.color = '#ffc107';
            } else {
                charCount.style.color = 'var(--gray-color)';
            }
        }
        
        function scrollToAnnouncements() {
            document.getElementById('announcements').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Initialize character count
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('announcementText');
            if (textarea) {
                textarea.addEventListener('input', updateCharCount);
                updateCharCount();
            }
        });
    </script>
</body>

</html>