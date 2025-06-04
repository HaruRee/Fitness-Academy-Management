<?php
// QR Scanner for Attendance Check-in
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['first_name'] ?? '';
if (isset($_SESSION['last_name'])) {
    $user_name .= ' ' . $_SESSION['last_name'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #ff6b6b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-color: #f3f4f6;
            --dark-color: #111827;
            --gray-color: #6b7280;
            --sidebar-width: 280px;
            --header-height: 72px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: var(--dark-color);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
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
            border-left: 4px solid var(--secondary-color);
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
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }

        /* Main Content Styles */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-actions {
            display: flex;
            align-items: center;
        }

        .notification-bell {
            background: #f3f4f6;
            height: 40px;
            width: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-right: 1rem;
            position: relative;
        }

        .notification-bell i {
            color: var(--gray-color);
            font-size: 1.1rem;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Update table styles to be more compact */
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th,
        td {
            padding: 0.6rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        th {
            font-weight: 600;
            color: #6b7280;
            background: #f9fafb;
            font-size: 0.85rem;
        }

        tr:hover {
            background: #f9fafb;
        }

        .badge {
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-member {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .badge-coach {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-gym {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .badge-class {
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
        }

        /* Make the location/session column wrap if needed */
        td:last-child {
            white-space: normal;
            max-width: 200px;
        }

        /* Adjust recent check-ins section spacing */
        .recent-checkins h3 {
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            color: #374151;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .scanner-container {
            margin: 30px 0;
        }

        #scanner {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border: 3px solid #667eea;
            border-radius: 15px;
            margin: 20px auto;
            display: block;
        }

        .manual-entry {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .manual-entry input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin: 10px 0;
            text-align: center;
            font-family: monospace;
        }

        .btn {
            background: #667eea;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            background: #d1ecf1;
            color: #0c5460;
        }

        .recent-checkins {
            margin-top: 30px;
            text-align: left;
        }

        .checkin-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .checkin-info {
            flex: 1;
        }

        .checkin-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-right: 8px;
            background: #667eea;
            color: white;
        }

        .checkin-time {
            color: #666;
            font-size: 0.9em;
        }

        .checkin-location {
            color: #444;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .scanner-instructions {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            color: #004085;
        }

        /* You can keep your .container and other styles below this line */
        .container {
            max-width: 700px;
            margin: 2rem auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .scanner-container {
            margin: 30px 0;
        }

        #scanner {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border: 3px solid #667eea;
            border-radius: 15px;
            margin: 20px auto;
            display: block;
        }

        .manual-entry {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .manual-entry input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin: 10px 0;
            text-align: center;
            font-family: monospace;
        }

        .btn {
            background: #667eea;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            background: #d1ecf1;
            color: #0c5460;
        }

        .recent-checkins {
            margin-top: 30px;
            text-align: left;
        }

        .checkin-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .checkin-info {
            flex: 1;
        }

        .checkin-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-right: 8px;
            background: #667eea;
            color: white;
        }

        .checkin-time {
            color: #666;
            font-size: 0.9em;
        }

        .checkin-location {
            color: #444;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .scanner-instructions {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            color: #004085;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h2,
            .sidebar a span,
            .user-info {
                display: none;
            }

            .sidebar a i {
                margin-right: 0;
            }

            .sidebar a {
                justify-content: center;
            }

            .user-profile {
                justify-content: center;
            }

            .main-wrapper {
                margin-left: 80px;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 1rem;
            }

            .header-search {
                display: none;
            }
        }
    </style>
    <!-- Include HTML5-QRcode library with fallback -->
    <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        // Fallback if primary CDN fails
        if (typeof Html5QrcodeScanner === 'undefined') {
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"><\/script>');
        }
    </script>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Fitness Academy</h2>
        </div>

        <nav class="sidebar-menu">
            <div class="sidebar-menu-header">Dashboard</div>
            <a href="admin_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>

            <div class="sidebar-menu-header">Management</div>
            <a href="manage_users.php">
                <i class="fas fa-users-cog"></i>
                <span>Manage Users</span>
            </a>
            <a href="member_list.php">
                <i class="fas fa-users"></i>
                <span>Member List</span>
            </a>
            <a href="coach_applications.php">
                <i class="fas fa-user-tie"></i>
                <span>Coach Applications</span>
            </a>
            <a href="admin_video_approval.php">
                <i class="fas fa-video"></i>
                <span>Video Approval</span>
            </a>
            <a href="track_payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payment Status</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>

            <div class="sidebar-menu-header">Attendance</div>
            <a href="qr_scanner.php" class="active">
                <i class="fas fa-camera"></i>
                <span>QR Scanner</span>
            </a>
            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
            </a>

            <div class="sidebar-menu-header">Point of Sale</div>
            <a href="pos_system.php">
                <i class="fas fa-cash-register"></i>
                <span>POS System</span>
            </a>

            <div class="sidebar-menu-header">Reports</div>
            <a href="report_generation.php">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="transaction_history.php">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>            <a href="audit_trail.php">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
            </a>

            <div class="sidebar-menu-header">Database</div>
            <a href="database_management.php">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>

            <div class="sidebar-menu-header">Account</div>
            <a href="admin_settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
        <div class="user-profile">
            <img src="../assets/images/avatar.jpg" alt="Admin" onerror="this.src='../assets/images/fa_logo.png'">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user_name ?? ($_SESSION['user_name'] ?? 'Admin')) ?></div>
                <div class="user-role"><?= htmlspecialchars($user_role ?? 'Administrator') ?></div>
            </div>
        </div>
    </aside>

        <!-- Main Content -->
        <div class="container">
            <div class="header">
                <h1>📱 QR Attendance Scanner</h1>
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
                    <span style="color: #666;"><?php echo htmlspecialchars($user_role); ?></span>
                </div>
            </div>

            <div class="scanner-instructions">
                <strong>Instructions:</strong><br>
                1. Allow camera access when prompted<br>
                2. Point your camera at the QR code<br>
                3. Wait for automatic scanning<br>
                4. Or enter QR code manually below
            </div>

            <div class="scanner-container">
                <div id="qr-reader" style="width: 100%; max-width: 400px; margin: 20px auto;"></div>
                <button id="startScanner" class="btn" onclick="startScanner()">Start Camera Scanner</button>
                <button id="stopScanner" class="btn" onclick="stopScanner()" style="display: none;">Stop Scanner</button>
            </div>

            <div class="manual-entry">
                <h3>Manual QR Code Entry</h3>
                <input type="text" id="manualQR" placeholder="Enter QR code manually..." maxlength="50">
                <br>
                <button class="btn" onclick="processManualQR()">Check In</button>
            </div>

            <div id="result"></div>

            <div class="recent-checkins">
                <h3>Recent Check-ins</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Name</th>
                                <th>User Type</th>
                                <th>Check-in Type</th>
                            </tr>
                        </thead>
                        <tbody id="recentCheckins">
                            <tr>
                                <td colspan="4" style="text-align: center;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        let html5QrcodeScanner = null;
        let scanning = false;

        // Initialize everything when the document loads
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentCheckins(); // Load check-ins immediately
            // Refresh check-ins every 30 seconds
            setInterval(loadRecentCheckins, 30000);
        });

        function showResult(message, type = 'loading') {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = `<div class="result ${type}">${message}</div>`;
        }

        function startScanner() {
            try {
                showResult('Starting camera scanner...', 'loading');

                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader", {
                        fps: 10,
                        qrbox: {
                            width: 250,
                            height: 250
                        },
                        aspectRatio: 1.0,
                        rememberLastUsedCamera: true
                    },
                    /* verbose= */
                    false
                );

                html5QrcodeScanner.render(onScanSuccess, onScanFailure);

                document.getElementById('startScanner').style.display = 'none';
                document.getElementById('stopScanner').style.display = 'inline-block';
                scanning = true;

                showResult('Camera ready! Point at QR code to scan...', 'loading');

            } catch (error) {
                showResult('Failed to start camera scanner. Please use manual entry.', 'error');
                console.error('Scanner error:', error);
            }
        }

        function stopScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear().catch(error => {
                    console.error("Failed to clear scanner:", error);
                });
                html5QrcodeScanner = null;
            }

            scanning = false;
            document.getElementById('startScanner').style.display = 'inline-block';
            document.getElementById('stopScanner').style.display = 'none';
            document.getElementById('qr-reader').innerHTML = '';

            showResult('Scanner stopped.', 'loading');
        }

        function onScanSuccess(decodedText, decodedResult) {
            console.log(`QR Code detected: ${decodedText}`);
            showResult('QR Code detected! Processing...', 'loading');

            // Stop scanner after successful scan
            stopScanner();

            // Process the scanned QR code
            processAttendance(decodedText);
        }

        function onScanFailure(error) {
            // This callback is called when QR code scan fails
            // We don't need to show errors for failed scans as they're common
            console.log(`QR scan error: ${error}`);
        }

        function processManualQR() {
            const qrCode = document.getElementById('manualQR').value.trim();
            if (!qrCode) {
                showResult('Please enter a QR code', 'error');
                return;
            }

            processAttendance(qrCode);
        }

        async function processAttendance(qrCode) {
            showResult('Processing check-in...', 'loading');

            try {
                const response = await fetch('../includes/process_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        qr_code: qrCode,
                        user_agent: navigator.userAgent,
                        timestamp: new Date().toISOString()
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    throw new Error('Invalid response from server');
                }

                if (data.success) {
                    showResult(`✅ ${data.message}`, 'success');
                    document.getElementById('manualQR').value = '';
                    loadRecentCheckins();
                } else {
                    showResult(`❌ ${data.message}`, 'error');
                }

            } catch (error) {
                showResult(`Error: ${error.message}. Please try again.`, 'error');
                console.error('Attendance error:', error);
            }
        }

        async function loadRecentCheckins() {
            try {
                console.log('Fetching recent check-ins...');
                const response = await fetch('../includes/get_recent_attendance.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Received data:', data);
                const container = document.getElementById('recentCheckins');

                if (!container) {
                    console.error('Could not find recentCheckins element');
                    return;
                }

                if (data.success && data.records && data.records.length > 0) {
                    container.innerHTML = data.records.map(record => {
                        const checkInTime = new Date(record.check_in_time);
                        const timeString = checkInTime.toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false
                        });

                        const userTypeBadgeClass = record.user_type?.toLowerCase() === 'member' ? 'badge-member' : 'badge-coach';
                        const attendanceTypeBadgeClass = record.attendance_type === 'gym_entry' ? 'badge-gym' : 'badge-class';

                        return `
                            <tr>
                                <td>${timeString}</td>
                                <td>${record.user_name || 'Unknown'}</td>
                                <td><span class="badge ${userTypeBadgeClass}">${record.user_type || 'Unknown'}</span></td>
                                <td><span class="badge ${attendanceTypeBadgeClass}">${(record.attendance_type || 'unknown').replace('_', ' ').toUpperCase()}</span></td>
                            </tr>`;
                    }).join('');
                } else {
                    container.innerHTML = '<tr><td colspan="4" style="text-align: center;">No recent check-ins found.</td></tr>';
                }

            } catch (error) {
                console.error('Recent check-ins error:', error);
                const container = document.getElementById('recentCheckins');
                if (container) {
                    container.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #ef4444;">Error loading recent check-ins: ${error.message}</td></tr>`;
                }
            }
        }

        // Handle enter key in manual input
        document.getElementById('manualQR').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                processManualQR();
            }
        });
    </script>
</body>

</html>