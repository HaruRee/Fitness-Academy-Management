<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header('Location: login.php');
    exit;
}

require '../config/database.php';

$user_id = $_SESSION['user_id'];

// Fetch user's classes as events for the calendar
$events = [];

try {
    $stmt = $conn->prepare("
        SELECT c.class_name, c.class_date, c.start_time, c.end_time
        FROM classes c
        JOIN classenrollments ce ON c.class_id = ce.class_id
        WHERE ce.user_id = ? AND ce.status = 'confirmed' AND c.is_active = 1
    ");
    $stmt->execute([$user_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'title' => $row['class_name'],
            'start' => $row['class_date'] . 'T' . $row['start_time'],
            'end' => $row['class_date'] . 'T' . $row['end_time'],
        ];
    }
} catch (Exception $e) {
    error_log("Failed to fetch schedule events: " . $e->getMessage());
}

include '../assets/format/member_header.php';
?>

<div class="container">
    <div id="calendar"></div>
</div>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');

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
    });

    calendar.render();
});
</script>

<!-- Add this style block before your closing </head> tag or before the calendar div -->
<style>
/* Main calendar container with dark theme */
#calendar {
    background: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 8px 32px var(--shadow-dark);
    border: 1px solid var(--border-color);
    padding: 20px;
    position: relative;
    overflow: hidden;
}

#calendar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary-red), #ff4449, var(--primary-red));
}

/* Calendar header styling */
.fc-toolbar-title {
    color: var(--text-primary) !important;
    font-size: 2rem !important;
    font-weight: 700 !important;
    letter-spacing: 1px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}

/* Calendar buttons */
.fc-button-primary {
    background: linear-gradient(135deg, var(--primary-red), #ff4449) !important;
    border: none !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    box-shadow: 0 3px 10px rgba(214, 35, 40, 0.3) !important;
    transition: all 0.3s ease !important;
}

.fc-button-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 5px 15px rgba(214, 35, 40, 0.4) !important;
}

.fc-button-primary:disabled {
    background: #666 !important;
    opacity: 0.5 !important;
    transform: none !important;
}

/* Today button active state */
.fc-today-button {
    background: linear-gradient(135deg, #333, #555) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-secondary) !important;
}

/* Calendar day headers */
.fc-col-header-cell {
    background: linear-gradient(135deg, #333333, #404040) !important;
    color: var(--text-primary) !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    border-color: var(--border-color) !important;
}

/* Calendar day cells */
.fc-daygrid-day {
    background: var(--darker-bg) !important;
    border-color: var(--border-color) !important;
    transition: all 0.3s ease !important;
}

.fc-daygrid-day:hover {
    background: rgba(214, 35, 40, 0.1) !important;
}

/* Today's date highlighting */
.fc-day-today {
    background: rgba(214, 35, 40, 0.2) !important;
    border: 2px solid var(--primary-red) !important;
}

/* Day numbers */
.fc-daygrid-day-number {
    color: var(--text-secondary) !important;
    font-weight: 500 !important;
    padding: 8px !important;
}

.fc-day-today .fc-daygrid-day-number {
    color: var(--text-primary) !important;
    font-weight: 700 !important;
}

/* Events */
.fc-event {
    background: linear-gradient(135deg, var(--primary-red), #ff4449) !important;
    border: none !important;
    border-radius: 6px !important;
    color: white !important;
    font-weight: 500 !important;
    box-shadow: 0 2px 8px rgba(214, 35, 40, 0.3) !important;
    transition: all 0.3s ease !important;
}

.fc-event:hover {
    transform: scale(1.02) !important;
    box-shadow: 0 4px 12px rgba(214, 35, 40, 0.4) !important;
}

/* Event text */
.fc-event-title {
    font-weight: 600 !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.5) !important;
}

/* More events link */
.fc-daygrid-more-link {
    color: var(--primary-red) !important;
    font-weight: 600 !important;
}

.fc-daygrid-more-link:hover {
    color: #ff4449 !important;
    text-decoration: underline !important;
}

/* Calendar grid borders */
.fc-scrollgrid {
    border-color: var(--border-color) !important;
}

.fc-scrollgrid-section > * {
    border-color: var(--border-color) !important;
}

/* Week view and day view styles */
.fc-timegrid-slot {
    border-color: var(--border-color) !important;
}

.fc-timegrid-axis {
    background: var(--card-bg) !important;
    color: var(--text-secondary) !important;
}

.fc-timegrid-col {
    background: var(--darker-bg) !important;
}

/* Loading indicator */
.fc-loading {
    color: var(--primary-red) !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .fc-toolbar-title {
        font-size: 1.5rem !important;
    }
    
    .fc-button {
        padding: 6px 10px !important;
        font-size: 0.9rem !important;
    }
    
    #calendar {
        padding: 15px !important;
    }
}
</style>

<?php
include '../assets/format/member_footer.php';
?>
