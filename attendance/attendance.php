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
    <title>FITNESS ACADEMY - Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="../assets/js/success-sound.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }        :root {
            --primary-red: #ef4444;
            --dark-red: #dc2626;
            --light-red: #f87171;
            --accent-red: #b91c1c;
            --black: #0f172a;
            --dark-gray: #1e293b;
            --medium-gray: #334155;
            --light-gray: #64748b;
            --white: #ffffff;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --glass-bg: rgba(15, 23, 42, 0.9);
            --glass-border: rgba(239, 68, 68, 0.3);
            --text-primary: #f8fafc;
            --text-secondary: rgba(248, 250, 252, 0.7);
            --text-muted: rgba(248, 250, 252, 0.5);
        }        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--black) 0%, var(--dark-gray) 50%, var(--medium-gray) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
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
        }        .particle {
            position: absolute;
            background: rgba(239, 68, 68, 0.3);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.6; }
            33% { transform: translateY(-40px) rotate(120deg); opacity: 0.3; }
            66% { transform: translateY(-80px) rotate(240deg); opacity: 0.1; }
        }

        /* Main container */
        .attendance-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 2px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            margin: 1rem;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .attendance-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            border-color: var(--primary-red);
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .gym-logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.8rem;
            font-size: 18px;
            font-weight: bold;
            color: white;
            animation: pulse-glow 2s ease-in-out infinite alternate;
            border: 1px solid rgba(211, 47, 47, 0.5);
        }

        @keyframes pulse-glow {
            from { box-shadow: 0 0 15px rgba(211, 47, 47, 0.5); }
            to { box-shadow: 0 0 25px rgba(211, 47, 47, 0.8); }
        }        .title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
        }

        .subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 400;
        }

        /* Mode toggle */
        .mode-toggle {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
        }        .toggle-container {
            position: relative;
            background: rgba(30, 41, 59, 0.8);
            border-radius: 25px;
            padding: 3px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .toggle-switch {
            display: flex;
            position: relative;
            width: 200px;
            height: 40px;
        }

        .toggle-option {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 22px;
            z-index: 2;
            position: relative;
        }

        .toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: calc(50% - 3px);
            height: calc(100% - 6px);
            background: linear-gradient(135deg, var(--success-color), #66bb6a);
            border-radius: 19px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
            box-shadow: 0 3px 10px rgba(76, 175, 80, 0.4);
        }

        .toggle-container.checkout .toggle-slider {
            transform: translateX(100%);
            background: linear-gradient(135deg, var(--warning-color), #ffb74d);
            box-shadow: 0 3px 10px rgba(255, 152, 0, 0.4);
        }

        .toggle-option.active {
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }        .toggle-option:not(.active) {
            color: var(--text-muted);
        }

        /* Scanner section */
        .scanner-section {
            margin-bottom: 1rem;
        }

        .scanner-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(211, 47, 47, 0.3);
            transition: all 0.3s ease;
        }        #scanner {
            width: 100%;
            height: 370px;
            border-radius: 13px;
        }

        #scanner:hover {
            border-color: var(--primary-red);
        }        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            backdrop-filter: blur(5px);
            text-align: center;
            padding: 2rem;
            z-index: 10;
        }

        .scanner-overlay i {
            margin-bottom: 1rem;
            opacity: 0.8;
            color: var(--primary-red);
            font-size: 2.5rem;
            display: block;
        }

        .scanner-overlay p {
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 600;
            line-height: 1.5;
            margin: 0 auto;
            text-align: center;
            letter-spacing: 0.5px;
        }/* Status message */
        .status-message {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid transparent;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .status-message.loading {
            background: linear-gradient(135deg, rgba(211, 47, 47, 0.2), rgba(183, 28, 28, 0.2));
            border-color: rgba(211, 47, 47, 0.4);
            animation: loading-pulse 1.5s ease-in-out infinite;
        }

        .status-message.success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.2), rgba(102, 187, 106, 0.2));
            border-color: rgba(76, 175, 80, 0.4);
            animation: success-bounce 0.5s ease-out;
        }

        .status-message.error {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.2), rgba(239, 83, 80, 0.2));
            border-color: rgba(244, 67, 54, 0.4);
            animation: error-shake 0.5s ease-out;
        }

        .status-message.warning {
            background: linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(255, 183, 77, 0.2));
            border-color: rgba(255, 152, 0, 0.4);            animation: error-shake 0.3s ease-out;
        }

        @keyframes loading-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.01); }
        }

        @keyframes success-bounce {
            0% { transform: scale(0.95); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        @keyframes error-shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }

        /* Recent activity - more compact */
        .recent-activity {
            margin-top: 1rem;
        }        .activity-header {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.6rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .activity-list {
            max-height: 120px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--glass-border) transparent;
        }

        .activity-list::-webkit-scrollbar {
            width: 3px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: transparent;
        }        .activity-list::-webkit-scrollbar-thumb {
            background: var(--glass-border);
            border-radius: 2px;
        }        .activity-item {
            background: rgba(30, 41, 59, 0.6);
            padding: 0.5rem 0.7rem;
            margin: 0.2rem 0;
            border-radius: 6px;
            font-size: 0.7rem;
            border-left: 3px solid var(--success-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .activity-item:hover {
            background: rgba(30, 41, 59, 0.8);
            transform: translateX(2px);
            border-color: var(--primary-red);
        }

        .activity-item.checkout {
            border-left-color: var(--warning-color);
        }        .activity-layout {
            display: grid;
            grid-template-columns: 1fr auto 100px;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .activity-user {
            justify-self: start;
            min-width: 0;
            overflow: hidden;
        }

        .activity-time {
            justify-self: center;
            font-size: 0.6rem;
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
        }

        .activity-action {
            justify-self: end;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
        }

        .activity-action.checkin {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(16, 185, 129, 0.15));
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.4);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .activity-action.checkout {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(245, 158, 11, 0.15));
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.4);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .activity-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        /* Time display */        .time-display {
            position: absolute;
            top: 0.8rem;
            right: 0.8rem;
            background: rgba(30, 41, 59, 0.8);
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: var(--primary-red);
        }

        /* Responsive design */        @media (max-width: 640px) {
            .attendance-container {
                margin: 0.5rem;
                padding: 1.2rem;
            }
            
            .title {
                font-size: 1.4rem;
            }
            
            .toggle-switch {
                width: 180px;
                height: 38px;
            }

            #scanner {
                height: 320px;
            }
            
            .activity-layout {
                grid-template-columns: 1fr auto 90px;
                gap: 0.4rem;
            }
        }@media (max-width: 480px) {
            .attendance-container {
                padding: 1rem;
            }
            
            .toggle-switch {
                width: 160px;
                height: 36px;
            }
            
            #scanner {
                height: 280px;
            }
            
            .activity-layout {
                grid-template-columns: 1fr auto 80px;
                gap: 0.3rem;
            }
            
            .activity-action {
                font-size: 0.55rem;
                padding: 0.15rem 0.35rem;
                letter-spacing: 0.5px;
            }
            
            .activity-time {
                font-size: 0.55rem;
            }
              .scanner-overlay p {
                font-size: 0.9rem;
                padding: 0 1rem;
            }
            
            .scanner-overlay i {
                font-size: 2rem;
                margin-bottom: 0.8rem;
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
    </div>

    <!-- Main container -->
    <div class="attendance-container">
        <!-- Time display -->
        <div class="time-display" id="currentTime">12:30</div>

        <!-- Header -->
        <div class="header">
            <div class="gym-logo">
                FA
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
            </div>            <div id="statusMessage" class="status-message" style="display: none;">
                <i class="fas fa-info-circle"></i>
                <span></span>
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
    </div>

    <script>
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
                    
                    // Always restart scanner when mode changes
                    if (isScanning) {
                        stopScanner();
                        setTimeout(() => startScanner(), 300);
                    } else {
                        startScanner();
                    }
                });
            });            function updateStatus(message, type = 'loading') {
                const icons = {
                    loading: 'fas fa-spinner fa-spin',
                    success: 'fas fa-check-circle',
                    error: 'fas fa-exclamation-circle',
                    warning: 'fas fa-exclamation-triangle'
                };
                
                statusMessage.innerHTML = `
                    <i class="${icons[type]}"></i>
                    <span>${message}</span>
                `;
                statusMessage.className = `status-message ${type}`;
            }

            function showResult(message, type = 'loading') {
                updateStatus(message, type);
                
                if (type === 'success' || type === 'error' || type === 'warning') {
                    setTimeout(() => {
                        statusMessage.style.display = 'none';
                    }, 3000);
                }
            }

            function startScanner() {
                if (isScanning) return;
                
                // Hide overlay
                scannerOverlay.style.display = 'none';
                
                const config = {
                    fps: 10,
                    qrbox: { width: 220, height: 220 },
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
                    onScanFailure                ).then(() => {
                    isScanning = true;
                    statusMessage.style.display = 'none';
                }).catch(err => {
                    console.error('Scanner start error:', err);
                    updateStatus('Failed to start camera. Please check permissions.', 'error');
                    scannerOverlay.style.display = 'flex';
                });
            }            function stopScanner() {
                if (!isScanning || !html5QrcodeScanner) return;
                
                html5QrcodeScanner.stop().then(() => {
                    html5QrcodeScanner.clear();
                    isScanning = false;
                    scannerOverlay.style.display = 'flex';
                }).catch(err => {
                    console.error('Scanner stop error:', err);
                    isScanning = false;
                    scannerOverlay.style.display = 'flex';
                });
            }

            function onScanSuccess(decodedText, decodedResult) {
                if (!isScanning) return;
                
                stopScanner();
                processAttendance(decodedText);
            }            function onScanFailure(error) {
                // Ignore scan failures - they're too frequent and noisy
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
                        let successMessage = `${actionText} successful: ${data.user_name}`;
                        
                        // Add duration for checkout
                        if (currentMode === 'checkout' && data.duration) {
                            successMessage += ` (Duration: ${data.duration})`;
                        }
                        
                        // Show warning for inactive accounts
                        if (data.warning) {
                            showResult(`${successMessage} - ${data.warning}`, 'warning');
                        } else {
                            showResult(successMessage, 'success');
                        }
                          // Add to recent activity
                        addToRecentActivity(data.user_name, currentMode, data.duration, data.role);
                        
                        // Load recent activity
                        setTimeout(() => loadRecentActivity(), 1000);
                    } else {
                        // Enhanced error messages based on common timeout scenarios
                        let errorMessage = data.message || `Failed to process ${currentMode}`;
                        
                        // Check for timeout-related errors
                        if (errorMessage.includes('Minimum stay time not met')) {
                            errorMessage = `⏱️ ${errorMessage}`;
                            showResult(errorMessage, 'warning');
                        } else if (errorMessage.includes('checked out recently') || errorMessage.includes('wait')) {
                            errorMessage = `⏱️ ${errorMessage}`;
                            showResult(errorMessage, 'warning');
                        } else if (errorMessage.includes('already checked in')) {
                            errorMessage = `🚫 ${errorMessage}`;
                            showResult(errorMessage, 'error');
                        } else if (errorMessage.includes('not currently checked in')) {
                            errorMessage = `❌ ${errorMessage}`;
                            showResult(errorMessage, 'error');
                        } else {
                            showResult(errorMessage, 'error');
                        }
                    }
                } catch (error) {
                    console.error('Process attendance error:', error);
                    showResult(`🌐 Network error occurred. Please check your connection.`, 'error');
                }
            }            function addToRecentActivity(userName, action, duration = null, role = null) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
                
                const activityItem = document.createElement('div');
                activityItem.className = `activity-item ${action}`;
                
                let userSection = `<strong style="color: var(--text-primary); font-size: 0.7rem;">${userName}</strong>`;
                
                // Add role badge if available
                if (role) {
                    const roleColor = role.toLowerCase() === 'member' ? '#10b981' : role.toLowerCase() === 'coach' ? '#f59e0b' : '#64748b';
                    userSection += `<span style="margin-left: 0.4rem; font-size: 0.6rem; padding: 0.05rem 0.3rem; background: rgba(${roleColor === '#10b981' ? '16, 185, 129' : roleColor === '#f59e0b' ? '245, 158, 11' : '100, 116, 139'}, 0.2); color: ${roleColor}; border-radius: 8px; border: 1px solid rgba(${roleColor === '#10b981' ? '16, 185, 129' : roleColor === '#f59e0b' ? '245, 158, 11' : '100, 116, 139'}, 0.3);">${role}</span>`;
                }
                
                if (duration && action === 'checkout') {
                    userSection += `<span style="margin-left: 0.4rem; font-size: 0.65rem; color: #f59e0b;">(${duration})</span>`;
                }
                
                let content = `
                    <div class="activity-layout">
                        <div class="activity-user">${userSection}</div>
                        <div class="activity-time">${timeString}</div>
                        <div class="activity-action ${action}">${action.toUpperCase()}</div>
                    </div>
                `;
                
                activityItem.innerHTML = content;
                
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
                    const allActivity = [];
                    
                    // Add checkins
                    if (checkinData.success && checkinData.checkins) {
                        checkinData.checkins.forEach(item => {
                            allActivity.push({
                                ...item,
                                action: 'checkin',
                                timestamp: new Date(item.check_in_time)
                            });
                        });
                    }
                    
                    // Add checkouts
                    if (checkoutData.success && checkoutData.checkouts) {
                        checkoutData.checkouts.forEach(item => {
                            allActivity.push({
                                ...item,
                                action: 'checkout',
                                timestamp: new Date(item.check_out_time)
                            });
                        });
                    }
                    
                    // Sort by timestamp (newest first) and take first 5
                    allActivity.sort((a, b) => b.timestamp - a.timestamp);
                    allActivity.slice(0, 5).forEach(item => {
                        const timeString = item.timestamp.toLocaleTimeString('en-US', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: true 
                        });
                          const activityItem = document.createElement('div');
                        activityItem.className = `activity-item ${item.action}`;
                        
                        let userSection = `<strong style="color: var(--text-primary);">${item.user_name}</strong>`;
                        
                        // Add role badge if available
                        if (item.role) {
                            const roleColor = item.role.toLowerCase() === 'member' ? '#10b981' : item.role.toLowerCase() === 'coach' ? '#f59e0b' : '#64748b';
                            userSection += `<span style="margin-left: 0.5rem; font-size: 0.65rem; padding: 0.1rem 0.4rem; background: rgba(${roleColor === '#10b981' ? '16, 185, 129' : roleColor === '#f59e0b' ? '245, 158, 11' : '100, 116, 139'}, 0.2); color: ${roleColor}; border-radius: 10px; border: 1px solid rgba(${roleColor === '#10b981' ? '16, 185, 129' : roleColor === '#f59e0b' ? '245, 158, 11' : '100, 116, 139'}, 0.3);">${item.role}</span>`;
                        }
                        
                        if (item.duration && item.action === 'checkout') {
                            userSection += `<span style="margin-left: 0.5rem; font-size: 0.7rem; color: #f59e0b;">(${item.duration})</span>`;
                        }
                        
                        let content = `
                            <div class="activity-layout">
                                <div class="activity-user">${userSection}</div>
                                <div class="activity-time">${timeString}</div>
                                <div class="activity-action ${item.action}">${item.action.toUpperCase()}</div>
                            </div>
                        `;
                        
                        activityItem.innerHTML = content;
                        recentActivity.appendChild(activityItem);
                    });                    // Show message if no activity
                    if (allActivity.length === 0) {
                        recentActivity.innerHTML = `
                            <div style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.8rem;">
                                <i class="fas fa-inbox" style="margin-bottom: 0.5rem; display: block; font-size: 1.2rem;"></i>
                                No recent activity today
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Failed to load recent activity:', error);
                    recentActivity.innerHTML = `
                        <div style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.8rem;">
                            <i class="fas fa-exclamation-triangle" style="margin-bottom: 0.5rem; display: block;"></i>
                            Failed to load activity
                        </div>
                    `;
                }
            }            // Auto-start scanner when page loads
            startScanner();

            // Initial load
            loadRecentActivity();
            
            // Auto-refresh recent activity every 30 seconds
            setInterval(loadRecentActivity, 30000);
        });
    </script>
</body>

</html>
