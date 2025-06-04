<?php
// Standalone QR Scanner for Attendance Check-out
session_start();
require_once '../config/database.php';

// We still require a session to identify the scanner, but this is now a standalone kiosk
// so we check if there's a kiosk session or create one if it doesn't exist
if (!isset($_SESSION['scanner_id'])) {
    $_SESSION['scanner_id'] = 'check_out_kiosk_' . uniqid();
    $_SESSION['scanner_type'] = 'check_out';
    $_SESSION['scanner_name'] = 'Exit Scanner';
    $_SESSION['scanner_location'] = 'Main Exit';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>GYM EXIT - Check-out Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="../assets/js/success-sound.js"></script>
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

        body {            
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        .btn-back {
            background: var(--gray-color);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
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
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
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
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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
            background: rgba(220, 38, 38, 0.1);
            color: #991b1b;
            border: 2px solid rgba(220, 38, 38, 0.3);
        }

        .recent-checkouts {
            margin-top: 3rem;
            text-align: left;
        }

        .recent-checkouts h3 {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .checkout-item {
            background: rgba(243, 244, 246, 0.8);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .checkout-info h4 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .checkout-info p {
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .checkout-time {
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
            background: rgba(220, 38, 38, 0.1);
            color: #991b1b;
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

<body>      <div class="header">
        <img src="/gym1/assets/images/fa_logo.png" alt="Gym Logo" class="gym-logo" onerror="console.log('Logo failed to load')">
        <h1>GYM EXIT</h1>
        <h2>CHECK-OUT SCANNER</h2>
    </div>

    <div class="container">
        <div class="scanner-header">
            <h2><i class="fas fa-sign-out-alt"></i> SCAN YOUR QR CODE</h2>
            <p>Please scan your membership QR code to exit the facility</p>
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

        <div class="recent-checkouts">
            <h3><i class="fas fa-history"></i> Recent Check-outs</h3>
            <div id="recentCheckouts">
                <div class="checkout-item">
                    <div class="checkout-info">
                        <p><i class="fas fa-spinner fa-spin"></i> Loading recent check-outs...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        let scanTimeout = false; // Prevent rapid scanning
        
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentCheckouts();
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
                    console.error('Scanner stop error:', err);                });
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
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

        async function processAttendance(qrCode) {
            showResult('Processing check-out...', 'loading');
            
            try {
                const response = await fetch('process_checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        qr_code: qrCode,
                        action: 'checkout',
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
                    
                    showResult(`✅ Check-out successful: ${data.user_name}`, 'success');
                    loadRecentCheckouts(); // Refresh the recent check-outs list
                } else {
                    showResult(`❌ ${data.message}`, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showResult('❌ Network error. Please try again.', 'error');
            }
        }async function loadRecentCheckouts() {
            try {
                console.log('Loading recent checkouts...');
                const response = await fetch('get_recent_checkouts.php');
                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('API data received:', data);
                
                const container = document.getElementById('recentCheckouts');
                
                if (data.success && data.checkouts && data.checkouts.length > 0) {
                    console.log('Found', data.checkouts.length, 'checkouts');
                    let html = '';
                    
                    data.checkouts.forEach(checkout => {
                        html += `
                            <div class="checkout-item">
                                <div class="checkout-info">
                                    <h4>${checkout.user_name}</h4>
                                    <p>Member #${checkout.user_id} • ${checkout.formatted_time}</p>
                                </div>
                                <div class="checkout-time">
                                    <i class="fas fa-sign-out-alt"></i> OUT
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                } else {
                    console.log('No checkouts found or API failed:', data);
                    container.innerHTML = `
                        <div class="checkout-item">
                            <div class="checkout-info">
                                <p><i class="fas fa-info-circle"></i> No recent check-outs to display</p>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading recent check-outs:', error);
                document.getElementById('recentCheckouts').innerHTML = `
                    <div class="checkout-item">
                        <div class="checkout-info">
                            <p><i class="fas fa-exclamation-triangle"></i> Error loading recent check-outs</p>
                        </div>
                    </div>
                `;
            }
        }// Manual entry removed for kiosk mode

        // Auto-refresh recent check-outs every 30 seconds
        setInterval(loadRecentCheckouts, 30000);
    </script>
</body>

</html>
