<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Helper function to properly handle thumbnail paths
function fixThumbnailPath($path) {
    if (empty($path)) return false;
    
    // If the path already starts with '../' remove it to avoid double path issues
    if (strpos($path, '../') === 0) {
        return substr($path, 3);
    }
    return $path;
}

// Check if logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle approval/rejection actions
if ($_POST && isset($_POST['action']) && isset($_POST['video_id'])) {
    $video_id = intval($_POST['video_id']);
    $action = $_POST['action'];
    $new_status = '';

    if ($action === 'approve') {
        $new_status = 'approved';
    } elseif ($action === 'reject') {
        $new_status = 'rejected';
    }

    if ($new_status) {
        try {
            $stmt = $conn->prepare("UPDATE coach_videos SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $video_id]);

            $success_message = "Video " . ($new_status === 'approved' ? 'approved' : 'rejected') . " successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating video status: " . $e->getMessage();
        }
    }
}

// Get all pending videos
$stmt = $conn->prepare("
    SELECT cv.*, u.First_Name, u.Last_Name
    FROM coach_videos cv
    JOIN users u ON cv.coach_id = u.UserID
    WHERE cv.status = 'pending'
    ORDER BY cv.created_at ASC
");
$stmt->execute();
$pending_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all approved videos
$stmt = $conn->prepare("
    SELECT cv.*, u.First_Name, u.Last_Name,
           COUNT(vv.id) as total_views,
           COUNT(DISTINCT vv.member_id) as unique_viewers
    FROM coach_videos cv
    JOIN users u ON cv.coach_id = u.UserID
    LEFT JOIN video_views vv ON cv.id = vv.video_id
    WHERE cv.status = 'approved'
    GROUP BY cv.id
    ORDER BY cv.updated_at DESC
    LIMIT 20
");
$stmt->execute();
$approved_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rejected videos
$stmt = $conn->prepare("
    SELECT cv.*, u.First_Name, u.Last_Name
    FROM coach_videos cv
    JOIN users u ON cv.coach_id = u.UserID
    WHERE cv.status = 'rejected'
    ORDER BY cv.updated_at DESC
    LIMIT 10
");
$stmt->execute();
$rejected_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Video Approval | Admin Panel</title>
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
            position: relative;
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
            left: 1rem;
            color: var(--gray-color);
        }        .header-actions {
            display: flex;
            align-items: center;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-color);
            font-size: 1rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #e5e7eb;
            color: var(--dark-color);
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .welcome-header {
            margin-bottom: 2rem;
        }

        .welcome-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .welcome-header p {
            color: var(--gray-color);
            font-size: 1.1rem;
        }

        .welcome-header p {
            color: var(--gray-color);
            font-size: 1.1rem;
        }

        /* Video Management Specific Styles */
        .tabs {
            display: flex;
            margin-bottom: 30px;
            background: #fff;
            border-radius: 10px;
            padding: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .tab.active {
            background: var(--secondary-color);
            color: #fff;
        }

        .tab:not(.active):hover {
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .video-card {
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .video-card:hover {
            transform: translateY(-5px);
        }

        .video-thumbnail {
            width: 100%;
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .video-thumbnail i {
            font-size: 3rem;
            color: #666;
        }

        .video-info {
            padding: 20px;
        }

        .video-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #333;
            line-height: 1.3;
        }

        .coach-name {
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .video-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 15px;
        }

        .access-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .access-free {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .access-paid {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-approve {
            background: var(--success-color);
            color: #fff;
        }

        .btn-approve:hover {
            background: #059669;
        }

        .btn-reject {
            background: var(--danger-color);
            color: #fff;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .btn-view {
            background: var(--primary-color);
            color: #fff;
        }

        .btn-view:hover {
            background: #1e3a8a;
        }

        .btn-secondary {
            background: var(--gray-color);
            color: #fff;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-danger {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 1200px) {
            .video-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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

            .video-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
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
            <a href="admin_video_approval.php" class="active">
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
        <!-- Header -->        <header class="header">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            <div class="header-actions">
                <!-- Removed logout button -->
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="page-header">
                <h1>Video Course Management</h1>
                <p>Approve, reject, and manage coach video submissions</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?= count($pending_videos) ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($approved_videos) ?></div>
                    <div class="stat-label">Approved Videos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($rejected_videos) ?></div>
                    <div class="stat-label">Rejected Videos</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('pending')">
                    <i class="fas fa-clock"></i> Pending Review (<?= count($pending_videos) ?>)
                </div>
                <div class="tab" onclick="showTab('approved')">
                    <i class="fas fa-check"></i> Approved (<?= count($approved_videos) ?>)
                </div>
                <div class="tab" onclick="showTab('rejected')">
                    <i class="fas fa-times"></i> Rejected (<?= count($rejected_videos) ?>)
                </div>
            </div>

            <!-- Pending Videos Tab -->
            <div id="pending-tab" class="tab-content active">
                <?php if (count($pending_videos) > 0): ?>
                    <div class="video-grid">                        <?php foreach ($pending_videos as $video): ?>
                            <div class="video-card">
                                <div class="video-thumbnail">
                                    <?php if ($video['thumbnail_path']): ?>
                                        <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail">
                                    <?php else: ?>
                                        <i class="fas fa-play-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="video-info">
                                    <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                    <div class="coach-name">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                                    </div>
                                    <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                    <div class="video-meta">
                                        <span>Uploaded: <?= date('M d, Y', strtotime($video['created_at'])) ?></span>
                                        <span class="access-badge access-<?= $video['access_type'] ?>">
                                            <?= $video['access_type'] === 'paid' ? '₱' . number_format($video['subscription_price'], 2) . '/mo' : 'Free' ?>
                                        </span>
                                    </div>
                                    <div class="action-buttons">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this video?')">
                                            <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reject this video?')">
                                            <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                        <a href="../<?= htmlspecialchars($video['video_path']) ?>" target="_blank" class="btn btn-view">
                                            <i class="fas fa-play"></i> Preview
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Pending Videos</h3>
                        <p>All videos have been reviewed</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approved Videos Tab -->
            <div id="approved-tab" class="tab-content">
                <?php if (count($approved_videos) > 0): ?>
                    <div class="video-grid">                        <?php foreach ($approved_videos as $video): ?>
                            <div class="video-card">
                                <div class="video-thumbnail">
                                    <?php if ($video['thumbnail_path']): ?>
                                        <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail">
                                    <?php else: ?>
                                        <i class="fas fa-play-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="video-info">
                                    <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                    <div class="coach-name">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                                    </div>
                                    <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                    <div class="video-meta">
                                        <span><i class="fas fa-eye"></i> <?= number_format($video['total_views']) ?> views</span>
                                        <span class="access-badge access-<?= $video['access_type'] ?>">
                                            <?= $video['access_type'] === 'paid' ? '₱' . number_format($video['subscription_price'], 2) . '/mo' : 'Free' ?>
                                        </span>
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <span class="status-badge status-approved">Approved</span>
                                    </div>
                                    <div class="action-buttons">
                                        <a href="../<?= htmlspecialchars($video['video_path']) ?>" target="_blank" class="btn btn-view">
                                            <i class="fas fa-play"></i> View
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reject this approved video?')">
                                            <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-secondary">
                                                <i class="fas fa-ban"></i> Revoke
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Approved Videos</h3>
                        <p>No videos have been approved yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Rejected Videos Tab -->
            <div id="rejected-tab" class="tab-content">
                <?php if (count($rejected_videos) > 0): ?>
                    <div class="video-grid">                        <?php foreach ($rejected_videos as $video): ?>
                            <div class="video-card">
                                <div class="video-thumbnail">
                                    <?php if ($video['thumbnail_path']): ?>
                                        <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail">
                                    <?php else: ?>
                                        <i class="fas fa-play-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="video-info">
                                    <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                    <div class="coach-name">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                                    </div>
                                    <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                    <div class="video-meta">
                                        <span>Rejected: <?= date('M d, Y', strtotime($video['updated_at'])) ?></span>
                                        <span class="access-badge access-<?= $video['access_type'] ?>">
                                            <?= $video['access_type'] === 'paid' ? '₱' . number_format($video['subscription_price'], 2) . '/mo' : 'Free' ?>
                                        </span>
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <span class="status-badge status-rejected">Rejected</span>
                                    </div>
                                    <div class="action-buttons">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this rejected video?')">
                                            <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <a href="../<?= htmlspecialchars($video['video_path']) ?>" target="_blank" class="btn btn-view">
                                            <i class="fas fa-play"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-times-circle"></i>
                        <h3>No Rejected Videos</h3>
                        <p>No videos have been rejected</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tabBtn => {
                tabBtn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>

</html>