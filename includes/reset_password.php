<?php
require '../config/database.php';

// Start the session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = $_GET['token'] ?? '';
$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Password validation
    if (
        strlen($new_password) < 8 ||
        !preg_match('/[A-Z]/', $new_password) ||
        !preg_match('/[a-z]/', $new_password) ||
        !preg_match('/[0-9]/', $new_password) ||
        !preg_match('/[\W_]/', $new_password)
    ) {
        $error_message = "Password must be at least 8 characters long and include a mix of uppercase and lowercase letters, numbers, and symbols.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Check if the token is valid and not expired
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            // Update the user's password
            $stmt = $conn->prepare("UPDATE users SET PasswordHash = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $reset['email']]);

            // Retrieve username for the audit record.
            // Use session value if available; otherwise, fetch from the users table using email.
            if (!isset($_SESSION['user_name'])) {
                $stmtUser = $conn->prepare("SELECT Username FROM users WHERE email = ?");
                $stmtUser->execute([$reset['email']]);
                $userRecord = $stmtUser->fetch(PDO::FETCH_ASSOC);
                $username = $userRecord ? $userRecord['Username'] : $reset['email'];
            } else {
                $username = $_SESSION['user_name'];
            }

            // Insert audit record for password change
            $stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (?, 'password change', NOW())");
            $stmt->execute([$username]);

            // Delete the token from the password_resets table
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            $success_message = "Your password has been reset successfully.";
        } else {
            $error_message = "Invalid or expired token.";
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('../assets/images/fa.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            position: relative;
        }

        /* Subtle red overlay */
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 107, 107, 0.2);
            z-index: -1;
        }

        .reset-container {
            background: rgba(0, 0, 0, 0.8);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .reset-container h2 {
            margin-bottom: 1.5rem;
            font-size: 2rem;
        }

        .reset-container input {
            width: 100%;
            padding: 0.8rem;
            margin: 0.5rem 0;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .reset-container button {
            width: 100%;
            padding: 0.8rem;
            margin-top: 1rem;
            background: #e63946;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .reset-container button:hover {
            background: #d62828;
        }

        .reset-container a {
            display: block;
            margin-top: 1rem;
            color: #fff;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .reset-container a:hover {
            text-decoration: underline;
        }

        .message {
            margin-top: 1rem;
            font-size: 0.95rem;
            color: #00ff99;
        }

        .error-message {
            margin-top: 1rem;
            font-size: 0.95rem;
            color: #ff6b6b;
        }

        /* Show Password checkbox styling */
        .show-password {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .show-password input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }

        .show-password label {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="reset-container">
        <h2>Reset Password</h2>
        <?php
        if (!empty($error_message)) {
            echo "<div class='error-message'>$error_message</div>";
        }
        if (!empty($success_message)) {
            echo "<div class='message'>$success_message</div>";
        }
        if (empty($success_message)) { // Show form only if not successful yet.
        ?>
            <form method="POST">
                <input type="password" id="new_password" name="new_password" placeholder="New Password" required>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required>
                <!-- Show Password checkbox placed exactly like in the login page -->
                <div class="show-password">
                    <input type="checkbox" id="togglePassword">
                    <label for="togglePassword">Show Password</label>
                </div>
                <button type="submit">Reset Password</button>
            </form>
        <?php } ?>
        <a href="login.php">Back to Login</a>
    </div>
    <script>
        const togglePassword = document.querySelector("#togglePassword");
        const newPassword = document.querySelector("#new_password");
        const confirmPassword = document.querySelector("#confirm_password");

        togglePassword.addEventListener("change", function() {
            const type = togglePassword.checked ? "text" : "password";
            newPassword.type = type;
            confirmPassword.type = type;
        });
    </script>
</body>

</html>