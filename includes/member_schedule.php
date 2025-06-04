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
/* Calendar background and border */
#calendar {
    background: #222;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    padding-bottom: 10px;
}

/* Calendar header (month/year title) */
.fc-toolbar-title {
    color: #fff !important;
    font-size: 2.5rem;
    font-weight: bold;
    letter-spacing: 1px;
}

/* Calendar navigation buttons */
.fc-button {
    background: #d62328 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 6px !important;
    margin-right: 5px;
    font-weight: bold;
    font-size: 1.1rem;
    transition: background 0.2s;
}
.fc-button.fc-button-primary:not(:disabled):hover {
    background: #a81a1e !important;
}

/* Today button */
.fc-button.fc-today-button {
    background: #232a32 !important;
    color: #fff !important;
    border-radius: 6px !important;
    margin-right: 5px;
}

/* View switch buttons (month/week/day) */
.fc-button-group .fc-button {
    background: #232a32 !important;
    color: #fff !important;
}
.fc-button-group .fc-button.fc-button-active {
    background: #d62328 !important;
    color: #fff !important;
}

/* Calendar weekday header */
.fc .fc-col-header-cell-cushion {
    color: #fff !important;
    font-weight: bold;
    font-size: 1.2rem;
}
.fc .fc-col-header-cell {
    background: #222 !important;
    border: none !important;
}

/* Calendar grid */
.fc-theme-standard .fc-scrollgrid,
.fc-theme-standard td, 
.fc-theme-standard th {
    border: 1px solid #fff1 !important;
}

/* Day numbers */
.fc-daygrid-day-number {
    color: #fff !important;
    font-size: 1.1rem;
    font-weight: normal;
}

/* Days outside current month */
.fc-day-other .fc-daygrid-day-number {
    color: #888 !important;
}

/* Today highlight */
.fc-day-today {
    background: #444 !important;
}

/* Event colors */
.fc-event, .fc-event-dot {
    background: #d62328 !important;
    border: none !important;
    color: #fff !important;
    font-weight: bold;
    border-radius: 4px !important;
}
</style>

<?php
include '../assets/format/member_footer.php';
?>
