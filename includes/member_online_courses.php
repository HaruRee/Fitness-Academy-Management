<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Helper function to properly handle thumbnail paths
function fixThumbnailPath($path) {
    if (empty($path)) return false;
    
    // If the path already starts with '../' remove it to avoid double path issues
    if (strpos($path, '../') === 0) {
        return substr($path, 3);
    }
    return $path;
}

// Check if logged in and is member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header("Location: ../login.php");
    exit;
}

$member_id = $_SESSION['user_id'];

// Get member's active subscriptions
$stmt = $conn->prepare("
    SELECT cs.coach_id, u.First_Name, u.Last_Name
    FROM coach_subscriptions cs
    JOIN users u ON cs.coach_id = u.UserID
    WHERE cs.member_id = ? AND cs.status = 'active'
");
$stmt->execute([$member_id]);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$subscribed_coaches = array_column($subscriptions, 'coach_id');

// Get all approved videos
$stmt = $conn->prepare("
    SELECT cv.*, u.First_Name, u.Last_Name,
           COUNT(vv.id) as total_views,
           COUNT(DISTINCT vv.member_id) as unique_viewers,
           MAX(CASE WHEN vv.member_id = ? THEN 1 ELSE 0 END) as user_has_viewed
    FROM coach_videos cv
    JOIN users u ON cv.coach_id = u.UserID
    LEFT JOIN video_views vv ON cv.id = vv.video_id
    WHERE cv.status = 'approved'
    GROUP BY cv.id
    ORDER BY cv.created_at DESC
");
$stmt->execute([$member_id]);
$all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate free and paid videos
$free_videos = array_filter($all_videos, function ($video) {
    return $video['access_type'] === 'free';
});

$paid_videos = array_filter($all_videos, function ($video) {
    return $video['access_type'] === 'paid';
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Online Courses | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Enhanced Member Online Courses Styling -->
    <style>
        :root {
            --primary-red: #d62328;
            --dark-bg: #1a1a1a;
            --darker-bg: #121212;
            --card-bg: #2d2d2d;
            --border-color: #404040;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-muted: #888888;
            --shadow-light: rgba(255, 255, 255, 0.1);
            --shadow-dark: rgba(0, 0, 0, 0.5);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        body {
            font-family: 'Montserrat', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
            margin: 0;
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--card-bg) 0%, var(--darker-bg) 100%);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            margin: 2rem 0;
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-red), #ff4449, var(--primary-red));
        }
        
        .hero-flex {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .hero-icon {
            font-size: 4rem;
            color: var(--primary-red);
            text-shadow: 0 0 20px rgba(214, 35, 40, 0.5);
        }
        
        .hero-section h1 {
            color: var(--text-primary);
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 1rem 0;
            background: linear-gradient(135deg, var(--text-primary), var(--primary-red));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-desc {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
        }
        
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }
        
        .filter-tab {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-bottom: none;
            border-radius: 12px 12px 0 0;
            padding: 1rem 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }
        
        .filter-tab:hover {
            background: var(--darker-bg);
            color: var(--text-primary);
            transform: translateY(-2px);
        }
        
        .filter-tab.active {
            background: var(--primary-red);
            color: white;
            border-color: var(--primary-red);
            box-shadow: 0 -4px 15px rgba(214, 35, 40, 0.3);
        }
        
        .filter-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-red);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-title {
            color: var(--text-primary);
            font-size: 1.8rem;
            font-weight: 700;
            margin: 2rem 0 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--primary-red);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 80px;
            height: 3px;
            background: #ff4449;
        }
        
        .section-title i {
            color: var(--primary-red);
        }
        
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .video-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            position: relative;
            cursor: pointer;
        }
        
        .video-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            border-color: var(--primary-red);
        }
        
        .video-thumbnail {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: var(--darker-bg);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .video-card:hover .video-thumbnail img {
            transform: scale(1.05);
        }
        
        .video-thumbnail i {
            font-size: 3rem;
            color: var(--text-muted);
        }
        
        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .video-card:hover .play-overlay {
            opacity: 1;
        }
        
        .play-button {
            width: 70px;
            height: 70px;
            background: rgba(214, 35, 40, 0.9);
            border: 3px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            transform: scale(0.8);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .video-card:hover .play-button {
            transform: scale(1);
            background: var(--primary-red);
        }
        
        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }
        
        .locked-overlay i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-red);
        }
        
        .locked-overlay p {
            margin: 0;
            font-weight: 600;
            text-align: center;
        }
        
        .video-info {
            padding: 1.5rem;
        }
        
        .video-title {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .coach-name {
            color: var(--primary-red);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .video-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .video-meta span {
            color: var(--text-muted);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .access-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .access-free {
            background: linear-gradient(135deg, var(--success-color), #34d058);
            color: white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        
        .access-paid {
            background: linear-gradient(135deg, var(--primary-red), #ff4449);
            color: white;
            box-shadow: 0 2px 8px rgba(214, 35, 40, 0.3);
        }
        
        .btn {
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-red), #ff4449);
            color: white;
            box-shadow: 0 4px 15px rgba(214, 35, 40, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(214, 35, 40, 0.4);
        }
        
        .btn-secondary {
            background: var(--border-color);
            color: var(--text-muted);
            cursor: not-allowed;
        }
        
        .empty-state {
            background: linear-gradient(135deg, #2d2d2d 0%, #3a3a3a 100%);
            border: 2px dashed var(--border-color);
            border-radius: 16px;
            padding: 4rem 2rem;
            text-align: center;
            color: var(--text-muted);
            margin: 2rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--primary-red);
            opacity: 0.7;
        }
        
        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Modal Styles */
        #videoModal {
            backdrop-filter: blur(5px);
        }
        
        #videoModal video {
            box-shadow: 0 20px 50px rgba(0,0,0,0.8);
        }
        
        #videoModal button {
            transition: all 0.3s ease;
        }
        
        #videoModal button:hover {
            transform: scale(1.1);
            color: var(--primary-red) !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-flex {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .hero-desc {
                font-size: 1rem;
            }
            
            .filter-tabs {
                flex-direction: column;
                gap: 0;
            }
            
            .filter-tab {
                border-radius: 0;
                border-left: none;
                border-right: none;
            }
            
            .filter-tab:first-child {
                border-radius: 12px 12px 0 0;
                border-left: 1px solid var(--border-color);
                border-right: 1px solid var(--border-color);
            }
            
            .filter-tab:last-child {
                border-left: 1px solid var(--border-color);
                border-right: 1px solid var(--border-color);
            }
            
            .section-title {
                font-size: 1.5rem;
                margin: 1.5rem 0 1rem;
            }
            
            .video-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .video-info {
                padding: 1.25rem;
            }
            
            .video-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .btn {
                width: 100%;
            }
            
            .empty-state {
                padding: 3rem 1.5rem;
            }
            
            .empty-state i {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 480px) {
            .hero-section {
                padding: 2rem 1rem;
                margin: 1rem 0;
            }
            
            .hero-section h1 {
                font-size: 1.8rem;
            }
            
            .filter-tab {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../assets/format/member_header.php'; ?>    <div class="container">
        <div class="hero-section">
            <div class="hero-flex">
                <div class="hero-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div>
                    <h1>Online Fitness Academy</h1>
                    <p class="hero-desc">
                        Access a curated library of expert-led fitness video courses—covering strength, mobility, nutrition, and more. Learn at your own pace, from anywhere, with both free and premium content designed for real results.
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <div id="free-tab-btn" class="filter-tab active" onclick="showTab('free')">
                <i class="fas fa-gift"></i> Free Courses
            </div>
            <div id="paid-tab-btn" class="filter-tab" onclick="showTab('paid')">
                <i class="fas fa-crown"></i> Premium Courses
            </div>
        </div>

        <!-- Free Videos Tab -->
        <div id="free-tab" class="tab-content active">
            <div class="section-title">
                <i class="fas fa-gift"></i> Free Courses
            </div>

            <?php if (count($free_videos) > 0): ?>
                <div class="video-grid">
                    <?php foreach ($free_videos as $video): ?>                        <div class="video-card" onclick="watchVideo(<?= $video['id'] ?>)">
                            <div class="video-thumbnail">
                                <?php if (isset($video['thumbnail_path']) && $video['thumbnail_path']): ?>
                                    <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="<?= htmlspecialchars($video['title']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-play-circle"></i>
                                <?php endif; ?>
                                <div class="play-overlay">
                                    <button class="play-button">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="video-info">
                                <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                <div class="coach-name">
                                    <i class="fas fa-user-tie"></i>
                                    <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                                </div>
                                <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                <div class="video-meta">
                                    <span><i class="fas fa-eye"></i> <?= number_format($video['total_views']) ?> views</span>
                                    <span class="access-badge access-free">
                                        <i class="fas fa-gift"></i> Free
                                    </span>
                                </div>
                                <?php if (isset($video['user_has_viewed']) && $video['user_has_viewed']): ?>
                                    <div style="color: #28a745; font-size: 0.85rem; margin-top: 0.5rem;">
                                        <i class="fas fa-check-circle"></i> Completed
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-video"></i>
                    <h3>No Free Courses Available</h3>
                    <p>Check back later for new free content</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paid Videos Tab -->
        <div id="paid-tab" class="tab-content">
            <div class="section-title">
                <i class="fas fa-crown"></i> Premium Courses
            </div>

            <?php if (count($paid_videos) > 0): ?>
                <div class="video-grid">
                    <?php foreach ($paid_videos as $video): ?>
                        <?php
                        $can_access = in_array($video['coach_id'], $subscribed_coaches);
                        ?>                        <div class="video-card" <?= $can_access ? 'onclick="watchVideo(' . $video['id'] . ')"' : '' ?>>
                            <div class="video-thumbnail">
                                <?php if (isset($video['thumbnail_path']) && $video['thumbnail_path']): ?>
                                    <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="<?= htmlspecialchars($video['title']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-play-circle"></i>
                                <?php endif; ?>

                                <?php if ($can_access): ?>
                                    <div class="play-overlay">
                                        <button class="play-button">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="locked-overlay">
                                        <i class="fas fa-lock"></i>
                                        <p>Subscription Required</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="video-info">
                                <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                <div class="coach-name">
                                    <i class="fas fa-user-tie"></i>
                                    <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                                </div>
                                <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                <div class="video-meta">
                                    <span><i class="fas fa-eye"></i> <?= number_format($video['total_views']) ?> views</span>
                                    <span class="access-badge access-paid">
                                        <i class="fas fa-crown"></i> ₱<?= number_format($video['subscription_price'], 2) ?>/mo
                                    </span>
                                </div>
                                
                                <?php if ($video['subscription_price'] < 20): ?>
                                    <div style="color: #dc3545; font-size: 0.8rem; margin: 0.5rem 0;">
                                        <i class="fas fa-exclamation-triangle"></i> Price below minimum - Contact coach
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($can_access): ?>
                                    <?php if ($video['user_has_viewed']): ?>
                                        <div style="color: #28a745; font-size: 0.85rem; margin-top: 0.5rem;">
                                            <i class="fas fa-check-circle"></i> Completed
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($video['subscription_price'] < 20): ?>
                                        <button class="btn btn-secondary" style="width: 100%; margin-top: 0.75rem;" disabled>
                                            <i class="fas fa-exclamation-triangle"></i> Unavailable
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary" style="width: 100%; margin-top: 0.75rem;"
                                            onclick="event.stopPropagation(); subscribeToCoach(<?= $video['coach_id'] ?>, '<?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>', <?= $video['subscription_price'] ?>)">
                                            <i class="fas fa-crown"></i> Subscribe Now
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-crown"></i>
                    <h3>No Premium Courses Available</h3>
                    <p>Exclusive premium content coming soon</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Video Modal -->
    <div id="videoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 800px;">
            <div style="position: relative;">
                <button onclick="closeVideoModal()" style="position: absolute; top: -40px; right: 0; background: none; border: none; color: white; font-size: 2rem; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
                <video id="modalVideo" controls style="width: 100%; border-radius: 10px;">
                    <source src="" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../assets/format/member_footer.php'; ?>

    <script>
        // Tab switching
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.filter-tab').forEach(tabBtn => {
                tabBtn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');

            // Make the clicked tab button active
            document.getElementById(tab + '-tab-btn').classList.add('active');
        }

        // Watch video function
        function watchVideo(videoId) {
            // Record view
            fetch('record_video_view.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    video_id: videoId
                })
            });

            // Get video path and show modal
            fetch('get_video_path.php?id=' + videoId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalVideo').src = data.video_path;
                        document.getElementById('videoModal').style.display = 'block';
                    } else {
                        alert('Error loading video: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading video:', error);
                    alert('Error loading video. Please try again later.');
                });
        }

        // Close video modal
        function closeVideoModal() {
            document.getElementById('videoModal').style.display = 'none';
            const videoElement = document.getElementById('modalVideo');
            videoElement.pause();
            videoElement.src = '';
        }

        // Subscribe to coach
        function subscribeToCoach(coachId, coachName, price) {
            if (confirm(`Subscribe to ${coachName} for ₱${price.toFixed(2)}/month?`)) {
                // Redirect to payment processing or show payment modal
                window.location.href = `process_subscription.php?coach_id=${coachId}&price=${price}`;
            }
        }

        // Close modal when clicking outside
        document.getElementById('videoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeVideoModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVideoModal();
            }
        });
    </script>
</body>

</html>