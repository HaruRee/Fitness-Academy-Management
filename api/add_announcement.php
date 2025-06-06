<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coach_id = $_SESSION['user_id'];
    $announcement = trim($_POST['announcement'] ?? '');
    
    // Validate announcement
    if (empty($announcement)) {
        echo json_encode(['success' => false, 'message' => 'Announcement cannot be empty']);
        exit;
    }
    
    if (strlen($announcement) > 500) {
        echo json_encode(['success' => false, 'message' => 'Announcement cannot exceed 500 characters']);
        exit;
    }
    
    try {
        // Insert announcement into database
        $stmt = $conn->prepare("INSERT INTO coach_announcements (coach_id, announcement, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$coach_id, $announcement]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Announcement added successfully',
            'announcement' => $announcement,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        error_log("Failed to add announcement: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add announcement. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
