<?php
/**
 * Activity Tracker Helper Functions
 * Used to track user activities for the auto-deactivation system
 */

/**
 * Track user activity
 * @param int $userId User ID
 * @param string $activityType Type of activity (login, logout, page_view, action, etc.)
 * @param string $activityDetails Description of the activity
 * @param PDO $conn Database connection (optional)
 */
function trackUserActivity($userId, $activityType, $activityDetails = '', $conn = null) {
    if ($conn === null) {
        global $conn;
    }
    
    if (!$conn || !$userId) {
        return false;
    }
    
    try {        // Update last activity date in users table
        $updateSql = "UPDATE users SET last_activity_date = NOW() WHERE UserID = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$userId]);
          // Log detailed activity
        $logSql = "INSERT INTO user_activity_log (user_id, activity_type, activity_description, activity_timestamp) VALUES (?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        $logStmt->execute([$userId, $activityType, $activityDetails]);
        
        return true;
    } catch (Exception $e) {
        error_log("Activity tracking error: " . $e->getMessage());
        return false;
    }
}

/**
 * Track page view activity (call this on protected pages)
 * @param int $userId User ID
 * @param string $pageName Name of the page being viewed
 */
function trackPageView($userId, $pageName) {
    trackUserActivity($userId, 'page_view', "Viewed page: $pageName");
}

/**
 * Track specific actions (payments, profile updates, etc.)
 * @param int $userId User ID
 * @param string $actionType Type of action
 * @param string $actionDetails Details of the action
 */
function trackUserAction($userId, $actionType, $actionDetails = '') {
    trackUserActivity($userId, $actionType, $actionDetails);
}

/**
 * Get users who need deactivation (inactive for 90+ days)
 * @param PDO $conn Database connection
 * @return array List of users needing deactivation
 */
function getUsersNeedingDeactivation($conn) {
    try {        $sql = "
            SELECT UserID, Username, First_Name, Last_Name, Email, last_activity_date,
                   DATEDIFF(NOW(), COALESCE(last_activity_date, RegistrationDate)) AS days_inactive
            FROM users 
            WHERE Role = 'Member' 
            AND account_status = 'active' 
            AND COALESCE(last_activity_date, RegistrationDate) < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND auto_deactivated = FALSE
            ORDER BY last_activity_date ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting users needing deactivation: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent activity for a user
 * @param int $userId User ID
 * @param int $limit Number of recent activities to fetch
 * @param PDO $conn Database connection
 * @return array Recent activities
 */
function getUserRecentActivity($userId, $limit = 10, $conn = null) {
    if ($conn === null) {
        global $conn;
    }
    
    if (!$conn || !$userId) {
        return [];
    }
    
    try {        $sql = "
            SELECT activity_type, activity_description, activity_timestamp 
            FROM user_activity_log 
            WHERE user_id = ? 
            ORDER BY activity_timestamp DESC 
            LIMIT ?
        ";
          $stmt = $conn->prepare($sql);
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user recent activity: " . $e->getMessage());
        return [];
    }
}

/**
 * Automatically check and deactivate users who have been inactive for 90+ days
 * This function runs silently in the background and should be called on admin activities
 * @param PDO $conn Database connection
 * @return int Number of users deactivated
 */
function runAutomaticDeactivation($conn = null) {
    if ($conn === null) {
        global $conn;
    }
    
    if (!$conn) {
        return 0;
    }
    
    try {
        // Get users who need deactivation
        $usersToDeactivate = getUsersNeedingDeactivation($conn);
        
        if (empty($usersToDeactivate)) {
            return 0;
        }
        
        $deactivatedCount = 0;
        
        foreach ($usersToDeactivate as $user) {
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
                        admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[AUTO] Deactivated on ', NOW(), ' for 90+ days inactivity')
                    WHERE UserID = ?
                ";
                
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([$user['UserID']]);
                
                // Log in status history
                $historySQL = "
                    INSERT INTO user_status_history 
                    (user_id, old_status, new_status, changed_by, change_reason, change_timestamp) 
                    VALUES (?, 'active', 'inactive', 'SYSTEM', 'Automatic deactivation - 90+ days inactive', NOW())
                ";
                
                $historyStmt = $conn->prepare($historySQL);
                $historyStmt->execute([$user['UserID']]);
                
                // Log in activity
                $activitySQL = "
                    INSERT INTO user_activity_log 
                    (user_id, activity_type, activity_description, activity_timestamp) 
                    VALUES (?, 'auto_deactivation', 'Account automatically deactivated for inactivity', NOW())
                ";
                
                $activityStmt = $conn->prepare($activitySQL);
                $activityStmt->execute([$user['UserID']]);
                
                // Log in audit trail
                $auditSQL = "
                    INSERT INTO audit_trail (username, action, timestamp) 
                    VALUES (?, 'auto_deactivation', NOW())
                ";
                
                $auditStmt = $conn->prepare($auditSQL);
                $auditStmt->execute([$user['Username']]);
                
                $conn->commit();
                $deactivatedCount++;
                
                // Log for system monitoring
                error_log("Auto-deactivated user: {$user['Username']} (ID: {$user['UserID']}) - inactive for {$user['days_inactive']} days");
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Failed to auto-deactivate user {$user['UserID']}: " . $e->getMessage());
            }
        }
        
        if ($deactivatedCount > 0) {
            error_log("Automatic deactivation completed: {$deactivatedCount} users deactivated");
        }
        
        return $deactivatedCount;
        
    } catch (Exception $e) {
        error_log("Automatic deactivation error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if automatic deactivation should run (throttle to once per hour)
 * @param PDO $conn Database connection
 * @return bool Whether to run automatic deactivation
 */
function shouldRunAutomaticDeactivation($conn = null) {
    if ($conn === null) {
        global $conn;
    }
    
    if (!$conn) {
        return false;
    }
    
    try {
        // Check if we've run in the last hour
        $checkSQL = "
            SELECT COUNT(*) as recent_runs 
            FROM audit_trail 
            WHERE action = 'auto_deactivation_check' 
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        
        $stmt = $conn->prepare($checkSQL);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['recent_runs'] > 0) {
            return false; // Already ran in the last hour
        }
        
        // Log that we're checking
        $logSQL = "INSERT INTO audit_trail (username, action, timestamp) VALUES ('SYSTEM', 'auto_deactivation_check', NOW())";
        $logStmt = $conn->prepare($logSQL);
        $logStmt->execute();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error checking auto-deactivation throttle: " . $e->getMessage());
        return false;
    }
}
?>
