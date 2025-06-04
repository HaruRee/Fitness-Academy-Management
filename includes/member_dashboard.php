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
    <!-- QR Check-in Section -->
    <div style="background: #333; padding: 25px; border-radius: 6px; border: 1px solid #444; margin-bottom: 30px;">
        <h3 style="margin-bottom: 20px; color: #eee; border-bottom: 2px solid #d62328; padding-bottom: 6px;">
            <i class="fas fa-qrcode" style="margin-right: 10px; color: #d62328;"></i>Quick Check-in
        </h3>

        <div style="text-align: center; margin-bottom: 20px;">
            <button onclick="generateMemberQR()" style="padding: 15px 30px; background: #d62328; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 16px; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                <i class="fas fa-qrcode"></i>
                Generate Check-in QR Code
            </button>
        </div>

        <!-- QR Code Display -->
        <div id="memberQrDisplay" style="display: none; text-align: center; padding: 20px; background: #222; border-radius: 6px; border: 1px solid #444; margin-top: 20px;">
            <h4 style="margin-bottom: 15px; color: #eee;">Your Check-in QR Code</h4>
            <div id="memberQrCodeContainer" style="margin: 20px auto; display: flex; justify-content: center; align-items: center;">
                <!-- QR code will be generated here -->
            </div>
            <p id="memberQrMessage" style="margin-bottom: 15px; font-weight: 600; color: #eee;"></p>
            <p style="color: #888; font-size: 0.9rem;">
                <i class="fas fa-clock"></i> This QR code expires in <span id="memberCountdown">5:00</span>
            </p>
            <button onclick="hideMemberQRDisplay()" style="padding: 8px 16px; background: #555; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 15px;">Close</button>
        </div>
    </div>

    <h2>Announcements</h2>
    <!-- Add this CSS inside a <style> tag or your CSS file -->
    <style>
        /* General container styling */
        .announcement-container {
            margin-bottom: 20px;
            padding: 15px;
            background: #222;
            border-radius: 8px;
            border: 1px solid #444;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        /* Profile image or initials styling */
        .profile-image,
        .profile-initials {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            object-fit: cover;
            border: 2px solid #d62328;
        }

        .profile-initials {
            background: #d62328;
            color: white;
        }

        /* Announcement text styling */
        .announcement-text {
            flex: 1;
        }

        .announcement-text strong {
            color: #eee;
            display: block;
        }

        .announcement-text small {
            color: #bbb;
            display: block;
            margin-bottom: 10px;
        }

        .announcement-text p {
            color: #ddd;
            margin-top: 10px;
        }

        /* Responsive design for mobile */
        @media (max-width: 600px) {
            .announcement-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile-image,
            .profile-initials {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .announcement-text strong {
                font-size: 1rem;
            }

            .announcement-text small {
                font-size: 0.9rem;
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
        <?php endforeach; ?>
    <?php else: ?>
        <div style="color:#bbb; font-size:1.1em; margin:20px 0;">There are no current announcements.</div>
    <?php endif; ?>
</div>

<?php include '../assets/format/member_footer.php'; ?>

<script>
    // Member QR Generation Functions
    async function generateMemberQR() {
        try {
            const requestData = {
                type: 'member'
            };

            const response = await fetch('../includes/generate_checkin_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();

            if (data.success) {
                displayMemberQRCode(data.qr_data, data.session_name, data.type);
            } else {
                alert('Failed to generate QR code: ' + data.message);
            }
        } catch (error) {
            console.error('Error generating QR code:', error);
            alert('Failed to generate QR code. Please try again.');
        }
    }

    function displayMemberQRCode(qrData, sessionName, type) {
        // Clear previous QR code if any
        const qrContainer = document.getElementById('memberQrCodeContainer');
        qrContainer.innerHTML = '';

        // Create new QR code
        const qrcode = new QRCode(qrContainer, {
            text: qrData,
            width: 200,
            height: 200,
            colorDark: "#FFFFFF",
            colorLight: "#000000",
            correctLevel: QRCode.CorrectLevel.H
        });

        // Show QR display container
        document.getElementById('memberQrDisplay').style.display = 'block';
        document.getElementById('memberQrMessage').textContent = sessionName || 'Gym Entry QR Code';

        // Start countdown
        startMemberCountdown();
    }

    function hideMemberQRDisplay() {
        document.getElementById('memberQrDisplay').style.display = 'none';
        stopMemberCountdown();
    }

    let memberCountdownInterval;

    function startMemberCountdown() {
        let timeLeft = 300; // 5 minutes in seconds
        const countdownElement = document.getElementById('memberCountdown');

        clearInterval(memberCountdownInterval);
        memberCountdownInterval = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (timeLeft <= 0) {
                hideMemberQRDisplay();
                clearInterval(memberCountdownInterval);
            }
            timeLeft--;
        }, 1000);
    }

    function stopMemberCountdown() {
        clearInterval(memberCountdownInterval);
    }
</script>