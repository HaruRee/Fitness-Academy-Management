<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_id'])) {
    $class_id = $_POST['class_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Start transaction
        $conn->beginTransaction();

        // Get class details first
        $stmt = $conn->prepare("SELECT c.*, ce.payment_status 
                               FROM classes c 
                               JOIN classenrollments ce ON c.class_id = ce.class_id 
                               WHERE c.class_id = ? AND ce.user_id = ?");
        $stmt->execute([$class_id, $user_id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$enrollment) {
            throw new Exception('Enrollment not found');
        }

        // Update enrollment status to cancelled
        $stmt = $conn->prepare("UPDATE classenrollments 
                              SET status = 'cancelled'
                              WHERE class_id = ? AND user_id = ?");
        $stmt->execute([$class_id, $user_id]);

        $conn->commit();
        $_SESSION['success_message'] = "Successfully dropped out from " . $enrollment['class_name'] .
            ". Note: Class fees are non-refundable.";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error dropping out: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Invalid request";
}

header('Location: member_class.php');
exit;
