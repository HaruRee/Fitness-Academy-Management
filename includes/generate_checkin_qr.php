<?php
// Simplified QR Generation API with Enhanced Error Handling
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

function sendError($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sendSuccess($data)
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

try {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    $type = $data['type'] ?? '';
    $class_id = $data['class_id'] ?? null;

    // Validate user session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not authenticated");
    }

    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    // Get user details
    $user_stmt = $conn->prepare("
        SELECT UserID, First_Name, Last_Name, Role, IsActive, account_status 
        FROM users 
        WHERE UserID = ? AND IsActive = 1 AND account_status = 'active'
    ");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found or inactive");
    }

    // Generate unique QR code data
    $qr_data = [
        'type' => strtoupper($type),
        'user_id' => $user_id,
        'user_name' => $user['First_Name'] . ' ' . $user['Last_Name'],
        'role' => $role,
        'timestamp' => time()
    ];

    if ($type === 'class' && $class_id) {
        // Add class session data
        $class_stmt = $conn->prepare("
            SELECT s.*, u.First_Name as coach_name 
            FROM attendance_sessions s
            LEFT JOIN users u ON s.coach_id = u.UserID
            WHERE s.id = ? AND s.status = 'active'
        ");
        $class_stmt->execute([$class_id]);
        $class_data = $class_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class_data) {
            throw new Exception("Class session not found or inactive");
        }

        $qr_data['session_id'] = $class_id;
        $qr_data['session_name'] = $class_data['session_name'];
        $qr_data['coach_name'] = $class_data['coach_name'];
    }

    // Generate QR code with proper encoding
    $qr_code = base64_encode(json_encode($qr_data));

    // Store QR code in session table for member check-ins
    if ($type === 'member') {
        // Check for existing active session
        $check_stmt = $conn->prepare("
            SELECT id FROM attendance_sessions 
            WHERE qr_code = ? AND status = 'active' 
            AND end_time > NOW()
        ");
        $check_stmt->execute([$qr_code]);

        if (!$check_stmt->fetch()) {
            // Only create new session if no active one exists
            $session_stmt = $conn->prepare("
                INSERT INTO attendance_sessions 
                (session_name, qr_code, session_type, start_time, end_time, status)
                VALUES (?, ?, 'member', NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 'active')
            ");
            $session_name = "Member Check-in: " . $user['First_Name'] . ' ' . $user['Last_Name'];
            $session_stmt->execute([$session_name, $qr_code]);
        }
    }

    // Return success response with proper data
    echo json_encode([
        'success' => true,
        'qr_data' => $qr_code,
        'session_name' => $qr_data['session_name'] ?? ("Check-in: " . $user['First_Name'] . ' ' . $user['Last_Name']),
        'type' => strtoupper($type),
        'expiry' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
        'user_name' => $user['First_Name'] . ' ' . $user['Last_Name']
    ]);
} catch (Exception $e) {
    error_log("QR Generation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
