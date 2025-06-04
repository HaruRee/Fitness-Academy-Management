<?php
// Process QR Attendance Check-in
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

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

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if (empty($qr_code)) {
    echo json_encode(['success' => false, 'message' => 'QR code is required']);
    exit;
}

try {
    // Get user's IP address
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    // Determine attendance type and process accordingly
    $attendance_processed = false;
    $response_message = '';

    // Try to decode QR code if it's base64 encoded
    $decoded_qr = null;
    try {
        $decoded_qr = json_decode(base64_decode($qr_code), true);
    } catch (Exception $e) {
        // Not a base64 encoded JSON, continue with regular processing
    }

    if ($decoded_qr && isset($decoded_qr['type'])) {
        // Process decoded QR code
        $qr_type = strtoupper($decoded_qr['type']);
        $qr_user_id = $decoded_qr['user_id'] ?? null;
        $qr_timestamp = $decoded_qr['timestamp'] ?? null;

        if (!$qr_user_id || !$qr_timestamp) {
            throw new Exception("Invalid QR code format");
        }

        // Validate QR code age (expire after 5 minutes for security)
        $current_time = time();
        if (($current_time - $qr_timestamp) > 300) {
            throw new Exception("QR code has expired. Please generate a new one.");
        }

        // Get the QR code owner's information
        $qr_owner_stmt = $conn->prepare("SELECT First_Name, Last_Name, Role FROM users WHERE UserID = ? AND IsActive = 1");
        $qr_owner_stmt->execute([$qr_user_id]);
        $qr_owner = $qr_owner_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$qr_owner) {
            throw new Exception("Invalid QR code - user not found or inactive");
        }

        // Process attendance
        $attendance_processed = processUniqueQRAttendance(
            $conn,
            $user_id,
            $user_role,
            $qr_code,
            $qr_type,
            $qr_user_id,
            $qr_owner,
            $ip_address,
            $user_agent
        );

        if (is_array($attendance_processed) && isset($attendance_processed['success'])) {
            if ($attendance_processed['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $attendance_processed['message'],
                    'user_name' => $attendance_processed['user_name'],
                    'type' => $attendance_processed['type'],
                    'session_name' => $attendance_processed['session_name'] ?? null,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
            } else {
                throw new Exception($attendance_processed['message']);
            }
        }

        $response_message = "Check-in successful: " . $qr_owner['First_Name'] . ' ' . $qr_owner['Last_Name'];
    } else {
        // Continue with existing legacy QR code format check
        if (preg_match('/^(MEMBER|WORK)_(\d+)_(\d+)_([a-zA-Z0-9]+)$/', $qr_code, $matches)) {
            // Parse unique QR code format: PREFIX_USER_ID_TIMESTAMP_HASH
            $qr_type = $matches[1];
            $qr_user_id = intval($matches[2]);
            $qr_timestamp = intval($matches[3]);
            $qr_hash = $matches[4];

            // Validate QR code age (expire after 24 hours)
            $current_time = time();
            if (($current_time - $qr_timestamp) > 86400) {
                echo json_encode(['success' => false, 'message' => 'QR code has expired. Please generate a new one.']);
                exit;
            }

            // Get the QR code owner's information
            $qr_owner_stmt = $conn->prepare("SELECT First_Name, Last_Name, Role FROM users WHERE UserID = ? AND IsActive = 1");
            $qr_owner_stmt->execute([$qr_user_id]);
            $qr_owner = $qr_owner_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$qr_owner) {
                echo json_encode(['success' => false, 'message' => 'Invalid QR code - user not found or inactive']);
                exit;
            }

            // Process unique QR code attendance
            $attendance_processed = processUniqueQRAttendance($conn, $user_id, $user_role, $qr_code, $qr_type, $qr_user_id, $qr_owner, $ip_address, $user_agent);

            if ($qr_type === 'MEMBER') {
                $response_message = "Member check-in successful: " . $qr_owner['First_Name'] . ' ' . $qr_owner['Last_Name'];
            } else {
                $response_message = "Coach work session check-in: " . $qr_owner['First_Name'] . ' ' . $qr_owner['Last_Name'];
            }
        } else {
            // Check if QR code is for a specific session
            $session_stmt = $conn->prepare("
                SELECT * FROM attendance_sessions 
                WHERE qr_code = ? AND status = 'active' 
                AND start_time <= NOW() AND end_time >= NOW()
            ");
            $session_stmt->execute([$qr_code]);
            $session = $session_stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                // Process session-specific attendance
                $attendance_processed = processSessionAttendance($conn, $user_id, $user_role, $session, $ip_address, $user_agent);
                $response_message = "Checked into: " . $session['session_name'];
            } else {
                // Check if QR code is for a location
                $location_stmt = $conn->prepare("
                    SELECT * FROM qr_locations 
                    WHERE qr_code = ? AND is_active = 1
                ");
                $location_stmt->execute([$qr_code]);
                $location = $location_stmt->fetch(PDO::FETCH_ASSOC);

                if ($location) {
                    // Process location-based attendance
                    $attendance_processed = processLocationAttendance($conn, $user_id, $user_role, $location, $ip_address, $user_agent);
                    $response_message = "Checked into: " . $location['location_name'];
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid or expired QR code']);
                    exit;
                }
            }
        }
    }

    if ($attendance_processed) {
        echo json_encode([
            'success' => true,
            'message' => $response_message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Check-in failed. You may have already checked in.']);
    }
} catch (Exception $e) {
    error_log("Attendance processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
}

function processUniqueQRAttendance($conn, $scanner_user_id, $scanner_role, $qr_code, $qr_type, $qr_owner_id, $qr_owner, $ip_address, $user_agent)
{
    try {
        // For unique QR codes, the attendance is recorded for the QR code owner, not the scanner
        $attendance_user_id = $qr_owner_id;

        // Get complete user information with proper join to ensure user exists and is active
        $user_stmt = $conn->prepare("
            SELECT u.UserID, u.First_Name, u.Last_Name, u.Role, u.IsActive, u.account_status
            FROM users u
            WHERE u.UserID = ? 
            AND u.IsActive = 1 
            AND u.account_status = 'active'
        ");
        $user_stmt->execute([$attendance_user_id]);
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            throw new Exception("User not found or inactive. Please check your account status.");
        }

        // Validate session if it's a class or personal training
        if ($qr_type === 'class' || $qr_type === 'personal_training') {
            $session_stmt = $conn->prepare("
                SELECT s.*, u.First_Name as coach_name
                FROM attendance_sessions s
                LEFT JOIN users u ON s.coach_id = u.UserID
                WHERE s.qr_code = ? 
                AND s.status = 'active'
                AND s.start_time <= NOW()
                AND s.end_time >= NOW()
            ");
            $session_stmt->execute([$qr_code]);
            $session_data = $session_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session_data) {
                throw new Exception("Invalid or expired session.");
            }
        }

        // Record the attendance
        $attendance_stmt = $conn->prepare("
            INSERT INTO attendance_records 
            (user_id, user_type, session_id, attendance_type, location, qr_code_used, scanned_by_user_id, device_info, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $attendance_type = ($qr_type === 'class') ? 'class_session' : (($qr_type === 'personal_training') ? 'class_session' : 'gym_entry');

        $session_id = isset($session_data['id']) ? $session_data['id'] : null;
        $location = isset($session_data['location']) ? $session_data['location'] : 'Main Entrance';

        $attendance_stmt->execute([
            $attendance_user_id,
            $user_data['Role'],
            $session_id,
            $attendance_type,
            $location,
            $qr_code,
            $scanner_user_id,
            $user_agent,
            $ip_address
        ]);

        // Log the activity
        $activity_stmt = $conn->prepare("
            INSERT INTO user_activity_log 
            (user_id, activity_type, activity_description, activity_timestamp)
            VALUES (?, 'attendance', ?, NOW())
        ");

        $activity_description = "Check-in recorded via QR code";
        if (isset($session_data)) {
            $activity_description .= " for {$session_data['session_name']}";
        }

        $activity_stmt->execute([$attendance_user_id, $activity_description]);

        return [
            'success' => true,
            'message' => 'Attendance recorded successfully',
            'user_name' => $user_data['First_Name'] . ' ' . $user_data['Last_Name'],
            'type' => $qr_type,
            'session_name' => $session_data['session_name'] ?? null
        ];
    } catch (Exception $e) {
        error_log("Attendance processing error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function processSessionAttendance($conn, $user_id, $user_role, $session, $ip_address, $user_agent)
{
    try {
        // Check if user already checked into this session
        $check_stmt = $conn->prepare("
            SELECT id FROM attendance_records 
            WHERE user_id = ? AND session_id = ? AND DATE(check_in_time) = CURDATE()
        ");
        $check_stmt->execute([$user_id, $session['id']]);

        if ($check_stmt->fetch()) {
            return false; // Already checked in
        }

        // Check capacity if applicable
        if ($session['max_participants']) {
            $capacity_stmt = $conn->prepare("
                SELECT COUNT(*) as current_count FROM attendance_records 
                WHERE session_id = ? AND DATE(check_in_time) = CURDATE()
            ");
            $capacity_stmt->execute([$session['id']]);
            $capacity_check = $capacity_stmt->fetch(PDO::FETCH_ASSOC);

            if ($capacity_check['current_count'] >= $session['max_participants']) {
                return false; // Session is full
            }
        }

        // Record attendance
        $attendance_stmt = $conn->prepare("
            INSERT INTO attendance_records 
            (user_id, user_type, session_id, attendance_type, location, qr_code_used, device_info, ip_address)
            VALUES (?, ?, ?, 'class_session', ?, ?, ?, ?)
        ");
        $attendance_stmt->execute([
            $user_id,
            $user_role,
            $session['id'],
            $session['location'],
            $session['qr_code'],
            $user_agent,
            $ip_address
        ]);

        // If user is a coach, also record work session
        if ($user_role === 'Coach') {
            recordCoachWorkSession($conn, $user_id, 'class', $session);
        }

        // Log to audit trail
        logAuditTrail($conn, $user_id, 'CHECK_IN', 'attendance_records', $conn->lastInsertId(), [
            'session_name' => $session['session_name'],
            'session_type' => $session['session_type'],
            'location' => $session['location']
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Session attendance error: " . $e->getMessage());
        return false;
    }
}

function processLocationAttendance($conn, $user_id, $user_role, $location, $ip_address, $user_agent)
{
    try {
        // Check if user already checked into this location today
        $check_stmt = $conn->prepare("
            SELECT id FROM attendance_records 
            WHERE user_id = ? AND location = ? AND DATE(check_in_time) = CURDATE()
            AND attendance_type = ?
        ");

        $attendance_type = ($location['location_type'] === 'entrance') ? 'gym_entry' : 'equipment_area';
        $check_stmt->execute([$user_id, $location['location_name'], $attendance_type]);

        if ($check_stmt->fetch()) {
            return false; // Already checked in today
        }

        // Record attendance
        $attendance_stmt = $conn->prepare("
            INSERT INTO attendance_records 
            (user_id, user_type, attendance_type, location, qr_code_used, device_info, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $attendance_stmt->execute([
            $user_id,
            $user_role,
            $attendance_type,
            $location['location_name'],
            $location['qr_code'],
            $user_agent,
            $ip_address
        ]);

        // If coach and entrance, start work shift
        if ($user_role === 'Coach' && $location['location_type'] === 'entrance') {
            recordCoachWorkSession($conn, $user_id, 'shift', null);
        }

        // Log to audit trail
        logAuditTrail($conn, $user_id, 'CHECK_IN', 'attendance_records', $conn->lastInsertId(), [
            'location_name' => $location['location_name'],
            'location_type' => $location['location_type'],
            'attendance_type' => $attendance_type
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Location attendance error: " . $e->getMessage());
        return false;
    }
}

function recordCoachWorkSession($conn, $coach_id, $session_type, $session_data)
{
    try {
        // Check if there's already an active work session today
        $active_stmt = $conn->prepare("
            SELECT id FROM coach_work_sessions 
            WHERE coach_id = ? AND status = 'active' AND DATE(start_time) = CURDATE()
        ");
        $active_stmt->execute([$coach_id]);

        if (!$active_stmt->fetch()) {
            // Create new work session
            $work_stmt = $conn->prepare("
                INSERT INTO coach_work_sessions (coach_id, session_type, start_time)
                VALUES (?, ?, NOW())
            ");
            $work_stmt->execute([$coach_id, $session_type]);
        }
    } catch (Exception $e) {
        error_log("Coach work session error: " . $e->getMessage());
    }
}

function logAuditTrail($conn, $user_id, $action, $table, $record_id, $data)
{
    try {
        $audit_stmt = $conn->prepare("
            INSERT INTO audit_trail (user_id, action_type, table_name, record_id, new_values, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $audit_stmt->execute([$user_id, $action, $table, $record_id, json_encode($data)]);
    } catch (Exception $e) {
        error_log("Audit trail error: " . $e->getMessage());
    }
}
