<?php
session_start();
require_once '../config/database.php';
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

$stmt = $conn->prepare("
    SELECT c.*, COUNT(ce.id) AS enrolled_members
    FROM classes c
    LEFT JOIN classenrollments ce ON c.class_id = ce.class_id
    WHERE c.coach_id = ?
    GROUP BY c.class_id
    ORDER BY c.class_date DESC, c.start_time
");
$stmt->execute([$coach_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get coach's video courses
$stmt = $conn->prepare("
    SELECT cv.*, 
           COUNT(vv.id) as total_views,
           COUNT(DISTINCT vv.member_id) as unique_viewers
    FROM coach_videos cv
    LEFT JOIN video_views vv ON cv.id = vv.video_id
    WHERE cv.coach_id = ?
    GROUP BY cv.id
    ORDER BY cv.created_at DESC
");
$stmt->execute([$coach_id]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatTime($time)
{
    return date("g:i A", strtotime($time));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Classes | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* EXACTLY copy the styles used in coach_dashboard.php */
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

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            color: #333;
        }

        .section-title i {
            margin-right: 10px;
            color: #e41e26;
        }

        .card {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f9f9f9;
        }

        .btn {
            display: inline-block;
            padding: 6px 15px;
            font-size: 0.9rem;
            border-radius: 5px;
            border: 1px solid #e41e26;
            color: #e41e26;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn:hover {
            background-color: #e41e26;
            color: #fff;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ccc;
        }

        .empty-state p {
            font-size: 1.1rem;
            color: #777;
        }

        .btn-primary {
            background-color: #e41e26;
            color: #fff;
            border: 1px solid #e41e26;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: #c71e24;
            border-color: #c71e24;
            color: #fff;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .video-card {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .video-card:hover {
            transform: translateY(-2px);
        }

        .video-thumbnail {
            width: 100%;
            height: 180px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .video-thumbnail i {
            font-size: 2rem;
            color: #666;
        }

        .video-info {
            padding: 15px;
        }

        .video-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .video-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #888;
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

        .access-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .access-free {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .access-paid {
            background: #fff3e0;
            color: #f57c00;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="date"],
        input[type="time"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        textarea {
            height: 100px;
            resize: vertical;
        }

        .modal-footer {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-edit,
        .btn-delete {
            padding: 5px 10px;
            margin: 0 2px;
        }

        .btn-edit {
            background: #4CAF50;
            color: white;
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .badge-full {
            background: #ff6b6b;
            color: white;
        }

        .badge-available {
            background: #4CAF50;
            color: white;
        }

        .badge-completed {
            background: #666;
            color: white;
        }

        .badge-upcoming {
            background: #2196F3;
            color: white;
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
                    <a href="coach_add_video.php" class="nav-item">
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
                <h2>My Classes</h2>
                <p><?= date('l, F j, Y') ?> · Manage your classes and online courses</p>
            </div>

            <!-- Physical Classes Section -->
            <div class="section-header">
                <div class="section-title"><i class="fas fa-dumbbell"></i> Physical Classes</div>
                <a href="#" class="btn-primary" onclick="showAddClassModal()">
                    <i class="fas fa-plus"></i> Add New Class
                </a>
            </div>

            <div class="card">
                <?php if (count($classes) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Class</th>
                                <th>Description</th>
                                <th>Time</th>
                                <th>Enrolled</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('M d, Y', strtotime($class['class_date']))) ?></td>
                                    <td><?= htmlspecialchars($class['class_name']) ?></td>
                                    <td><?= htmlspecialchars($class['class_description'] ?? 'N/A') ?></td>
                                    <td><?= formatTime($class['start_time']) ?> - <?= formatTime($class['end_time']) ?></td>
                                    <td>
                                        <span class="badge <?= ($class['enrolled_members'] >= ($class['capacity'] ?? PHP_INT_MAX) ? 'badge-full' : 'badge-available') ?>">
                                            <?= $class['enrolled_members'] ?> members
                                        </span>
                                    </td>
                                    <td><?= $class['price'] > 0 ? '₱' . number_format($class['price'], 2) : 'Free' ?></td>
                                    <td>
                                        <span class="badge <?= strtotime($class['class_date']) < strtotime('today') ? 'badge-completed' : 'badge-upcoming' ?>">
                                            <?= strtotime($class['class_date']) < strtotime('today') ? 'Completed' : 'Upcoming' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="editClass(<?= htmlspecialchars(json_encode($class)) ?>)" class="btn btn-edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteClass(<?= $class['class_id'] ?>)" class="btn btn-delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No physical classes found</p>
                        <button onclick="showAddClassModal()" class="btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Create Your First Class
                        </button>
                    </div>
                <?php endif; ?>
            </div>            <!-- Online Courses Section -->
            <div class="section-header">
                <div class="section-title"><i class="fas fa-video"></i> Online Courses</div>
                <a href="coach_add_video.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Add Video Course
                </a>
            </div>

            <div class="card">
                <?php if (count($videos) > 0): ?>
                    <div class="video-grid">
                        <?php foreach ($videos as $video): ?>
                            <div class="video-card">
                                <div class="video-thumbnail">
                                    <?php if ($video['thumbnail_path']): ?>
                                        <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-play-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="video-info">
                                    <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                    <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                                    <div class="video-meta">
                                        <div>
                                            <span class="status-badge status-<?= $video['status'] ?>">
                                                <?= ucfirst($video['status']) ?>
                                            </span>
                                            <span class="access-type access-<?= $video['access_type'] ?>">
                                                <?= $video['access_type'] === 'paid' ? '₱' . number_format($video['subscription_price'], 2) . '/mo' : 'Free' ?>
                                            </span>
                                        </div>
                                        <div>
                                            <i class="fas fa-eye"></i> <?= $video['total_views'] ?>
                                            <i class="fas fa-users" style="margin-left: 10px;"></i> <?= $video['unique_viewers'] ?>
                                        </div>
                                    </div>
                                    <div style="margin-top: 10px;">
                                        <a href="coach_edit_video.php?id=<?= $video['id'] ?>" class="btn" style="margin-right: 5px;">Edit</a>
                                        <a href="coach_view_video.php?id=<?= $video['id'] ?>" class="btn">View</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-video"></i>
                        <p>No video courses uploaded yet</p>
                        <a href="coach_add_video.php" class="btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Upload Your First Video
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Class Modal -->
            <div id="classModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2 id="modalTitle">Add New Class</h2>
                    <form id="classForm" onsubmit="handleClassSubmit(event)">
                        <input type="hidden" id="class_id" name="class_id">

                        <div class="form-group">
                            <label for="class_name">Class Name*</label>
                            <input type="text" id="class_name" name="class_name" required>
                        </div>

                        <div class="form-group">
                            <label for="class_description">Description*</label>
                            <textarea id="class_description" name="class_description" required></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="class_date">Date*</label>
                                <input type="date" id="class_date" name="class_date" required min="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group">
                                <label for="start_time">Start Time*</label>
                                <input type="time" id="start_time" name="start_time" required>
                            </div>

                            <div class="form-group">
                                <label for="end_time">End Time*</label>
                                <input type="time" id="end_time" name="end_time" required>
                            </div>

                            <div class="form-group">
                                <label for="price">Price (₱)*</label>
                                <input type="number" id="price" name="price" min="0" step="0.01" value="0.00" required>
                                <small class="form-text text-muted">Set to 0 for free classes</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="difficulty_level">Difficulty Level*</label>
                            <select id="difficulty_level" name="difficulty_level" required>
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="requirements">Requirements</label>
                            <textarea id="requirements" name="requirements" placeholder="Any special requirements or items needed for the class"></textarea>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Class</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('classModal');
        const closeBtn = document.getElementsByClassName('close')[0];

        function showAddClassModal() {
            document.getElementById('modalTitle').textContent = 'Add New Class';
            document.getElementById('classForm').reset();
            document.getElementById('class_id').value = '';
            modal.style.display = 'block';
        }

        function editClass(classData) {
            document.getElementById('modalTitle').textContent = 'Edit Class';
            document.getElementById('class_id').value = classData.class_id;
            document.getElementById('class_name').value = classData.class_name;
            document.getElementById('class_description').value = classData.class_description || '';
            document.getElementById('class_date').value = classData.class_date;
            document.getElementById('start_time').value = classData.start_time;
            document.getElementById('end_time').value = classData.end_time;
            document.getElementById('price').value = classData.price || '0.00';
            document.getElementById('difficulty_level').value = classData.difficulty_level || 'Beginner';
            document.getElementById('requirements').value = classData.requirements || '';
            modal.style.display = 'block';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        closeBtn.onclick = closeModal;

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        async function handleClassSubmit(event) {
            event.preventDefault();
            const formData = new FormData(event.target);

            try {
                const response = await fetch('../api/manage_class.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.reload();
                } else {
                    alert(result.message || 'Error saving class');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving class');
            }
        }

        async function deleteClass(classId) {
            if (!confirm('Are you sure you want to delete this class?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('class_id', classId);

                const response = await fetch('../api/manage_class.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.reload();
                } else {
                    alert(result.message || 'Error deleting class');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting class');
            }
        }
    </script>
</body>

</html>