<?php
// Test page for the new attendance system
session_start();
require_once '../config/database.php';

// Mock a session for testing (remove in production)
if (!isset($_SESSION['user_id'])) {
    // For testing only - you can set a real user ID here
    echo "<!DOCTYPE html><html><head><title>Attendance System Test</title></head><body>";
    echo "<h1>Attendance System Test</h1>";
    echo "<p>Please log in first to test the attendance system.</p>";
    echo "<p><a href='../includes/login.php'>Login</a></p>";
    echo "</body></html>";
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
        .success { color: green; }
        .error { color: red; }
        button { padding: 10px 15px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Attendance System Test Page</h1>
    <p>User ID: <?php echo htmlspecialchars($user_id); ?></p>
    <p>Session Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Unknown'); ?></p>

    <div class="test-section">
        <h3>Static QR Code Generation</h3>
        <button onclick="testQRGeneration()">Test QR Generation</button>
        <div id="qr-result"></div>
    </div>

    <div class="test-section">
        <h3>Check-in Process</h3>
        <button onclick="testCheckin()">Test Check-in</button>
        <div id="checkin-result"></div>
    </div>

    <div class="test-section">
        <h3>Check-out Process</h3>
        <button onclick="testCheckout()">Test Check-out</button>
        <div id="checkout-result"></div>
    </div>

    <div class="test-section">
        <h3>Recent Check-ins</h3>
        <button onclick="getRecentCheckins()">Get Recent Check-ins</button>
        <div id="recent-checkins"></div>
    </div>

    <div class="test-section">
        <h3>Recent Check-outs</h3>
        <button onclick="getRecentCheckouts()">Get Recent Check-outs</button>
        <div id="recent-checkouts"></div>
    </div>

    <div class="test-section">
        <h3>Navigation Links</h3>
        <p><a href="checkin.php">Check-in Scanner</a></p>
        <p><a href="checkout.php">Check-out Scanner</a></p>
        <p><a href="../includes/member_dashboard.php">Member Dashboard</a></p>
        <p><a href="../includes/coach_dashboard.php">Coach Dashboard</a></p>
    </div>

    <script>
        function testQRGeneration() {
            fetch('generate_static_qr.php')
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('qr-result');
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="success">QR Generation successful!</div>
                            <p>QR Data: ${data.qr_data}</p>
                            <img src="${data.qr_code_url}" alt="QR Code" style="max-width: 200px;">
                        `;
                    } else {
                        resultDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('qr-result').innerHTML = `<div class="error">Error: ${error.message}</div>`;
                });
        }

        function testCheckin() {
            const formData = new FormData();
            formData.append('qr_data', '<?php echo $user_id; ?>');
            
            fetch('process_checkin.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('checkin-result');
                    if (data.success) {
                        resultDiv.innerHTML = `<div class="success">Check-in successful! ${data.message}</div>`;
                    } else {
                        resultDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('checkin-result').innerHTML = `<div class="error">Error: ${error.message}</div>`;
                });
        }

        function testCheckout() {
            const formData = new FormData();
            formData.append('qr_data', '<?php echo $user_id; ?>');
            
            fetch('process_checkout.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('checkout-result');
                    if (data.success) {
                        resultDiv.innerHTML = `<div class="success">Check-out successful! ${data.message}</div>`;
                    } else {
                        resultDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('checkout-result').innerHTML = `<div class="error">Error: ${error.message}</div>`;
                });
        }

        function getRecentCheckins() {
            fetch('get_recent_checkins.php')
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('recent-checkins');
                    if (data.success) {
                        let html = '<div class="success">Recent check-ins loaded!</div><ul>';
                        data.checkins.forEach(checkin => {
                            html += `<li>${checkin.first_name} ${checkin.last_name} - ${checkin.check_in_time}</li>`;
                        });
                        html += '</ul>';
                        resultDiv.innerHTML = html;
                    } else {
                        resultDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('recent-checkins').innerHTML = `<div class="error">Error: ${error.message}</div>`;
                });
        }

        function getRecentCheckouts() {
            fetch('get_recent_checkouts.php')
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('recent-checkouts');
                    if (data.success) {
                        let html = '<div class="success">Recent check-outs loaded!</div><ul>';
                        data.checkouts.forEach(checkout => {
                            html += `<li>${checkout.user_name} - ${checkout.formatted_time}</li>`;
                        });
                        html += '</ul>';
                        resultDiv.innerHTML = html;
                    } else {
                        resultDiv.innerHTML = `<div class="error">Error: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('recent-checkouts').innerHTML = `<div class="error">Error: ${error.message}</div>`;
                });
        }
    </script>
</body>
</html>
