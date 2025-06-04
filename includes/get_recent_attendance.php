<?php
// Get Recent Attendance Records for User
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get recent attendance records with more detailed information
    $query = "
        SELECT 
            ar.*,
            CASE 
                WHEN ar.session_id IS NOT NULL THEN ats.session_name
                ELSE ar.location
            END as location_or_session,
            ats.session_type,
            u.First_Name,
            u.Last_Name,
            u.Role
        FROM attendance_records ar
        LEFT JOIN attendance_sessions ats ON ar.session_id = ats.id
        LEFT JOIN users u ON ar.user_id = u.UserID";

    // If not admin, only show user's own records
    if ($_SESSION['role'] !== 'Admin') {
        $query .= " WHERE ar.user_id = ?";
    }

    $query .= " ORDER BY ar.check_in_time DESC LIMIT 10";

    $stmt = $conn->prepare($query);

    if ($_SESSION['role'] !== 'Admin') {
        $stmt->execute([$user_id]);
    } else {
        $stmt->execute();
    }

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($records)) {
        echo json_encode([
            'success' => true,
            'records' => []
        ]);
        exit;
    }

    // Format the records for display
    $formatted_records = array_map(function ($record) {
        return [
            'check_in_time' => $record['check_in_time'],
            'location' => $record['location_or_session'],
            'session_name' => $record['session_name'] ?? null,
            'attendance_type' => $record['attendance_type'] ?? 'unknown',
            'user_type' => $record['Role'] ?? 'Unknown',
            'user_name' => ($record['First_Name'] ?? '') . ' ' . ($record['Last_Name'] ?? ''),
            'qr_code' => $record['qr_code_used'] ?? '-'
        ];
    }, $records);

    echo json_encode([
        'success' => true,
        'records' => $formatted_records
    ]);
} catch (Exception $e) {
    error_log("Get recent attendance error: " . $e->getMessage());
    error_log("Query: " . $query);
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
}
