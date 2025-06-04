<?php
session_start();
require '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Fetch user from the database
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['PasswordHash'])) {
        // Check if account is active
        if (isset($user['account_status']) && $user['account_status'] !== 'active') {
            $error_message = "Your account is currently " . $user['account_status'] . ". Please contact an administrator.";
        } else {
            // Set session variables
            $_SESSION['user_id']   = $user['UserID'];
            $_SESSION['user_name'] = $user['Username'];
            $_SESSION['role']      = $user['Role'];            // Update last activity date
            $updateActivitySql = "UPDATE users SET last_activity_date = NOW() WHERE UserID = ?";
            $updateStmt = $conn->prepare($updateActivitySql);
            $updateStmt->execute([$user['UserID']]);// Log activity in user_activity_log
            $activitySql = "INSERT INTO user_activity_log (user_id, activity_type, activity_description, activity_timestamp) VALUES (?, 'login', 'User logged in', NOW())";
            $activityStmt = $conn->prepare($activitySql);
            $activityStmt->execute([$user['UserID']]);

            // Log the login action in the audit trail
            $username = $user['Username'];
            $stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (?, 'login', NOW())");
            $stmt->execute([$username]);

            // Run automatic deactivation check for admin users
            if ($user['Role'] === 'Admin') {
                require_once 'activity_tracker.php';
                if (shouldRunAutomaticDeactivation($conn)) {
                    runAutomaticDeactivation($conn);
                }
            }

            // Redirect based on role
            if ($user['Role'] === 'Admin') {
                header('Location: admin_dashboard.php');
            } elseif ($user['Role'] === 'Coach') {
                header('Location: coach_dashboard.php');
            } elseif ($user['Role'] === 'Member') {
                header('Location: member_dashboard.php');
            } elseif ($user['Role'] === 'Staff') {
                header('Location: staff_dashboard.php');
            } else {
                header('Location: login.php'); // Fallback
            }
            exit;
        }
    } else {
        $error_message = "Invalid email or password!";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Login | Fitness Academy</title>
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

        .nav-links a:hover {
            color: #e41e26;
        }

        .login-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 73px);
            padding: 2rem;
        }

        .login-container {
            background: #fff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .login-container h1 {
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            color: #333;
            text-align: left;
        }

        .login-container p {
            color: #666;
            margin-bottom: 2rem;
            text-align: left;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #e41e26;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin: 1rem 0;
        }

        .remember-me input {
            margin-right: 0.5rem;
        }

        .btn {
            width: 100%;
            padding: 0.9rem;
            background: #e41e26;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #c81a21;
        }

        .links {
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-between;
        }

        .links a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .links a:hover {
            color: #e41e26;
            text-decoration: underline;
        }

        .error-message {
            color: #e41e26;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            text-align: left;
            background: rgba(228, 30, 38, 0.1);
            padding: 0.8rem;
            border-radius: 4px;
            border-left: 3px solid #e41e26;
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 1.5rem;
            }
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
            <a href="register.php">Join now</a>
        </div>
    </nav>

    <div class="login-page">
        <div class="login-container">
            <h1>Sign in to access your account</h1>
            <p>Welcome back! Please enter your details.</p>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <span onclick="togglePassword()" style="position: absolute; right: 10px; top: 10px; cursor: pointer;">
                            <i id="passwordToggleIcon" class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Keep me signed in</label>
                </div>

                <button type="submit" class="btn">Sign in</button>
            </form>

            <div class="links">
                <a href="forgot_password.php">Forgot password?</a>
                <a href="register.php">Create a new account</a>
            </div>
        </div>
    </div>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordToggleIcon = document.getElementById('passwordToggleIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordToggleIcon.classList.remove('fa-eye');
                passwordToggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                passwordToggleIcon.classList.remove('fa-eye-slash');
                passwordToggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>