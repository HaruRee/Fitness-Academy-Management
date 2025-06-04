<?php
// Process QR Attendance Check-in
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
    
    // Check if member is inactive but allow check-in with warning
    if ($member['IsActive'] != 1 || $member['account_status'] != 'active') {
        $warning = "WARNING: Member account is inactive. Please verify membership status.";
    } else {
        $warning = "";
    }

    $member_name = $member['First_Name'] . ' ' . $member['Last_Name'];    // Check if member already checked in today
    $existing_checkin_stmt = $conn->prepare("
        SELECT id, check_in_time, time_out 
        FROM attendance_records 
        WHERE user_id = ? AND DATE(check_in_time) = CURDATE()
        ORDER BY check_in_time DESC 
        LIMIT 1
    ");
    $existing_checkin_stmt->execute([$member_user_id]);
    $existing_checkin = $existing_checkin_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_checkin) {
        if (!$existing_checkin['time_out']) {
            throw new Exception("$member_name is already checked in today. Please check out first.");
        }
        
        // If they checked out, allow another check-in
        // But warn if it's been less than 5 minutes
        $checkout_time = new DateTime($existing_checkin['time_out']);
        $current_time = new DateTime();
        $time_diff = $current_time->getTimestamp() - $checkout_time->getTimestamp();
        
        if ($time_diff < 300) { // Less than 5 minutes
            $minutes = ceil($time_diff / 60);
            throw new Exception("$member_name checked out recently ($minutes minutes ago). Please wait a moment before checking in again.");
        }
    }    // Record the check-in
    $checkin_stmt = $conn->prepare("
        INSERT INTO attendance_records (
            user_id, 
            user_type,
            attendance_type,
            check_in_time, 
            location, 
            scanned_by_user_id, 
            ip_address,
            device_info
        ) VALUES (?, ?, 'gym_entry', NOW(), ?, ?, ?, ?)
    ");
    
    $location = 'Main Entrance';
    $checkin_stmt->execute([
        $member_user_id,
        $member['Role'],
        $location,
        $scanner_user_id,
        $ip_address,
        $user_agent
    ]);

    $attendance_id = $conn->lastInsertId();

    // Log to audit trail
    if (function_exists('logAuditTrail')) {
        logAuditTrail($conn, $scanner_user_id, 'CHECK_IN', 'attendance_records', $attendance_id, [
            'member_id' => $member_user_id,
            'member_name' => $member_name,
            'location' => $location,
            'scanner_role' => $scanner_role
        ]);
    }    echo json_encode([
        'success' => true,
        'message' => 'Check-in successful' . (!empty($warning) ? " - $warning" : ""),
        'user_name' => $member_name,
        'type' => 'checkin',
        'location' => $location,
        'timestamp' => date('Y-m-d H:i:s'),
        'attendance_id' => $attendance_id,
        'warning' => $warning ?? "",
        'is_active' => ($member['IsActive'] == 1 && $member['account_status'] == 'active')
    ]);

} catch (Exception $e) {
    error_log("Check-in error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Database error in check-in: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
