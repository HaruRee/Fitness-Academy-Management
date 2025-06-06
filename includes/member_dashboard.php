<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header('Location: login.php');
    exit;
}

require '../config/database.php';

$announcements = [];

try {
    $stmt = $conn->prepare("
        SELECT ca.announcement, ca.created_at, u.First_Name, u.Last_Name
        FROM coach_announcements ca
        JOIN users u ON ca.coach_id = u.UserID
        ORDER BY ca.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch announcements: " . $e->getMessage());
}

// Get user's membership details
$membership_data = null;
try {
    $member_stmt = $conn->prepare("
        SELECT u.First_Name, u.Last_Name, u.current_sessions_remaining, u.membership_start_date, 
               u.membership_end_date, u.membership_plan, u.membership_price,
               mp.plan_type, mp.session_count, mp.duration_months, mp.name as plan_name, mp.description
        FROM users u
        LEFT JOIN membershipplans mp ON u.plan_id = mp.id
        WHERE u.UserID = ?
    ");
    $member_stmt->execute([$_SESSION['user_id']]);
    $membership_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch membership data: " . $e->getMessage());
}
?>

<?php include '../assets/format/member_header.php'; ?>

<head>
    <!-- Add QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>

<?php if (isset($_GET['debug'])): ?>
<div class="container mt-3">
    <div class="card bg-dark">
        <div class="card-header bg-danger text-white">
            Debug Info (Admin Only)
        </div>
        <div class="card-body">
            <h5 class="card-title text-white">User ID: <?php echo $_SESSION['user_id']; ?></h5>
            <div class="bg-dark text-light p-3" style="overflow-x: auto;">
                <pre><?php print_r($membership_data); ?></pre>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container">
    <!-- Membership Status Section -->
    <div class="card mb-4 shadow-lg">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-id-card me-3 text-danger"></i>
            <h4 class="mb-0 text-white">My Membership Status</h4>
        </div>
        <div class="card-body">
            <?php if ($membership_data): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="membership-info-card h-100">
                            <h5 class="text-danger mb-3">
                                <i class="fas fa-star me-2"></i>Plan Details
                            </h5>
                            <p class="mb-2"><strong>Plan:</strong> <span class="text-light"><?php echo htmlspecialchars($membership_data['plan_name'] ?? $membership_data['membership_plan'] ?? 'N/A'); ?></span></p>
                            <p class="mb-2"><strong>Type:</strong> 
                                <span class="badge <?php echo $membership_data['plan_type'] === 'session' ? 'bg-info' : 'bg-success'; ?>">
                                    <?php echo ucfirst($membership_data['plan_type'] ?? 'Unknown'); ?>
                                </span>
                            </p>                            <p class="mb-2"><strong>Price:</strong> <span class="text-warning">₱<?php echo number_format($membership_data['membership_price'] ?? 0, 2); ?></span></p>
                            <?php 
                            // Only show description if it's different from the plan name
                            $plan_name = $membership_data['plan_name'] ?? $membership_data['membership_plan'] ?? 'N/A';
                            $description = $membership_data['description'] ?? '';
                            if ($description && $description !== $plan_name): 
                            ?>
                                <p class="mb-0 text-muted small"><?php echo htmlspecialchars($description); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="membership-status-card h-100">
                            <h5 class="text-danger mb-3">
                                <i class="fas fa-clock me-2"></i>Status
                            </h5>
                            <?php if ($membership_data['plan_type'] === 'session'): ?>
                                <div class="sessions-remaining">
                                    <p class="mb-2"><strong>Sessions Remaining:</strong></p>
                                    <div class="progress mb-2" style="height: 25px;">
                                        <?php 
                                        $sessions_remaining = $membership_data['current_sessions_remaining'] ?? 0;
                                        $total_sessions = $membership_data['session_count'] ?? 1;
                                        $percentage = ($sessions_remaining / $total_sessions) * 100;
                                        $bar_color = $percentage > 50 ? 'bg-success' : ($percentage > 20 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <div class="progress-bar <?php echo $bar_color; ?>" style="width: <?php echo $percentage; ?>%">
                                            <?php echo $sessions_remaining; ?> / <?php echo $total_sessions; ?>
                                        </div>
                                    </div>
                                    <?php if ($sessions_remaining <= 5): ?>
                                        <div class="alert alert-warning py-2 mb-0">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <?php if ($sessions_remaining == 0): ?>
                                                No sessions remaining! Please renew your membership.
                                            <?php else: ?>
                                                Low sessions remaining. Consider renewing soon.
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($membership_data['plan_type'] === 'monthly'): ?>                                <div class="membership-dates">
                                    <?php
                                    // Format dates for proper comparison
                                    $start_date = new DateTime($membership_data['membership_start_date']);
                                    $end_date = new DateTime($membership_data['membership_end_date']);
                                    $today = new DateTime();
                                    
                                    // For comparison, normalize to beginning of day
                                    $today_normalized = new DateTime($today->format('Y-m-d'));
                                    $end_date_normalized = new DateTime($end_date->format('Y-m-d'));
                                    
                                    // Calculate days remaining - if end date is today, show 0 days
                                    $days_remaining = $today_normalized < $end_date_normalized ? $today_normalized->diff($end_date_normalized)->days : 0;
                                    
                                    // Check if today is after end date or the exact same day as end date
                                    $is_expired = $today_normalized >= $end_date_normalized;
                                    
                                    // For debugging
                                    if (isset($_GET['debug'])) {
                                        echo "<div class='alert alert-info mb-3'>";
                                        echo "Today: " . $today_normalized->format('Y-m-d') . "<br>";
                                        echo "End Date: " . $end_date_normalized->format('Y-m-d') . "<br>";
                                        echo "Days Remaining: " . $days_remaining . "<br>";
                                        echo "Is Expired: " . ($is_expired ? 'Yes' : 'No') . "<br>";
                                        echo "</div>";
                                    }
                                    ?>
                                    <p class="mb-2"><strong>Start Date:</strong> <span class="text-light"><?php echo $start_date->format('M d, Y'); ?></span></p>
                                    <p class="mb-2"><strong>End Date:</strong> <span class="text-light"><?php echo $end_date->format('M d, Y'); ?></span></p>
                                    <p class="mb-2"><strong>Status:</strong> 
                                        <?php if ($is_expired): ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php elseif ($days_remaining <= 7): ?>
                                            <span class="badge bg-warning">Expires Soon</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$is_expired): ?>
                                        <p class="mb-0"><strong>Days Remaining:</strong> <span class="text-info"><?php echo $days_remaining; ?> days</span></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_expired): ?>
                                        <div class="alert alert-danger py-2 mb-0">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            Your membership has expired. Please renew to continue accessing the gym.
                                        </div>
                                    <?php elseif ($days_remaining <= 7): ?>
                                        <div class="alert alert-warning py-2 mb-0">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Your membership expires in <?php echo $days_remaining; ?> days. Consider renewing soon.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Plan type information not available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Membership information not available. Please contact support.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- QR Attendance Section -->
    <div class="card mb-4 shadow-lg">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-qrcode me-3 text-danger"></i>
            <h4 class="mb-0 text-white">My Attendance QR Code</h4>
        </div>
        <div class="card-body">
            <p class="text-center text-light mb-4 lead">
                Generate your personal QR code for gym attendance. Show this QR code at the gym entrance/exit scanners.
            </p>
            
            <div class="text-center mb-4">
                <button onclick="generateMemberQR()" class="btn btn-primary btn-lg px-5 py-3 shadow">
                    <i class="fas fa-qrcode me-2"></i>
                    Generate My QR Code
                </button>
            </div>

            <!-- QR Code Display -->
            <div id="memberQrDisplay" style="display: none;" class="card bg-dark border-danger mt-4">
                <div class="card-body text-center">
                    <h5 class="card-title text-white mb-3">
                        <i class="fas fa-qrcode me-2 text-danger"></i>
                        Your Attendance QR Code
                    </h5>
                    <div id="memberQrCodeContainer" class="d-flex justify-content-center align-items-center mb-3">
                        <!-- QR code will be generated here -->
                    </div>
                    <p id="memberQrMessage" class="text-white fw-bold mb-3"></p>
                    <p class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i> 
                        Show this QR code at gym entrance/exit scanners
                    </p>
                    <button onclick="hideMemberQRDisplay()" class="btn btn-outline-secondary btn-sm mt-2">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>    <div class="d-flex align-items-center mb-4">
        <i class="fas fa-bullhorn me-3 text-danger fs-4"></i>
        <h2 class="mb-0">Announcements</h2>
    </div>
      <!-- Enhanced announcements styling -->
    <style>
        .membership-info-card,
        .membership-status-card {
            background: linear-gradient(135deg, #2d2d2d 0%, #3a3a3a 100%);
            border: 1px solid #404040;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .membership-info-card:hover,
        .membership-status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-color: var(--primary-red);
        }

        .sessions-remaining .progress {
            background-color: #404040;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
        }

        .sessions-remaining .progress-bar {
            font-weight: bold;
            font-size: 14px;
            line-height: 25px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            transition: width 0.6s ease;
        }

        .membership-dates p {
            margin-bottom: 8px;
        }

        .membership-dates .badge {
            font-size: 0.9rem;
            padding: 5px 10px;
        }

        @media (max-width: 768px) {
            .membership-info-card,
            .membership-status-card {
                margin-bottom: 15px;
                padding: 15px;
            }
        }

        .announcement-container {
            background: linear-gradient(135deg, #2d2d2d 0%, #3a3a3a 100%);
            border: 1px solid #404040;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .announcement-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-color: var(--primary-red);
        }

        .profile-image,
        .profile-initials {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            object-fit: cover;
            border: 3px solid var(--primary-red);
            box-shadow: 0 4px 15px rgba(214, 35, 40, 0.3);
            transition: all 0.3s ease;
        }

        .profile-initials {
            background: linear-gradient(135deg, var(--primary-red), #ff4449);
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        .announcement-container:hover .profile-image,
        .announcement-container:hover .profile-initials {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(214, 35, 40, 0.4);
        }

        .announcement-text {
            flex: 1;
        }

        .announcement-text strong {
            color: var(--text-primary);
            display: block;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .announcement-text small {
            color: var(--text-muted);
            display: block;
            margin-bottom: 12px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .announcement-text p {
            color: var(--text-secondary);
            margin-top: 10px;
            line-height: 1.6;
            font-size: 1rem;
        }

        .no-announcements {
            background: linear-gradient(135deg, #2d2d2d 0%, #3a3a3a 100%);
            border: 2px dashed #404040;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .no-announcements i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--primary-red);
            opacity: 0.7;
        }

        /* Responsive design for mobile */
        @media (max-width: 600px) {
            .announcement-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 15px;
            }

            .profile-image,
            .profile-initials {
                width: 50px;
                height: 50px;
                font-size: 18px;
            }

            .announcement-text strong {
                font-size: 1rem;
            }

            .announcement-text small {
                font-size: 0.85rem;
            }

            .announcement-text p {
                font-size: 0.95rem;
            }
        }
    </style>

    <?php if (count($announcements) > 0): ?>
        <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-container">
                <!-- Profile Image or Initials -->
                <?php
                $profileImagePath = "../assets/images/profiles/" . htmlspecialchars($announcement['First_Name'] . '_' . $announcement['Last_Name']) . ".jpg";
                if (file_exists($profileImagePath)):
                ?>
                    <img src="<?php echo $profileImagePath; ?>"
                        alt="Profile Image"
                        class="profile-image">
                <?php else: ?>
                    <div class="profile-initials">
                        <?php
                        echo strtoupper(substr($announcement['First_Name'], 0, 1)) . strtoupper(substr($announcement['Last_Name'], 0, 1));
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Announcement Text -->
                <div class="announcement-text">
                    <strong><?php echo htmlspecialchars($announcement['First_Name'] . ' ' . $announcement['Last_Name']); ?></strong>
                    <small><?php echo date('F j, Y', strtotime($announcement['created_at'])); ?></small>
                    <p><?php echo nl2br(htmlspecialchars($announcement['announcement'])); ?></p>
                </div>
            </div>
        <?php endforeach; ?>    <?php else: ?>
        <div class="no-announcements">
            <i class="fas fa-bullhorn"></i>
            <p class="mb-0">There are no current announcements.</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../assets/format/member_footer.php'; ?>

<script>    // Member Static QR Generation Functions
    async function generateMemberQR() {
        try {
            const response = await fetch('../attendance/generate_static_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const data = await response.json();

            if (data.success) {
                displayMemberQRCode(data.qr_code_url, data.user_name, data.instructions);
            } else {
                alert('Failed to generate QR code: ' + data.message);
            }
        } catch (error) {
            console.error('Error generating QR code:', error);
            alert('Failed to generate QR code. Please try again.');
        }
    }    function displayMemberQRCode(qrImageUrl, userName, instructions) {
        // Clear previous QR code if any
        const qrContainer = document.getElementById('memberQrCodeContainer');
        qrContainer.innerHTML = '';

        // Create QR code image element
        const qrImage = document.createElement('img');
        qrImage.src = qrImageUrl;
        qrImage.style.width = '200px';
        qrImage.style.height = '200px';
        qrImage.style.border = '3px solid #1e40af';
        qrImage.style.borderRadius = '10px';
        qrImage.alt = 'Your Attendance QR Code';
        
        qrContainer.appendChild(qrImage);

        // Show QR display container
        document.getElementById('memberQrDisplay').style.display = 'block';
        document.getElementById('memberQrMessage').innerHTML = `
            <strong>${userName}'s Attendance QR Code</strong><br>
            <small style="color: #666;">${instructions}</small>
        `;    }
    
    function hideMemberQRDisplay() {
        document.getElementById('memberQrDisplay').style.display = 'none';
    }
</script>