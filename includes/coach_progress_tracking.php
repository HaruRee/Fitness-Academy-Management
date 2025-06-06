<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

$coach_id = $_SESSION['user_id'];

// Handle manual progress input from coach
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_progress') {
    $client_id = intval($_POST['client_id']);
    $weight = floatval($_POST['weight']);
    $height = floatval($_POST['height']);
    $body_fat = !empty($_POST['body_fat']) ? floatval($_POST['body_fat']) : null;
    $workout_duration = !empty($_POST['workout_duration']) ? intval($_POST['workout_duration']) : null;
    $notes = trim($_POST['notes']);
    
    // Calculate BMI
    $bmi = ($height > 0) ? round($weight / (($height/100) * ($height/100)), 2) : null;
    
    try {
        // Check if record exists for today
        $checkStmt = $conn->prepare("SELECT id FROM clientprogress WHERE user_id = ? AND date = CURDATE()");
        $checkStmt->execute([$client_id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
          if ($existing) {
            // Update existing record
            $updateStmt = $conn->prepare("
                UPDATE clientprogress 
                SET weight = ?, height = ?, bmi = ?, body_fat = ?, workout_duration_min = ?, notes = ?, data_source = 'coach_input', recorded_by = ?, updated_at = NOW()
                WHERE user_id = ? AND date = CURDATE()
            ");
            $updateStmt->execute([$weight, $height, $bmi, $body_fat, $workout_duration, $notes, $coach_id, $client_id]);
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("
                INSERT INTO clientprogress (user_id, date, weight, height, bmi, body_fat, workout_duration_min, notes, data_source, recorded_by, created_at, updated_at) 
                VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, 'coach_input', ?, NOW(), NOW())
            ");
            $insertStmt->execute([$client_id, $weight, $height, $bmi, $body_fat, $workout_duration, $notes, $coach_id]);
        }
        
        $success_message = "Progress data saved successfully!";
    } catch (PDOException $e) {
        $error_message = "Error saving progress data: " . $e->getMessage();
    }
}

// Fetch coach info (optional, for sidebar or header)
$stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ? AND Role = 'Coach'");
$stmt->execute([$coach_id]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    header("Location: logout.php");
    exit;
}

// Fetch clients assigned to this coach
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

// For each client, fetch their latest progress data (weight, bmi, body fat, workout time)
$progressData = [];
if (count($clients) > 0) {
    $clientIds = array_column($clients, 'UserID');
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));    $stmt = $conn->prepare("
        SELECT p.user_id, p.date, p.weight, p.height, p.bmi, p.body_fat, p.workout_duration_min, p.notes, p.data_source
        FROM clientprogress p
        JOIN (
            SELECT user_id, MAX(date) AS latest_date
            FROM clientprogress
            WHERE user_id IN ($placeholders)
            GROUP BY user_id
        ) latest ON p.user_id = latest.user_id AND p.date = latest.latest_date
    ");
    $stmt->execute($clientIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $progressData[$row['user_id']] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Progress Tracking | Fitness Academy</title>
    <link rel="icon" href="../assets/images/fa_logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        /* Your existing styles, plus new ones for progress section */

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
            border-left: 5px solid #e41e26;
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

        .progress-tracking-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .progress-tracking-header h2 {
            flex: 1;
            font-weight: 700;
            font-size: 1.8rem;
            color: #222;
        }

        .client-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: box-shadow 0.3s ease;
        }

        .client-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .client-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .client-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 25px;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
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
            font-size: 1.3rem;
            font-weight: 700;
            color: #222;
        }

        .last-class-date {
            margin-top: 5px;
            font-size: 0.95rem;
            color: #555;
            font-style: italic;
        }

        .progress-stats {
            display: flex;
            gap: 30px;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .stat-box {
            flex: 1 1 150px;
            background: #f9f9f9;
            border-radius: 10px;
            padding: 15px 20px;
            text-align: center;
            box-shadow: inset 0 0 10px #ddd;
        }

        .stat-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #e41e26;
        }        .no-clients {
            text-align: center;
            color: #666;
            font-style: italic;
            margin-top: 60px;
            font-size: 1.1rem;
        }

        /* Progress Input Form Styles */
        .add-progress-btn {
            background: #ff4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .add-progress-btn:hover {
            background: #cc3333;
        }

        .progress-form {
            display: none;
            margin-top: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .progress-form.show {
            display: block;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .form-group textarea {
            resize: vertical;
            height: 60px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-save {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 600px) {
            .main-content {
                padding: 20px;
            }

            .client-card {
                padding: 15px 15px 25px;
            }

            .progress-stats {
                flex-direction: column;
                gap: 15px;
            }

            .stat-box {
                flex: none;
                width: 100%;
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
                    <a href="coach_my_clients.php" class="nav-item">
                        <i class="fas fa-users"></i><span>My Clients</span>
                    </a>
                    <a href="coach_progress_tracking.php" class="nav-item active">
                        <i class="fas fa-chart-line"></i><span>Progress Tracking</span>
                    </a>
                </div>                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
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
        </aside>        <!-- Main Content -->
        <div class="main-content">
            <div class="progress-tracking-header">
                <h2>Progress Tracking</h2>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if (count($clients) > 0): ?>
                <?php foreach ($clients as $client): ?>
                    <div class="client-card" tabindex="0">
                        <div class="client-header">
                            <div class="client-avatar">
                                <?php if (!empty($client['ProfileImage'])): ?>
                                    <img src="<?= htmlspecialchars($client['ProfileImage']) ?>" alt="Profile Image" />
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="client-info">
                                <div class="client-name"><?= htmlspecialchars($client['First_Name'] . ' ' . $client['Last_Name']) ?></div>
                                <div class="last-class-date">Last class: <?= date('M d, Y', strtotime($client['last_class_date'])) ?></div>
                            </div>
                        </div>
                        <?php
                        $p = $progressData[$client['UserID']] ?? null;
                        ?>                        <?php if ($p): ?>
                            <div class="progress-stats">                                <div class="stat-box">
                                    <div class="stat-label">Weight (kg)</div>
                                    <div class="stat-value"><?= isset($p['weight']) ? htmlspecialchars($p['weight']) : 'N/A' ?></div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-label">BMI</div>
                                    <div class="stat-value"><?= isset($p['bmi']) ? htmlspecialchars($p['bmi']) : 'N/A' ?></div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-label">Body Fat (%)</div>
                                    <div class="stat-value"><?= isset($p['body_fat']) && $p['body_fat'] ? htmlspecialchars($p['body_fat']) : 'N/A' ?></div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-label">Workout Duration (min)</div>
                                    <div class="stat-value"><?= isset($p['workout_duration_min']) && $p['workout_duration_min'] ? htmlspecialchars($p['workout_duration_min']) : 'N/A' ?></div>
                                </div>
                            </div>                            <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                                Last updated: <?= isset($p['date']) ? date('M d, Y', strtotime($p['date'])) : 'Unknown' ?>
                                <?php if (isset($p['data_source'])): ?>
                                <span style="margin-left: 10px; color: <?= $p['data_source'] === 'member_self' ? '#28a745' : '#007bff' ?>;">
                                    <i class="fas <?= $p['data_source'] === 'member_self' ? 'fa-user' : 'fa-user-tie' ?>"></i>
                                    <?= $p['data_source'] === 'member_self' ? 'Self-reported' : 'Coach input' ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 15px; color: #888; font-style: italic;">
                                No progress data available for this client.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add Progress Button -->
                        <button class="add-progress-btn" onclick="toggleProgressForm(<?= $client['UserID'] ?>)">
                            <i class="fas fa-plus"></i> Add/Update Progress
                        </button>
                        
                        <!-- Progress Input Form -->
                        <div class="progress-form" id="progress-form-<?= $client['UserID'] ?>">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="add_progress">
                                <input type="hidden" name="client_id" value="<?= $client['UserID'] ?>">
                                  <div class="form-row">
                                    <div class="form-group">
                                        <label for="weight-<?= $client['UserID'] ?>">Weight (kg)</label>
                                        <input type="number" step="0.1" name="weight" id="weight-<?= $client['UserID'] ?>" 
                                               value="<?= $p && isset($p['weight']) ? htmlspecialchars($p['weight']) : '' ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="height-<?= $client['UserID'] ?>">Height (cm)</label>
                                        <input type="number" step="0.1" name="height" id="height-<?= $client['UserID'] ?>" 
                                               value="<?= $p && isset($p['height']) ? htmlspecialchars($p['height']) : '' ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="body_fat-<?= $client['UserID'] ?>">Body Fat (%)</label>
                                        <input type="number" step="0.1" name="body_fat" id="body_fat-<?= $client['UserID'] ?>" 
                                               value="<?= $p && isset($p['body_fat']) ? htmlspecialchars($p['body_fat']) : '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="workout_duration-<?= $client['UserID'] ?>">Workout Duration (min)</label>
                                        <input type="number" name="workout_duration" id="workout_duration-<?= $client['UserID'] ?>" 
                                               value="<?= $p && isset($p['workout_duration_min']) ? htmlspecialchars($p['workout_duration_min']) : '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes-<?= $client['UserID'] ?>">Notes</label>
                                    <textarea name="notes" id="notes-<?= $client['UserID'] ?>" 
                                              placeholder="Optional notes about client's progress..."><?= $p && isset($p['notes']) ? htmlspecialchars($p['notes']) : '' ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" class="btn-cancel" onclick="toggleProgressForm(<?= $client['UserID'] ?>)">Cancel</button>
                                    <button type="submit" class="btn-save">Save Progress</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-clients">No clients assigned yet.</div>
            <?php endif; ?>        </div>
    </div>

    <script>
        function toggleProgressForm(clientId) {
            const form = document.getElementById('progress-form-' + clientId);
            if (form.classList.contains('show')) {
                form.classList.remove('show');
            } else {
                // Hide all other forms first
                document.querySelectorAll('.progress-form').forEach(f => f.classList.remove('show'));
                form.classList.add('show');
            }
        }
    </script>
</body>

</html>