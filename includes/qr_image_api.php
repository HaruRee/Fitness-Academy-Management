<?php
// Free API-based QR Code Generator
// Uses multiple free QR code API services as fallbacks

// Set proper headers
header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate');

$qr_data = $_GET['data'] ?? '';

if (empty($qr_data)) {
    // Create a simple error image
    $width = 300;
    $height = 300;
    $image = imagecreate($width, $height);

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $red = imagecolorallocate($image, 214, 35, 40);

    imagefill($image, 0, 0, $white);
    imagerectangle($image, 0, 0, $width - 1, $height - 1, $red);
    imagestring($image, 5, 80, 140, "No QR Data", $black);

    imagepng($image);
    imagedestroy($image);
    exit;
}

// List of free QR code API services
$qr_apis = [
    // API 1: QR Server (most reliable)
    'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_data),

    // API 2: QR Code Generator API
    'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_data),

    // API 3: QuickChart.io
    'https://quickchart.io/qr?text=' . urlencode($qr_data) . '&size=300'
];

// Try each API service
foreach ($qr_apis as $api_url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'GymQR/2.0 (Fitness Management System)',
            'method' => 'GET'
        ]
    ]);

    $qr_image = @file_get_contents($api_url, false, $context);

    if ($qr_image !== false && strlen($qr_image) > 100) {
        // Successfully got QR image
        echo $qr_image;
        exit;
    }
}

// If all APIs fail, create a pattern-based fallback
$width = 300;
$height = 300;
$image = imagecreate($width, $height);

$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);

imagefill($image, 0, 0, $white);

// Create QR-like pattern based on data hash
$hash = md5($qr_data);
$grid_size = 15;
$cell_size = ($width - 40) / $grid_size;

for ($x = 0; $x < $grid_size; $x++) {
    for ($y = 0; $y < $grid_size; $y++) {
        $pos = ($x * $grid_size) + $y;
        $char_pos = $pos % strlen($hash);
        $hex_val = hexdec($hash[$char_pos]);

        if ($hex_val % 2 == 1) {
            $x1 = 20 + ($x * $cell_size);
            $y1 = 20 + ($y * $cell_size);
            $x2 = $x1 + $cell_size - 1;
            $y2 = $y1 + $cell_size - 1;

            imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);
        }
    }
}

// Add corner markers (QR code style)
$corner_size = $cell_size * 3;

// Top-left corner
imagefilledrectangle($image, 20, 20, 20 + $corner_size, 20 + $corner_size, $black);
imagefilledrectangle($image, 20 + $cell_size, 20 + $cell_size, 20 + $corner_size - $cell_size, 20 + $corner_size - $cell_size, $white);

// Top-right corner
$top_right_x = $width - 20 - $corner_size;
imagefilledrectangle($image, $top_right_x, 20, $top_right_x + $corner_size, 20 + $corner_size, $black);
imagefilledrectangle($image, $top_right_x + $cell_size, 20 + $cell_size, $top_right_x + $corner_size - $cell_size, 20 + $corner_size - $cell_size, $white);

// Bottom-left corner
$bottom_left_y = $height - 20 - $corner_size;
imagefilledrectangle($image, 20, $bottom_left_y, 20 + $corner_size, $bottom_left_y + $corner_size, $black);
imagefilledrectangle($image, 20 + $cell_size, $bottom_left_y + $cell_size, 20 + $corner_size - $cell_size, $bottom_left_y + $corner_size - $cell_size, $white);

imagepng($image);
imagedestroy($image);
