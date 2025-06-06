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

$updateMsg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        $phone = $_POST['phone'];
        if (!empty($_FILES['profile_image']['name'])) {
            $image = $_FILES['profile_image'];
            $imgName = time() . '_' . basename($image['name']);
            $targetPath = "../uploads/" . $imgName;
            if (move_uploaded_file($image['tmp_name'], $targetPath)) {
                $stmt = $conn->prepare("UPDATE users SET Phone = ?, ProfileImage = ? WHERE UserID = ?");
                $stmt->execute([$phone, $targetPath, $coach_id]);
                $updateMsg = "Profile updated successfully!";
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET Phone = ? WHERE UserID = ?");
            $stmt->execute([$phone, $coach_id]);
            $updateMsg = "Profile updated successfully!";
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (password_verify($current, $coach['Password'])) {
            if ($new === $confirm) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                $stmt->execute([$hashed, $coach_id]);
                $updateMsg = "Password changed successfully!";
            } else {
                $updateMsg = "New passwords do not match.";
            }
        } else {
            $updateMsg = "Current password is incorrect.";
        }
    }    // Refresh user info
    $stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ? AND Role = 'Coach'");
    $stmt->execute([$coach_id]);
    $coach = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Profile | Fitness Academy</title>
    <link rel="icon" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #fff;
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
        }

        .form-container {
            background: #1e1e1e;
            padding: 30px;
            border-radius: 12px;
            max-width: 900px;
            margin: auto;
            color: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        h2 {
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin: 15px 0 5px;
            font-weight: 600;
        }

        input[type="text"],
        input[type="password"],
        input[type="file"],
        input[readonly] {
            width: 100%;
            padding: 10px;
            border: none;
            background: #2c2c2c;
            color: #fff;
            border-radius: 6px;
        }

        input[readonly] {
            background: #333;
            color: #aaa;
        }

        .readonly-lock {
            color: #e41e26;
            margin-left: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-actions {
            text-align: right;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-update {
            background: #e41e26;
            color: white;
        }

        .update-msg {
            margin-top: 20px;
            color: #00c853;
            font-weight: bold;
        }

        .two-col {
            display: flex;
            gap: 40px;
        }

        .two-col>div {
            flex: 1;
        }

        .section-title {
            font-size: 1.2rem;
            color: #e41e26;
            margin-top: 30px;
            border-bottom: 1px solid #e41e26;
            padding-bottom: 5px;
        }

        .profile-image-preview {
            margin-top: 10px;
            max-width: 120px;
            border-radius: 8px;
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
                </div>                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="coach_my_profile.php" class="nav-item active"><i class="fas fa-user"></i><span>My Profile</span></a>
                    <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="form-container">
                <h2>Update Profile</h2>
                <?php if ($updateMsg): ?>
                    <div class="update-msg"><?= htmlspecialchars($updateMsg) ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="two-col">
                        <div>
                            <div class="section-title">Personal Information</div>
                            <div class="form-group">
                                <label>First Name <i class="fas fa-lock readonly-lock"></i></label>
                                <input type="text" value="<?= htmlspecialchars($coach['First_Name']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Last Name <i class="fas fa-lock readonly-lock"></i></label>
                                <input type="text" value="<?= htmlspecialchars($coach['Last_Name']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($coach['Phone']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Date of Birth <i class="fas fa-lock readonly-lock"></i></label>
                                <input type="text" value="<?= htmlspecialchars($coach['DateOfBirth']) ?>" readonly>
                            </div>
                        </div>
                        <div>
                            <div class="section-title">Address</div>
                            <div class="form-group">
                                <label>Address <i class="fas fa-lock readonly-lock"></i></label>
                                <input type="text" value="<?= htmlspecialchars($coach['Address']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Profile Image</label>
                                <input type="file" name="profile_image">
                                <?php if (!empty($coach['ProfileImage'])): ?>
                                    <img src="<?= htmlspecialchars($coach['ProfileImage']) ?>" class="profile-image-preview">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-update">Save Changes</button>
                    </div>
                </form>

                <form method="post">
                    <div class="section-title">Change Password</div>
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" id="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <input type="checkbox" id="showPassword" onclick="togglePasswordVisibility()">
                        <label for="showPassword" style="display:inline; margin-left: 8px;">Show Password</label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-update">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            ['current_password', 'new_password', 'confirm_password'].forEach(id => {
                const input = document.getElementById(id);
                if (input.type === "password") {
                    input.type = "text";
                } else {
                    input.type = "password";
                }
            });
        }
    </script>
</body>

</html>