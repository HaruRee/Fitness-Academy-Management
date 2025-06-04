<?php
// Static QR Code Generator - Generates permanent user-specific QR codes
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {    // Get user details - removed active status restriction
    $user_stmt = $conn->prepare("
        SELECT UserID, First_Name, Last_Name, Role, IsActive, account_status 
        FROM users 
        WHERE UserID = ?
    ");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Optional: Add a warning if user is inactive (but still generate the QR)
    $is_inactive = ($user['IsActive'] != 1 || $user['account_status'] != 'active');
    $inactive_warning = $is_inactive ? "Note: Your account appears to be inactive. Please contact staff." : "";

    // Generate static QR code data
    // Simple format: just the user ID (static and permanent)
    $qr_data = $user_id;
    
    // Alternative JSON format (more structured but still static)
    $qr_data_json = json_encode([
        'user_id' => $user_id,
        'type' => 'static_attendance',
        'version' => '1.0'
    ]);

    // Use simple user ID for now (easier to scan and process)
    $qr_code_text = (string)$user_id;

    // Generate QR code URL using a QR code service
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '300x300',
        'data' => $qr_code_text,
        'format' => 'png',
        'margin' => 10,
        'qzone' => 2,
        'color' => '000000',
        'bgcolor' => 'ffffff'
    ]);

    // Alternative: Use Google Charts QR API (if available)
    $qr_code_url_google = 'https://chart.googleapis.com/chart?' . http_build_query([
        'chs' => '300x300',
        'cht' => 'qr',
        'chl' => $qr_code_text,
        'choe' => 'UTF-8'
    ]);    echo json_encode([
        'success' => true,
        'qr_code_url' => $qr_code_url,
        'qr_code_url_alt' => $qr_code_url_google,
        'qr_data' => $qr_code_text,
        'user_id' => $user_id,
        'user_name' => $user['First_Name'] . ' ' . $user['Last_Name'],
        'role' => $user['Role'],
        'is_static' => true,
        'is_active' => ($user['IsActive'] == 1 && $user['account_status'] == 'active'),
        'message' => 'Static QR code generated successfully',
        'instructions' => 'This QR code is permanent and does not expire. Use it for attendance check-in and check-out.' . 
                         ($inactive_warning ? ' ' . $inactive_warning : '')
    ]);

} catch (Exception $e) {
    error_log("Static QR generation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Database error in static QR generation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
