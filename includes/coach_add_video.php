<?php
session_start();
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Manila');

// Helper function to properly handle thumbnail paths
function fixThumbnailPath($path) {
    if (empty($path)) return false;
    
    // If the path already starts with '../' remove it to avoid double path issues
    if (strpos($path, '../') === 0) {
        return substr($path, 3);
    }
    return $path;
}

// Helper functions for file size formatting and parsing
function return_bytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int)$val;
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Check if logged in and is coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

$coach_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ? AND Role = 'Coach'");
$stmt->execute([$coach_id]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    header("Location: logout.php");
    exit;
}

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $access_type = $_POST['access_type'];
    $subscription_price = ($access_type === 'paid') ? floatval($_POST['subscription_price']) : null;

    // Validate inputs
    if (empty($title)) {
        $error_message = "Video title is required.";
    } elseif (empty($description)) {
        $error_message = "Video description is required.";
    } elseif ($access_type === 'paid' && ($subscription_price < 20.00 || $subscription_price > 9999.99)) {
        $error_message = "Please enter a valid subscription price between ₱20.00 and ₱9,999.99. (PayMongo requires a minimum of ₱20.00)";
    } elseif (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Please select a valid video file.";
    } else {
        // Validate video file
        $video_file = $_FILES['video_file'];
        $allowed_types = ['video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo'];
        $max_size = 500 * 1024 * 1024; // 500MB
        $php_limit = min(
            return_bytes(ini_get('upload_max_filesize')),
            return_bytes(ini_get('post_max_size'))
        );

        // Use the smaller of our desired limit and PHP's actual limit
        $effective_max_size = min($max_size, $php_limit);

        // Check file type
        if (!in_array($video_file['type'], $allowed_types)) {
            $error_message = "Please upload a valid video file (MP4, AVI, MOV).";
        } elseif ($video_file['size'] > $effective_max_size) {
            // If PHP limits are smaller than our desired 500MB, show a more precise message
            if ($php_limit < $max_size) {
                $error_message = "Video file size must be less than " . formatBytes($php_limit) .
                    " due to server settings. Please compress your video or contact the administrator.";
            } else {
                $error_message = "Video file size must be less than 500MB.";
            }
        } else {
            // Create upload directories with proper error handling
            $upload_dir = '../uploads/coach_videos/';
            $thumb_dir = '../uploads/video_thumbnails/';

            // Ensure directories exist with proper permissions
            if (!file_exists($upload_dir)) {
                if (!@mkdir($upload_dir, 0777, true)) {
                    $error_message = "Unable to create video upload directory. Please contact the administrator.";
                    error_log("Failed to create directory: " . $upload_dir . " - " . error_get_last()['message']);
                }
            }

            if (!file_exists($thumb_dir)) {
                if (!@mkdir($thumb_dir, 0777, true)) {
                    $error_message = "Unable to create thumbnail directory. Please contact the administrator.";
                    error_log("Failed to create directory: " . $thumb_dir . " - " . error_get_last()['message']);
                }
            }

            // Double-check that directories are writable
            if (!is_writable($upload_dir)) {
                $error_message = "Video upload directory is not writable. Please contact the administrator.";
                error_log("Directory not writable: " . $upload_dir);
            }

            if (!is_writable($thumb_dir)) {
                $error_message = "Thumbnail directory is not writable. Please contact the administrator.";
                error_log("Directory not writable: " . $thumb_dir);
            }

            // Only proceed if no errors occurred in directory setup
            if (empty($error_message)) {

                // Generate unique filename
                $video_extension = pathinfo($video_file['name'], PATHINFO_EXTENSION);
                $video_filename = 'video_' . $coach_id . '_' . time() . '_' . uniqid() . '.' . $video_extension;
                $video_path = $upload_dir . $video_filename;                // Handle thumbnail upload
                $thumbnail_path = null;
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $thumbnail_file = $_FILES['thumbnail'];
                    $thumb_allowed = ['image/jpeg', 'image/png', 'image/gif'];

                    if (in_array($thumbnail_file['type'], $thumb_allowed)) {
                        $thumb_extension = pathinfo($thumbnail_file['name'], PATHINFO_EXTENSION);
                        $thumb_filename = 'thumb_' . $coach_id . '_' . time() . '_' . uniqid() . '.' . $thumb_extension;
                        // Full physical path to the thumbnail file
                        $thumbnail_file_path = $thumb_dir . $thumb_filename;

                        if (move_uploaded_file($thumbnail_file['tmp_name'], $thumbnail_file_path)) {
                            // Store with '../' prefix for consistency across the application
                            $thumbnail_path = '../uploads/video_thumbnails/' . $thumb_filename;
                        } else {
                            $thumbnail_path = null;
                        }
                    }
                }

                // Upload video file
                if (move_uploaded_file($video_file['tmp_name'], $video_path)) {
                    // Insert into database
                    try {
                        $stmt = $conn->prepare("
                        INSERT INTO coach_videos (coach_id, title, description, video_path, thumbnail_path, access_type, subscription_price, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                        $stmt->execute([
                            $coach_id,
                            $title,
                            $description,
                            $video_path,
                            $thumbnail_path,
                            $access_type,
                            $subscription_price
                        ]);

                        $success_message = "Video uploaded successfully! It is now pending admin approval.";

                        // Clear form data
                        $_POST = array();
                    } catch (PDOException $e) {
                        $error_message = "Database error: " . $e->getMessage();
                        // Clean up uploaded files on database error
                        if (file_exists($video_path)) unlink($video_path);
                        if ($thumbnail_path) {
                            $physical_path = strpos($thumbnail_path, '../') === 0 
                                ? $thumbnail_path 
                                : '../' . $thumbnail_path;
                                
                            if (file_exists($physical_path)) {
                                unlink($physical_path);
                            }
                        }
                    }
                } else {
                    // Enhanced error logging
                    $upload_error = error_get_last();
                    $error_code = $_FILES['video_file']['error'];

                    // Map error code to human-readable message
                    switch ($error_code) {
                        case UPLOAD_ERR_INI_SIZE:
                            $error_message = "The uploaded file exceeds the upload_max_filesize directive in php.ini (current limit: " . ini_get('upload_max_filesize') . ").";
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = "The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = "The uploaded file was only partially uploaded. Please try again.";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message = "No file was uploaded. Please select a file.";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error_message = "Missing a temporary folder. Contact the administrator.";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error_message = "Failed to write file to disk. Check folder permissions.";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = "A PHP extension stopped the file upload.";
                            break;
                        default:
                            $error_message = "Failed to upload video file. Please try again.";

                            // Add detailed error info for debugging
                            if ($upload_error) {
                                error_log("Video upload error: " . print_r($upload_error, true));
                            }

                            // Check directory permissions
                            if (!is_writable($upload_dir)) {
                                $error_message .= " The upload directory is not writable.";
                                error_log("Directory not writable: " . $upload_dir);
                            }
                            break;
                    }

                    // Log the error for debugging
                    error_log("Video upload failed. Error code: " . $error_code . " - " . $error_message);
                }
            } // Close the if (empty($error_message)) block
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Video Course | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            color: #333;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background-color: #222;
            color: #fff;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }

        .sidebar-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
        }

        .sidebar-nav {
            padding: 15px 0;
        }

        .nav-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            padding: 10px 25px;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 500;
            border-left: 5px solid transparent;
            transition: 0.3s;
        }

        .nav-item i {
            margin-right: 15px;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-item.active {
            background-color: rgba(228, 30, 38, 0.2);
            color: #fff;
            border-left-color: #e41e26;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: 100%;
        }

        .welcome-banner {
            background: linear-gradient(45deg, #e41e26, #ff6b6b);
            color: #fff;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            margin: 0;
        }

        .welcome-banner p {
            margin-top: 5px;
        }

        .card {
            background-color: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            max-width: 800px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e41e26;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            font-size: 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            font-weight: 600;
        }

        .btn-primary {
            background-color: #e41e26;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #c71e24;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: border-color 0.3s;
            background: #fafafa;
        }

        .file-upload-area:hover {
            border-color: #e41e26;
        }

        .file-upload-area.dragover {
            border-color: #e41e26;
            background: #fef5f5;
        }

        .upload-icon {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }

        .upload-text {
            color: #666;
        }

        .file-info {
            margin-top: 15px;
            padding: 10px;
            background: #e8f5e8;
            border-radius: 5px;
            display: none;
        }

        .pricing-field {
            display: none;
            margin-top: 15px;
        }

        .pricing-field.show {
            display: block;
        }

        .form-note {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/images/fa_logo.png" alt="Logo">
                <h3>Fitness Academy</h3>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="coach_dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Classes</div>
                    <a href="coach_my_classes.php" class="nav-item active"><i class="fas fa-dumbbell"></i><span>My Classes</span></a>
                    <a href="coach_class_schedule.php" class="nav-item"><i class="fas fa-calendar-alt"></i><span>Class Schedule</span></a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Members</div>
                    <a href="coach_my_clients.php" class="nav-item"><i class="fas fa-users"></i><span>My Clients</span></a>
                    <a href="coach_progress_tracking.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Progress Tracking</span></a>
                </div>                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item active">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="coach_my_profile.php" class="nav-item"><i class="fas fa-user"></i><span>My Profile</span></a>
                    <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="welcome-banner">
                <h2>Add Video Course</h2>
                <p>Upload a new video course for your members</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data" id="videoUploadForm">
                    <div class="form-group">
                        <label for="title">Video Title *</label>
                        <input type="text" id="title" name="title" required maxlength="255"
                            value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required maxlength="1000"
                            placeholder="Describe what members will learn from this video..."><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="video_file">Video File *</label>
                        <div class="file-upload-area" id="videoDropArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                <strong>Click to select video file</strong> or drag and drop<br>
                                <small>Supported formats: MP4, AVI, MOV (Max: 500MB)</small>
                            </div>
                            <input type="file" id="video_file" name="video_file" accept="video/*" required style="display: none;">
                            <div class="file-info" id="videoFileInfo"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="thumbnail">Thumbnail Image (Optional)</label>
                        <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
                        <div class="form-note">Upload a custom thumbnail image. If not provided, a default thumbnail will be used.</div>
                    </div>

                    <div class="form-group">
                        <label for="access_type">Access Type *</label>
                        <select id="access_type" name="access_type" required>
                            <option value="free" <?= (isset($_POST['access_type']) && $_POST['access_type'] === 'free') ? 'selected' : '' ?>>Free</option>
                            <option value="paid" <?= (isset($_POST['access_type']) && $_POST['access_type'] === 'paid') ? 'selected' : '' ?>>Paid (Subscription)</option>
                        </select>
                    </div>

                    <div class="form-group pricing-field" id="pricingField">
                        <label for="subscription_price">Monthly Subscription Price (₱) *</label>
                        <input type="number" id="subscription_price" name="subscription_price"
                            min="20.00" max="9999.99" step="0.01"
                            value="<?= isset($_POST['subscription_price']) ? $_POST['subscription_price'] : '' ?>">
                        <div class="form-note">Set the monthly subscription price for accessing your paid content. Minimum ₱20.00 required by payment processor.</div>
                    </div>

                    <div style="margin-top: 30px; display: flex; gap: 15px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Video
                        </button>
                        <a href="coach_my_classes.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle access type change
        document.getElementById('access_type').addEventListener('change', function() {
            const pricingField = document.getElementById('pricingField');
            const subscriptionPrice = document.getElementById('subscription_price');

            if (this.value === 'paid') {
                pricingField.classList.add('show');
                subscriptionPrice.required = true;
            } else {
                pricingField.classList.remove('show');
                subscriptionPrice.required = false;
                subscriptionPrice.value = '';
            }
        });

        // Initialize pricing field visibility
        document.addEventListener('DOMContentLoaded', function() {
            const accessType = document.getElementById('access_type');
            if (accessType.value === 'paid') {
                document.getElementById('pricingField').classList.add('show');
            }
        });

        // File upload handling
        const videoDropArea = document.getElementById('videoDropArea');
        const videoFileInput = document.getElementById('video_file');
        const videoFileInfo = document.getElementById('videoFileInfo');

        videoDropArea.addEventListener('click', () => videoFileInput.click());

        videoDropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            videoDropArea.classList.add('dragover');
        });

        videoDropArea.addEventListener('dragleave', () => {
            videoDropArea.classList.remove('dragover');
        });

        videoDropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            videoDropArea.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                videoFileInput.files = files;
                updateVideoFileInfo(files[0]);
            }
        });

        videoFileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateVideoFileInfo(e.target.files[0]);
            }
        });

        function updateVideoFileInfo(file) {
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
            videoFileInfo.innerHTML = `
        <i class="fas fa-file-video"></i>
        <strong>${file.name}</strong> (${sizeInMB} MB)
    `;
            videoFileInfo.style.display = 'block';
        }
    </script>
</body>

</html>