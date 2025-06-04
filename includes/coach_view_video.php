<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Check if logged in and is coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

$coach_id = $_SESSION['user_id'];
$video_id = $_GET['id'] ?? null;

if (!$video_id) {
    header("Location: coach_my_classes.php");
    exit;
}

// Get video details and verify ownership
try {
    $stmt = $conn->prepare("
        SELECT cv.*, 
               COUNT(vv.id) as total_views,
               COUNT(DISTINCT vv.member_id) as unique_viewers,
               COUNT(DISTINCT cs.member_id) as subscribers,
               u.First_Name as admin_name
        FROM coach_videos cv
        LEFT JOIN video_views vv ON cv.id = vv.video_id
        LEFT JOIN coach_subscriptions cs ON cv.coach_id = cs.coach_id AND cs.status = 'active'
        LEFT JOIN users u ON cv.approved_by = u.UserID
        WHERE cv.id = ? AND cv.coach_id = ?
        GROUP BY cv.id
    ");
    $stmt->execute([$video_id, $coach_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        header("Location: coach_my_classes.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get recent views for analytics
try {
    $stmt = $conn->prepare("
        SELECT vv.view_date as viewed_at, u.First_Name, u.Last_Name
        FROM video_views vv
        JOIN users u ON vv.member_id = u.UserID
        WHERE vv.video_id = ?
        ORDER BY vv.view_date DESC
        LIMIT 10
    ");
    $stmt->execute([$video_id]);
    $recent_views = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get daily view statistics for the last 30 days
try {
    $stmt = $conn->prepare("
        SELECT DATE(view_date) as view_date, COUNT(*) as daily_views
        FROM video_views
        WHERE video_id = ? AND view_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(view_date)
        ORDER BY view_date ASC
    ");
    $stmt->execute([$video_id]);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get subscription revenue if it's a paid video
$monthly_revenue = 0;
if ($video['access_type'] === 'paid') {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) * ? as revenue
            FROM coach_subscriptions
            WHERE coach_id = ? AND status = 'active'
        ");
        $stmt->execute([$video['subscription_price'], $coach_id]);
        $revenue_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $monthly_revenue = $revenue_data['revenue'] ?? 0;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Update the created_at and updated_at references
$created_at = $video['upload_date'] ?? $video['created_at'];
$updated_at = $video['updated_at'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Video Details | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            color: #333;
        }

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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

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
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
        }

        .sidebar-nav {
            padding: 15px 0;
        }

        .nav-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            padding: 10px 25px;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 500;
            border-left: 5px solid transparent;
            transition: 0.3s;
        }

        .nav-item i {
            margin-right: 15px;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-item.active {
            background-color: rgba(228, 30, 38, 0.2);
            color: #fff;
            border-left-color: #e41e26;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: 100%;
        }

        .welcome-banner {
            background: linear-gradient(45deg, #e41e26, #ff6b6b);
            color: #fff;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            margin: 0;
        }

        .welcome-banner p {
            margin-top: 5px;
        }

        .card {
            background-color: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .video-player-container {
            width: 100%;
            max-width: 800px;
            margin-bottom: 20px;
        }

        .video-player {
            width: 100%;
            height: 450px;
            background: #000;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
        }

        .video-info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-card .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .stat-views .stat-icon {
            color: #e41e26;
        }

        .stat-viewers .stat-icon {
            color: #17a2b8;
        }

        .stat-subscribers .stat-icon {
            color: #28a745;
        }

        .stat-revenue .stat-icon {
            color: #ffc107;
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

        .access-type {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
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

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background-color: #e41e26;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #c71e24;
            color: #fff;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #545b62;
            color: #fff;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: #e41e26;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .back-link i {
            margin-right: 8px;
        }

        .back-link:hover {
            color: #c71e24;
        }

        .chart-container {
            height: 300px;
            margin-top: 20px;
        }

        .recent-views {
            max-height: 400px;
            overflow-y: auto;
        }

        .view-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .view-item:last-child {
            border-bottom: none;
        }

        .viewer-name {
            font-weight: 600;
        }

        .view-time {
            color: #666;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .video-details h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #333;
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/images/fa_logo.png" alt="Logo">
                <h3>Fitness Academy</h3>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="coach_dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Classes</div>
                    <a href="coach_my_classes.php" class="nav-item"><i class="fas fa-dumbbell"></i><span>My Classes</span></a>
                    <a href="coach_class_schedule.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>Class Schedule</span></a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Members</div>
                    <a href="coach_my_clients.php" class="nav-item"><i class="fas fa-users"></i><span>My Clients</span></a>
                    <a href="coach_progress_tracking.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Progress Tracking</span></a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
                    </a>
                    <a href="coach_view_video.php" class="nav-item active">
                        <i class="fas fa-play"></i>
                        <span>My Videos</span>
                    </a>
                    <a href="coach_edit_video.php" class="nav-item">
                        <i class="fas fa-edit"></i>
                        <span>Edit Videos</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="coach_my_profile.php" class="nav-item"><i class="fas fa-user"></i><span>My Profile</span></a>
                    <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <a href="coach_my_classes.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Videos
            </a>

            <div class="welcome-banner">
                <h2><?= htmlspecialchars($video['title']) ?></h2>
                <p>Video analytics and performance metrics</p>
            </div>

            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card stat-views">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-number"><?= number_format($video['total_views']) ?></div>
                    <div class="stat-label">Total Views</div>
                </div>

                <div class="stat-card stat-viewers">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= number_format($video['unique_viewers']) ?></div>
                    <div class="stat-label">Unique Viewers</div>
                </div>

                <div class="stat-card stat-subscribers">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-number"><?= number_format($video['subscribers']) ?></div>
                    <div class="stat-label">Subscribers</div>
                </div>

                <?php if ($video['access_type'] === 'paid'): ?>
                    <div class="stat-card stat-revenue">
                        <div class="stat-icon">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stat-number">₱<?= number_format($monthly_revenue, 2) ?></div>
                        <div class="stat-label">Monthly Revenue</div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="video-info-grid">
                <!-- Video Player -->
                <div class="card">
                    <h3><i class="fas fa-play-circle"></i> Video Preview</h3>
                    <div class="video-player-container">
                        <?php if ($video['status'] === 'approved'): ?>
                            <video controls class="video-player" style="width: 100%; height: 450px;">
                                <source src="/gym1/uploads/coach_videos/<?= basename($video['video_path']) ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php else: ?>
                            <div class="video-player">
                                <div style="text-align: center;">
                                    <i class="fas fa-video" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                                    <p>Video preview available after approval</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="action-buttons">
                        <a href="coach_edit_video.php?id=<?= $video['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Video
                        </a>
                        <a href="/gym1/uploads/coach_videos/<?= basename($video['video_path']) ?>" class="btn btn-secondary" download>
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>

                <!-- Video Details -->
                <div class="card">
                    <h3><i class="fas fa-info-circle"></i> Video Details</h3>
                    <div class="video-details">
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="status-badge status-<?= $video['status'] ?>">
                                    <?= ucfirst($video['status']) ?>
                                </span>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">Access Type:</span>
                            <span class="detail-value">
                                <span class="access-type access-<?= $video['access_type'] ?>">
                                    <?= $video['access_type'] === 'paid' ? 'Paid - ₱' . number_format($video['subscription_price'], 2) . '/month' : 'Free' ?>
                                </span>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">Created:</span>
                            <span class="detail-value"><?= date('M d, Y g:i A', strtotime($created_at)) ?></span>
                        </div>

                        <?php if ($updated_at && $updated_at !== $created_at): ?>
                            <div class="detail-row">
                                <span class="detail-label">Last Updated:</span>
                                <span class="detail-value"><?= date('M d, Y g:i A', strtotime($updated_at)) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="detail-row">
                            <span class="detail-label">Description:</span>
                            <span class="detail-value" style="text-align: right; max-width: 60%;">
                                <?= nl2br(htmlspecialchars($video['description'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Chart -->
            <div class="card">
                <h3><i class="fas fa-chart-line"></i> View Trends (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="viewsChart"></canvas>
                </div>
            </div>

            <!-- Recent Views -->
            <div class="card">
                <h3><i class="fas fa-history"></i> Recent Views</h3>
                <?php if (count($recent_views) > 0): ?>
                    <div class="recent-views">
                        <?php foreach ($recent_views as $view): ?>
                            <div class="view-item">
                                <div class="viewer-name">
                                    <?= htmlspecialchars($view['First_Name'] . ' ' . $view['Last_Name']) ?>
                                </div>
                                <div class="view-time">
                                    <?= date('M d, Y g:i A', strtotime($view['viewed_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        No views recorded yet
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Prepare chart data
        const chartLabels = [];
        const chartData = [];
        const dailyStats = <?= json_encode($daily_stats) ?>;

        // Fill in missing days with 0 views
        const today = new Date();
        for (let i = 29; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];

            chartLabels.push(date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            }));

            const dayData = dailyStats.find(d => d.view_date === dateStr);
            chartData.push(dayData ? parseInt(dayData.daily_views) : 0);
        }

        // Create chart
        const ctx = document.getElementById('viewsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Daily Views',
                    data: chartData,
                    borderColor: '#e41e26',
                    backgroundColor: 'rgba(228, 30, 38, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>

</html>