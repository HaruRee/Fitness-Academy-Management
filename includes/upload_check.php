<?php
// Simple script to check PHP upload configuration settings
// Create an easily accessible page to help debug upload issues

session_start();
require_once '../config/database.php';

// Check if logged in as admin or coach
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Coach'])) {
    header("Location: login.php");
    exit;
}

// Function to convert bytes to more readable format
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Test if directories are writable
$upload_dir = '../uploads/coach_videos/';
$thumb_dir = '../uploads/video_thumbnails/';

// Create directories if they don't exist
if (!file_exists($upload_dir)) {
    $create_upload_dir = @mkdir($upload_dir, 0755, true);
} else {
    $create_upload_dir = true;
}

if (!file_exists($thumb_dir)) {
    $create_thumb_dir = @mkdir($thumb_dir, 0755, true);
} else {
    $create_thumb_dir = true;
}

$upload_dir_writable = is_writable($upload_dir);
$thumb_dir_writable = is_writable($thumb_dir);

// Get Apache user
$apache_user = shell_exec('ps aux | grep -E "httpd|apache" | grep -v root | grep -v grep | awk \'{print $1}\' | head -n 1');
$apache_user = trim($apache_user);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Configuration Check | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            border-bottom: 2px solid #f5f5f5;
            padding-bottom: 10px;
        }

        h2 {
            margin-top: 20px;
            color: #555;
        }

        .item {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }

        .item strong {
            display: inline-block;
            width: 250px;
        }

        .good {
            color: green;
        }

        .warning {
            color: orange;
        }

        .error {
            color: red;
        }

        .actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f5f5f5;
        }

        button,
        .button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        button:hover,
        .button:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Upload Configuration Checker</h1>

        <h2>PHP Upload Settings</h2>
        <div class="item">
            <strong>upload_max_filesize:</strong>
            <?php
            $max_upload = ini_get('upload_max_filesize');
            echo $max_upload;
            if ((int)$max_upload < 100) echo ' <span class="warning">(Recommended: at least 100M)</span>';
            ?>
        </div>
        <div class="item">
            <strong>post_max_size:</strong>
            <?php
            $max_post = ini_get('post_max_size');
            echo $max_post;
            if ((int)$max_post < 100) echo ' <span class="warning">(Recommended: at least 100M)</span>';
            ?>
        </div>
        <div class="item">
            <strong>memory_limit:</strong>
            <?php echo ini_get('memory_limit'); ?>
        </div>
        <div class="item">
            <strong>max_execution_time:</strong>
            <?php
            $max_execution = ini_get('max_execution_time');
            echo $max_execution . ' seconds';
            if ($max_execution < 120) echo ' <span class="warning">(Recommended: at least 120 for large uploads)</span>';
            ?>
        </div>
        <div class="item">
            <strong>max_input_time:</strong>
            <?php
            $max_input = ini_get('max_input_time');
            echo $max_input . ' seconds';
            if ($max_input < 120) echo ' <span class="warning">(Recommended: at least 120 for large uploads)</span>';
            ?>
        </div>

        <h2>Directory Status</h2>
        <div class="item">
            <strong>Video Upload Directory:</strong>
            <?php echo $upload_dir; ?><br>
            <strong>Exists:</strong>
            <?php echo file_exists($upload_dir) ? '<span class="good">Yes</span>' : '<span class="error">No</span>'; ?><br>
            <strong>Creation Attempted:</strong>
            <?php echo $create_upload_dir ? '<span class="good">Success</span>' : '<span class="error">Failed</span>'; ?><br>
            <strong>Writable:</strong>
            <?php echo $upload_dir_writable ? '<span class="good">Yes</span>' : '<span class="error">No</span>'; ?>
        </div>

        <div class="item">
            <strong>Thumbnail Directory:</strong>
            <?php echo $thumb_dir; ?><br>
            <strong>Exists:</strong>
            <?php echo file_exists($thumb_dir) ? '<span class="good">Yes</span>' : '<span class="error">No</span>'; ?><br>
            <strong>Creation Attempted:</strong>
            <?php echo $create_thumb_dir ? '<span class="good">Success</span>' : '<span class="error">Failed</span>'; ?><br>
            <strong>Writable:</strong>
            <?php echo $thumb_dir_writable ? '<span class="good">Yes</span>' : '<span class="error">No</span>'; ?>
        </div>

        <h2>Server Information</h2>
        <div class="item">
            <strong>Apache/PHP User:</strong> <?php echo $apache_user ? $apache_user : 'Unknown'; ?>
        </div>
        <div class="item">
            <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?>
        </div>
        <div class="item">
            <strong>PHP Version:</strong> <?php echo phpversion(); ?>
        </div>

        <div class="actions">
            <a href="coach_add_video.php" class="button">Try Video Upload Again</a>
        </div>
    </div>
</body>

</html>