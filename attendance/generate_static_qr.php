<?php
// Static QR Code Generator - Generates permanent user-specific QR codes
session_start();
require_once '../config/database.php';

// Log request for debugging
error_log("QR Generation Request: " . json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'user_id' => $_SESSION['user_id'] ?? 'not_set',
    'role' => $_SESSION['role'] ?? 'not_set',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'timestamp' => date('Y-m-d H:i:s')
]));

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Simple rate limiting to prevent abuse
$rate_limit_key = 'qr_gen_' . session_id();
$rate_limit_file = sys_get_temp_dir() . '/' . $rate_limit_key;

if (file_exists($rate_limit_file)) {
    $last_request = filemtime($rate_limit_file);
    if (time() - $last_request < 2) { // 2 second cooldown
        http_response_code(429); // Too Many Requests
        echo json_encode([
            'success' => false,
            'message' => 'Please wait before generating another QR code',
            'retry_after' => 2
        ]);
        exit;
    }
}

// Update rate limit timestamp
touch($rate_limit_file);

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
    ]);    // Use simple user ID for now (easier to scan and process)
    $qr_code_text = (string)$user_id;
    
    // Debug logging
    error_log("QR Generation Debug: user_id=$user_id, qr_code_text='$qr_code_text', empty_check=" . (empty($qr_code_text) ? 'true' : 'false'));
    
    // Validate QR data - ensure it's not empty and properly encoded
    if (empty($qr_code_text) || trim($qr_code_text) === '' || trim($qr_code_text) === '0') {
        error_log("QR Generation Error: Empty QR code text. user_id=$user_id, qr_code_text='$qr_code_text'");
        throw new Exception("QR code data cannot be empty. User ID: $user_id");
    }
    
    // Sanitize the QR code text to prevent issues
    $qr_code_text = trim((string)$qr_code_text);
    
    // Validate user ID is numeric and positive
    if (!is_numeric($qr_code_text) || (int)$qr_code_text <= 0) {
        throw new Exception("Invalid user ID for QR code generation");
    }
    
    $encoded_data = urlencode($qr_code_text);
    
    // Generate QR code URL using QRServer.com (reliable fallback)
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '300x300',
        'data' => $qr_code_text,
        'format' => 'png',
        'margin' => 10,
        'qzone' => 2,
        'color' => '000000',
        'bgcolor' => 'ffffff',
        'ecc' => 'M'  // Error correction level
    ]);

    // Alternative: Use Google Charts QR API (more reliable)
    // Note: Google Charts has deprecated this API but it still works
    $qr_code_url_google = 'https://chart.googleapis.com/chart?' . http_build_query([
        'chs' => '300x300',
        'cht' => 'qr',
        'chl' => $qr_code_text,
        'choe' => 'UTF-8',
        'chld' => 'M|2'  // Error correction level and margin
    ]);

    // Alternative using QuickChart.io (more modern and reliable)
    $qr_code_url_quickchart = 'https://quickchart.io/qr?' . http_build_query([
        'text' => $qr_code_text,
        'size' => '300x300',
        'format' => 'png',
        'margin' => 2,
        'ecLevel' => 'M'
    ]);

    // Use QuickChart as primary (most reliable and modern)
    $primary_qr_url = $qr_code_url_quickchart;
    
    // Validate URLs are properly formed
    if (!filter_var($primary_qr_url, FILTER_VALIDATE_URL) || 
        !filter_var($qr_code_url_google, FILTER_VALIDATE_URL) || 
        !filter_var($qr_code_url, FILTER_VALIDATE_URL)) {
        throw new Exception("Failed to generate valid QR code URLs");
    }echo json_encode([
        'success' => true,
        'qr_code_url' => $primary_qr_url,
        'qr_code_url_alt' => $qr_code_url_google,
        'qr_code_url_fallback' => $qr_code_url,
        'qr_data' => $qr_code_text,
        'user_id' => $user_id,
        'user_name' => $user['First_Name'] . ' ' . $user['Last_Name'],
        'role' => $user['Role'],
        'is_static' => true,
        'is_active' => ($user['IsActive'] == 1 && $user['account_status'] == 'active'),
        'message' => 'Static QR code generated successfully',
        'instructions' => 'This QR code is permanent and does not expire. Use it for attendance check-in and check-out.' . 
                         ($inactive_warning ? ' ' . $inactive_warning : ''),
        'generation_timestamp' => date('Y-m-d H:i:s')
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
