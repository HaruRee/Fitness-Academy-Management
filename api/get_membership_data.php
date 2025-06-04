<?php
require_once '../config/database.php';
header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get days parameter (default to 30)
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// Validate days parameter
if (!in_array($days, [7, 30, 90])) {
    $days = 30; // Default to 30 if invalid
}

try {
    // Prepare response arrays
    $labels = [];
    $data = [];

    if ($days == 7) {
        // Daily data for last 7 days
        $stmt = $conn->prepare("
            SELECT 
                DATE(RegistrationDate) as reg_date,
                COUNT(*) as user_count
            FROM Users
            WHERE RegistrationDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
            GROUP BY DATE(RegistrationDate)
            ORDER BY reg_date ASC
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate dates for the past 7 days
        $period = new DatePeriod(
            new DateTime(date('Y-m-d', strtotime('-6 days'))),
            new DateInterval('P1D'),
            new DateTime(date('Y-m-d', strtotime('+1 day')))
        );

        // Initialize with zeros
        $dateData = [];
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dateData[$dateStr] = 0;
            $labels[] = $date->format('D'); // Day name (Mon, Tue, etc)
        }

        // Fill in actual data
        foreach ($results as $row) {
            $dateData[$row['reg_date']] = (int)$row['user_count'];
        }

        $data = array_values($dateData);
    } elseif ($days == 90) {
        // Monthly data for last 90 days (3 months)
        $stmt = $conn->prepare("
            SELECT 
                DATE_FORMAT(RegistrationDate, '%Y-%m') as month,
                COUNT(*) as user_count
            FROM Users
            WHERE RegistrationDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH)
            GROUP BY DATE_FORMAT(RegistrationDate, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate months for the past 3 months
        $period = new DatePeriod(
            new DateTime(date('Y-m-01', strtotime('-2 months'))),
            new DateInterval('P1M'),
            new DateTime(date('Y-m-01', strtotime('+1 month')))
        );

        // Initialize with zeros
        $monthData = [];
        foreach ($period as $date) {
            $monthStr = $date->format('Y-m');
            $monthData[$monthStr] = 0;
            $labels[] = $date->format('M'); // Month name (Jan, Feb, etc)
        }

        // Fill in actual data
        foreach ($results as $row) {
            $monthData[$row['month']] = (int)$row['user_count'];
        }

        $data = array_values($monthData);
    } else {
        // Default: Last 30 days - show weekly data
        $stmt = $conn->prepare("
            SELECT 
                YEARWEEK(RegistrationDate, 1) as year_week,
                DATE(DATE_SUB(RegistrationDate, INTERVAL WEEKDAY(RegistrationDate) DAY)) as week_start,
                COUNT(*) as user_count
            FROM Users
            WHERE RegistrationDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY YEARWEEK(RegistrationDate, 1), week_start
            ORDER BY year_week ASC
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process weekly data
        foreach ($results as $row) {
            $date = new DateTime($row['week_start']);
            $labels[] = $date->format('M d');
            $data[] = (int)$row['user_count'];
        }
    }

    echo json_encode([
        'labels' => $labels,
        'data' => $data
    ]);
} catch (PDOException $e) {
    // Return error
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'labels' => ['Error'],
        'data' => [0]
    ]);
}
