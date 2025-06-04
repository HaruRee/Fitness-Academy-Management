<?php
// QR Code Generation and Display for Attendance
session_start();
require_once '../config/database.php';

// Check if user is logged in and has proper access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Coach', 'Admin'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle QR code generation for sessions
if ($_POST['action'] ?? '' === 'create_session') {
    $session_name = $_POST['session_name'] ?? '';
    $session_type = $_POST['session_type'] ?? 'general';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $location = $_POST['location'] ?? '';
    $max_participants = $_POST['max_participants'] ?? null;

    if ($session_name && $start_time && $end_time) {
        // Generate unique QR code
        $qr_code = 'SESSION_' . time() . '_' . substr(md5($session_name . $user_id), 0, 8);

        $stmt = $conn->prepare("
            INSERT INTO attendance_sessions (session_name, qr_code, coach_id, session_type, start_time, end_time, location, max_participants)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$session_name, $qr_code, $user_id, $session_type, $start_time, $end_time, $location, $max_participants]);

        // Log to audit trail
        $audit_stmt = $conn->prepare("
            INSERT INTO audit_trail (user_id, action_type, table_name, record_id, old_values, new_values, timestamp)
            VALUES (?, 'CREATE', 'attendance_sessions', ?, '', ?, NOW())
        ");
        $audit_stmt->execute([$user_id, $conn->lastInsertId(), json_encode([
            'session_name' => $session_name,
            'qr_code' => $qr_code,
            'session_type' => $session_type
        ])]);

        $success_message = "Session created successfully!";
    }
}

// Get active sessions
$stmt = $conn->prepare("
    SELECT * FROM attendance_sessions 
    WHERE status = 'active' AND end_time > NOW()
    ORDER BY start_time ASC
");
$stmt->execute();
$active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get QR locations
$stmt = $conn->prepare("SELECT * FROM qr_locations WHERE is_active = 1");
$stmt->execute();
$qr_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }

        .tab {
            padding: 15px 30px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .qr-display {
            text-align: center;
            padding: 30px;
            border: 3px dashed #ddd;
            border-radius: 15px;
            margin: 20px 0;
        }

        .qr-code {
            font-size: 48px;
            margin: 20px 0;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 15px;
            font-family: monospace;
            color: #333;
            border: 2px solid #667eea;
        }

        .session-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .session-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .session-card h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .session-card .qr-code-small {
            font-family: monospace;
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 14px;
            border: 1px solid #ddd;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .location-qr {
            display: inline-block;
            margin: 10px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #667eea;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🔲 QR Attendance System</h1>
            <p>Manage attendance sessions and QR codes</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab active" onclick="showTab('create')">Create Session</button>
            <button class="tab" onclick="showTab('active')">Active Sessions</button>
            <button class="tab" onclick="showTab('locations')">QR Locations</button>
        </div>

        <div id="create" class="tab-content active">
            <h3>Create New Attendance Session</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_session">

                <div class="form-group">
                    <label for="session_name">Session Name</label>
                    <input type="text" id="session_name" name="session_name" required
                        placeholder="e.g., Morning Yoga Class">
                </div>

                <div class="form-group">
                    <label for="session_type">Session Type</label>
                    <select id="session_type" name="session_type" required>
                        <option value="class">Class</option>
                        <option value="personal_training">Personal Training</option>
                        <option value="general">General</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="datetime-local" id="start_time" name="start_time" required>
                </div>

                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <input type="datetime-local" id="end_time" name="end_time" required>
                </div>

                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="e.g., Studio A">
                </div>

                <div class="form-group">
                    <label for="max_participants">Max Participants (optional)</label>
                    <input type="number" id="max_participants" name="max_participants" min="1">
                </div>

                <button type="submit" class="btn">Create Session & Generate QR</button>
            </form>
        </div>

        <div id="active" class="tab-content">
            <h3>Active Sessions</h3>
            <?php if (empty($active_sessions)): ?>
                <p>No active sessions found.</p>
            <?php else: ?>
                <div class="session-list">
                    <?php foreach ($active_sessions as $session): ?>
                        <div class="session-card">
                            <h4><?php echo htmlspecialchars($session['session_name']); ?></h4>
                            <p><strong>Type:</strong> <?php echo ucfirst($session['session_type']); ?></p>
                            <p><strong>Time:</strong> <?php echo date('M j, Y g:i A', strtotime($session['start_time'])); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($session['location']); ?></p>

                            <div class="qr-display">
                                <div class="qr-code"><?php echo $session['qr_code']; ?></div>
                                <p>Show this QR code for attendance</p>
                                <button onclick="showQRFullscreen('<?php echo $session['qr_code']; ?>')" class="btn">
                                    Show Fullscreen
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="locations" class="tab-content">
            <h3>QR Location Codes</h3>
            <p>These QR codes are for general gym entry and location-based check-ins:</p>

            <div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;">
                <?php foreach ($qr_locations as $location): ?>
                    <div class="location-qr">
                        <h4><?php echo htmlspecialchars($location['location_name']); ?></h4>
                        <div class="qr-code" style="font-size: 24px;"><?php echo $location['qr_code']; ?></div>
                        <p><?php echo ucfirst($location['location_type']); ?></p>
                        <button onclick="showQRFullscreen('<?php echo $location['qr_code']; ?>')" class="btn">
                            Show Fullscreen
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Fullscreen QR Modal -->
    <div id="qrModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; display: flex; align-items: center; justify-content: center;">
        <div style="text-align: center; color: white;">
            <div id="fullscreenQR" style="font-size: 120px; font-family: monospace; background: white; color: black; padding: 40px; border-radius: 20px; margin-bottom: 20px;"></div>
            <button onclick="closeQRModal()" style="background: #667eea; color: white; padding: 15px 30px; border: none; border-radius: 10px; font-size: 18px; cursor: pointer;">Close</button>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function showQRFullscreen(qrCode) {
            document.getElementById('fullscreenQR').textContent = qrCode;
            document.getElementById('qrModal').style.display = 'flex';
        }

        function closeQRModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        // Set default datetime values
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);

            document.getElementById('start_time').value = now.toISOString().slice(0, 16);
            document.getElementById('end_time').value = oneHourLater.toISOString().slice(0, 16);
        });
    </script>
</body>

</html>