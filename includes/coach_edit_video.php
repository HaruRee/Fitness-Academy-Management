<?php
session_start();
require_once '../config/database.php';

// Helper function to properly handle thumbnail paths
function fixThumbnailPath($path) {
    if (empty($path)) return false;
    
    // If the path already starts with '../' remove it to avoid double path issues
    if (strpos($path, '../') === 0) {
        return substr($path, 3);
    }
    return $path;
}

// Check if logged in and is coach
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

$coach_id = $_SESSION['user_id'];
$video_id = $_GET['id'] ?? null;

if (!$video_id) {
    header("Location: coach_my_classes.php");
    exit;
}

// Get video details and verify ownership
$stmt = $conn->prepare("SELECT * FROM coach_videos WHERE id = ? AND coach_id = ?");
$stmt->execute([$video_id, $coach_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    header("Location: coach_my_classes.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_POST && $_POST['action'] === 'update_video') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $access_type = $_POST['access_type'];
    $subscription_price = ($access_type === 'paid') ? floatval($_POST['subscription_price']) : null;

    // Basic validation
    if (empty($title) || empty($description)) {
        $error_message = "Title and description are required.";
    } elseif ($access_type === 'paid' && ($subscription_price < 20.00 || $subscription_price > 10000)) {
        $error_message = "Subscription price must be between ₱20.00 and ₱10,000. (PayMongo requires a minimum of ₱20.00)";
    } else {
        try {
            // Handle thumbnail upload if provided
            $thumbnail_path = $video['thumbnail_path'];
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['size'] > 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                $file_type = $_FILES['thumbnail']['type'];

                if (in_array($file_type, $allowed_types) && $_FILES['thumbnail']['size'] <= 5000000) {
                    $upload_dir = '../uploads/video_thumbnails/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_extension = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'thumb_' . $video_id . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_path)) {                        // Delete old thumbnail if exists
                        if ($thumbnail_path) {
                            $old_path = strpos($thumbnail_path, '../') === 0 
                                ? $thumbnail_path 
                                : '../' . $thumbnail_path;
                                
                            if (file_exists($old_path)) {
                                unlink($old_path);
                            }
                        }
                        // Store path with '../' prefix for consistency across the application
                        $thumbnail_path = '../uploads/video_thumbnails/' . $new_filename;
                    }
                }
            }

            // Update video details
            $stmt = $conn->prepare("
                UPDATE coach_videos 
                SET title = ?, description = ?, access_type = ?, subscription_price = ?, thumbnail_path = ?, updated_at = NOW()
                WHERE id = ? AND coach_id = ?
            ");
            $stmt->execute([$title, $description, $access_type, $subscription_price, $thumbnail_path, $video_id, $coach_id]);

            $success_message = "Video updated successfully!";

            // Refresh video data
            $stmt = $conn->prepare("SELECT * FROM coach_videos WHERE id = ? AND coach_id = ?");
            $stmt->execute([$video_id, $coach_id]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error_message = "Error updating video: " . $e->getMessage();
        }
    }
}

// Handle delete action
if ($_POST && $_POST['action'] === 'delete_video') {
    try {
        // Delete video file and thumbnail
        if ($video['video_path'] && file_exists('../' . $video['video_path'])) {
            unlink('../' . $video['video_path']);
        }
        if ($video['thumbnail_path'] && file_exists('../' . $video['thumbnail_path'])) {
            unlink('../' . $video['thumbnail_path']);
        }

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM coach_videos WHERE id = ? AND coach_id = ?");
        $stmt->execute([$video_id, $coach_id]);

        header("Location: coach_my_classes.php?deleted=1");
        exit;
    } catch (Exception $e) {
        $error_message = "Error deleting video: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Video | Fitness Academy</title>
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
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #e41e26;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background-color: #e41e26;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #c71e24;
            color: #fff;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #545b62;
            color: #fff;
        }

        .btn-danger {
            background-color: #dc3545;
            color: #fff;
        }

        .btn-danger:hover {
            background-color: #c82333;
            color: #fff;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .video-preview {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .video-thumbnail {
            width: 200px;
            height: 120px;
            background: #e9ecef;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .video-thumbnail i {
            font-size: 2rem;
            color: #6c757d;
        }

        .video-details h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .video-details p {
            margin: 5px 0;
            color: #666;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .upload-zone {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: border-color 0.3s;
            cursor: pointer;
        }

        .upload-zone:hover {
            border-color: #e41e26;
        }

        .upload-zone.dragover {
            border-color: #e41e26;
            background: rgba(228, 30, 38, 0.05);
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: #e41e26;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .back-link i {
            margin-right: 8px;
        }

        .back-link:hover {
            color: #c71e24;
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
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
                    </a>
                    <a href="coach_view_video.php" class="nav-item">
                        <i class="fas fa-play"></i>
                        <span>My Videos</span>
                    </a>
                    <a href="coach_edit_video.php" class="nav-item active">
                        <i class="fas fa-edit"></i>
                        <span>Edit Videos</span>
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
            <a href="coach_my_classes.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to My Classes
            </a>

            <div class="welcome-banner">
                <h2>Edit Video Course</h2>
                <p>Update your video course details and settings</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Video Preview -->
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-video"></i> Current Video</h3>
                <div class="video-preview">
                    <div class="video-thumbnail">                        <?php if ($video['thumbnail_path']): ?>
                            <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail">
                        <?php else: ?>
                            <i class="fas fa-play-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="video-details">
                        <h4><?= htmlspecialchars($video['title']) ?></h4>
                        <p><strong>Status:</strong> <span class="status-badge status-<?= $video['status'] ?>"><?= ucfirst($video['status']) ?></span></p>
                        <p><strong>Access Type:</strong> <?= $video['access_type'] === 'paid' ? 'Paid (₱' . number_format($video['subscription_price'], 2) . '/month)' : 'Free' ?></p>
                        <p><strong>Uploaded:</strong> <?= date('M d, Y g:i A', strtotime($video['created_at'])) ?></p>
                        <?php if ($video['updated_at'] && $video['updated_at'] !== $video['created_at']): ?>
                            <p><strong>Last Updated:</strong> <?= date('M d, Y g:i A', strtotime($video['updated_at'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-edit"></i> Edit Video Details</h3>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_video">

                    <div class="form-group">
                        <label for="title">Video Title *</label>
                        <input type="text" id="title" name="title" class="form-control"
                            value="<?= htmlspecialchars($video['title']) ?>" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" class="form-control"
                            required maxlength="1000"><?= htmlspecialchars($video['description']) ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="access_type">Access Type *</label>
                            <select id="access_type" name="access_type" class="form-control" required>
                                <option value="free" <?= $video['access_type'] === 'free' ? 'selected' : '' ?>>Free</option>
                                <option value="paid" <?= $video['access_type'] === 'paid' ? 'selected' : '' ?>>Paid Subscription</option>
                            </select>
                        </div>

                        <div class="form-group" id="price-group" style="<?= $video['access_type'] === 'free' ? 'display: none;' : '' ?>">
                            <label for="subscription_price">Monthly Price (₱) * (Minimum ₱20.00)</label>
                            <input type="number" id="subscription_price" name="subscription_price"
                                class="form-control" min="20.00" max="10000" step="0.01"
                                value="<?= $video['subscription_price'] ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="thumbnail">Update Thumbnail (Optional)</label>
                        <div class="upload-zone" onclick="document.getElementById('thumbnail').click()">
                            <i class="fas fa-image" style="font-size: 2rem; color: #ccc; margin-bottom: 10px;"></i>
                            <p>Click to select a new thumbnail image</p>
                            <p style="font-size: 0.9rem; color: #666;">JPEG, PNG files only. Max 5MB.</p>
                        </div>
                        <input type="file" id="thumbnail" name="thumbnail" style="display: none;"
                            accept=".jpg,.jpeg,.png">
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Video
                        </button>
                        <a href="coach_my_classes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Delete Video -->
            <div class="card" style="border: 1px solid #dc3545;">
                <h3 style="margin-bottom: 20px; color: #dc3545;"><i class="fas fa-trash"></i> Danger Zone</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    Deleting this video will permanently remove it from the system. This action cannot be undone.
                </p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this video? This action cannot be undone.');">
                    <input type="hidden" name="action" value="delete_video">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Video
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle access type change
        document.getElementById('access_type').addEventListener('change', function() {
            const priceGroup = document.getElementById('price-group');
            const priceInput = document.getElementById('subscription_price');

            if (this.value === 'paid') {
                priceGroup.style.display = 'block';
                priceInput.required = true;
            } else {
                priceGroup.style.display = 'none';
                priceInput.required = false;
                priceInput.value = '';
            }
        });

        // Handle file upload preview
        document.getElementById('thumbnail').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const uploadZone = document.querySelector('.upload-zone');
                uploadZone.innerHTML = `
            <i class="fas fa-check-circle" style="font-size: 2rem; color: #28a745; margin-bottom: 10px;"></i>
            <p style="color: #28a745;">New thumbnail selected: ${file.name}</p>
        `;
            }
        });

        // Drag and drop functionality
        const uploadZone = document.querySelector('.upload-zone');

        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('thumbnail').files = files;
                document.getElementById('thumbnail').dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>