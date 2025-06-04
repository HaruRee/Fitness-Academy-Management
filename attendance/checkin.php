<?php
// Standalone QR Scanner for Attendance Check-in
session_start();
require_once '../config/database.php';

// We still require a session to identify the scanner, but this is now a standalone kiosk
// so we check if there's a kiosk session or create one if it doesn't exist
if (!isset($_SESSION['scanner_id'])) {
    $_SESSION['scanner_id'] = 'check_in_kiosk_' . uniqid();
    $_SESSION['scanner_type'] = 'check_in';
    $_SESSION['scanner_name'] = 'Entrance Scanner';
    $_SESSION['scanner_location'] = 'Main Entrance';
    $_SESSION['kiosk_mode'] = true; // Enable kiosk mode for API access
}

$scanner_id = $_SESSION['scanner_id'];
$scanner_type = $_SESSION['scanner_type'];
$scanner_name = $_SESSION['scanner_name'];
$scanner_location = $_SESSION['scanner_location'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYM ENTRANCE - Check-in Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        :root {
            --primary-color: #d62328; /* Red gym branding */
            --primary-dark: #aa1c20;
            --secondary-color: #333;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-color: #f9f9f9;
            --dark-color: #111827;
            --gray-color: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #000;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: white;
        }

        .header {
            background-color: var(--primary-color);
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .header h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header h2 {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.5rem;
            font-weight: 400;
            margin: 5px 0 0;
        }
        
        .gym-logo {
            margin-bottom: 10px;
            max-height: 60px;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .scanner-header {
            margin-bottom: 2rem;
        }

        .scanner-header h2 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .scanner-header p {
            color: var(--gray-color);
            font-size: 1.1rem;
        }

        .scanner-container {
            margin: 2rem 0;
            position: relative;
        }

        #scanner {
            width: 100%;
            max-width: 500px;
            height: 350px;
            border: 4px solid var(--primary-color);
            border-radius: 20px;
            margin: 0 auto;
            display: block;
            background: #f8f9fa;
        }

        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--gray-color);
            font-size: 1.2rem;
            pointer-events: none;
        }

        .controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .btn {
            background: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(30, 64, 175, 0.3);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-success {
            background: var(--success-color);
        }

        .btn-success:hover {
            background: #059669;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-danger {
            background: var(--danger-color);
        }

        .btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        .manual-entry {
            margin-top: 2rem;
            padding: 2rem;
            background: rgba(243, 244, 246, 0.8);
            border-radius: 15px;
            border: 2px dashed var(--gray-color);
        }

        .manual-entry h3 {
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        .manual-entry input {
            width: 100%;
            max-width: 400px;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            text-align: center;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
            transition: border-color 0.3s;
        }

        .manual-entry input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .result {
            margin: 2rem 0;
            padding: 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            font-size: 1.1rem;
            display: none;
        }

        .result.show {
            display: block;
        }

        .result.success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 2px solid rgba(16, 185, 129, 0.3);
        }

        .result.error {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 2px solid rgba(239, 68, 68, 0.3);
        }

        .result.loading {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
            border: 2px solid rgba(59, 130, 246, 0.3);
        }

        .recent-checkins {
            margin-top: 3rem;
            text-align: left;
        }

        .recent-checkins h3 {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .checkin-item {
            background: rgba(243, 244, 246, 0.8);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--success-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .checkin-info h4 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .checkin-info p {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .checkin-time {
            color: var(--primary-color);
            font-weight: 600;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-indicator.ready {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .status-indicator.scanning {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }

        .status-indicator.error {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 1.5rem;
            }

            .controls {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }

            #scanner {
                height: 250px;
            }
        }
    </style>
</head>

<body>    <div class="header">
        <img src="/gym1/assets/images/fa_logo.png" alt="Gym Logo" class="gym-logo" onerror="console.log('Logo failed to load')">
        <h1>GYM ENTRANCE</h1>
        <h2>CHECK-IN SCANNER</h2>
    </div>

    <div class="container">
        <div class="scanner-header">
            <h2><i class="fas fa-sign-in-alt"></i> SCAN YOUR QR CODE</h2>
            <p>Please scan your membership QR code to enter the facility</p>
        </div>

        <div class="scanner-container">
            <div id="scanner"></div>
            <div class="scanner-overlay" id="scannerOverlay">
                <i class="fas fa-camera"></i> Click "Start Scanner" to begin
            </div>
        </div>

        <div class="status-indicator ready" id="statusIndicator">
            <i class="fas fa-circle"></i> Ready to scan
        </div>

        <div class="controls">
            <button class="btn" id="startBtn" onclick="startScanner()">
                <i class="fas fa-play"></i> Start Scanner
            </button>
            <button class="btn btn-danger" id="stopBtn" onclick="stopScanner()" disabled>
                <i class="fas fa-stop"></i> Stop Scanner
            </button>
        </div>

        <div class="result" id="result"></div>

        <div class="recent-checkins">
            <h3><i class="fas fa-history"></i> Recent Check-ins</h3>
            <div id="recentCheckins">
                <div class="checkin-item">
                    <div class="checkin-info">
                        <p><i class="fas fa-spinner fa-spin"></i> Loading recent check-ins...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;        document.addEventListener('DOMContentLoaded', function() {
            loadRecentCheckins();
            // Auto-start scanner for kiosk mode
            setTimeout(() => {
                startScanner();
            }, 1000);
        });

        function updateStatus(message, type = 'ready') {
            const indicator = document.getElementById('statusIndicator');
            const icon = type === 'ready' ? 'fas fa-circle' : 
                        type === 'scanning' ? 'fas fa-spinner fa-spin' : 'fas fa-exclamation-circle';
            
            indicator.className = `status-indicator ${type}`;
            indicator.innerHTML = `<i class="${icon}"></i> ${message}`;
        }

        function showResult(message, type = 'loading') {
            const result = document.getElementById('result');
            result.className = `result ${type} show`;
            
            const icon = type === 'success' ? 'fas fa-check-circle' : 
                        type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-spinner fa-spin';
            
            result.innerHTML = `<i class="${icon}"></i> ${message}`;
            
            if (type !== 'loading') {
                setTimeout(() => {
                    result.classList.remove('show');
                }, 5000);
            }
        }

        function startScanner() {
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            const overlay = document.getElementById('scannerOverlay');
            
            startBtn.disabled = true;
            stopBtn.disabled = false;
            overlay.style.display = 'none';
            
            updateStatus('Starting camera...', 'scanning');
            
            html5QrcodeScanner = new Html5Qrcode("scanner");
            
            const config = {
                fps: 10,
                qrbox: { width: 300, height: 300 },
                aspectRatio: 1.0
            };
            
            html5QrcodeScanner.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanFailure
            ).then(() => {
                isScanning = true;
                updateStatus('Scanning for QR codes...', 'scanning');
            }).catch(err => {
                console.error('Scanner start error:', err);
                updateStatus('Camera error - try manual entry', 'error');
                startBtn.disabled = false;
                stopBtn.disabled = true;
                overlay.style.display = 'block';
                overlay.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Camera not available';
            });
        }

        function stopScanner() {
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            const overlay = document.getElementById('scannerOverlay');
            
            if (html5QrcodeScanner && isScanning) {
                html5QrcodeScanner.stop().then(() => {
                    isScanning = false;
                    startBtn.disabled = false;
                    stopBtn.disabled = true;
                    overlay.style.display = 'block';
                    overlay.innerHTML = '<i class="fas fa-camera"></i> Click "Start Scanner" to begin';
                    updateStatus('Scanner stopped', 'ready');
                }).catch(err => {
                    console.error('Scanner stop error:', err);
                });
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            if (decodedText) {
                stopScanner();
                processAttendance(decodedText);
            }
        }

        function onScanFailure(error) {
            // Silent fail - don't log every scan attempt
        }

        function processManualQR() {
            const manualInput = document.getElementById('manualQR');
            const qrCode = manualInput.value.trim();
            
            if (!qrCode) {
                showResult('Please enter a QR code', 'error');
                return;
            }
            
            processAttendance(qrCode);
            manualInput.value = '';
        }        async function processAttendance(qrCode) {
            showResult('Processing check-in...', 'loading');
            
            try {
                const response = await fetch('process_checkin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        qr_code: qrCode,
                        action: 'checkin',
                        user_agent: navigator.userAgent,
                        timestamp: Date.now()
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showResult(`✅ Check-in successful: ${data.user_name}`, 'success');
                    loadRecentCheckins(); // Refresh the recent check-ins list
                } else {
                    showResult(`❌ ${data.message}`, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showResult('❌ Network error. Please try again.', 'error');
            }
        }
          async function loadRecentCheckins() {
            try {
                const response = await fetch('get_recent_checkins.php');
                const data = await response.json();
                
                const container = document.getElementById('recentCheckins');
                
                if (data.success && data.checkins && data.checkins.length > 0) {
                    let html = '';
                      data.checkins.forEach(checkin => {
                        html += `
                            <div class="checkin-item">
                                <div class="checkin-info">
                                    <h4>${checkin.user_name}</h4>
                                    <p>Member #${checkin.user_id} • ${checkin.formatted_time}</p>
                                </div>
                                <div class="checkin-time">
                                    <i class="fas fa-sign-in-alt"></i> IN
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `
                        <div class="checkin-item">
                            <div class="checkin-info">
                                <p><i class="fas fa-info-circle"></i> No recent check-ins to display</p>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading recent check-ins:', error);
                document.getElementById('recentCheckins').innerHTML = `
                    <div class="checkin-item">
                        <div class="checkin-info">
                            <p><i class="fas fa-exclamation-triangle"></i> Error loading recent check-ins</p>
                        </div>
                    </div>
                `;
            }
        }

        // Handle manual entry with Enter key
        document.getElementById('manualQR').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                processManualQR();
            }
        });

        // Auto-refresh recent check-ins every 30 seconds
        setInterval(loadRecentCheckins, 30000);
    </script>
</body>

</html>
