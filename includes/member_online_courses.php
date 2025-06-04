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
    <style>
        body {
            font-family: 'Montserrat', 'Segoe UI', Arial, sans-serif;
            background: #18191a;
            margin: 0;
            color: #f5f6fa;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px;
        }

        .hero-section {
            background: linear-gradient(90deg, #e41e26 0%, #ff6b6b 100%);
            color: #fff;
            padding: 48px 0 32px 0;
            margin-bottom: 32px;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10);
        }

        .hero-flex {
            display: flex;
            align-items: center;
            gap: 32px;
            flex-wrap: wrap;
        }

        .hero-icon {
            background: rgba(24, 25, 26, 0.13);
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .hero-icon i {
            font-size: 2.5rem;
            color: #fff;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.13);
        }

        .hero-section h1 {
            font-size: 2.1rem;
            margin-bottom: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            text-shadow: 0 2px 8px rgba(24, 25, 26, 0.10);
        }

        .hero-desc {
            font-size: 1.13rem;
            font-weight: 500;
            color: #f9f9f9;
            opacity: 0.98;
            max-width: 700px;
            text-shadow: 0 2px 8px rgba(24, 25, 26, 0.10);
        }

        .section-title {
            font-size: 1.3rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .section-title i {
            margin-right: 12px;
            color: #ff5252;
            font-size: 1.2em;
        }

        .filter-tabs {
            display: flex;
            margin-bottom: 24px;
            background: #23272f;
            border-radius: 10px;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .filter-tab {
            flex: 1;
            padding: 12px 0;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.2s;
            color: #b0b3b8;
            background: none;
            border: none;
            outline: none;
            user-select: none;
        }

        .filter-tab.active {
            background: linear-gradient(90deg, #e41e26 0%, #ff6b6b 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(228, 30, 38, 0.10);
        }

        .filter-tab:not(.active):hover {
            background: #23272f;
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 28px;
            margin-bottom: 40px;
        }

        .video-card {
            background: #23272f;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.13);
            transition: transform 0.18s, box-shadow 0.18s;
            position: relative;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            min-height: 380px;
        }

        .video-card:hover {
            transform: translateY(-4px) scale(1.015);
            box-shadow: 0 10px 32px rgba(228, 30, 38, 0.10);
        }

        .video-thumbnail {
            width: 100%;
            height: 180px;
            background: #18191a;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-bottom: 1px solid #23272f;
            transition: filter 0.2s;
        }

        .video-thumbnail i {
            font-size: 3rem;
            color: #ff5252;
            opacity: 0.7;
        }

        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .video-card:hover .play-overlay {
            opacity: 1;
        }

        .play-button {
            background: linear-gradient(90deg, #e41e26 0%, #ff6b6b 100%);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 54px;
            height: 54px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.13);
            transition: background 0.2s, transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .play-button:hover {
            background: linear-gradient(90deg, #c71e24 0%, #ff5252 100%);
            transform: scale(1.07);
        }

        .video-info {
            padding: 18px 18px 14px 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .video-title {
            font-weight: 700;
            font-size: 1.08rem;
            margin-bottom: 7px;
            color: #fff;
            line-height: 1.3;
            letter-spacing: 0.1px;
        }

        .coach-name {
            color: #ff5252;
            font-weight: 600;
            font-size: 0.93rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .coach-name i {
            font-size: 1em;
            color: #ff5252;
        }

        .video-description {
            color: #b0b3b8;
            font-size: 0.97rem;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            min-height: 2.7em;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.89rem;
            color: #b0b3b8;
            margin-bottom: 10px;
            gap: 8px;
        }

        .access-badge {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            border: none;
            display: inline-block;
        }

        .access-free {
            background: #1e4620;
            color: #7fff7f;
            border: 1px solid #2e7d32;
        }

        .access-paid {
            background: #2d1d00;
            color: #ffc107;
            border: 1px solid #f57c00;
        }

        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(24, 25, 26, 0.93);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            z-index: 2;
            padding: 0 10px;
        }

        .locked-overlay i {
            font-size: 2.2rem;
            margin-bottom: 10px;
            color: #ffc107;
        }

        .locked-overlay p {
            text-align: center;
            margin: 0;
            font-weight: 600;
            font-size: 1.05em;
            color: #fff;
        }

        .btn {
            padding: 10px 0;
            border: none;
            border-radius: 25px;
            font-size: 0.98rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            width: 100%;
            margin-top: 8px;
            letter-spacing: 0.2px;
        }

        .btn-primary {
            background: linear-gradient(90deg, #e41e26 0%, #ff6b6b 100%);
            color: #fff;
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, #c71e24 0%, #ff5252 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.03);
        }

        .btn-secondary {
            background: #444950;
            color: #fff;
            border: 1.5px solid #888;
        }

        .btn-secondary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #b0b3b8;
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 18px;
            color: #23272f;
        }

        .empty-state h3 {
            margin-bottom: 8px;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Video Modal */
        #videoModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.96);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        #videoModal.active {
            display: flex;
        }

        #videoModal>div {
            position: relative;
            width: 95vw;
            max-width: 800px;
            background: #18191a;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            padding: 0;
        }

        #videoModal button[onclick^="closeVideoModal"] {
            position: absolute;
            top: -44px;
            right: 0;
            background: none;
            border: none;
            color: #fff;
            font-size: 2.2rem;
            cursor: pointer;
            z-index: 10;
            transition: color 0.2s;
        }

        #videoModal button[onclick^="closeVideoModal"]:hover {
            color: #ff5252;
        }

        #modalVideo {
            width: 100%;
            border-radius: 10px;
            background: #000;
            min-height: 220px;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .container {
                padding: 12px 4vw;
            }

            .video-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 18px;
            }

            .video-thumbnail {
                height: 140px;
            }

            .video-card {
                min-height: 320px;
            }
        }

        @media (max-width: 600px) {
            .container {
                padding: 6px 2vw;
            }

            .hero-section {
                padding: 36px 0 22px 0;
                border-radius: 0 0 12px 12px;
            }

            .hero-section h1 {
                font-size: 1.25rem;
            }

            .hero-section p {
                font-size: 0.97rem;
            }

            .section-title {
                font-size: 1.05rem;
            }

            .video-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .video-thumbnail {
                height: 110px;
            }

            .video-card {
                min-height: 220px;
            }

            .video-info {
                padding: 12px 10px 10px 10px;
            }

            .btn,
            .btn-primary,
            .btn-secondary {
                font-size: 0.93rem;
                padding: 8px 0;
            }

            #videoModal>div {
                width: 99vw;
                max-width: 99vw;
            }

            #modalVideo {
                min-height: 120px;
            }
        }
    </style>
</head>

<body>
    <?php include '../assets/format/member_header.php'; ?>

    <div class="hero-section">
        <div class="container hero-flex">
            <div class="hero-icon">
                <i class="fas fa-dumbbell"></i>
            </div>
            <div>
                <h1>Unlock Your Fitness Potential</h1>
                <p class="hero-desc">
                    Access a curated library of expert-led fitness video courses—covering strength, mobility, nutrition, and more. Learn at your own pace, from anywhere, with both free and premium content designed for real results.
                </p>
            </div>
        </div>
    </div>

    <div class="container">
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
                    <?php foreach ($free_videos as $video): ?>
                        <div class="video-card" onclick="watchVideo(<?= $video['id'] ?>)">                            <div class="video-thumbnail">
                                <?php if (isset($video['thumbnail_path']) && $video['thumbnail_path']): ?>
                                    <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail">
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
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                                </div>
                                <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                <div class="video-meta">
                                    <span><i class="fas fa-eye"></i> <?= number_format($video['total_views']) ?> views</span>
                                    <span class="access-badge access-free">Free</span>
                                </div>
                                <?php if (isset($video['user_has_viewed']) && $video['user_has_viewed']): ?>
                                    <div style="color: #28a745; font-size: 0.85rem;">
                                        <i class="fas fa-check-circle"></i> Watched
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
                        ?>
                        <div class="video-card" <?= $can_access ? 'onclick="watchVideo(' . $video['id'] . ')"' : '' ?>>                            <div class="video-thumbnail">
                                <?php if (isset($video['thumbnail_path']) && $video['thumbnail_path']): ?>
                                    <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail">
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
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                                </div>
                                <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                <div class="video-meta">
                                    <span><i class="fas fa-eye"></i> <?= number_format($video['total_views']) ?> views</span>
                                    <span class="access-badge access-paid">
                                        ₱<?= number_format($video['subscription_price'], 2) ?>/mo
                                    </span>
                                    <?php if ($video['subscription_price'] < 20): ?>
                                        <div style="color: #dc3545; font-size: 0.8rem; margin-top: 5px;">
                                            <i class="fas fa-exclamation-triangle"></i> Price below payment minimum - Contact coach
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($can_access): ?>
                                    <?php if ($video['user_has_viewed']): ?>
                                        <div style="color: #28a745; font-size: 0.85rem;">
                                            <i class="fas fa-check-circle"></i> Watched
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($video['subscription_price'] < 20): ?>
                                        <button class="btn btn-secondary" style="width: 100%; margin-top: 10px;" disabled>
                                            <i class="fas fa-exclamation-triangle"></i> Unavailable (Price too low)
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary" style="width: 100%; margin-top: 10px;"
                                            onclick="event.stopPropagation(); subscribeToCoach(<?= $video['coach_id'] ?>, '<?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>', <?= $video['subscription_price'] ?>)">
                                            <i class="fas fa-crown"></i> Subscribe
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