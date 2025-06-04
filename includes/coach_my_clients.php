<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

$coach_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ? AND Role = 'Coach'");
$stmt->execute([$coach_id]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    header("Location: logout.php");
    exit;
}

// Fetch all clients assigned to this coach
$stmt = $conn->prepare("
    SELECT u.UserID, u.First_Name, u.Last_Name, u.ProfileImage, MAX(c.class_date) AS last_class_date
    FROM users u
    JOIN classenrollments ce ON u.UserID = ce.user_id
    JOIN classes c ON ce.class_id = c.class_id
    WHERE c.coach_id = ?
    GROUP BY u.UserID
    ORDER BY last_class_date DESC
");
$stmt->execute([$coach_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>My Clients | Fitness Academy</title>
    <link rel="icon" href="../assets/images/fa_logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        /* Use the same styles as coach_dashboard.php sidebar and layout */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .sidebar-header {
            padding: 20px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header img {
            width: 35px;
            margin-right: 10px;
        }

        .sidebar-header h3 {
            font-weight: 700;
            font-size: 1.2rem;
        }

        .sidebar-nav {
            padding: 15px 0;
        }

        .nav-section {
            margin-bottom: 10px;
        }

        .nav-section-title {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
            padding: 10px 25px;
            text-transform: uppercase;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 500;
            border-left: 5px solid transparent;
            transition: all 0.3s;
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

        .nav-item i {
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 280px;
            padding: 40px;
            background: #fff;
            color: #000;
            width: 100%;
            min-height: 100vh;
        }

        h2 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 25px;
            color: #222;
        }

        .client-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .client-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            transition: box-shadow 0.3s ease;
            cursor: pointer;
        }

        .client-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .client-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 20px;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #aaa;
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
            font-size: 1.25rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 5px;
        }

        .client-last-class {
            font-size: 0.95rem;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 600px) {
            .main-content {
                padding: 20px;
            }

            .client-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .client-avatar {
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/images/fa_logo.png" alt="Fitness Academy" />
                <h3>Fitness Academy</h3>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="coach_dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i><span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Classes</div>
                    <a href="coach_my_classes.php" class="nav-item">
                        <i class="fas fa-dumbbell"></i><span>My Classes</span>
                    </a>
                    <a href="coach_class_schedule.php" class="nav-item">
                        <i class="fas fa-calendar-alt"></i><span>Class Schedule</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Members</div>
                    <a href="coach_my_clients.php" class="nav-item active">
                        <i class="fas fa-users"></i><span>My Clients</span>
                    </a>
                    <a href="coach_progress_tracking.php" class="nav-item">
                        <i class="fas fa-chart-line"></i><span>Progress Tracking</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
                    </a>
                    <a href="coach_view_video.php" class="nav-item">
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
                    <a href="coach_my_profile.php" class="nav-item">
                        <i class="fas fa-user"></i><span>My Profile</span>
                    </a>
                    <a href="logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <h2>My Clients</h2>
            <?php if (count($clients) > 0): ?>
                <div class="client-list">
                    <?php foreach ($clients as $client): ?>
                        <div class="client-card" tabindex="0">
                            <div class="client-avatar">
                                <?php if (!empty($client['ProfileImage'])): ?>
                                    <img src="<?= htmlspecialchars($client['ProfileImage']) ?>" alt="Profile Image" />
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="client-info">
                                <div class="client-name"><?= htmlspecialchars($client['First_Name'] . ' ' . $client['Last_Name']) ?></div>
                                <div class="client-last-class">Last class: <?= date('M d, Y', strtotime($client['last_class_date'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No clients assigned yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>