<?php
// Get Recent Check-ins
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

try {
    // Get recent check-ins from today
    $stmt = $conn->prepare("        SELECT 
            ar.id,
            ar.user_id,
            ar.check_in_time,
            ar.location,
            u.First_Name,
            u.Last_Name,
            u.Role,
            DATE_FORMAT(ar.check_in_time, '%h:%i %p') as formatted_time,
            ar.time_out
        FROM attendance_records ar
        JOIN users u ON ar.user_id = u.UserID
        WHERE DATE(ar.check_in_time) = CURDATE()
        ORDER BY ar.check_in_time DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_checkins = [];
    foreach ($checkins as $checkin) {
        $formatted_checkins[] = [
            'id' => $checkin['id'],
            'user_id' => $checkin['user_id'],
            'user_name' => $checkin['First_Name'] . ' ' . $checkin['Last_Name'],
            'role' => $checkin['Role'],
            'location' => $checkin['location'] ?? 'Main Entrance',
            'formatted_time' => $checkin['formatted_time'],            'check_in_time' => $checkin['check_in_time'],
            'is_checked_out' => !empty($checkin['time_out'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'checkins' => $formatted_checkins,
        'count' => count($formatted_checkins)
    ]);

} catch (Exception $e) {
    error_log("Error getting recent check-ins: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading recent check-ins',
        'checkins' => []
    ]);
} catch (PDOException $e) {
    error_log("Database error getting recent check-ins: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'checkins' => []
    ]);
}
?>
