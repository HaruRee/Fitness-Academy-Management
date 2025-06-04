<?php
// Process QR Attendance Check-out
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

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$qr_code = $input['qr_code'] ?? '';
$user_agent = $input['user_agent'] ?? '';
$client_timestamp = $input['timestamp'] ?? '';

$scanner_user_id = $_SESSION['user_id'];
$scanner_role = $_SESSION['role'];

if (empty($qr_code)) {
    echo json_encode(['success' => false, 'message' => 'QR code is required']);
    exit;
}

try {
    // Get scanner's IP address
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    // Decode the static QR code (user-specific QR code)
    // Expected format: static QR code containing user ID
    $member_user_id = null;
    
    // Try to extract user ID from QR code
    // Format 1: Simple user ID
    if (is_numeric($qr_code)) {
        $member_user_id = (int)$qr_code;
    }
    // Format 2: JSON format with user_id
    else {
        try {
            $decoded_qr = json_decode($qr_code, true);
            if (isset($decoded_qr['user_id'])) {
                $member_user_id = (int)$decoded_qr['user_id'];
            }
        } catch (Exception $e) {
            // Try base64 decode
            try {
                $decoded_qr = json_decode(base64_decode($qr_code), true);
                if (isset($decoded_qr['user_id'])) {
                    $member_user_id = (int)$decoded_qr['user_id'];
                }
            } catch (Exception $e2) {
                // Continue with original QR code
            }
        }
    }
    
    if (!$member_user_id) {
        throw new Exception("Invalid QR code format. Please generate a new QR code.");
    }    // Get the member's information
    $member_stmt = $conn->prepare("
        SELECT UserID, First_Name, Last_Name, Role, IsActive, account_status 
        FROM users 
        WHERE UserID = ?
    ");
    $member_stmt->execute([$member_user_id]);
    $member = $member_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception("Member not found");
    }
    
    // Check if member is inactive but allow check-out with warning
    if ($member['IsActive'] != 1 || $member['account_status'] != 'active') {
        $warning = "WARNING: Member account is inactive. Please verify membership status.";
    } else {
        $warning = "";
    }

    $member_name = $member['First_Name'] . ' ' . $member['Last_Name'];    // Find the most recent check-in without check-out
    $active_checkin_stmt = $conn->prepare("
        SELECT id, check_in_time, location 
        FROM attendance_records 
        WHERE user_id = ? AND time_out IS NULL
        ORDER BY check_in_time DESC 
        LIMIT 1
    ");
    $active_checkin_stmt->execute([$member_user_id]);
    $active_checkin = $active_checkin_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$active_checkin) {
        throw new Exception("$member_name is not currently checked in. Please check in first.");
    }

    // Calculate duration
    $checkin_time = new DateTime($active_checkin['check_in_time']);
    $checkout_time = new DateTime();
    $duration_seconds = $checkout_time->getTimestamp() - $checkin_time->getTimestamp();
    
    // Minimum stay validation (at least 5 minutes)
    if ($duration_seconds < 300) {
        $minutes = ceil(300 - $duration_seconds) / 60;
        throw new Exception("Minimum stay time not met. Please wait " . ceil($minutes) . " more minute(s) before checking out.");
    }    // Update the attendance record with check-out time
    $checkout_stmt = $conn->prepare("
        UPDATE attendance_records 
        SET time_out = NOW(), 
            checked_out_by = ?,
            checkout_ip_address = ?,
            checkout_user_agent = ?
        WHERE id = ?
    ");
    
    $checkout_stmt->execute([
        $scanner_user_id,
        $ip_address,
        $user_agent,
        $active_checkin['id']
    ]);

    // Calculate final duration for display
    $duration_minutes = floor($duration_seconds / 60);
    $duration_hours = floor($duration_minutes / 60);
    $remaining_minutes = $duration_minutes % 60;
    
    $duration_text = '';
    if ($duration_hours > 0) {
        $duration_text = $duration_hours . 'h ' . $remaining_minutes . 'm';
    } else {
        $duration_text = $duration_minutes . ' minutes';
    }

    // Log to audit trail
    if (function_exists('logAuditTrail')) {
        logAuditTrail($conn, $scanner_user_id, 'CHECK_OUT', 'attendance_records', $active_checkin['id'], [
            'member_id' => $member_user_id,
            'member_name' => $member_name,
            'location' => $active_checkin['location'],
            'duration_minutes' => $duration_minutes,
            'scanner_role' => $scanner_role
        ]);
    }    echo json_encode([
        'success' => true,
        'message' => 'Check-out successful' . (!empty($warning) ? " - $warning" : ""),
        'user_name' => $member_name,
        'type' => 'checkout',
        'location' => $active_checkin['location'],
        'duration' => $duration_text,
        'duration_minutes' => $duration_minutes,
        'timestamp' => date('Y-m-d H:i:s'),
        'attendance_id' => $active_checkin['id'],
        'warning' => $warning ?? "",
        'is_active' => ($member['IsActive'] == 1 && $member['account_status'] == 'active')
    ]);

} catch (Exception $e) {
    error_log("Check-out error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Database error in check-out: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
