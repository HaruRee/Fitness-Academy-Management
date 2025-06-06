<?php
// Combined QR Scanner for Attendance Check-in/Check-out with Toggle
session_start();
require_once '../config/database.php';

// Initialize scanner session for combined functionality
if (!isset($_SESSION['scanner_id'])) {
    $_SESSION['scanner_id'] = 'attendance_kiosk_' . uniqid();
    $_SESSION['scanner_type'] = 'combined';
    $_SESSION['scanner_name'] = 'Attendance Scanner';
    $_SESSION['scanner_location'] = 'Main Entrance/Exit';
    $_SESSION['kiosk_mode'] = true;
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
    <title>GYM ATTENDANCE - Check-in/Check-out Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="../assets/js/success-sound.js"></script>    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-light: #f8fafc;
            --bg-dark: #0f172a;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --checkin-gradient: linear-gradient(135deg, #10b981, #059669);
            --checkout-gradient: linear-gradient(135deg, #f59e0b, #d97706);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: white;
            overflow: hidden;
            position: relative;
        }

        /* Animated background particles */
        .bg-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Main container */
        .attendance-container {
            position: relative;
            z-index: 10;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .attendance-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .gym-logo {
            width: 60px;
            height: 60px;
            background: var(--checkin-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 24px;
            animation: pulse-glow 3s ease-in-out infinite;
        }

        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
            }
            50% {
                box-shadow: 0 0 40px rgba(16, 185, 129, 0.8);
            }
        }

        .title {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        /* Mode toggle */
        .mode-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .toggle-container {
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            padding: 6px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .toggle-switch {
            display: flex;
            position: relative;
            width: 200px;
            height: 50px;
            background: transparent;
            border-radius: 25px;
            overflow: hidden;
        }

        .toggle-option {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .toggle-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 50%;
            height: 100%;
            background: var(--checkin-gradient);
            border-radius: 25px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
            z-index: 1;
        }

        .toggle-container.checkout .toggle-slider {
            transform: translateX(100%);
            background: var(--checkout-gradient);
            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
        }

        .toggle-option.active {
            color: white;
        }

        .toggle-option:not(.active) {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Scanner section */
        .scanner-section {
            margin: 2rem 0;
        }

        .scanner-container {
            position: relative;
            margin: 1.5rem 0;
        }

        #scanner {
            width: 100%;
            height: 280px;
            border-radius: 16px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        #scanner:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.3);
        }

        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            pointer-events: none;
        }

        .scanner-overlay i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.6;
        }

        /* Controls */
        .controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(99, 102, 241, 0.4);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .btn.stop {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .btn.stop:hover {
            box-shadow: 0 12px 35px rgba(239, 68, 68, 0.4);
        }

        /* Manual input */
        .manual-input {
            margin: 1.5rem 0;
        }

        .input-group {
            position: relative;
        }

        .manual-input input {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .manual-input input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .manual-input input:focus {
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
        }

        .input-icon {
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
        }

        /* Status message */
        .status-message {
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin: 1.5rem 0;
            font-size: 0.95rem;
            font-weight: 500;
            text-align: center;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .status-message.loading {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(251, 146, 60, 0.2));
            border-color: rgba(245, 158, 11, 0.3);
            animation: loading-pulse 2s ease-in-out infinite;
        }

        .status-message.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(34, 197, 94, 0.2));
            border-color: rgba(16, 185, 129, 0.3);
            animation: success-bounce 0.5s ease-out;
        }

        .status-message.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(248, 113, 113, 0.2));
            border-color: rgba(239, 68, 68, 0.3);
            animation: error-shake 0.5s ease-out;
        }

        .status-message.ready {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        @keyframes loading-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.02); }
        }

        @keyframes success-bounce {
            0% { transform: scale(0.9); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes error-shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Recent activity */
        .recent-activity {
            margin-top: 2rem;
        }

        .activity-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .activity-list {
            max-height: 200px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
        }

        .activity-list::-webkit-scrollbar {
            width: 4px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .activity-item {
            background: rgba(255, 255, 255, 0.08);
            padding: 0.8rem 1rem;
            margin: 0.5rem 0;
            border-radius: 12px;
            font-size: 0.85rem;
            border-left: 4px solid var(--success-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .activity-item:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(5px);
        }

        .activity-item.checkout {
            border-left-color: var(--warning-color);
        }

        .activity-time {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.2rem;
        }

        /* Time display */
        .time-display {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Responsive design */
        @media (max-width: 640px) {
            .attendance-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .title {
                font-size: 1.5rem;
            }
            
            .toggle-switch {
                width: 180px;
                height: 45px;
            }
            
            #scanner {
                height: 240px;
            }
            
            .controls {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .attendance-container {
                padding: 1rem;
            }
            
            .toggle-switch {
                width: 160px;
                height: 40px;
            }
        }
    </style>
</head>

<body>
    <!-- Animated background particles -->
    <div class="bg-particles">
        <div class="particle" style="left: 10%; animation-delay: 0s; width: 4px; height: 4px;"></div>
        <div class="particle" style="left: 20%; animation-delay: 2s; width: 6px; height: 6px;"></div>
        <div class="particle" style="left: 30%; animation-delay: 4s; width: 3px; height: 3px;"></div>
        <div class="particle" style="left: 40%; animation-delay: 6s; width: 5px; height: 5px;"></div>
        <div class="particle" style="left: 50%; animation-delay: 8s; width: 4px; height: 4px;"></div>
        <div class="particle" style="left: 60%; animation-delay: 10s; width: 6px; height: 6px;"></div>
        <div class="particle" style="left: 70%; animation-delay: 12s; width: 3px; height: 3px;"></div>
        <div class="particle" style="left: 80%; animation-delay: 14s; width: 5px; height: 5px;"></div>
        <div class="particle" style="left: 90%; animation-delay: 16s; width: 4px; height: 4px;"></div>
    </div>

    <!-- Main container -->
    <div class="attendance-container">
        <!-- Time display -->
        <div class="time-display" id="currentTime">12:30</div>

        <!-- Header -->
        <div class="header">
            <div class="gym-logo">
                <i class="fas fa-dumbbell"></i>
            </div>
            <h1 class="title">Fitness Academy</h1>
            <p class="subtitle">Attendance Management System</p>
        </div>

        <!-- Mode toggle -->
        <div class="mode-toggle">
            <div class="toggle-container" id="toggleContainer">
                <div class="toggle-switch">
                    <div class="toggle-option active" data-mode="checkin">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>CHECK IN</span>
                    </div>
                    <div class="toggle-option" data-mode="checkout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>CHECK OUT</span>
                    </div>
                    <div class="toggle-slider"></div>
                </div>
            </div>
        </div>

        <!-- Scanner section -->
        <div class="scanner-section">
            <div class="scanner-container">
                <div id="scanner"></div>
                <div class="scanner-overlay" id="scannerOverlay">
                    <i class="fas fa-qrcode"></i>
                    <p>Position QR code within the frame</p>
                </div>
            </div>

            <div class="controls">
                <button id="startBtn" class="btn">
                    <i class="fas fa-play"></i>
                    <span>Start Scanner</span>
                </button>
                <button id="stopBtn" class="btn stop" style="display: none;">
                    <i class="fas fa-stop"></i>
                    <span>Stop Scanner</span>
                </button>
            </div>

            <div class="manual-input">
                <div class="input-group">
                    <input type="text" id="manualQR" placeholder="Enter QR code manually">
                    <i class="fas fa-keyboard input-icon"></i>
                </div>
            </div>

            <div id="statusMessage" class="status-message ready">
                <i class="fas fa-info-circle"></i>
                <span>Ready to scan QR codes for CHECK-IN</span>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="recent-activity">
            <div class="activity-header">
                <i class="fas fa-history"></i>
                <span>Recent Activity</span>
            </div>
            <div class="activity-list" id="recentActivity">
                <!-- Activity items will be loaded here -->
            </div>
        </div>
    </div>    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let html5QrcodeScanner = null;
            let isScanning = false;
            let currentMode = 'checkin'; // Default mode
            
            const toggleContainer = document.getElementById('toggleContainer');
            const toggleOptions = document.querySelectorAll('.toggle-option');
            const statusMessage = document.getElementById('statusMessage');
            const recentActivity = document.getElementById('recentActivity');
            const scannerOverlay = document.getElementById('scannerOverlay');
            
            // Update current time
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                document.getElementById('currentTime').textContent = timeString;
            }
            
            // Update time every minute
            updateTime();
            setInterval(updateTime, 60000);
            
            // Toggle mode change handler
            toggleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const mode = this.dataset.mode;
                    if (mode === currentMode) return;
                    
                    // Update current mode
                    currentMode = mode;
                    
                    // Update UI
                    toggleOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update toggle container class
                    if (mode === 'checkout') {
                        toggleContainer.classList.add('checkout');
                    } else {
                        toggleContainer.classList.remove('checkout');
                    }
                    
                    // Update status message
                    updateStatus(`Ready to scan QR codes for ${mode.toUpperCase()}`, 'ready');
                    
                    // If scanner is running, restart it to update the mode
                    if (isScanning) {
                        stopScanner();
                        setTimeout(() => startScanner(), 500);
                    }
                });
            });

            function updateStatus(message, type = 'ready') {
                const icons = {
                    ready: 'fas fa-info-circle',
                    loading: 'fas fa-spinner fa-spin',
                    success: 'fas fa-check-circle',
                    error: 'fas fa-exclamation-circle'
                };
                
                statusMessage.innerHTML = `
                    <i class="${icons[type]}"></i>
                    <span>${message}</span>
                `;
                statusMessage.className = `status-message ${type}`;
            }

            function showResult(message, type = 'loading') {
                updateStatus(message, type);
                
                if (type === 'success' || type === 'error') {
                    setTimeout(() => {
                        updateStatus(`Ready to scan QR codes for ${currentMode.toUpperCase()}`, 'ready');
                    }, 3000);
                }
            }

            function startScanner() {
                if (isScanning) return;
                
                // Hide overlay
                scannerOverlay.style.display = 'none';
                
                const config = {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0,
                    disableFlip: false,
                    videoConstraints: {
                        facingMode: "environment"
                    }
                };

                html5QrcodeScanner = new Html5Qrcode("scanner");
                
                html5QrcodeScanner.start(
                    { facingMode: "environment" },
                    config,
                    onScanSuccess,
                    onScanFailure
                ).then(() => {
                    isScanning = true;
                    document.getElementById('startBtn').style.display = 'none';
                    document.getElementById('stopBtn').style.display = 'flex';
                    updateStatus(`Scanning for ${currentMode.toUpperCase()}...`, 'loading');
                }).catch(err => {
                    console.error('Scanner start error:', err);
                    updateStatus('Failed to start camera. Please check permissions.', 'error');
                    scannerOverlay.style.display = 'block';
                });
            }

            function stopScanner() {
                if (!isScanning || !html5QrcodeScanner) return;
                
                html5QrcodeScanner.stop().then(() => {
                    html5QrcodeScanner.clear();
                    isScanning = false;
                    document.getElementById('startBtn').style.display = 'flex';
                    document.getElementById('stopBtn').style.display = 'none';
                    updateStatus(`Ready to scan QR codes for ${currentMode.toUpperCase()}`, 'ready');
                    scannerOverlay.style.display = 'block';
                }).catch(err => {
                    console.error('Scanner stop error:', err);
                    isScanning = false;
                    document.getElementById('startBtn').style.display = 'flex';
                    document.getElementById('stopBtn').style.display = 'none';
                    scannerOverlay.style.display = 'block';
                });
            }

            function onScanSuccess(decodedText, decodedResult) {
                if (!isScanning) return;
                
                stopScanner();
                processAttendance(decodedText);
            }

            function onScanFailure(error) {
                // Ignore scan failures - they're too frequent and noisy
            }

            function processManualQR() {
                const qrCode = document.getElementById('manualQR').value.trim();
                if (qrCode) {
                    document.getElementById('manualQR').value = '';
                    processAttendance(qrCode);
                }
            }

            async function processAttendance(qrCode) {
                const action = currentMode === 'checkin' ? 'checkin' : 'checkout';
                const endpoint = currentMode === 'checkin' ? 'process_checkin.php' : 'process_checkout.php';
                
                showResult(`Processing ${action}...`, 'loading');
                
                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            qr_code: qrCode,
                            action: action,
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
                        
                        const actionText = currentMode === 'checkin' ? 'Check-in' : 'Check-out';
                        showResult(`${actionText} successful: ${data.user_name}`, 'success');
                        
                        // Add to recent activity
                        addToRecentActivity(data.user_name, currentMode);
                        
                        // Load recent activity
                        setTimeout(() => loadRecentActivity(), 1000);
                    } else {
                        showResult(`${data.message || 'Failed to process ' + currentMode}`, 'error');
                    }
                } catch (error) {
                    console.error('Process attendance error:', error);
                    showResult(`Network error occurred`, 'error');
                }
            }

            function addToRecentActivity(userName, action) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                
                const activityItem = document.createElement('div');
                activityItem.className = `activity-item ${action}`;
                activityItem.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>${userName}</strong>
                            <span style="margin-left: 0.5rem; font-size: 0.75rem; opacity: 0.8;">
                                ${action.toUpperCase()}
                            </span>
                        </div>
                        <div class="activity-time">${timeString}</div>
                    </div>
                `;
                
                recentActivity.insertBefore(activityItem, recentActivity.firstChild);
                
                // Keep only last 5 items
                while (recentActivity.children.length > 5) {
                    recentActivity.removeChild(recentActivity.lastChild);
                }
            }

            async function loadRecentActivity() {
                try {
                    const [checkinResponse, checkoutResponse] = await Promise.all([
                        fetch('get_recent_checkins.php'),
                        fetch('get_recent_checkouts.php')
                    ]);
                    
                    const checkinData = await checkinResponse.json();
                    const checkoutData = await checkoutResponse.json();
                    
                    // Clear current activity
                    recentActivity.innerHTML = '';
                    
                    // Combine and sort by timestamp
                    const allActivity = [
                        ...checkinData.map(item => ({...item, action: 'checkin'})),
                        ...checkoutData.map(item => ({...item, action: 'checkout'}))
                    ].sort((a, b) => new Date(b.check_in_time || b.check_out_time) - new Date(a.check_in_time || a.check_out_time))
                     .slice(0, 5);
                    
                    allActivity.forEach(item => {
                        const time = new Date(item.check_in_time || item.check_out_time);
                        const timeString = time.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: true 
                        });
                        
                        const activityItem = document.createElement('div');
                        activityItem.className = `activity-item ${item.action}`;
                        activityItem.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>${item.first_name} ${item.last_name}</strong>
                                    <span style="margin-left: 0.5rem; font-size: 0.75rem; opacity: 0.8;">
                                        ${item.action.toUpperCase()}
                                    </span>
                                </div>
                                <div class="activity-time">${timeString}</div>
                            </div>
                        `;
                        
                        recentActivity.appendChild(activityItem);
                    });

                    // Show message if no activity
                    if (allActivity.length === 0) {
                        recentActivity.innerHTML = `
                            <div style="text-align: center; color: rgba(255, 255, 255, 0.5); padding: 1rem; font-size: 0.85rem;">
                                <i class="fas fa-inbox" style="margin-bottom: 0.5rem; display: block; font-size: 1.5rem;"></i>
                                No recent activity
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Failed to load recent activity:', error);
                    recentActivity.innerHTML = `
                        <div style="text-align: center; color: rgba(255, 255, 255, 0.5); padding: 1rem; font-size: 0.85rem;">
                            <i class="fas fa-exclamation-triangle" style="margin-bottom: 0.5rem; display: block;"></i>
                            Failed to load activity
                        </div>
                    `;
                }
            }

            // Event listeners
            document.getElementById('startBtn').addEventListener('click', startScanner);
            document.getElementById('stopBtn').addEventListener('click', stopScanner);
            
            document.getElementById('manualQR').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    processManualQR();
                }
            });

            // Initial load
            loadRecentActivity();
            
            // Auto-refresh recent activity every 30 seconds
            setInterval(loadRecentActivity, 30000);
        });
    </script>
</body>

</html>
