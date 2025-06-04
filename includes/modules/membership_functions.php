<?php

/**
 * Get user's active membership plan
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return array|null Active plan details or null if no active plan
 */
function getUserActivePlan($conn, $userId)
{
    try {
        $stmt = $conn->prepare("
            SELECT m.*, mp.name as plan_name 
            FROM memberships m 
            JOIN membershipplans mp ON m.plan_id = mp.id 
            WHERE m.user_id = ? 
            AND m.status = 'active' 
            AND m.end_date >= CURRENT_DATE()
            ORDER BY m.end_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active plan: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all active membership plans
 * @param PDO $conn Database connection
 * @return array List of active membership plans
 */
function getActiveMembershipPlans($conn)
{
    try {
        $stmt = $conn->prepare("
            SELECT * FROM membershipplans 
            WHERE is_active = 1 
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting membership plans: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a new membership for a user
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param int $planId Plan ID
 * @param string $startDate Start date (YYYY-MM-DD)
 * @param string $endDate End date (YYYY-MM-DD)
 * @return bool Success status
 */
function createMembership($conn, $userId, $planId, $startDate, $endDate)
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO memberships (user_id, plan_id, start_date, end_date, status) 
            VALUES (?, ?, ?, ?, 'active')
        ");
        return $stmt->execute([$userId, $planId, $startDate, $endDate]);
    } catch (PDOException $e) {
        error_log("Error creating membership: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate membership end date based on plan duration
 * @param string $startDate Start date string
 * @param int $months Number of months
 * @return string End date in YYYY-MM-DD format
 */
function calculateMembershipEndDate($startDate, $months)
{
    $date = new DateTime($startDate);
    $date->modify("+{$months} months");
    return $date->format('Y-m-d');
}

/**
 * Check if user can purchase a new plan
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return bool True if user can purchase, false otherwise
 */
function canPurchasePlan($conn, $userId)
{
    $activePlan = getUserActivePlan($conn, $userId);
    return empty($activePlan);
}
