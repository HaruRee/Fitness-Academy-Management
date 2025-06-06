<?php
/**
 * Health Check and Configuration Verification Endpoint
 * Access this at: yourdomain.com/health_check.php
 */

header('Content-Type: application/json');

// Function to format bytes
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Function to convert PHP ini values to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Get current configuration
$config = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'php_version' => phpversion(),
        'operating_system' => php_uname(),
    ],
    'php_upload_settings' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'upload_max_filesize_bytes' => return_bytes(ini_get('upload_max_filesize')),
        'upload_max_filesize_formatted' => formatBytes(return_bytes(ini_get('upload_max_filesize'))),
        'post_max_size' => ini_get('post_max_size'),
        'post_max_size_bytes' => return_bytes(ini_get('post_max_size')),
        'post_max_size_formatted' => formatBytes(return_bytes(ini_get('post_max_size'))),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'max_input_time' => ini_get('max_input_time'),
    ],
    'directory_permissions' => [
        'uploads_exists' => is_dir('uploads'),
        'uploads_writable' => is_writable('uploads'),
        'coach_videos_exists' => is_dir('uploads/coach_videos'),
        'coach_videos_writable' => is_writable('uploads/coach_videos'),
        'video_thumbnails_exists' => is_dir('uploads/video_thumbnails'),
        'video_thumbnails_writable' => is_writable('uploads/video_thumbnails'),
    ],
    'recommendations' => []
];

// Check for potential issues and add recommendations
$upload_limit = return_bytes(ini_get('upload_max_filesize'));
$post_limit = return_bytes(ini_get('post_max_size'));
$memory_limit = return_bytes(ini_get('memory_limit'));

if ($upload_limit < 500 * 1024 * 1024) { // Less than 500MB
    $config['recommendations'][] = "Consider increasing upload_max_filesize to 500M for large video uploads";
}

if ($post_limit < 500 * 1024 * 1024) { // Less than 500MB
    $config['recommendations'][] = "Consider increasing post_max_size to 500M for large video uploads";
}

if ($memory_limit < 512 * 1024 * 1024) { // Less than 512MB
    $config['recommendations'][] = "Consider increasing memory_limit to 512M for video processing";
}

if (!$config['directory_permissions']['uploads_writable']) {
    $config['recommendations'][] = "Uploads directory is not writable - check permissions";
}

if (ini_get('max_execution_time') < 300) {
    $config['recommendations'][] = "Consider increasing max_execution_time to 300 seconds for large uploads";
}

// Calculate effective upload limit
$effective_limit = min($upload_limit, $post_limit);
$config['effective_upload_limit'] = [
    'bytes' => $effective_limit,
    'formatted' => formatBytes($effective_limit),
    'status' => $effective_limit >= 500 * 1024 * 1024 ? 'OK' : 'NEEDS_ATTENTION'
];

// Overall health status
$config['health_status'] = empty($config['recommendations']) ? 'HEALTHY' : 'NEEDS_ATTENTION';

// Pretty print JSON
echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
