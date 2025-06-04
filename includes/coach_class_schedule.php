<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

$coach_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE UserID = ? AND Role = 'Coach'");
$stmt->execute([$coach_id]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    header("Location: logout.php");
    exit;
}

// Fetch class schedule events
$events = [];

try {
    $stmt = $conn->prepare("
        SELECT 
            class_id,
            class_name,
            class_description,
            class_date,
            start_time,
            end_time,
            difficulty_level,
            (SELECT COUNT(*) FROM classenrollments WHERE class_id = c.class_id) as enrolled_count
        FROM classes c
        WHERE coach_id = ?
        AND class_date >= CURDATE()
        ORDER BY class_date, start_time
    ");
    $stmt->execute([$coach_id]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format the event for FullCalendar
        $events[] = [
            'id' => $row['class_id'],
            'title' => $row['class_name'],
            'start' => $row['class_date'] . 'T' . $row['start_time'],
            'end' => $row['class_date'] . 'T' . $row['end_time'],
            'description' => $row['class_description'],
            'difficulty' => $row['difficulty_level'],
            'enrolled' => $row['enrolled_count'],
            'backgroundColor' => getColorForDifficulty($row['difficulty_level']),
            'borderColor' => getColorForDifficulty($row['difficulty_level']),
            'textColor' => '#ffffff'
        ];
    }
} catch (Exception $e) {
    error_log("Calendar fetch error: " . $e->getMessage());
}

function getColorForDifficulty($level)
{
    switch (strtolower($level)) {
        case 'beginner':
            return '#28a745'; // Green
        case 'intermediate':
            return '#ffc107'; // Yellow
        case 'advanced':
            return '#dc3545'; // Red
        default:
            return '#17a2b8'; // Blue
    }
}

// Function to format time from 24-hour to 12-hour format
function formatTime($time)
{
    return date("g:i A", strtotime($time));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Class Schedule | Fitness Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
            margin: 0;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background-color: #222;
            color: #fff;
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }

        .sidebar-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
        }

        .sidebar-nav {
            padding: 15px 0;
        }

        .nav-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            padding: 10px 25px;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-weight: 500;
            border-left: 5px solid transparent;
            transition: 0.3s;
        }

        .nav-item i {
            margin-right: 15px;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-item.active {
            background-color: rgba(228, 30, 38, 0.2);
            color: #fff;
            border-left-color: #e41e26;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: 100%;
        }

        .welcome-banner {
            background: linear-gradient(45deg, #e41e26, #ff6b6b);
            color: #fff;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            margin: 0;
        }

        .welcome-banner p {
            font-size: 1rem;
            margin-top: 5px;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            color: #333;
        }

        .section-title i {
            color: #e41e26;
            margin-right: 10px;
        }

        #calendar {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            min-height: 600px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .fc-event {
            cursor: pointer;
            padding: 4px;
            margin: 2px 0;
        }

        .fc-event-title {
            font-weight: 600;
            font-size: 0.9em;
        }

        /* Event Tooltip Styles */
        .event-tooltip {
            position: absolute;
            z-index: 1000;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 300px;
            display: none;
        }

        .event-tooltip h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .event-tooltip p {
            margin: 5px 0;
            color: #666;
            font-size: 0.9em;
        }

        .event-tooltip .difficulty {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            margin-top: 8px;
        }

        .difficulty.beginner {
            background: #d4edda;
            color: #155724;
        }

        .difficulty.intermediate {
            background: #fff3cd;
            color: #856404;
        }

        .difficulty.advanced {
            background: #f8d7da;
            color: #721c24;
        }

        /* Modal styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 14px;
            position: relative;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .close-btn {
            position: absolute;
            top: 16px;
            right: 22px;
            font-size: 2rem;
            color: #333;
            cursor: pointer;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #e41e26;
        }

        /* Class details styles within modal */
        .modal .class-details {
            margin: 0;
            padding: 0;
            box-shadow: none;
            max-width: none;
        }

        .modal .class-details h2 {
            margin-top: 0;
            padding-right: 40px;
        }

        @media (max-width: 600px) {
            .modal-content {
                padding: 20px;
                width: 95%;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h3,
            .nav-section-title,
            .nav-item span {
                display: none;
            }

            .nav-item {
                padding: 15px;
                justify-content: center;
            }

            .nav-item i {
                margin-right: 0;
            }

            .main-content {
                margin-left: 80px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/images/fa_logo.png" alt="Logo">
                <h3>Fitness Academy</h3>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="coach_dashboard.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Classes</div>
                    <a href="coach_my_classes.php" class="nav-item">
                        <i class="fas fa-dumbbell"></i>
                        <span>My Classes</span>
                    </a>
                    <a href="coach_class_schedule.php" class="nav-item active">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Class Schedule</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Members</div>
                    <a href="coach_my_clients.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>My Clients</span>
                    </a>
                    <a href="coach_progress_tracking.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Progress Tracking</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="coach_add_video.php" class="nav-item">
                        <i class="fas fa-video"></i>
                        <span>Add Video</span>
                    </a>
                    <a href="coach_view_video.php" class="nav-item">
                        <i class="fas fa-play"></i>
                        <span>My Videos</span>
                    </a>
                    <a href="coach_edit_video.php" class="nav-item">
                        <i class="fas fa-edit"></i>
                        <span>Edit Videos</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="coach_my_profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="logout.php" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <div class="main-content">
            <div class="welcome-banner">
                <h2>Class Schedule</h2>
                <p><?= date('l, F j, Y') ?> · Here's your calendar view</p>
            </div>

            <div class="section-title"><i class="fas fa-calendar-alt"></i> Calendar View</div>
            <div id="calendar"></div>

            <!-- Event Tooltip -->
            <div id="eventTooltip" class="event-tooltip"></div>
        </div>
    </div>

    <!-- Add this modal structure inside the body -->
    <div id="classModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <div id="modalDetails">
                <!-- Class details will be dynamically loaded here -->
            </div>
        </div>
    </div>

    <!-- FullCalendar Script -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const tooltip = document.getElementById('eventTooltip');
            const modal = document.getElementById('classModal');
            const modalDetails = document.getElementById('modalDetails');
            const closeModal = document.querySelector('.close-btn');

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                themeSystem: 'standard',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?= json_encode($events) ?>,
                height: 'auto',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                },
                eventMouseEnter: function(info) {
                    const event = info.event;
                    const rect = info.el.getBoundingClientRect();

                    tooltip.innerHTML = `
                        <h4>${event.title}</h4>
                        <p><i class="far fa-clock"></i> ${formatEventTime(event.start, event.end)}</p>
                        <p><i class="fas fa-users"></i> ${event.extendedProps.enrolled} enrolled</p>
                        <p>${event.extendedProps.description || 'No description available'}</p>
                        <span class="difficulty ${event.extendedProps.difficulty.toLowerCase()}">
                            ${event.extendedProps.difficulty}
                        </span>
                    `;

                    tooltip.style.display = 'block';
                    tooltip.style.left = rect.right + 10 + 'px';
                    tooltip.style.top = rect.top + 'px';
                },
                eventMouseLeave: function() {
                    tooltip.style.display = 'none';
                },
                eventClick: function(info) {
                    // Show loading state
                    modalDetails.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
                    modal.style.display = 'flex';
                    modal.classList.add('show');

                    // Fetch class details with AJAX header
                    fetch(`view_class.php?id=${info.event.id}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.text())
                        .then(data => {
                            modalDetails.innerHTML = data;
                        })
                        .catch(error => {
                            console.error('Error fetching class details:', error);
                            modalDetails.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Error loading class details. Please try again.</div>';
                        });
                }
            });

            calendar.render();

            // Hide tooltip when clicking outside
            document.addEventListener('click', function() {
                tooltip.style.display = 'none';
            });

            // Close modal
            function closeModalHandler() {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }

            closeModal.addEventListener('click', closeModalHandler);

            // Close modal when clicking outside the modal content
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModalHandler();
                }
            });

            // Prevent modal close when clicking inside modal content
            modalDetails.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });

        function formatEventTime(start, end) {
            const options = {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            };
            return `${start.toLocaleTimeString('en-US', options)} - ${end.toLocaleTimeString('en-US', options)}`;
        }
    </script>
</body>

</html>