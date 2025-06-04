<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

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
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .hero-section {
            background: linear-gradient(45deg, #e41e26, #ff6b6b);
            color: #fff;
            padding: 60px 0;
            text-align: center;
            margin-bottom: 40px;
        }

        .hero-section h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .hero-section p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .section-title {
            font-size: 1.8rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            color: #333;
        }

        .section-title i {
            margin-right: 15px;
            color: #e41e26;
        }

        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .video-card {
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }

        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .video-thumbnail {
            width: 100%;
            height: 200px;
            background: #f0f0f0;
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
        }

        .video-thumbnail i {
            font-size: 3rem;
            color: #666;
        }

        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .video-card:hover .play-overlay {
            opacity: 1;
        }

        .play-button {
            background: rgba(228, 30, 38, 0.9);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .play-button:hover {
            background: rgba(199, 30, 36, 0.9);
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
            color: #e41e26;
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
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 15px;
        }

        .access-badge {
            padding: 6px 12px;
            border-radius: 20px;
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

        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .locked-overlay i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #ffc107;
        }

        .locked-overlay p {
            text-align: center;
            margin: 0;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(45deg, #e41e26, #ff6b6b);
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #c71e24, #ff5252);
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 2px solid #e41e26;
            color: #e41e26;
            background: transparent;
        }

        .btn-outline:hover {
            background: #e41e26;
            color: #fff;
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

        .empty-state h3 {
            margin-bottom: 10px;
            color: #555;
        }

        .filter-tabs {
            display: flex;
            margin-bottom: 30px;
            background: #555;
            border-radius: 10px;
            padding: 5px;
            box-shadow: 0 4px 8px rgba(72, 68, 68, 0.1);
        }

        .filter-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            color: #bbb;
        }

        .filter-tab.active {
            background: #d62328;
            color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .filter-tab:not(.active):hover {
            background: #333;
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <?php include '../assets/format/member_header.php'; ?>

    <div class="hero-section">
        <div class="container">
            <h1>Online Fitness Courses</h1>
            <p>Learn from our comprehensive video library with free and premium courses</p>
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
                        <div class="video-card" onclick="watchVideo(<?= $video['id'] ?>)">
                            <div class="video-thumbnail">
                                <?php if (isset($video['thumbnail_path']) && $video['thumbnail_path']): ?>
                                    <img src="../<?= htmlspecialchars($video['thumbnail_path']) ?>" alt="Thumbnail">
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
                        <div class="video-card" <?= $can_access ? 'onclick="watchVideo(' . $video['id'] . ')"' : '' ?>>
                            <div class="video-thumbnail">
                                <?php if (isset($video['thumbnail_path']) && $video['thumbnail_path']): ?>
                                    <img src="../<?= htmlspecialchars($video['thumbnail_path']) ?>" alt="Thumbnail">
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