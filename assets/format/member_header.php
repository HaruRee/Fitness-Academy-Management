<?php
// Start session and access control should remain in member_dashboard.php, not in header.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Member Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #111;
            color: #eee;
            font-family: Arial, sans-serif;
        }
        .page-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 15px;
        }
        header {
            background: #fff;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }
        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #d62328;
            text-decoration: none;
        }
        nav {
            display: flex;
            gap: 20px;
        }
        nav a {
            color: #000;
            text-decoration: none;
            font-weight: bold;
        }
        nav a:hover {
            color: #d62328;
        }
        .menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
        }
        .menu-toggle span {
            height: 3px;
            width: 25px;
            background: #d62328;
            margin: 4px 0;
            border-radius: 5px;
        }
        @media (max-width: 768px) {
            nav {
                display: none;
                flex-direction: column;
                background: #fff;
                position: absolute;
                top: 60px;
                right: 0;
                width: 100%;
                text-align: right;
                padding: 10px 20px;
                z-index: 100;
            }
            nav.active {
                display: flex;
            }
            .menu-toggle {
                display: flex;
            }
            .page-wrapper {
                padding: 0 10px;
            }
        }
        .container {
            flex: 1;
            max-width: 1400px;
            width: 100%;
            margin: 30px auto;
            padding: 20px 30px;
            background: #222;
            border-radius: 6px;
            box-sizing: border-box;
        }
        h2 {
            border-bottom: 2px solid #d62328;
            padding-bottom: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <header>
        <a href="member_dashboard.php" class="logo">Fitness Academy</a>
        <div class="menu-toggle"><span></span><span></span><span></span></div>        <nav>
            <a href="member_class.php">Class</a>
            <a href="member_schedule.php">Schedule</a>
            <a href="member_analytics.php">Analytics</a>
            <a href="member_payments.php">Payments</a>
            <a href="membership.php">Membership</a>
            <a href="member_online_courses.php">Online Courses</a>
            <a href="update_profile.php">Settings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
