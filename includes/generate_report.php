<?php
// Turn off all error reporting to prevent output before headers
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';

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

// Clean any output that might have happened
ob_clean();

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Fitness Academy Management System');
$pdf->SetAuthor('Fitness Academy');
$pdf->SetTitle(ucfirst($reportType) . ' Report');
$pdf->SetSubject('Business Analytics Report');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font - Use DejaVu Sans for proper Unicode support including peso symbol
$pdf->SetFont('dejavusans', '', 10);

// Define colors - simplified to just black
$primaryColor = array(0, 0, 0); // Black
$secondaryColor = array(0, 0, 0); 
$accentColor = array(0, 0, 0);
$dangerColor = array(0, 0, 0);

// Helper function to create simple header
function createHeader($pdf, $title, $subtitle, $primaryColor) {
    // Simple header with border
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, 40, 195, 40);
    
    // Header text
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 18);
    $pdf->SetXY(15, 15);
    $pdf->Cell(0, 10, 'FITNESS ACADEMY', 0, 1, 'L');
    
    // Report title
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->SetXY(15, 30);
    $pdf->Cell(0, 10, $title, 0, 1, 'L');
    
    // Subtitle and date
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetXY(15, 45);
    $pdf->Cell(0, 6, $subtitle, 0, 1, 'L');
    
    $pdf->SetXY(15, 52);
    $pdf->Cell(0, 6, 'Generated on: ' . date('F j, Y \a\t g:i A'), 0, 1, 'L');
    
    return 65; // Return Y position after header
}

// Helper function to create simple summary box
function createSummaryBox($pdf, $x, $y, $width, $height, $title, $value, $subtitle, $color) {
    // Simple box with border
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    $pdf->Rect($x, $y, $width, $height);
    
    // Content
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetXY($x + 5, $y + 5);
    $pdf->Cell(0, 6, $value, 0, 1, 'L');
    
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetXY($x + 5, $y + 12);
    $pdf->Cell(0, 6, $title, 0, 1, 'L');
    
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetXY($x + 5, $y + 19);
    $pdf->Cell(0, 5, $subtitle, 0, 1, 'L');
}

// Helper function to create simple data table
function createDataTable($pdf, $y, $headers, $data, $columnWidths) {
    $pdf->SetTextColor(0, 0, 0);
    
    // Table header
    $pdf->SetFont('dejavusans', 'B', 9);
    
    $x = 15;
    foreach ($headers as $i => $header) {
        $pdf->SetXY($x, $y);
        $pdf->Cell($columnWidths[$i], 8, $header, 1, 0, 'C');
        $x += $columnWidths[$i];
    }
    
    $y += 8;
    
    // Table data
    $pdf->SetFont('dejavusans', '', 8);
    
    foreach ($data as $row) {
        $x = 15;
        foreach ($row as $i => $cell) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($columnWidths[$i], 6, $cell, 1, 0, 'C');
            $x += $columnWidths[$i];
        }
        
        $y += 6;
        
        // Check if we need a new page
        if ($y > 250) {
            $pdf->AddPage();
            $y = 20;
            
            // Repeat headers
            $pdf->SetFont('dejavusans', 'B', 9);
            
            $x = 15;
            foreach ($headers as $i => $header) {
                $pdf->SetXY($x, $y);
                $pdf->Cell($columnWidths[$i], 8, $header, 1, 0, 'C');
                $x += $columnWidths[$i];
            }
            
            $y += 8;
            $pdf->SetFont('dejavusans', '', 8);
        }
    }
    
    return $y + 10;
}

// Generate different reports based on type
switch ($reportType) {
    case 'revenue':
        $currentY = createHeader($pdf, 'Revenue Analytics Report', 
            'Period: ' . date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)), 
            $primaryColor);
        
        try {
            // Get summary data
            $stmt = $conn->prepare("
                SELECT 
                    SUM(amount) as total_revenue,
                    COUNT(*) as total_transactions,
                    AVG(amount) as avg_transaction,
                    COUNT(DISTINCT DATE(payment_date)) as active_days
                FROM payments
                WHERE status = 'completed'
                AND DATE(payment_date) BETWEEN :start_date AND :end_date
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create summary boxes
            createSummaryBox($pdf, 15, $currentY, 45, 35, 'Total Revenue', 
                '₱' . number_format($summary['total_revenue'] ?? 0, 2), 
                'All completed payments', $primaryColor);
                
            createSummaryBox($pdf, 65, $currentY, 45, 35, 'Transactions', 
                number_format($summary['total_transactions'] ?? 0), 
                'Completed payments', $accentColor);
                
            createSummaryBox($pdf, 115, $currentY, 45, 35, 'Average Sale', 
                '₱' . number_format($summary['avg_transaction'] ?? 0, 2), 
                'Per transaction', $dangerColor);
                
            createSummaryBox($pdf, 165, $currentY, 30, 35, 'Active Days', 
                number_format($summary['active_days'] ?? 0), 
                'With revenue', $secondaryColor);
            
            $currentY += 50;
            
            // Get detailed data
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
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    date('M j, Y', strtotime($row['date'])),
                    '₱' . number_format($row['total_amount'], 2),
                    $row['transactions'],
                    ucfirst($row['payment_method']),
                    '₱' . number_format($row['avg_transaction'], 2)
                ];
            }
            
            if (!empty($data)) {                $pdf->SetFont('dejavusans', 'B', 12);
                $pdf->SetXY(15, $currentY);
                $pdf->Cell(0, 8, 'Daily Revenue Breakdown', 0, 1, 'L');
                $currentY += 15;
                
                $headers = ['Date', 'Total Amount', 'Transactions', 'Payment Method', 'Average'];
                $columnWidths = [35, 35, 25, 35, 30];
                
                createDataTable($pdf, $currentY, $headers, $data, $columnWidths);
            }
            
        } catch (PDOException $e) {
            $pdf->SetXY(15, $currentY);
            $pdf->Cell(0, 10, 'Error generating report: ' . $e->getMessage(), 0, 1, 'L');
        }
        break;
        
    case 'membership':
        $currentY = createHeader($pdf, 'Membership Analytics Report', 
            'Comprehensive membership analysis and insights', 
            $primaryColor);
        
        try {
            // Get summary data
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT u.UserID) as total_members,
                    SUM(mp.price) as total_value,
                    COUNT(DISTINCT mp.id) as active_plans,
                    AVG(mp.price) as avg_plan_price
                FROM users u
                JOIN membershipplans mp ON mp.id = u.plan_id
                WHERE u.plan_id IS NOT NULL AND u.Role = 'Member'
            ");
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create summary boxes
            createSummaryBox($pdf, 15, $currentY, 45, 35, 'Total Members', 
                number_format($summary['total_members'] ?? 0), 
                'Active memberships', $primaryColor);
                
            createSummaryBox($pdf, 65, $currentY, 45, 35, 'Total Value', 
                '₱' . number_format($summary['total_value'] ?? 0, 2), 
                'All memberships', $accentColor);
                
            createSummaryBox($pdf, 115, $currentY, 45, 35, 'Avg Plan Price', 
                '₱' . number_format($summary['avg_plan_price'] ?? 0, 2), 
                'Per membership', $dangerColor);
                
            createSummaryBox($pdf, 165, $currentY, 30, 35, 'Active Plans', 
                number_format($summary['active_plans'] ?? 0), 
                'Different plans', $secondaryColor);
            
            $currentY += 50;
              // Get detailed data
            $stmt = $conn->prepare("
                SELECT 
                    mp.name as plan_name,
                    mp.plan_type,
                    mp.price,
                    COUNT(u.UserID) as member_count,
                    SUM(mp.price) as total_revenue,
                    ROUND(COUNT(u.UserID) * 100.0 / (SELECT COUNT(*) FROM users WHERE plan_id IS NOT NULL), 2) as percentage
                FROM users u
                JOIN membershipplans mp ON mp.id = u.plan_id
                WHERE u.plan_id IS NOT NULL
                GROUP BY mp.id, mp.name, mp.plan_type, mp.price
                ORDER BY member_count DESC
            ");
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    $row['plan_name'],
                    ucfirst($row['plan_type']),
                    '₱' . number_format($row['price'], 2),
                    $row['member_count'],
                    '₱' . number_format($row['total_revenue'], 2),
                    $row['percentage'] . '%'
                ];
            }
            
            if (!empty($data)) {                $pdf->SetFont('dejavusans', 'B', 12);
                $pdf->SetXY(15, $currentY);
                $pdf->Cell(0, 8, 'Membership Plan Distribution', 0, 1, 'L');
                $currentY += 15;
                
                $headers = ['Plan Name', 'Type', 'Price', 'Members', 'Total Revenue', 'Share'];
                $columnWidths = [35, 25, 25, 20, 30, 25];
                
                createDataTable($pdf, $currentY, $headers, $data, $columnWidths);
            }
            
        } catch (PDOException $e) {
            $pdf->SetXY(15, $currentY);
            $pdf->Cell(0, 10, 'Error generating report: ' . $e->getMessage(), 0, 1, 'L');
        }
        break;
        
    case 'attendance':
        $currentY = createHeader($pdf, 'Attendance Analytics Report', 
            'Period: ' . date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)), 
            $primaryColor);
        
        try {
            // Get summary data
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT c.class_id) as total_classes,
                    COUNT(DISTINCT ce.user_id) as unique_attendees,
                    SUM(CASE WHEN ce.attended = 1 THEN 1 ELSE 0 END) as total_attendance,
                    ROUND(AVG(CASE WHEN ce.attended = 1 THEN 100 ELSE 0 END), 2) as avg_attendance_rate
                FROM classes c
                LEFT JOIN classenrollments ce ON c.class_id = ce.class_id
                WHERE c.class_date BETWEEN :start_date AND :end_date
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create summary boxes
            createSummaryBox($pdf, 15, $currentY, 45, 35, 'Total Classes', 
                number_format($summary['total_classes'] ?? 0), 
                'Scheduled classes', $primaryColor);
                
            createSummaryBox($pdf, 65, $currentY, 45, 35, 'Unique Attendees', 
                number_format($summary['unique_attendees'] ?? 0), 
                'Different members', $accentColor);
                
            createSummaryBox($pdf, 115, $currentY, 45, 35, 'Total Attendance', 
                number_format($summary['total_attendance'] ?? 0), 
                'Member sessions', $dangerColor);
                
            createSummaryBox($pdf, 165, $currentY, 30, 35, 'Avg Rate', 
                number_format($summary['avg_attendance_rate'] ?? 0, 1) . '%', 
                'Attendance rate', $secondaryColor);
            
            $currentY += 50;
            
            // Get detailed data
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
                HAVING enrolled_members > 0
                ORDER BY c.class_date DESC
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $enrolledMembers = $row['enrolled_members'] ?: 0;
                $attendanceRate = $enrolledMembers > 0 ? $row['attendance_rate'] : 0;
                
                $data[] = [
                    date('M j, Y', strtotime($row['class_date'])),
                    $row['class_name'],
                    $row['coach_name'] ?? 'Unassigned',
                    $enrolledMembers,
                    $row['attended_members'] ?: 0,
                    number_format($attendanceRate, 1) . '%'
                ];
            }
            
            if (!empty($data)) {                $pdf->SetFont('dejavusans', 'B', 12);
                $pdf->SetXY(15, $currentY);
                $pdf->Cell(0, 8, 'Class Attendance Details', 0, 1, 'L');
                $currentY += 15;
                
                $headers = ['Date', 'Class Name', 'Coach', 'Enrolled', 'Attended', 'Rate'];
                $columnWidths = [28, 40, 35, 22, 22, 23];
                
                createDataTable($pdf, $currentY, $headers, $data, $columnWidths);
            }
            
        } catch (PDOException $e) {
            $pdf->SetXY(15, $currentY);
            $pdf->Cell(0, 10, 'Error generating report: ' . $e->getMessage(), 0, 1, 'L');
        }
        break;
        
    default:
        $currentY = createHeader($pdf, 'Invalid Report Type', 
            'The requested report type is not available', 
            $dangerColor);
        
        $pdf->SetXY(15, $currentY);
        $pdf->Cell(0, 10, 'Please select a valid report type: Revenue, Membership, or Attendance', 0, 1, 'L');
        break;
}

// Add footer with page numbers and generation info
$pdf->SetY(-20);
// Draw a simple footer line
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY() - 5, 195, $pdf->GetY() - 5);

$pdf->SetFont('dejavusans', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, 'Fitness Academy | Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'L');
$pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'R');

// Output the PDF
$filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'D'); // D = download, I = inline view
exit;
?>