<?php
session_start();
require '../config/database.php';

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    if ($user_name && isset($_SESSION['user_id'])) {
        // Update last activity date
        $updateActivitySql = "UPDATE users SET last_activity_date = NOW() WHERE UserID = ?";
        $updateStmt = $conn->prepare($updateActivitySql);
        $updateStmt->execute([$_SESSION['user_id']]);        // Log activity in user_activity_log
        $activitySql = "INSERT INTO user_activity_log (user_id, activity_type, activity_description, activity_timestamp) VALUES (?, 'logout', 'User logged out', NOW())";
        $activityStmt = $conn->prepare($activitySql);
        $activityStmt->execute([$_SESSION['user_id']]);

        // Log in audit trail
        $stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (?, 'logout', NOW())");
        $stmt->execute([$user_name]);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// Set dashboard and message based on role
switch ($user_role) {
    case 'Admin':
        $dashboard = 'admin_dashboard.php';
        $confirm_message = 'Are you sure you want to log out Admin?';
        break;
    case 'Coach':
        $dashboard = 'coach_dashboard.php';
        $confirm_message = 'Are you sure you want to log out Coach?';
        break;
    default:
        $dashboard = 'member_dashboard.php';
        $confirm_message = 'Are you sure you want to log out Ka-TroFA?';
        break;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Logout Confirmation</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f7f7f7;
            color: #222;
            font-family: 'Montserrat', 'Inter', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .confirm-box {
            background: #fff;
            padding: 2.5rem 2.5rem 2rem 2.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10);
            text-align: center;
            width: 100%;
            max-width: 420px;
        }

        .confirm-box h2 {
            margin-bottom: 1.5rem;
            color: #e41e26;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .confirm-box form {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
            box-shadow: 0 2px 8px rgba(228, 30, 38, 0.08);
        }

        .btn-logout {
            background: #e41e26;
            color: #fff;
        }

        .btn-logout:hover {
            background: #c81a21;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(228, 30, 38, 0.13);
        }

        .btn-cancel {
            background: #f2f2f2;
            color: #222;
            border: 1px solid #ddd;
        }

        .btn-cancel:hover {
            background: #eaeaea;
        }
    </style>
</head>

<body>
    <div class="confirm-box">
        <h2><?= htmlspecialchars($confirm_message) ?></h2>
        <form method="post">
            <button type="submit" name="confirm_logout" class="btn btn-logout">Yes, Log Out</button>
            <a href="<?= htmlspecialchars($dashboard) ?>" class="btn btn-cancel">No, Cancel</a>
        </form>
    </div>
</body>

</html>