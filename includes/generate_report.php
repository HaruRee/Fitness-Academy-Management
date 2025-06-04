<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php'; // For any PDF or Excel generation libraries

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Check if report type is specified
if (!isset($_GET['type'])) {
    header('Location: report_generation.php');
    exit;
}

$reportType = $_GET['type'];
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $reportType . '_report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open the output stream
$output = fopen('php://output', 'w');

// Generate different reports based on type
switch ($reportType) {
    case 'revenue':
        // Add CSV header
        fputcsv($output, ['Date', 'Amount', 'Transactions', 'Payment Method', 'Average Transaction']);

        try {
            $stmt = $conn->prepare("
                SELECT 
                    DATE(payment_date) as date,
                    SUM(amount) as total_amount,
                    COUNT(*) as transactions,
                    payment_method,
                    AVG(amount) as avg_transaction
                FROM payments
                WHERE status = 'completed'
                AND DATE(payment_date) BETWEEN :start_date AND :end_date
                GROUP BY DATE(payment_date), payment_method
                ORDER BY date DESC, total_amount DESC
            ");

            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    date('Y-m-d', strtotime($row['date'])),
                    number_format($row['total_amount'], 2),
                    $row['transactions'],
                    ucfirst($row['payment_method']),
                    number_format($row['avg_transaction'], 2)
                ]);
            }
        } catch (PDOException $e) {
            fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
        }
        break;

    case 'membership':
        // Add CSV header
        fputcsv($output, ['Plan Name', 'Plan Type', 'Price', 'Member Count', 'Total Revenue']);

        try {
            $stmt = $conn->prepare("
                SELECT 
                    mp.name as plan_name,
                    mp.package_type,
                    mp.price,
                    COUNT(u.UserID) as member_count,
                    SUM(mp.price) as total_revenue
                FROM users u
                JOIN membershipplans mp ON mp.id = u.plan_id
                WHERE u.plan_id IS NOT NULL
                GROUP BY mp.id, mp.name, mp.package_type, mp.price
                ORDER BY member_count DESC
            ");

            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['plan_name'],
                    $row['package_type'],
                    number_format($row['price'], 2),
                    $row['member_count'],
                    number_format($row['total_revenue'], 2)
                ]);
            }
        } catch (PDOException $e) {
            fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
        }
        break;

    case 'attendance':
        // Add CSV header
        fputcsv($output, ['Date', 'Class Name', 'Coach Name', 'Enrolled Members', 'Attended Members', 'Attendance Rate']);

        try {
            $stmt = $conn->prepare("
                SELECT 
                    c.class_date,
                    c.class_name,
                    CONCAT(u.First_Name, ' ', u.Last_Name) as coach_name,
                    COUNT(ce.user_id) as enrolled_members,
                    SUM(CASE WHEN ce.attended = 1 THEN 1 ELSE 0 END) as attended_members,
                    ROUND(SUM(CASE WHEN ce.attended = 1 THEN 1 ELSE 0 END) / COUNT(ce.user_id) * 100, 2) as attendance_rate
                FROM classes c
                LEFT JOIN classenrollments ce ON c.class_id = ce.class_id
                LEFT JOIN users u ON c.coach_id = u.UserID
                WHERE c.class_date BETWEEN :start_date AND :end_date
                GROUP BY c.class_id, c.class_date, c.class_name, coach_name
                ORDER BY c.class_date DESC
            ");

            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Handle classes with no enrollments
                $enrolledMembers = $row['enrolled_members'] ?: 0;
                $attendanceRate = $enrolledMembers > 0 ? $row['attendance_rate'] : 0;

                fputcsv($output, [
                    date('Y-m-d', strtotime($row['class_date'])),
                    $row['class_name'],
                    $row['coach_name'],
                    $enrolledMembers,
                    $row['attended_members'] ?: 0,
                    $attendanceRate . '%'
                ]);
            }
        } catch (PDOException $e) {
            fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
        }
        break;

    default:
        fputcsv($output, ['Invalid report type requested']);
        break;
}

// Close the output stream
fclose($output);
exit;
