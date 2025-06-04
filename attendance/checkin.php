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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>GYM ENTRANCE - Check-in Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="../assets/js/success-sound.js"></script>    <style>
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
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: white;
            overflow-x: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 1rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(214, 35, 40, 0.3);
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }

        .header h1 {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .header h2 {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 400;
            margin: 5px 0 0;        }.container {
            max-width: 450px;
            margin: 1rem auto;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
        }        .scanner-header {
            margin-bottom: 0.8rem;
        }

        .scanner-header h2 {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }

        .scanner-header p {
            color: var(--gray-color);
            font-size: 0.85rem;
        }

        .scanner-container {
            margin: 1rem 0;
            position: relative;
        }        #scanner {
            width: 100%;
            max-width: 320px;
            height: 220px;
            border: 2px solid var(--primary-color);
            border-radius: 12px;
            margin: 0 auto;
            display: block;
            background: #1a1a1a;
        }

        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--gray-color);
            font-size: 1rem;
            pointer-events: none;
            text-align: center;
        }

        .controls {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 1rem 0;
            flex-wrap: wrap;
        }        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(214, 35, 40, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(214, 35, 40, 0.4);
        }

        .btn:disabled {
            background: var(--gray-color);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
        }

        .status-indicator {
            padding: 0.7rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
        }

        .status-indicator.ready {
            color: var(--success-color);
        }

        .status-indicator.scanning {
            color: var(--warning-color);
        }

        .status-indicator.error {
            color: var(--danger-color);
        }

        .result {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .result.show {
            opacity: 1;
            transform: translateY(0);
        }

        .result.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .result.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .result.loading {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }        .recent-checkins {
            margin-top: 1.2rem;
        }

        .recent-checkins h3 {
            color: white;
            font-size: 1rem;
            margin-bottom: 0.6rem;
            text-align: left;
        }        #recentCheckins {
            max-height: 180px;
            overflow-y: auto;
            border-radius: 8px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        .checkin-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem;
            border-bottom: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.02);
        }

        .checkin-item:last-child {
            border-bottom: none;
        }        .checkin-info h4 {
            color: white;
            font-size: 0.9rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkin-info p {
            color: var(--gray-color);
            font-size: 0.8rem;
            margin: 0.2rem 0 0;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .role-member {
            background-color: var(--primary-color);
            color: white;
        }
        
        .role-coach {
            background-color: var(--success-color);
            color: white;
        }
        
        .role-staff {
            background-color: var(--warning-color);
            color: white;
        }
        
        .role-admin {
            background-color: #6366f1;
            color: white;
        }

        .checkin-time {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                margin: 0.5rem;
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            #scanner {
                height: 200px;
            }
            
            .btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.8rem;
            }        }
    </style>
</head>

<body>    <div class="header">
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
    </div>    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        let scanTimeout = false; // Prevent rapid scanning
        
        document.addEventListener('DOMContentLoaded', function() {
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
        }        function onScanSuccess(decodedText, decodedResult) {
            if (decodedText && !scanTimeout) {
                // Set timeout to prevent rapid scanning
                scanTimeout = true;
                updateStatus('Processing scan...', 'scanning');
                
                processAttendance(decodedText);
                
                // Reset timeout after 5 seconds to allow next scan
                setTimeout(() => {
                    scanTimeout = false;
                    if (isScanning) {
                        updateStatus('Scanning for QR codes...', 'scanning');
                    }
                }, 5000);
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
                    // Play success sound
                    if (window.successSound) {
                        window.successSound.playSuccess();
                    }
                    
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
                    let html = '';                      data.checkins.forEach(checkin => {
                        // Determine role styling
                        const role = checkin.role ? checkin.role.toLowerCase() : 'member';
                        const roleClass = `role-${role}`;
                        const roleDisplay = checkin.role || 'Member';
                        
                        html += `
                            <div class="checkin-item">
                                <div class="checkin-info">
                                    <h4>
                                        ${checkin.user_name}
                                        <span class="role-badge ${roleClass}">${roleDisplay}</span>
                                    </h4>
                                    <p>ID #${checkin.user_id} • ${checkin.formatted_time}</p>
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
