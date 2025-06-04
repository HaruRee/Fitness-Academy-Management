<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Coach') {
    header("Location: login.php");
    exit;
}

$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("
    SELECT 
        c.class_name,
        c.class_description,
        c.class_date,
        c.start_time,
        c.end_time,
        c.difficulty_level,
        c.requirements,
        (SELECT COUNT(*) FROM classenrollments WHERE class_id = c.class_id) as enrolled_count
    FROM classes c
    WHERE c.class_id = ?
");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    echo "Class not found.";
    exit;
}

// Check if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Return only the class details content
?>
    <div class="class-details">
        <h2><?= htmlspecialchars($class['class_name']) ?></h2>
        <p><strong>Description:</strong> <?= htmlspecialchars($class['class_description']) ?></p>
        <p><strong>Date:</strong> <?= htmlspecialchars($class['class_date']) ?></p>
        <p><strong>Time:</strong> <?= htmlspecialchars($class['start_time']) ?> - <?= htmlspecialchars($class['end_time']) ?></p>
        <p><strong>Difficulty:</strong>
            <span class="badge <?= strtolower($class['difficulty_level']) ?>">
                <?= htmlspecialchars($class['difficulty_level']) ?>
            </span>
        </p>
        <p><strong>Requirements:</strong> <?= htmlspecialchars($class['requirements']) ?></p>
        <p><strong>Enrolled Participants:</strong> <?= htmlspecialchars($class['enrolled_count']) ?></p>
    </div>
<?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Class | Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
            margin: 0;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: 100%;
        }

        .class-details {
            background: #fff;
            padding: 24px 28px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
            max-width: 400px;
            margin: 40px auto;
            width: 95vw;
            position: relative;
            top: 0;
            left: 0;
            right: 0;
        }

        .class-details h2 {
            margin: 0 0 14px;
            color: #222;
            font-size: 2rem;
            word-break: break-word;
        }

        .class-details p {
            margin: 8px 0;
            color: #444;
            font-size: 1.05em;
            word-break: break-word;
        }

        .class-details .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 0.95em;
            font-weight: 600;
            color: #fff;
        }

        .badge.beginner {
            background: #28a745;
        }

        .badge.intermediate {
            background: #ffc107;
            color: #222;
        }

        .badge.advanced {
            background: #dc3545;
        }

        @media (max-width: 600px) {
            .class-details {
                padding: 14px 4vw;
                max-width: 98vw;
                margin: 16px auto;
            }

            .class-details h2 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <!-- Main Content -->
        <div class="main-content">
            <div class="class-details">
                <h2><?= htmlspecialchars($class['class_name']) ?></h2>
                <p><strong>Description:</strong> <?= htmlspecialchars($class['class_description']) ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($class['class_date']) ?></p>
                <p><strong>Time:</strong> <?= htmlspecialchars($class['start_time']) ?> - <?= htmlspecialchars($class['end_time']) ?></p>
                <p><strong>Difficulty:</strong>
                    <span class="badge <?= strtolower($class['difficulty_level']) ?>">
                        <?= htmlspecialchars($class['difficulty_level']) ?>
                    </span>
                </p>
                <p><strong>Requirements:</strong> <?= htmlspecialchars($class['requirements']) ?></p>
                <p><strong>Enrolled Participants:</strong> <?= htmlspecialchars($class['enrolled_count']) ?></p>
            </div>
        </div>
    </div>
</body>

</html>