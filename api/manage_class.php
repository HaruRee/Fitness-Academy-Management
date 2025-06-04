<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

// Check if user is logged in and is a coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$coach_id = $_SESSION['user_id'];

// Handle DELETE action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $class_id = $_POST['class_id'] ?? null;

    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required']);
        exit;
    }

    try {
        // Check if the class belongs to the coach
        $stmt = $conn->prepare("SELECT coach_id FROM classes WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch();

        if (!$class || $class['coach_id'] !== $coach_id) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized to delete this class']);
            exit;
        }

        // Delete the class
        $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
        $stmt->execute([$class_id]);

        echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
        exit;
    } catch (PDOException $e) {
        error_log("Error deleting class: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting class']);
        exit;
    }
}

// Handle CREATE/UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'] ?? null;
    $class_name = $_POST['class_name'] ?? '';
    $class_description = $_POST['class_description'] ?? '';
    $class_date = $_POST['class_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $difficulty_level = $_POST['difficulty_level'] ?? 'Beginner';
    $requirements = $_POST['requirements'] ?? '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.00;

    // Validate required fields
    if (!$class_name || !$class_description || !$class_date || !$start_time || !$end_time) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }

    // Validate price
    if ($price < 0) {
        echo json_encode(['success' => false, 'message' => 'Price cannot be negative']);
        exit;
    }

    // Validate date and time
    if (strtotime($class_date) < strtotime('today')) {
        echo json_encode(['success' => false, 'message' => 'Class date cannot be in the past']);
        exit;
    }

    if (strtotime($start_time) >= strtotime($end_time)) {
        echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
        exit;
    }

    try {
        // Check for time conflicts for this coach
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM classes 
            WHERE coach_id = ?
            AND class_date = ? 
            AND ((start_time BETWEEN ? AND ?) OR (end_time BETWEEN ? AND ?))
            AND class_id != ?
        ");
        $stmt->execute([
            $coach_id,
            $class_date,
            $start_time,
            $end_time,
            $start_time,
            $end_time,
            $class_id ?? 0
        ]);

        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have a class scheduled during this time']);
            exit;
        }

        if ($class_id) {
            // Update existing class
            $stmt = $conn->prepare("
                UPDATE classes SET 
                    class_name = ?,
                    class_description = ?,
                    class_date = ?,
                    start_time = ?,
                    end_time = ?,
                    difficulty_level = ?,
                    requirements = ?,
                    price = ?,
                    updated_at = NOW()
                WHERE class_id = ? AND coach_id = ?
            ");
            $stmt->execute([
                $class_name,
                $class_description,
                $class_date,
                $start_time,
                $end_time,
                $difficulty_level,
                $requirements,
                $price,
                $class_id,
                $coach_id
            ]);

            $message = 'Class updated successfully';
        } else {
            // Create new class
            $stmt = $conn->prepare("
                INSERT INTO classes (
                    coach_id,
                    class_name,
                    class_description,
                    class_date,
                    start_time,
                    end_time,
                    difficulty_level,
                    requirements,
                    price,
                    created_at,
                    updated_at,
                    is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
            ");
            $stmt->execute([
                $coach_id,
                $class_name,
                $class_description,
                $class_date,
                $start_time,
                $end_time,
                $difficulty_level,
                $requirements,
                $price
            ]);

            $message = 'Class created successfully';
        }

        echo json_encode(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        error_log("Error managing class: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error saving class']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
