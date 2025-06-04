<?php
session_start();
require_once '../config/database.php';
require_once 'activity_tracker.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Update user info
        if (!empty($firstName) || !empty($lastName) || !empty($email) || !empty($phone)) {
            $updateSql = "UPDATE users SET ";
            $updateParams = [];
            
            if (!empty($firstName)) {
                $updateSql .= "First_Name = ?, ";
                $updateParams[] = $firstName;
            }
            
            if (!empty($lastName)) {
                $updateSql .= "Last_Name = ?, ";
                $updateParams[] = $lastName;
            }
            
            if (!empty($email)) {
                // Check if email is unique
                $checkEmailStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE Email = ? AND UserID != ?");
                $checkEmailStmt->execute([$email, $userId]);
                if ($checkEmailStmt->fetchColumn() > 0) {
                    throw new Exception("Email address is already in use.");
                }
                
                $updateSql .= "Email = ?, ";
                $updateParams[] = $email;
            }
            
            if (!empty($phone)) {
                $updateSql .= "Phone = ?, ";
                $updateParams[] = $phone;
            }
            
            // Remove the trailing comma and space
            $updateSql = rtrim($updateSql, ', ');
            
            $updateSql .= " WHERE UserID = ?";
            $updateParams[] = $userId;
            
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute($updateParams);
        }
        
        // Handle password change if requested
        if (!empty($currentPassword) && !empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                throw new Exception("New password and confirmation do not match.");
            }
            
            // Verify current password
            $verifyStmt = $conn->prepare("SELECT PasswordHash FROM users WHERE UserID = ?");
            $verifyStmt->execute([$userId]);
            $storedHash = $verifyStmt->fetchColumn();
            
            if (!password_verify($currentPassword, $storedHash)) {
                throw new Exception("Current password is incorrect.");
            }
            
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare("UPDATE users SET PasswordHash = ? WHERE UserID = ?");
            $passwordStmt->execute([$newHash, $userId]);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log the activity
        trackUserActivity($userId, "settings_update", "Administrator updated their account settings");
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $success_message = "Settings updated successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error_message = $e->getMessage();
    }
}

trackUserActivity($userId, "page_view", "User accessed admin settings page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Settings | Fitness Academy</title>
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
            left: 3rem;
            color: var(--gray-color);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
        }

        .main-content {
            padding: 2rem;
            flex: 1;
        }
        
        /* Settings Specific Styles */
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .settings-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4f46e5 100%);
            color: white;
            padding: 1.5rem 2rem;
            text-align: center;
        }
        
        .settings-content {
            padding: 2rem;
        }
        
        .settings-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f9fafb;
        }
        
        .settings-section h3 {
            color: var(--dark-color);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            position: relative;
            padding-left: 3rem;
        }
        
        .alert i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.25rem;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
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
            <a href="admin_video_approval.php">
                <i class="fas fa-video"></i>
                <span>Video Approval</span>
            </a>
            <a href="track_payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payment Status</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>
            
            <div class="sidebar-menu-header">Attendance</div>
            <a href="../attendance/checkin.php">
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
            </a>
            <a href="audit_trail.php">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
            </a>

            <div class="sidebar-menu-header">Database</div>
            <a href="database_management.php">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>

            <div class="sidebar-menu-header">Account</div>
            <a href="admin_settings.php" class="active">
                <i class="fas fa-cog"></i>
                <span>Settings</span>            </a>
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
        <!-- Header -->
        <header class="header">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="settings-container">
                <div class="settings-header">
                    <h1><i class="fas fa-cog"></i> Admin Settings</h1>
                    <p>Configure system settings and preferences</p>
                </div>                <div class="settings-content">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <div class="settings-section">
                            <h3><i class="fas fa-user"></i> Admin Profile Settings</h3>
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['First_Name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['Last_Name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['Phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-lock"></i> Change Password</h3>
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3><i class="fas fa-database"></i> System Information</h3>
                            <div class="form-group">
                                <label>System Status</label>
                                <input type="text" class="form-control" value="Operational" readonly>
                            </div>
                            <div class="form-group">
                                <label>Database Connection</label>
                                <input type="text" class="form-control" value="Connected" readonly>
                            </div>
                            <div class="form-group">
                                <label>Current User</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['Username'] . ' (' . $user['Role'] . ')'); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Last Login</label>
                                <input type="text" class="form-control" value="<?php echo !empty($user['LastLogin']) ? date('F d, Y h:i A', strtotime($user['LastLogin'])) : 'N/A'; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Registration Date</label>
                                <input type="text" class="form-control" value="<?php echo !empty($user['RegistrationDate']) ? date('F d, Y', strtotime($user['RegistrationDate'])) : 'N/A'; ?>" readonly>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 2rem;">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>                </div>
            </div>
        </main>
    </div>

    <script>
        // Add any JavaScript functionality here if needed
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin Settings page loaded');
        });
    </script>
</body>
</html>