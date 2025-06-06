<?php
session_start();
require '../config/database.php';
require 'activity_tracker.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header('Location: login.php');
    exit;
}

// Track page view activity
if (isset($_SESSION['user_id'])) {
    trackPageView($_SESSION['user_id'], 'Staff Settings');
}

$success_message = '';
$error_message = '';

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password, $user['Password_Hash'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET Password_Hash = ? WHERE UserID = ?");
                    if ($stmt->execute([$new_password_hash, $user_id])) {
                        $success_message = "Password updated successfully!";
                    } else {
                        $error_message = "Failed to update password.";
                    }
                } else {
                    $error_message = "Password must be at least 6 characters long.";
                }
            } else {
                $error_message = "New passwords do not match.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Settings - Fitness Academy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="../assets/js/auto-logout.js" defer></script><style>
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
            --white-color: #ffffff;
            --text-color: #1e293b;
            --border-color: #e2e8f0;
            --error-color: #ef4444;
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
            font-size: 0.85rem;
            color: var(--gray-color);
        }

        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            height: var(--header-height);
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        main {
            padding: 2rem;
            background: #f3f4f6;
            flex: 1;
        }

        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .settings-card {
            background: var(--white-color);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .settings-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: #f8fafc;
        }

        .settings-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .settings-content {
            padding: 1.5rem;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
        }

        .profile-info h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .profile-info p {
            color: var(--gray-color);
            margin-bottom: 0.25rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #5147e5;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-color);
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 500;
            color: var(--text-color);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .main-wrapper {
                margin-left: 70px;
            }

            .sidebar-header h2,
            .sidebar-menu-header,
            .sidebar a span,
            .user-info {
                display: none;
            }

            .sidebar a {
                justify-content: center;
            }

            .user-profile {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 0.5rem;
            }

            main {
                padding: 0.5rem;
            }

            .settings-card {
                padding: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Fitness Academy</h2>
        </div>        <nav class="sidebar-menu">
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
            <a href="staff_memberlist.php">
                <i class="fas fa-users"></i>
                <span>Member List</span>
            </a>

            <div class="sidebar-menu-header">POS</div>
            <a href="staff_pos.php">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>

            <div class="sidebar-menu-header">Account</div>
            <a href="staff_settings.php" class="active">
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
            <h1>Settings</h1>
            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></span>
            </div>
        </header>

        <main>
            <div class="settings-container">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success_message ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Information -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h3><i class="fas fa-user"></i> Profile Information</h3>
                    </div>
                    <div class="settings-content">
                        <div class="profile-section">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['First_Name'], 0, 1) . substr($user['Last_Name'], 0, 1)) ?>
                            </div>
                            <div class="profile-info">
                                <h4><?= htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']) ?></h4>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['Email']) ?></p>
                                <p><i class="fas fa-id-badge"></i> Staff Member</p>
                            </div>
                        </div>                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Join Date</div>
                                <div class="info-value"><?= date('M j, Y', strtotime($user['RegistrationDate'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                    </div>
                    <div class="settings-content">
                        <form method="POST">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                            <button type="submit" name="update_password" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
