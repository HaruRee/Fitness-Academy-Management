<?php
require '../config/database.php';
require '../config/api_config.php'; // Load API configuration
require '../vendor/autoload.php'; // Load PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Check if the email exists
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        // Use configurable domain instead of hardcoded URL
        $resetLink = APP_DOMAIN . "/includes/reset_password.php?token=$token";

        // Insert token and expiry into the password_resets table
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $stmt->execute([$email, $token]);

        // Send email
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPDebug  = 0; // Set to SMTP::DEBUG_SERVER for debug output

            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email, $user['username'] ?? 'User');
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME . ' Support');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - ' . APP_NAME;
            $mail->Body    = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Password Reset - " . APP_NAME . "</title>
                    <style>
                        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
                        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                        .header { background: linear-gradient(135deg, #e41e26, #c81a21); padding: 30px; text-align: center; }
                        .header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; }
                        .content { padding: 40px 30px; }
                        .greeting { font-size: 18px; color: #333; margin-bottom: 20px; }
                        .message { font-size: 16px; color: #555; line-height: 1.6; margin-bottom: 30px; }
                        .reset-button { text-align: center; margin: 30px 0; }
                        .reset-button a { 
                            background: #e41e26; 
                            color: #ffffff !important; 
                            padding: 15px 30px; 
                            text-decoration: none; 
                            border-radius: 6px; 
                            font-weight: 600; 
                            font-size: 16px;
                            display: inline-block;
                            transition: background 0.3s;
                        }
                        .reset-button a:hover { background: #c81a21; }
                        .security-note { 
                            background: #fff3cd; 
                            border: 1px solid #ffeaa7; 
                            border-radius: 6px; 
                            padding: 15px; 
                            margin: 20px 0; 
                            font-size: 14px; 
                            color: #856404; 
                        }
                        .footer { 
                            background: #f8f9fa; 
                            padding: 20px 30px; 
                            text-align: center; 
                            border-top: 1px solid #e9ecef; 
                            font-size: 14px; 
                            color: #6c757d; 
                        }
                        .logo { color: #e41e26; font-weight: 700; font-size: 20px; margin-bottom: 10px; }
                        .link-fallback { 
                            word-break: break-all; 
                            background: #f8f9fa; 
                            padding: 10px; 
                            border-radius: 4px; 
                            font-family: monospace; 
                            font-size: 12px; 
                            color: #495057; 
                            margin-top: 15px; 
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>🏋️‍♂️ " . APP_NAME . "</h1>
                        </div>
                        <div class='content'>
                            <div class='greeting'>Hello <strong>" . htmlspecialchars($user['username'] ?? 'Member') . "</strong>,</div>
                            
                            <div class='message'>
                                We received a request to reset your password for your " . APP_NAME . " account. 
                                If you made this request, click the button below to set a new password:
                            </div>
                            
                            <div class='reset-button'>
                                <a href='$resetLink'>Reset My Password</a>
                            </div>
                            
                            <div class='security-note'>
                                <strong>⚠️ Security Notice:</strong><br>
                                • This link will expire in 1 hour for your security<br>
                                • If you didn't request this reset, please ignore this email<br>
                                • Never share this link with anyone
                            </div>
                            
                            <div class='link-fallback'>
                                If the button doesn't work, copy and paste this link into your browser:<br>
                                $resetLink
                            </div>
                        </div>
                        <div class='footer'>
                            <div class='logo'>" . APP_NAME . "</div>
                            <div>This is an automated message. Please don't reply to this email.</div>
                            <div>© " . date('Y') . " " . APP_NAME . ". All rights reserved.</div>
                        </div>
                    </div>
                </body>
                </html>
            ";
            $mail->AltBody = "Hello " . htmlspecialchars($user['username'] ?? 'Member') . ",

We received a request to reset your password for your " . APP_NAME . " account.

Reset your password here: $resetLink

This link will expire in 1 hour for your security.

If you didn't request this reset, please ignore this email.

--
" . APP_NAME . " Team
© " . date('Y') . " " . APP_NAME . ". All rights reserved.";

            $mail->send();
            $message = "A password reset link has been sent to your email.";
        } catch (Exception $e) {
            $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $message = "No account found with that email address.";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Forgot Password | <?php echo defined('APP_NAME') ? APP_NAME : 'Fitness Academy'; ?></title>
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

        .forgot-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 73px);
            padding: 2rem;
        }

        .forgot-container {
            background: #fff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .forgot-container .icon {
            background: linear-gradient(135deg, #e41e26, #c81a21);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }

        .forgot-container h1 {
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            color: #333;
            text-align: center;
        }

        .forgot-container p {
            color: #666;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 0.95rem;
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
            box-shadow: 0 0 0 3px rgba(228, 30, 38, 0.1);
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

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .links {
            margin-top: 1.5rem;
            text-align: center;
        }

        .links a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .links a:hover {
            color: #e41e26;
            text-decoration: underline;
        }

        .success-message {
            color: #10b981;
            font-size: 0.9rem;
            margin: 1rem 0;
            text-align: center;
            background: rgba(16, 185, 129, 0.1);
            padding: 0.8rem;
            border-radius: 4px;
            border-left: 3px solid #10b981;
        }

        .error-message {
            color: #e41e26;
            font-size: 0.9rem;
            margin: 1rem 0;
            text-align: center;
            background: rgba(228, 30, 38, 0.1);
            padding: 0.8rem;
            border-radius: 4px;
            border-left: 3px solid #e41e26;
        }

        .info-message {
            color: #3b82f6;
            font-size: 0.9rem;
            margin: 1rem 0;
            text-align: center;
            background: rgba(59, 130, 246, 0.1);
            padding: 0.8rem;
            border-radius: 4px;
            border-left: 3px solid #3b82f6;
        }

        @media (max-width: 768px) {
            .forgot-container {
                padding: 1.5rem;
            }
            
            .navbar {
                padding: 1rem 3%;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="logo">
            <a href="../index.php"><img src="../assets/images/fa_logo.png" alt="<?php echo defined('APP_NAME') ? APP_NAME : 'Fitness Academy'; ?>"></a>
            <a href="../index.php" class="logo-text"><?php echo defined('APP_NAME') ? APP_NAME : 'Fitness Academy'; ?></a>
        </div>
        <div class="nav-links">
            <a href="login.php">Sign In</a>
            <a href="register.php">Join now</a>
        </div>
    </nav>

    <div class="forgot-page">
        <div class="forgot-container">
            <div class="icon">
                <i class="fas fa-key"></i>
            </div>
            <h1>Forgot your password?</h1>
            <p>No worries! Enter your email address and we'll send you a link to reset your password.</p>

            <?php if (!empty($message)): ?>
                <?php if (strpos($message, 'sent') !== false): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i> Check your email inbox and spam folder for the reset link.
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Enter your email address" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>

            <div class="links">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>

</html>