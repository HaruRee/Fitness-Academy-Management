<?php
session_start();
require_once '../config/database.php';
require_once 'activity_tracker.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Run automatic deactivation check when accessing user management (throttled)
if (shouldRunAutomaticDeactivation($conn)) {
    $deactivatedCount = runAutomaticDeactivation($conn);
    if ($deactivatedCount > 0) {
        $success = "Automatic deactivation completed: {$deactivatedCount} inactive users were automatically deactivated.";
    }
}

// Initialize variables
$users = [];
$error = '';
$success = '';

// Process delete user request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        // Begin transaction to ensure all operations complete or none
        $conn->beginTransaction();

        // First, get the username of the user being deleted
        $get_username = $conn->prepare("SELECT Username FROM users WHERE UserID = :id");
        $get_username->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
        $get_username->execute();
        $user_data = $get_username->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $username_to_delete = $user_data['Username'];

            // FIRST: Check if audit_trail table allows NULL values for username
            $stmt = $conn->prepare("SHOW COLUMNS FROM audit_trail LIKE 'username'");
            $stmt->execute();
            $column = $stmt->fetch(PDO::FETCH_ASSOC);
            $allowsNull = (strtoupper($column['Null']) === 'YES');

            if (!$allowsNull) {
                // If NULL is not allowed, modify it first to allow NULL values
                // This is safer than trying to update all records
                $conn->exec("ALTER TABLE audit_trail MODIFY COLUMN username VARCHAR(50) NULL");
            }

            // Update any audit_trail entries with this username to prevent constraint violation
            // Use a safe approach that handles potential NULL values
            $update_audit = $conn->prepare("UPDATE audit_trail SET username = CONCAT('deleted_user_', :id) WHERE username = :username");
            $update_audit->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $update_audit->bindParam(':username', $username_to_delete);
            $update_audit->execute();

            // Now delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE UserID = :id");
            $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $stmt->execute();

            // Log the action in audit trail
            $audit_stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (:username, :action, NOW())");
            $audit_stmt->bindParam(':username', $_SESSION['username']);
            $action = "deleted user ID: " . $_GET['id'] . " (Username: " . $username_to_delete . ")";
            $audit_stmt->bindParam(':action', $action);
            $audit_stmt->execute();

            // Commit transaction
            $conn->commit();
            $success = "User deleted successfully.";
        } else {            // Only roll back if in a transaction
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "User not found.";
        }
    } catch (PDOException $e) {
        // Rollback transaction on error, but only if there's an active transaction
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Error deleting user: " . $e->getMessage();

        // Get more detailed information about the exception
        $errorInfo = "";
        if ($e->errorInfo && is_array($e->errorInfo)) {
            $errorInfo = " | SQL State: " . ($e->errorInfo[0] ?? 'N/A') .
                " | Error Code: " . ($e->errorInfo[1] ?? 'N/A') .
                " | Message: " . ($e->errorInfo[2] ?? 'N/A');
        }
        error_log("User deletion error: " . $e->getMessage() . $errorInfo);
    }
}

// Get all users with enhanced data
try {
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            CASE 
                WHEN u.last_activity_date < DATE_SUB(NOW(), INTERVAL 90 DAY) 
                    AND u.Role = 'Member' 
                    AND u.account_status = 'active' 
                THEN 1 
                ELSE 0 
            END as needs_auto_deactivation,
            DATEDIFF(NOW(), u.last_activity_date) as days_since_last_activity
        FROM users u 
        ORDER BY u.UserID DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}

// Get count of users by role
try {
    $stmt = $conn->prepare("SELECT Role, COUNT(*) as count FROM users GROUP BY Role");
    $stmt->execute();
    $roleCounts = $stmt->fetchAll(PDO::FETCH_ASSOC); // Initialize counts
    $adminCount = $coachCount = $staffCount = $memberCount = 0;

    foreach ($roleCounts as $roleCount) {
        switch ($roleCount['Role']) {
            case 'Admin':
                $adminCount = $roleCount['count'];
                break;
            case 'Coach':
                $coachCount = $roleCount['count'];
                break;
            case 'Staff':
                $staffCount = $roleCount['count'];
                break;
            case 'Member':
                $memberCount = $roleCount['count'];
                break;
        }
    }
} catch (PDOException $e) {
    $error = "Error fetching role counts: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>User Management | Fitness Academy</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="../assets/js/auto-logout.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
            position: relative;
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
            left: 0.75rem;
            color: var(--gray-color);
            z-index: 1;
        }

        .header-actions {
            display: flex;
            align-items: center;
        }

        .notification-bell {
            background: #f3f4f6;
            height: 40px;
            width: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-right: 1rem;
            position: relative;
        }

        .notification-bell i {
            color: var(--gray-color);
        }

        .content {
            flex: 1;
            padding: 2rem;
            background: #f8fafc;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(30, 64, 175, 0.1);
            color: var(--primary-color);
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .controls-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .bulk-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .bulk-actions-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            color: var(--dark-color);
        }

        .checkbox-container input {
            margin-right: 0.5rem;
        }

        .results-count {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table {
            margin: 0;
        }

        .table th {
            border: none;
            background: #f8fafc;
            color: var(--gray-color);
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
        }

        .table td {
            border: none;
            padding: 1rem 1.5rem;
            vertical-align: middle;
        }

        .table tbody tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 500;
            color: var(--dark-color);
        }

        .user-details {
            margin-top: 0.25rem;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }

        .status-indicator.warning {
            color: var(--warning-color);
        }

        .status-indicator.danger {
            color: var(--danger-color);
        }

        .activity-info {
            display: flex;
            flex-direction: column;
        }

        .activity-date {
            font-weight: 500;
            color: var(--dark-color);
        }

        .activity-ago {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        .status-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-archived {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .status-blacklisted {
            background: rgba(0, 0, 0, 0.1);
            color: #000;
        }

        .status-unknown {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
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

        .notification-bell {
            background: #f3f4f6;
            height: 40px;
            width: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-right: 1rem;
            position: relative;
        }

        .notification-bell i {
            color: var(--gray-color);
            font-size: 1.1rem;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-content {
            padding: 2rem;
            flex: 1;
        }

        .welcome-header {
            margin-bottom: 2rem;
        }

        .welcome-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .welcome-header p {
            color: var(--gray-color);
            font-size: 1rem;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .icon-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .icon-red {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .icon-green {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .icon-orange {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .icon-purple {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .stat-icon i {
            font-size: 1.5rem;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-color);
            font-size: 0.85rem;
        }

        /* Filter and Action Styles */
        .controls-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .controls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .controls-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.6rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.9rem;
            background: white;
            color: var(--dark-color);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: var(--gray-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .bulk-actions label {
            font-weight: 500;
            color: var(--dark-color);
        }

        .bulk-actions select {
            width: 200px;
        }

        .selected-count {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: auto;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            color: var(--gray-color);
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.85rem;
            background: #f9fafb;
        }

        .users-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
            color: var(--dark-color);
        }

        .users-table tr:hover {
            background: rgba(0, 0, 0, 0.02);
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-archived {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .status-blacklisted {
            background: rgba(0, 0, 0, 0.1);
            color: #000;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
        }

        .btn-secondary {
            background: var(--gray-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        /* Form Controls */
        .form-select,
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            color: var(--dark-color);
            background: white;
            transition: border-color 0.2s ease;
        }

        .form-select:focus,
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e5e7eb;
            padding: 1.5rem;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out;
            transform: translate(0, -50px);
        }

        .modal.show .modal-dialog {
            transform: none;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Ensure modals appear above everything */
        .modal {
            z-index: 1050;
        }

        .modal-backdrop {
            z-index: 1040;
        }

        .role-admin {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .role-coach {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .role-staff {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .role-member {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .btn-edit {
            background: var(--primary-color);
            color: white;
        }

        .btn-edit:hover {
            background: #1e3a8a;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .text-center {
            text-align: center;
        }

        .mt-4 {
            margin-top: 2rem;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .filters-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h2,
            .sidebar a span,
            .user-info {
                display: none;
            }

            .sidebar a i {
                margin-right: 0;
            }

            .sidebar a {
                justify-content: center;
            }

            .user-profile {
                padding: 1rem;
            }

            .main-wrapper {
                margin-left: 80px;
            }

            .header {
                padding: 0 1rem;
            }

            .header-search input {
                width: 200px;
            }

            .content {
                padding: 1rem;
            }

            .bulk-actions {
                flex-direction: column;
                gap: 1rem;
            }

            .bulk-actions-left {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }

            .main-wrapper {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .header-search {
                display: none;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .action-btns {
                flex-direction: column;
                gap: 0.25rem;
            }
        }


        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-wrapper {
                margin-left: 0;
            }

            .header {
                padding: 0 1rem;
            }

            .mobile-menu-btn {
                display: flex;
                background: none;
                border: none;
                color: var(--gray-color);
                font-size: 1.2rem;
                cursor: pointer;
                padding: 0.5rem;
                margin-right: 1rem;
            }

            .header-search input {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .header-search {
                display: none;
            }

            .main-content {
                padding: 1.5rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .controls-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-btns {
                flex-direction: column;
                gap: 0.25rem;
            }
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

            <div class="sidebar-menu-header">Management</div> <a href="manage_users.php" class="active">
                <i class="fas fa-users-cog"></i>
                <span>Manage Users</span>
            </a><a href="member_list.php">
                <i class="fas fa-users"></i>
                <span>Member List</span>
            </a>
            <a href="coach_applications.php">
                <i class="fas fa-user-tie"></i>
                <span>Coach Applications</span>
                <a href="admin_video_approval.php">
                    <i class="fas fa-video"></i>
                    <span>Video Approval</span>
                </a>
            </a> <a href="track_payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payment Status</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>

            <div class="sidebar-menu-header">Attendance</div>
            <a href="qr_scanner.php">
                <i class="fas fa-camera"></i>
                <span>QR Scanner</span>
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
            </a>            <a href="audit_trail.php">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
            </a>

            <div class="sidebar-menu-header">Database</div>
            <a href="database_management.php">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>

            <div class="sidebar-menu-header">Account</div>
            <a href="admin_settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
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
        <div class="header">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" id="userSearch" placeholder="Search users...">
            </div>
            <div class="header-actions">
                <div class="notification-bell">
                    <i class="fas fa-bell"></i>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus"></i> Add User
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card role-btn" data-role="all">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($users); ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>
                <div class="stat-card role-btn" data-role="Admin">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $adminCount; ?></div>
                        <div class="stat-label">Admins</div>
                    </div>
                </div>
                <div class="stat-card role-btn" data-role="Coach">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $coachCount; ?></div>
                        <div class="stat-label">Coaches</div>
                    </div>
                </div>
                <div class="stat-card role-btn" data-role="Staff">
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $staffCount; ?></div>
                        <div class="stat-label">Staff</div>
                    </div>
                </div>
                <div class="stat-card role-btn" data-role="Member">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $memberCount; ?></div>
                        <div class="stat-label">Members</div>
                    </div>
                </div>
            </div> <!-- Controls and Filters -->
            <div class="controls-section">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Role</label>
                        <select id="roleFilterDropdown" class="form-select">
                            <option value="all">All Roles</option>
                            <option value="Admin">Admins</option>
                            <option value="Coach">Coaches</option>
                            <option value="Staff">Staff</option>
                            <option value="Member">Members</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                            <option value="blacklisted">Blacklisted</option>
                            <option value="auto_deactivated">Auto-Deactivated</option>
                            <option value="needs_deactivation">Needs Deactivation</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sort by</label>
                        <select id="sortDropdown" class="form-select">
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                            <option value="date_asc">Registration (Oldest)</option>
                            <option value="date_desc" selected>Registration (Newest)</option>
                            <option value="activity_asc">Last Activity (Oldest)</option>
                            <option value="activity_desc">Last Activity (Recent)</option>
                        </select>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions">
                    <div class="bulk-actions-left">
                        <label class="checkbox-container">
                            <input type="checkbox" id="selectAll">
                            <span class="checkmark"></span>
                            Select All
                        </label>
                        <select id="bulkActionSelect" class="form-select">
                            <option value="">Bulk Actions</option>
                            <option value="active">Activate Selected</option>
                            <option value="inactive">Deactivate Selected</option>
                            <option value="archived">Archive Selected</option>
                            <option value="blacklisted">Blacklist Selected</option>
                        </select>
                        <button type="button" class="btn btn-warning" onclick="executeBulkAction()">
                            <i class="fas fa-tasks"></i> Execute
                        </button>
                    </div>
                    <div class="bulk-actions-right">
                        <span class="results-count">Showing <span id="filteredCount"><?php echo count($users); ?></span> of <?php echo count($users); ?> users</span>
                    </div>
                </div>
            </div>
            <!-- Users Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3>Users</h3>
                </div>
                <div class="table-responsive">
                    <table class="table" id="usersTable">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" class="form-check-input" id="selectAllHeader">
                                </th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user):
                                // Determine account status with fallback
                                $accountStatus = $user['account_status'] ?? ($user['IsActive'] ? 'active' : 'inactive');
                                $lastActivity = $user['last_activity_date'] ?? $user['RegistrationDate'];
                                $daysSinceActivity = $user['days_since_last_activity'] ?? 0;
                                $needsDeactivation = $user['needs_auto_deactivation'] ?? false;
                                $autoDeactivated = $user['auto_deactivated'] ?? false;
                            ?>
                                <tr class="user-row"
                                    data-role="<?php echo $user['Role']; ?>"
                                    data-user-id="<?php echo $user['UserID']; ?>"
                                    data-status="<?php echo $accountStatus; ?>"
                                    data-auto-deactivated="<?php echo $autoDeactivated ? 'true' : 'false'; ?>"
                                    data-needs-deactivation="<?php echo $needsDeactivation ? 'true' : 'false'; ?>">

                                    <td>
                                        <input type="checkbox" class="form-check-input user-checkbox"
                                            value="<?php echo $user['UserID']; ?>" name="selected_users[]">
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($user['First_Name'] . ' ' . $user['Last_Name']); ?></div>
                                            <div class="user-details">
                                                <?php if ($needsDeactivation): ?>
                                                    <span class="status-indicator warning" title="Needs auto-deactivation (90+ days inactive)">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($autoDeactivated): ?>
                                                    <span class="status-indicator danger" title="Auto-deactivated due to inactivity">
                                                        <i class="fas fa-robot"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['Username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower($user['Role']); ?>">
                                            <?php echo $user['Role']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="status-badges">
                                            <?php
                                            switch ($accountStatus) {
                                                case 'active':
                                                    echo '<span class="status-badge status-active">Active</span>';
                                                    break;
                                                case 'inactive':
                                                    echo '<span class="status-badge status-inactive">Inactive</span>';
                                                    break;
                                                case 'archived':
                                                    echo '<span class="status-badge status-archived">Archived</span>';
                                                    break;
                                                case 'blacklisted':
                                                    echo '<span class="status-badge status-blacklisted">Blacklisted</span>';
                                                    break;
                                                default:
                                                    echo '<span class="status-badge status-unknown">Unknown</span>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="activity-info">
                                            <?php if ($lastActivity): ?>
                                                <div class="activity-date"><?php echo date('M d, Y', strtotime($lastActivity)); ?></div>
                                                <?php if ($daysSinceActivity > 0): ?>
                                                    <div class="activity-ago"><?php echo $daysSinceActivity; ?> days ago</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <!-- Edit User Button -->
                                            <button type="button" class="btn btn-sm btn-edit edit-user"
                                                data-id="<?php echo $user['UserID']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['Username']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['Email']); ?>"
                                                data-role="<?php echo $user['Role']; ?>"
                                                data-firstname="<?php echo htmlspecialchars($user['First_Name']); ?>"
                                                data-lastname="<?php echo htmlspecialchars($user['Last_Name']); ?>"
                                                data-phone="<?php echo htmlspecialchars($user['Phone'] ?? ''); ?>"
                                                data-address="<?php echo htmlspecialchars($user['Address'] ?? ''); ?>"
                                                data-dob="<?php echo $user['DateOfBirth'] ?? ''; ?>"
                                                data-status="<?php echo $user['IsActive']; ?>"
                                                data-approved="<?php echo $user['is_approved']; ?>"
                                                data-account-status="<?php echo $accountStatus; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- Status Change Dropdown -->
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="Change Status">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($accountStatus !== 'active'): ?>
                                                        <li><a class="dropdown-item" href="user_actions.php?action=activate&id=<?php echo $user['UserID']; ?>"
                                                                onclick="return confirm('Activate this user?')">
                                                                <i class="fas fa-check-circle text-success"></i> Activate
                                                            </a></li>
                                                    <?php endif; ?>

                                                    <?php if ($accountStatus !== 'inactive'): ?>
                                                        <li><a class="dropdown-item" href="user_actions.php?action=deactivate&id=<?php echo $user['UserID']; ?>"
                                                                onclick="return confirm('Deactivate this user?')">
                                                                <i class="fas fa-pause-circle text-warning"></i> Deactivate
                                                            </a></li>
                                                    <?php endif; ?>

                                                    <?php if ($accountStatus !== 'archived'): ?>
                                                        <li><a class="dropdown-item" href="user_actions.php?action=archive&id=<?php echo $user['UserID']; ?>"
                                                                onclick="return confirm('Archive this user?')">
                                                                <i class="fas fa-archive text-secondary"></i> Archive
                                                            </a></li>
                                                    <?php endif; ?>

                                                    <?php if ($accountStatus !== 'blacklisted'): ?>
                                                        <li><a class="dropdown-item" href="user_actions.php?action=blacklist&id=<?php echo $user['UserID']; ?>"
                                                                onclick="return confirm('Blacklist this user? This is a serious action.')">
                                                                <i class="fas fa-ban text-danger"></i> Blacklist
                                                            </a></li>
                                                    <?php endif; ?>

                                                    <?php if ($autoDeactivated || $accountStatus === 'inactive'): ?>
                                                        <li>
                                                            <hr class="dropdown-divider">
                                                        </li>
                                                        <li><a class="dropdown-item" href="#"
                                                                onclick="showReactivateModal(<?php echo $user['UserID']; ?>, '<?php echo htmlspecialchars($user['Username']); ?>')">
                                                                <i class="fas fa-redo text-primary"></i> Reactivate
                                                            </a></li>
                                                    <?php endif; ?>

                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="#"
                                                            onclick="showStatusChangeModal(<?php echo $user['UserID']; ?>, '<?php echo htmlspecialchars($user['Username']); ?>', '<?php echo $accountStatus; ?>')">
                                                            <i class="fas fa-exchange-alt text-info"></i> Change Status
                                                        </a></li>
                                                </ul>
                                            </div>

                                            <!-- Delete User Button -->
                                            <button type="button" class="btn btn-sm btn-delete delete-user"
                                                data-id="<?php echo $user['UserID']; ?>"
                                                title="Delete User">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUserModalLabel">Add New Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" action="user_actions.php" method="post">
                        <input type="hidden" name="action" value="add">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6"> <label for="role" class="form-label">Role</label> <select class="form-select" id="role" name="role" required>
                                    <option value="Member" selected>Member</option>
                                    <option value="Admin">Admin</option>
                                    <option value="Coach">Coach</option>
                                    <option value="Staff">Staff</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                                    <label class="form-check-label" for="isActive">Account Active</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isApproved" name="isApproved" checked>
                                    <label class="form-check-label" for="isApproved">Account Approved</label>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" action="user_actions.php" method="post">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="userId" id="editUserId">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="editFirstName" name="firstName" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="editLastName" name="lastName" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="editUsername" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editPassword" class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" id="editPassword" name="password">
                            </div>
                            <div class="col-md-6">
                                <label for="editRole" class="form-label">Role</label> <select class="form-select" id="editRole" name="role" required>
                                    <option value="Admin">Admin</option>
                                    <option value="Coach">Coach</option>
                                    <option value="Staff">Staff</option>
                                    <option value="Member">Member</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="editPhone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="editDob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="editDob" name="dob">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editAddress" class="form-label">Address</label>
                            <textarea class="form-control" id="editAddress" name="address" rows="2"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editIsActive" name="isActive">
                                    <label class="form-check-label" for="editIsActive">Account Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editIsApproved" name="isApproved">
                                    <label class="form-check-label" for="editIsApproved">Account Approved</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editEmailConfirmed" name="emailConfirmed">
                                    <label class="form-check-label" for="editEmailConfirmed">Email Confirmed</label>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="statusChangeModalLabel">Change User Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="statusChangeForm" action="user_actions.php" method="post">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="user_id" id="statusChangeUserId">

                        <div class="mb-3">
                            <label class="form-label fw-bold">User:</label>
                            <div id="statusChangeUserInfo" class="p-2 bg-light rounded"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Status:</label>
                            <div id="currentStatusDisplay" class="p-2 bg-light rounded"></div>
                        </div>

                        <div class="mb-3">
                            <label for="newStatus" class="form-label fw-bold">New Status:</label>
                            <select class="form-select" id="newStatus" name="new_status" required>
                                <option value="">Select new status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                                <option value="blacklisted">Blacklisted</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="changeReason" class="form-label fw-bold">Reason for change:</label>
                            <textarea class="form-control" id="changeReason" name="reason" rows="3"
                                placeholder="Enter reason for status change..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="adminNotes" class="form-label">Admin Notes (Optional):</label>
                            <textarea class="form-control" id="adminNotes" name="admin_notes" rows="2"
                                placeholder="Additional notes for internal use..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="statusChangeForm" class="btn btn-warning">Change Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reactivate User Modal -->
    <div class="modal fade" id="reactivateModal" tabindex="-1" aria-labelledby="reactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="reactivateModalLabel">Reactivate User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="reactivateForm" action="user_actions.php" method="post">
                        <input type="hidden" name="action" value="reactivate">
                        <input type="hidden" name="user_id" id="reactivateUserId">

                        <div class="mb-3">
                            <label class="form-label fw-bold">User to reactivate:</label>
                            <div id="reactivateUserInfo" class="p-2 bg-light rounded"></div>
                        </div>

                        <div class="mb-3">
                            <label for="reactivateReason" class="form-label fw-bold">Reason for reactivation:</label>
                            <textarea class="form-control" id="reactivateReason" name="reason" rows="3"
                                placeholder="Enter reason for reactivating this account..." required></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Reactivating this account will:
                            <ul class="mb-0 mt-2">
                                <li>Set account status to "Active"</li>
                                <li>Update last activity date to now</li>
                                <li>Clear auto-deactivation flag</li>
                                <li>Log the action in audit trail</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="reactivateForm" class="btn btn-success">Reactivate Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Action Confirmation Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1" aria-labelledby="bulkActionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="bulkActionModalLabel">Confirm Bulk Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bulkActionForm" action="user_actions.php" method="post">
                        <input type="hidden" name="action" value="bulk_action">
                        <input type="hidden" name="bulk_action_type" id="bulkActionType">
                        <div id="selectedUsersContainer"></div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Action:</label>
                            <div id="bulkActionDescription" class="p-2 bg-light rounded"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Selected Users (<span id="bulkUserCount">0</span>):</label>
                            <div id="bulkUserList" class="p-2 bg-light rounded" style="max-height: 150px; overflow-y: auto;"></div>
                        </div>

                        <div class="mb-3">
                            <label for="bulkReason" class="form-label fw-bold">Reason for bulk action:</label>
                            <textarea class="form-control" id="bulkReason" name="bulk_reason" rows="3"
                                placeholder="Enter reason for this bulk action..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="bulkActionForm" class="btn btn-warning">Execute Bulk Action</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() { // Handle search functionality
            $("#userSearch").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#usersTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });

            // Enhanced sorting functionality
            $("#sortDropdown").on("change", function() {
                var value = $(this).val();
                var $tbody = $("#usersTable tbody");
                var rows = $tbody.find("tr").toArray();

                rows.sort(function(a, b) {
                    var aVal, bVal;

                    if (value === "name_asc" || value === "name_desc") {
                        aVal = $(a).find("td:first").text().trim().toLowerCase();
                        bVal = $(b).find("td:first").text().trim().toLowerCase();

                        return value === "name_asc" ?
                            aVal.localeCompare(bVal) :
                            bVal.localeCompare(aVal);
                    } else {
                        // Parse dates for registration date sorting
                        var aDate = new Date($(a).find("td:eq(5)").text());
                        var bDate = new Date($(b).find("td:eq(5)").text());

                        return value === "date_asc" ?
                            aDate - bDate :
                            bDate - aDate;
                    }
                });

                // Reappend sorted rows
                $.each(rows, function(index, row) {
                    $tbody.append(row);
                });

                // Maintain current role filter after sorting
                var currentRole = $("#roleFilterDropdown").val();
                if (currentRole !== "all") {
                    filterByRole(currentRole);
                }
            });

            // Handle role filtering from card buttons
            $(".role-btn").click(function() {
                var role = $(this).data("role");
                $("#roleFilterDropdown").val(role === "all" ? "all" : role);
                filterByRole(role);

                // Highlight the active filter button
                $(".role-btn").removeClass("active-filter");
                $(this).addClass("active-filter");

                // Update page title to reflect filtered view
                updateTitleForRole(role);
            });

            // Handle role filtering from dropdown
            $("#roleFilterDropdown").change(function() {
                var role = $(this).val();
                filterByRole(role);

                // Update card highlights to match
                $(".role-btn").removeClass("active-filter");
                if (role === "all") {
                    $(".role-btn[data-role='all']").addClass("active-filter");
                } else {
                    $(".role-btn[data-role='" + role + "']").addClass("active-filter");
                }

                // Update page title to reflect filtered view
                updateTitleForRole(role);
            });

            // Common function for filtering by role
            function filterByRole(role) {
                if (role === "all") {
                    $(".user-row").show();
                } else {
                    $(".user-row").hide();
                    $(".user-row[data-role='" + role + "']").show();
                }
            }

            // Update page title based on selected role
            function updateTitleForRole(role) {
                var title = "User Management";
                if (role !== "all") {
                    title = role + " Management";
                }
                $(".page-title").text(title);
            }

            // Handle delete confirmation
            $(".delete-user").click(function(e) {
                e.preventDefault();
                var userId = $(this).data("id");
                if (confirm("Are you sure you want to delete this user? This action cannot be undone.")) {
                    window.location.href = "manage_users.php?action=delete&id=" + userId;
                }
            });

            // Handle edit user modal data population
            $(".edit-user").click(function() {
                $("#editUserId").val($(this).data("id"));
                $("#editUsername").val($(this).data("username"));
                $("#editEmail").val($(this).data("email"));
                $("#editRole").val($(this).data("role"));
                $("#editFirstName").val($(this).data("firstname"));
                $("#editLastName").val($(this).data("lastname"));
                $("#editPhone").val($(this).data("phone"));
                $("#editAddress").val($(this).data("address"));
                $("#editDob").val($(this).data("dob"));
                $("#editIsActive").prop("checked", $(this).data("status") == 1);
                $("#editIsApproved").prop("checked", $(this).data("approved") == 1);
            });

            // Form validation
            $("#addUserForm").submit(function(e) {
                var password = $("#password").val();
                var confirmPassword = $("#confirmPassword").val();

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert("Passwords do not match!");
                }
            });

            // Enhanced filtering functionality
            $("#statusFilter").on("change", function() {
                applyCurrentFilters();
            });

            function applyCurrentFilters() {
                var roleFilter = $("#roleFilterDropdown").val();
                var statusFilter = $("#statusFilter").val();

                $("#usersTable tbody tr").each(function() {
                    var $row = $(this);
                    var userRole = $row.data("role");
                    var userStatus = $row.data("status");
                    var needsDeactivation = $row.data("needs-deactivation");
                    var autoDeactivated = $row.data("auto-deactivated");

                    var showRole = (roleFilter === "all" || userRole === roleFilter);
                    var showStatus = true;

                    switch (statusFilter) {
                        case "all":
                            showStatus = true;
                            break;
                        case "auto_deactivated":
                            showStatus = autoDeactivated;
                            break;
                        case "needs_deactivation":
                            showStatus = needsDeactivation;
                            break;
                        default:
                            showStatus = (userStatus === statusFilter);
                    }

                    $row.toggle(showRole && showStatus);
                });

                updateSelectedCount();
            }

            // Select all functionality
            $("#selectAll, #selectAllHeader").change(function() {
                var isChecked = $(this).prop('checked');
                $("#usersTable tbody tr:visible .user-checkbox").prop('checked', isChecked);
                updateSelectedCount();

                // Sync both select all checkboxes
                $("#selectAll, #selectAllHeader").prop('checked', isChecked);
            });

            // Update selected count when individual checkboxes change
            $(document).on('change', '.user-checkbox', function() {
                updateSelectedCount();

                // Update select all checkbox state
                var totalVisible = $("#usersTable tbody tr:visible .user-checkbox").length;
                var selectedVisible = $("#usersTable tbody tr:visible .user-checkbox:checked").length;
                var selectAllState = selectedVisible === totalVisible && totalVisible > 0;

                $("#selectAll, #selectAllHeader").prop('checked', selectAllState);
            });

            function updateSelectedCount() {
                var selected = $("#usersTable tbody tr:visible .user-checkbox:checked").length;
                $("#selectedCount").text(selected + " selected");
            }
        });

        // Global functions for modal handling
        function showStatusChangeModal(userId, username, currentStatus) {
            $("#statusChangeUserId").val(userId);
            $("#statusChangeUserInfo").html("<strong>" + username + "</strong> (ID: " + userId + ")");
            $("#currentStatusDisplay").html('<span class="badge bg-secondary">' + currentStatus + '</span>');

            // Remove current status from options
            $("#newStatus option").prop('disabled', false);
            $("#newStatus option[value='" + currentStatus + "']").prop('disabled', true);
            $("#newStatus").val('');

            $("#statusChangeModal").modal('show');
        }

        function showReactivateModal(userId, username) {
            $("#reactivateUserId").val(userId);
            $("#reactivateUserInfo").html("<strong>" + username + "</strong> (ID: " + userId + ")");
            $("#reactivateReason").val('');
            $("#reactivateModal").modal('show');
        }

        function executeBulkAction() {
            var selectedUsers = [];
            $("#usersTable tbody tr:visible .user-checkbox:checked").each(function() {
                var userId = $(this).val();
                var username = $(this).closest('tr').find('td:eq(2)').text().trim();
                selectedUsers.push({
                    id: userId,
                    username: username
                });
            });

            if (selectedUsers.length === 0) {
                alert("Please select at least one user.");
                return;
            }

            var actionType = $("#bulkActionSelect").val();
            if (!actionType) {
                alert("Please select a bulk action.");
                return;
            }

            // Populate modal
            $("#bulkActionType").val(actionType);
            $("#bulkUserCount").text(selectedUsers.length);

            var actionDescriptions = {
                'active': 'Activate selected users',
                'inactive': 'Deactivate selected users',
                'archived': 'Archive selected users',
                'blacklisted': 'Blacklist selected users'
            };

            $("#bulkActionDescription").html('<span class="badge bg-warning">' + actionDescriptions[actionType] + '</span>');

            // Clear and populate user list
            $("#selectedUsersContainer").empty();
            $("#bulkUserList").empty();

            selectedUsers.forEach(function(user) {
                $("#selectedUsersContainer").append('<input type="hidden" name="selected_users[]" value="' + user.id + '">');
                $("#bulkUserList").append('<div class="mb-1"><strong>' + user.username + '</strong> (ID: ' + user.id + ')</div>');
            });
            $("#bulkReason").val('');
            $("#bulkActionModal").modal('show');
        }
    </script>

    <?php function getBadgeClass($role)
    {
        switch ($role) {
            case 'Admin':
                return 'bg-danger';
            case 'Coach':
                return 'bg-success';
            case 'Staff':
                return 'bg-info';
            case 'Member':
                return 'bg-warning';
            default:
                return 'bg-primary';
        }
    }
    ?>
</body>

</html>