<?php
session_start();
require '../config/database.php';
require '../vendor/autoload.php';

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

// Check if user is logged in and has proper access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Staff'])) {
    header('Location: login.php');
    exit;
}

// Get staff ID from URL
$staff_id = $_GET['id'] ?? null;

if (!$staff_id) {
    die('Staff ID is required');
}

// Verify staff exists and is active
$stmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE UserID = ? AND Role = 'Staff' AND IsActive = 1");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    die('Invalid staff ID or staff is inactive');
}

// Generate QR code data
$qr_data = 'staff_' . $staff_id;

// Create QR code
$renderer = new ImageRenderer(
    new RendererStyle(300),
    new ImagickImageBackEnd()
);
$writer = new Writer($renderer);
$qr_image = $writer->writeString($qr_data);

// Set appropriate headers
header('Content-Type: image/png');
header('Content-Disposition: inline; filename="staff_qr_' . $staff_id . '.png"');

// Output the image
echo $qr_image;
