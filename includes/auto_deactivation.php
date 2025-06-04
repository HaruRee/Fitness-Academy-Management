<?php
/**
 * Automatic User Deactivation Script
 * This script automatically deactivates members who have been inactive for 90 days
 * Can be run via cron job, scheduled task, or called programmatically
 * 
 * Usage:
 * - Via web: auto_deactivation.php?run=1
 * - Via CLI: php auto_deactivation.php
 * - Programmatically: include and call runAutomaticDeactivation()
 */

require_once '../config/database.php';
require_once 'activity_tracker.php';
date_default_timezone_set('Asia/Manila');

function logActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] Auto-Deactivation: $message");
    
    // Only echo if running from command line or web with debug
    if (php_sapi_name() === 'cli' || isset($_GET['debug'])) {
        echo "[$timestamp] $message\n";
    }
}

function deactivateInactiveUsers($conn) {
    try {        // Find users who have been inactive for 90 days
        $sql = "
            SELECT UserID, Username, First_Name, Last_Name, Email, last_activity_date
            FROM users 
            WHERE Role = 'Member' 
            AND account_status = 'active' 
            AND last_activity_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND auto_deactivated = FALSE
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $inactiveUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($inactiveUsers)) {
            logActivity("No users found for automatic deactivation.");
            return 0;
        }
        
        $deactivatedCount = 0;
        
        foreach ($inactiveUsers as $user) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                  // Update user status to inactive
                $updateSql = "
                    UPDATE users 
                    SET account_status = 'inactive', 
                        IsActive = 0,
                        deactivation_date = NOW(),
                        auto_deactivated = TRUE,
                        admin_notes = CONCAT(COALESCE(admin_notes, ''), 'Auto-deactivated on ', NOW(), ' due to 90 days inactivity. Last activity: ', last_activity_date, '. ')
                    WHERE UserID = :user_id
                ";
                
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bindParam(':user_id', $user['UserID'], PDO::PARAM_INT);
                $updateStmt->execute();
                
                // Log status change in history
                $historySql = "
                    INSERT INTO user_status_history (user_id, previous_status, new_status, change_reason, changed_by)
                    VALUES (:user_id, 'active', 'inactive', 'Automatic deactivation - 90 days of inactivity', NULL)
                ";
                
                $historyStmt = $conn->prepare($historySql);
                $historyStmt->bindParam(':user_id', $user['UserID'], PDO::PARAM_INT);
                $historyStmt->execute();
                
                // Log in audit trail
                $auditSql = "
                    INSERT INTO audit_trail (username, action, timestamp)
                    VALUES ('system', :action, NOW())
                ";
                
                $action = "Auto-deactivated user: {$user['Username']} ({$user['First_Name']} {$user['Last_Name']}) - 90 days inactive since {$user['last_activity_date']}";
                $auditStmt = $conn->prepare($auditSql);
                $auditStmt->bindParam(':action', $action);
                $auditStmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                logActivity("Deactivated user: {$user['Username']} (ID: {$user['UserID']}) - Last active: {$user['last_activity_date']}");
                $deactivatedCount++;
                
            } catch (Exception $e) {
                $conn->rollBack();
                logActivity("Error deactivating user {$user['Username']}: " . $e->getMessage());
            }
        }
        
        logActivity("Automatic deactivation complete. Total users deactivated: $deactivatedCount");
        return $deactivatedCount;
        
    } catch (Exception $e) {
        logActivity("Error in automatic deactivation process: " . $e->getMessage());
        return -1;
    }
}

function updateUserActivity($conn, $userId) {
    try {
        $sql = "UPDATE users SET last_activity_date = NOW() WHERE UserID = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        logActivity("Error updating user activity for user ID $userId: " . $e->getMessage());
        return false;
    }
}

function logUserActivity($conn, $userId, $activityType, $description = '', $ipAddress = '', $userAgent = '') {
    try {
        $sql = "
            INSERT INTO user_activity_log (user_id, activity_type, activity_description, ip_address, user_agent)
            VALUES (:user_id, :activity_type, :description, :ip_address, :user_agent)
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':activity_type', $activityType);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':ip_address', $ipAddress);
        $stmt->bindParam(':user_agent', $userAgent);
        $stmt->execute();
        
        // Also update last_activity_date
        updateUserActivity($conn, $userId);
        
        return true;
    } catch (Exception $e) {
        logActivity("Error logging user activity: " . $e->getMessage());
        return false;
    }
}

// Main execution
if (php_sapi_name() === 'cli' || isset($_GET['run']) || isset($_POST['run_auto_deactivation'])) {
    logActivity("Starting automatic user deactivation process...");
    
    try {
        $deactivatedCount = runAutomaticDeactivation($conn);
        
        if ($deactivatedCount > 0) {
            logActivity("Process completed successfully. Deactivated {$deactivatedCount} users.");
        } else {
            logActivity("Process completed successfully. No users required deactivation.");
        }
        
        // If called via web, redirect back or show result
        if (php_sapi_name() !== 'cli' && isset($_GET['run'])) {
            if (isset($_GET['redirect'])) {
                header('Location: ' . $_GET['redirect'] . '?auto_deactivation_result=' . $deactivatedCount);
                exit;
            } else {
                echo json_encode(['success' => true, 'deactivated' => $deactivatedCount]);
                exit;
            }
        }
        
    } catch (Exception $e) {
        logActivity("Error during automatic deactivation: " . $e->getMessage());
        
        if (php_sapi_name() !== 'cli') {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
} else {
    // If accessed directly via web without parameters, show info
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Auto-Deactivation Service</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .info { background: #e8f4fd; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
            .warning { background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
            .button { 
                background: #007bff; color: white; padding: 10px 20px; 
                text-decoration: none; border-radius: 4px; display: inline-block; 
            }
        </style>
    </head>
    <body>
        <h1>Automatic User Deactivation Service</h1>
        
        <div class="info">
            <h3>Service Status: Active</h3>
            <p>This service automatically deactivates gym members who have been inactive for 90+ days.</p>
            <p><strong>How it works:</strong></p>
            <ul>
                <li>Runs automatically when admins access the dashboard or user management</li>
                <li>Checks are throttled to once per hour to avoid performance impact</li>
                <li>Can also be run manually via scheduled task or cron job</li>
            </ul>
        </div>
        
        <div class="warning">
            <h3>Manual Execution</h3>
            <p>You can manually trigger the deactivation process, but this is normally not needed as it runs automatically.</p>
            <a href="?run=1&debug=1" class="button">Run Auto-Deactivation Now</a>
        </div>
        
        <h3>Integration Status</h3>
        <ul>
            <li>✅ Integrated with admin login process</li>
            <li>✅ Integrated with admin dashboard</li>
            <li>✅ Integrated with user management page</li>
            <li>✅ Throttled to prevent excessive execution</li>
            <li>✅ Full audit trail logging</li>
        </ul>
        
        <p><a href="manage_users.php">← Back to User Management</a></p>
    </body>
    </html>
    <?php
}
?>
