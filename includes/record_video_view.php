<?php
session_start();
require_once '../config/database.php';

// Check if logged in and is member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$video_id = $input['video_id'] ?? null;
$member_id = $_SESSION['user_id'];

if (!$video_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Video ID required']);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Check if video exists and member has access
    $stmt = $conn->prepare("
        SELECT cv.id, cv.access_type, cv.coach_id,
               CASE 
                   WHEN cv.access_type = 'free' THEN 1
                   WHEN cv.access_type = 'paid' AND EXISTS (
                       SELECT 1 FROM coach_subscriptions cs 
                       WHERE cs.member_id = ? AND cs.coach_id = cv.coach_id AND cs.status = 'active'
                   ) THEN 1
                   ELSE 0
               END as can_access
        FROM coach_videos cv
        WHERE cv.id = ? AND cv.status = 'approved'
    ");
    $stmt->execute([$member_id, $video_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Video not found']);
        exit;
    }

    if (!$video['can_access']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Record the view
    $stmt = $conn->prepare("
        INSERT INTO video_views (video_id, member_id, view_date)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$video_id, $member_id]);

    echo json_encode(['success' => true, 'message' => 'View recorded']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
