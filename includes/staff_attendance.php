<?php
session_start();
require '../config/database.php';
require 'activity_tracker.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Staff') {
    header('Location: login.php');
    exit;
}

// Track page view activity
if (isset($_SESSION['user_id'])) {
    trackPageView($_SESSION['user_id'], 'Staff Attendance');
}

$error = null;
$success = null;

// Handle QR code attendance (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr'])) {
    $qr = $_POST['qr'];
    if (preg_match('/^staff_(\d+)$/', $qr, $matches)) {
        $staff_id = $matches[1];
        $now = date('Y-m-d H:i:s');

        // Check if staff exists and is active
        $staff_stmt = $conn->prepare("SELECT UserID, First_Name, Last_Name FROM users WHERE UserID = ? AND Role = 'Staff' AND IsActive = 1");
        $staff_stmt->execute([$staff_id]);
        $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff) {
            // Check if already checked in today
            $check_stmt = $conn->prepare("SELECT id, time_out FROM attendance_records WHERE user_id = ? AND DATE(check_in_time) = CURDATE() AND user_type = 'Staff'");
            $check_stmt->execute([$staff_id]);
            $attendance = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($attendance) {
                if (empty($attendance['time_out'])) {
                    // Time out
                    $update_stmt = $conn->prepare("UPDATE attendance_records SET time_out = ? WHERE id = ?");
                    if ($update_stmt->execute([$now, $attendance['id']])) {
                        echo json_encode(['success' => true, 'message' => "Time out recorded for " . $staff['First_Name'] . " " . $staff['Last_Name']]);
                    } else {
                        echo json_encode(['success' => false, 'message' => "Failed to record time out"]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => "Already completed attendance for today"]);
                }
            } else {
                // Time in
                $insert_stmt = $conn->prepare("INSERT INTO attendance_records (user_id, user_type, attendance_type, check_in_time, location) VALUES (?, 'Staff', 'work_shift', ?, 'Main Office')");
                if ($insert_stmt->execute([$staff_id, $now])) {
                    echo json_encode(['success' => true, 'message' => "Time in recorded for " . $staff['First_Name'] . " " . $staff['Last_Name']]);
                } else {
                    echo json_encode(['success' => false, 'message' => "Failed to record time in"]);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => "Invalid staff QR code"]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => "Invalid QR code format"]);
    }
    exit;
}

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$staff_filter = $_GET['staff'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build the query with filters
$where_conditions = ["DATE(ar.check_in_time) = ?"];
$params = [$date_filter];

if ($staff_filter) {
    $where_conditions[] = "u.UserID = ?";
    $params[] = $staff_filter;
}

if ($status_filter) {
    if ($status_filter === 'present') {
        $where_conditions[] = "ar.check_in_time IS NOT NULL";
    } elseif ($status_filter === 'absent') {
        $where_conditions[] = "ar.check_in_time IS NULL";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch attendance records
$stmt = $conn->prepare("
    SELECT 
        ar.id,
        u.UserID as user_id,
        u.First_Name,
        u.Last_Name,
        ar.check_in_time,
        ar.time_out,
        TIMESTAMPDIFF(HOUR, ar.check_in_time, COALESCE(ar.time_out, NOW())) as hours_worked
    FROM users u
    LEFT JOIN attendance_records ar ON u.UserID = ar.user_id 
        AND DATE(ar.check_in_time) = ?
    WHERE u.Role = 'Staff' AND u.IsActive = 1
    ORDER BY ar.check_in_time DESC
");
$stmt->execute([$date_filter]);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active staff for filter dropdown
$staff_stmt = $conn->prepare("SELECT UserID, First_Name, Last_Name FROM users WHERE Role = 'Staff' AND IsActive = 1 ORDER BY First_Name, Last_Name");
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Staff Attendance | Fitness Academy</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="../assets/js/auto-logout.js" defer></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: #111827;
            color: white;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .sidebar-menu-header {
            padding: 0 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.75rem;
            margin-top: 1.25rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid #ff6b6b;
        }

        .sidebar a i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .user-profile {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            margin-top: auto;
            background: rgba(0, 0, 0, 0.2);
        }

        .user-profile img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            margin-right: 0.75rem;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: white;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Main Content Styles */
        .main-wrapper {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            background: #f3f4f6;
        }

        .header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }

        .date {
            font-size: 1rem;
            color: #6b7280;
        }

        /* QR Scanner Section */
        .qr-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .qr-section h3 {
            margin: 0 0 1rem 0;
            font-size: 1.25rem;
            color: #111827;
        }

        .qr-section p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        #qr-reader {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Filters Section */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filter-item label {
            font-weight: 500;
            color: #374151;
            min-width: 60px;
        }

        .filter-item select,
        .filter-item input {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #374151;
            min-width: 200px;
            background: white;
        }

        .filter-item select:focus,
        .filter-item input:focus {
            outline: none;
            border-color: #60a5fa;
            ring: 2px solid #60a5fa;
        }

        /* Table Styles */
        .attendance-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-collapse: collapse;
            overflow: hidden;
        }

        .attendance-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        .attendance-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }

        .attendance-table tr:last-child td {
            border-bottom: none;
        }

        .attendance-table tr:hover {
            background: #f9fafb;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-present {
            background: #dcfce7;
            color: #15803d;
        }

        .status-absent {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* QR Button Styles */
        .qr-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #f3f4f6;
            color: #4b5563;
            margin-left: 0.75rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .qr-btn:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .qr-btn i {
            font-size: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-wrapper {
                margin-left: 0;
                padding: 1rem;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .filter-item {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .filter-item label {
                min-width: auto;
            }

            .filter-item select,
            .filter-item input {
                min-width: auto;
                width: 100%;
            }

            .attendance-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Fitness Academy</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="sidebar-menu-header">Dashboard</div>
            <a href="staff_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>
            <div class="sidebar-menu-header">Attendance</div>
            <a href="staff_attendance.php" class="active">
                <i class="fas fa-user-check"></i>
                <span>Attendance</span>
            </a>
            <div class="sidebar-menu-header">Members</div>
            <a href="staff_dashboard.php#all_members">
                <i class="fas fa-users"></i>
                <span>All Members</span>
            </a>
            <div class="sidebar-menu-header">POS</div>
            <a href="staff_pos.php">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>
            <div class="sidebar-menu-header">Account</div>
            <a href="staff_settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
        <div class="user-profile">
            <img src="../assets/images/avatar.jpg" alt="Staff" onerror="this.src='../assets/images/fa_logo.png'">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></div>
                <div class="user-role">Staff</div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="header">
            <h1>Staff Attendance</h1>
            <div class="date"><?= date('F d, Y') ?></div>
        </div>

        <!-- QR Scanner Section -->
        <div class="qr-section">
            <h3>QR Code Scanner</h3>
            <p>Scan staff QR code to record attendance</p>
            <div id="qr-reader"></div>
            <div id="qr-reader-results"></div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-item">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" value="<?= $date_filter ?>" onchange="applyFilters()">
            </div>
            <div class="filter-item">
                <label for="staff">Staff:</label>
                <select id="staff" name="staff" onchange="applyFilters()">
                    <option value="">All Staff</option>
                    <?php foreach ($staff_list as $staff): ?>
                        <option value="<?= $staff['UserID'] ?>" <?= $staff_filter == $staff['UserID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($staff['First_Name'] . ' ' . $staff['Last_Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label for="status">Status:</label>
                <select id="status" name="status" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="present" <?= $status_filter === 'present' ? 'selected' : '' ?>>Present</option>
                    <option value="absent" <?= $status_filter === 'absent' ? 'selected' : '' ?>>Absent</option>
                </select>
            </div>
        </div>

        <!-- Attendance Table -->
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Hours Worked</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance_records)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No attendance records found for the selected date.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($record['First_Name'] . ' ' . $record['Last_Name']) ?>
                                <a href="generate_staff_qr.php?id=<?= $record['user_id'] ?>" target="_blank" class="qr-btn" title="Generate QR Code">
                                    <i class="fas fa-qrcode"></i>
                                </a>
                            </td>
                            <td><?= $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-' ?></td>
                            <td><?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-' ?></td>
                            <td><?= $record['hours_worked'] ?? '0' ?> hours</td>
                            <td>
                                <?php if ($record['check_in_time']): ?>
                                    <span class="status-badge status-present">Present</span>
                                <?php else: ?>
                                    <span class="status-badge status-absent">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        // Initialize QR Scanner
        function onScanSuccess(decodedText, decodedResult) {
            // Send QR code to server via AJAX
            fetch('staff_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'qr=' + encodeURIComponent(decodedText)
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        // Reload the page to show updated attendance
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error processing QR code');
                });
        }

        var html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader", {
                fps: 10,
                qrbox: 250
            });
        html5QrcodeScanner.render(onScanSuccess);

        // Filter functionality
        function applyFilters() {
            const date = document.getElementById('date').value;
            const staff = document.getElementById('staff').value;
            const status = document.getElementById('status').value;

            let url = `staff_attendance.php?date=${date}`;
            if (staff) url += `&staff=${staff}`;
            if (status) url += `&status=${status}`;

            window.location.href = url;
        }
    </script>
</body>

</html>