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
?>

<?php include '../assets/format/member_header.php'; ?>

<head>
    <!-- Add QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>

<div class="container">
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