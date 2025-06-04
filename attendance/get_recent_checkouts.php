<?php
// Get Recent Check-outs
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Check if we have either a user session or kiosk mode
if (!isset($_SESSION['user_id']) && !isset($_SESSION['kiosk_mode'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session required']);
    exit;
}

try {    // Get recent check-outs from today
    $stmt = $conn->prepare("        SELECT 
            ar.id,
            ar.user_id,
            ar.check_in_time,
            ar.time_out,
            ar.location,
            TIMESTAMPDIFF(MINUTE, ar.check_in_time, ar.time_out) as duration_minutes,
            u.First_Name,
            u.Last_Name,
            u.Role,
            DATE_FORMAT(ar.time_out, '%h:%i %p') as formatted_time
        FROM attendance_records ar
        JOIN users u ON ar.user_id = u.UserID
        WHERE DATE(ar.time_out) = CURDATE()
        AND ar.time_out IS NOT NULL
        ORDER BY ar.time_out DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $checkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_checkouts = [];
    foreach ($checkouts as $checkout) {
        $duration_text = '';
        if ($checkout['duration_minutes']) {
            $hours = floor($checkout['duration_minutes'] / 60);
            $minutes = $checkout['duration_minutes'] % 60;
            if ($hours > 0) {
                $duration_text = $hours . 'h ' . $minutes . 'm';
            } else {
                $duration_text = $minutes . ' minutes';
            }
        }
        
        $formatted_checkouts[] = [
            'id' => $checkout['id'],
            'user_id' => $checkout['user_id'],
            'user_name' => $checkout['First_Name'] . ' ' . $checkout['Last_Name'],
            'role' => $checkout['Role'],
            'location' => $checkout['location'] ?? 'Main Exit',
            'formatted_time' => $checkout['formatted_time'],
            'check_out_time' => $checkout['time_out'],
            'duration' => $duration_text,
            'duration_minutes' => $checkout['duration_minutes']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'checkouts' => $formatted_checkouts,
        'count' => count($formatted_checkouts)
    ]);

} catch (Exception $e) {
    error_log("Error getting recent check-outs: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading recent check-outs',
        'checkouts' => []
    ]);
} catch (PDOException $e) {
    error_log("Database error getting recent check-outs: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'checkouts' => []
    ]);
}
?>
