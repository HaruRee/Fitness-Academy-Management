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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>    <style>
        :root {
            --bs-dark-rgb: 33, 37, 41;
            --bs-danger-rgb: 214, 35, 40;
            --primary-red: #d62328;
            --dark-bg: #1a1a1a;
            --darker-bg: #121212;
            --card-bg: #2d2d2d;
            --border-color: #404040;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-muted: #888888;
            --shadow-light: rgba(255, 255, 255, 0.1);
            --shadow-dark: rgba(0, 0, 0, 0.5);
        }

        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
            color: var(--text-primary);
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .page-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 15px;
        }
        
        header {
            background: linear-gradient(135deg, #1f1f1f 0%, #2a2a2a 100%);
            backdrop-filter: blur(10px);
            border-bottom: 2px solid var(--primary-red);
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px var(--shadow-dark);
            position: relative;
            z-index: 1000;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-red);
            text-decoration: none;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            color: #ff4449;
            transform: scale(1.05);
        }
        
        nav {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        nav a:hover {
            color: var(--text-primary);
            background: rgba(214, 35, 40, 0.1);
            transform: translateY(-2px);
        }
        
        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-red);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        nav a:hover::after {
            width: 80%;
        }
        
        .menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 5px;
        }
        
        .menu-toggle span {
            height: 3px;
            width: 25px;
            background: var(--primary-red);
            margin: 3px 0;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .menu-toggle:hover span {
            background: #ff4449;
        }
        
        @media (max-width: 768px) {
            nav {
                display: none;
                flex-direction: column;
                background: linear-gradient(135deg, #1f1f1f 0%, #2a2a2a 100%);
                backdrop-filter: blur(10px);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                position: absolute;
                top: 70px;
                right: 20px;
                min-width: 200px;
                padding: 15px;
                z-index: 1001;
                box-shadow: 0 10px 40px var(--shadow-dark);
            }
            
            nav.active {
                display: flex;
            }
            
            nav a {
                text-align: center;
                margin: 5px 0;
                padding: 12px 20px;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .page-wrapper {
                padding: 0 10px;
            }
            
            .logo {
                font-size: 20px;
            }
        }
        
        .container {
            flex: 1;
            max-width: 1400px;
            width: 100%;
            margin: 30px auto;
            padding: 30px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 8px 32px var(--shadow-dark);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-red), #ff4449, var(--primary-red));
        }
        
        h1, h2, h3, h4, h5, h6 {
            color: var(--text-primary);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        h2 {
            border-bottom: 2px solid var(--primary-red);
            padding-bottom: 8px;
            margin-bottom: 25px;
            position: relative;
            font-size: 1.8rem;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: #ff4449;
        }
        
        /* Enhanced Bootstrap component overrides */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-red), #ff4449);
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-red);
            color: var(--primary-red);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-red);
            color: white;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.4);
        }
        
        .card-header {
            background: linear-gradient(135deg, #333333, #404040);
            border-bottom: 2px solid var(--primary-red);
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .table-dark {
            --bs-table-bg: var(--card-bg);
            --bs-table-border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .table-dark th {
            border-bottom: 2px solid var(--primary-red);
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #155724, #28a745);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #721c24, #dc3545);
            color: white;
        }
        
        .badge {
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 6px;
            padding: 6px 12px;
        }
        
        .form-control, .form-select {
            background: var(--darker-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--darker-bg);
            border-color: var(--primary-red);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(214, 35, 40, 0.25);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--darker-bg);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-red);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #ff4449;
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
            <a href="update_profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
