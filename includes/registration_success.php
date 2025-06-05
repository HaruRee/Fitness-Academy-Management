<?php
session_start();

// Function to get correct URL for hosting environment
function getCorrectUrl($path)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Check if we're on InfinityFree hosting or localhost
    if (strpos($host, '.ct.ws') !== false || strpos($host, '.infinityfreeapp.com') !== false || strpos($host, '.epizy.com') !== false || strpos($host, '.rf.gd') !== false) {
        return $protocol . $host . '/' . ltrim($path, '/');
    } else {
        return $protocol . $host . '/' . ltrim($path, '/');
    }
}

// If user didn't complete registration, redirect to register page
if (!isset($_SESSION['registration_success'])) {
    header('Location: ' . getCorrectUrl('includes/register.php'));
    exit;
}

// Clear session variable
unset($_SESSION['registration_success']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Registration Success | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .navbar .logo {
            display: flex;
            align-items: center;
        }

        .navbar .logo img {
            height: 40px;
            margin-right: 10px;
        }

        .navbar .logo-text {
            font-weight: 700;
            font-size: 1.4rem;
            color: #e41e26;
            text-decoration: none;
        }

        .nav-links a {
            margin-left: 20px;
            color: #333;
            text-decoration: none;
            font-weight: 600;
        }

        .success-container {
            max-width: 700px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .success-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 20px;
        }

        .success-heading {
            font-size: 28px;
            margin-bottom: 15px;
            color: #333;
        }

        .success-message {
            color: #666;
            margin-bottom: 30px;
            font-size: 18px;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #e41e26;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #c81a21;
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="logo">
            <a href="index.php"><img src="../assets/images/fa_logo.png" alt="Fitness Academy"></a>
            <a href="index.php" class="logo-text">Fitness Academy</a>
        </div>
        <div class="nav-links">
            <a href="login.php">Login</a>
        </div>
    </nav>

    <div class="success-container">
        <i class="fas fa-check-circle success-icon"></i>
        <h1 class="success-heading">Registration Successful!</h1>
        <p class="success-message">Thank you for joining Fitness Academy. Your account has been created successfully.</p>
        <p class="success-message">You can now log in to access your account and start your fitness journey.</p>
        <a href="login.php" class="btn">Login to your account</a>
    </div>
</body>

</html>