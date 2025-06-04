<?php
session_start();
require_once '../config/database.php';
require_once 'includes/activity_tracker.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Staff'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // This is a basic framework - you can expand this to handle actual settings
    $message = "Settings functionality is currently under development.";
    $message_type = "info";
}

trackActivity($_SESSION['user_id'], "Visited Staff Settings", "User accessed staff settings page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Settings - Fitness Gym</title>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .settings-content {
            padding: 2rem;
        }
        
        .settings-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e1e8ed;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .settings-section h3 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .coming-soon {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .coming-soon i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <i class="fas fa-dumbbell"></i>
                <span>Fitness Gym Staff</span>
            </div>
            <div class="nav-menu">
                <a href="staff_dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <div class="nav-user">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-menu-header">Main Menu</div>
            <a href="staff_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="staff_pos.php">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>
            <a href="staff_attendance.php">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
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
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="settings-container">
                <div class="settings-header">
                    <h1><i class="fas fa-cog"></i> Staff Settings</h1>
                    <p>Configure your preferences and account settings</p>
                </div>

                <div class="settings-content">
                    <?php if (isset($message)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="coming-soon">
                        <i class="fas fa-tools"></i>
                        <h2>Settings Panel Coming Soon</h2>
                        <p>Personal configuration options are currently being developed</p>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-user"></i> Account Information</h3>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['role']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Account Status</label>
                            <input type="text" class="form-control" value="Active" readonly>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                        <div class="form-group">
                            <label>Email Notifications</label>
                            <select class="form-control" disabled>
                                <option>Enabled (Coming Soon)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Desktop Notifications</label>
                            <select class="form-control" disabled>
                                <option>Enabled (Coming Soon)</option>
                            </select>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-lock"></i> Security Settings</h3>
                        <div class="form-group">
                            <label>Change Password</label>
                            <input type="password" class="form-control" placeholder="Current Password" disabled>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" class="form-control" placeholder="New Password" disabled>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" class="form-control" placeholder="Confirm New Password" disabled>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3><i class="fas fa-palette"></i> Display Preferences</h3>
                        <div class="form-group">
                            <label>Theme</label>
                            <select class="form-control" disabled>
                                <option>Light Theme</option>
                                <option>Dark Theme</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Language</label>
                            <select class="form-control" disabled>
                                <option>English</option>
                            </select>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="button" class="btn-primary" disabled>
                            <i class="fas fa-save"></i>
                            Save Settings (Coming Soon)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add any JavaScript functionality here if needed
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Staff Settings page loaded');
        });
    </script>
</body>
</html>
