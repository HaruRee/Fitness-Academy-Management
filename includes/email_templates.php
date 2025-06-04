<?php
/**
 * Centralized Email Template System for Fitness Academy
 * Provides consistent, modern email templates for all system emails
 */

class EmailTemplates {
    
    private static $brandColor = '#e41e26';
    private static $secondaryColor = '#c81a21';
    private static $successColor = '#10b981';
    private static $warningColor = '#f59e0b';
    private static $infoColor = '#2563eb';
    
    /**
     * Base email template structure
     */
    private static function getBaseTemplate($title, $content, $footer = null) {
        $currentYear = date('Y');
        $appName = defined('APP_NAME') ? APP_NAME : 'Fitness Academy';
        
        if ($footer === null) {
            $footer = "
                <div style='text-align: center; color: #6c757d; font-size: 14px; margin-top: 20px;'>
                    <div style='color: " . self::$brandColor . "; font-weight: 700; font-size: 18px; margin-bottom: 10px;'>
                        🏋️‍♂️ {$appName}
                    </div>
                    <div>This is an automated message. Please don't reply to this email.</div>
                    <div>© {$currentYear} {$appName}. All rights reserved.</div>
                </div>
            ";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                * { box-sizing: border-box; }
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f8f9fa; 
                    line-height: 1.6;
                }
                .email-container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background-color: #ffffff; 
                    border-radius: 12px; 
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .header { 
                    background: linear-gradient(135deg, " . self::$brandColor . ", " . self::$secondaryColor . "); 
                    padding: 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    color: #ffffff; 
                    margin: 0; 
                    font-size: 28px; 
                    font-weight: 700; 
                    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                .content { 
                    padding: 40px 30px; 
                }
                .greeting { 
                    font-size: 18px; 
                    color: #333; 
                    margin-bottom: 20px; 
                    font-weight: 600;
                }
                .message { 
                    font-size: 16px; 
                    color: #555; 
                    line-height: 1.6; 
                    margin-bottom: 25px; 
                }
                .button { 
                    text-align: center; 
                    margin: 30px 0; 
                }
                .button a { 
                    background: " . self::$brandColor . "; 
                    color: #ffffff !important; 
                    padding: 15px 30px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    font-weight: 600; 
                    font-size: 16px;
                    display: inline-block;
                    transition: background 0.3s;
                    box-shadow: 0 4px 12px rgba(228, 30, 38, 0.3);
                }
                .button a:hover { 
                    background: " . self::$secondaryColor . "; 
                }
                .alert { 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 20px 0; 
                    font-size: 14px; 
                }
                .alert-warning { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    color: #856404; 
                }
                .alert-info { 
                    background: #e7f3ff; 
                    border: 1px solid #b3d9ff; 
                    color: #0c5460; 
                }
                .alert-success { 
                    background: #d1edcc; 
                    border: 1px solid #a3d977; 
                    color: #155724; 
                }
                .details-box { 
                    background: #f8f9fa; 
                    border: 1px solid #e9ecef; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 20px 0; 
                }
                .details-box h3 { 
                    color: #333; 
                    margin: 0 0 15px 0; 
                    font-size: 18px;
                }
                .details-row { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 8px; 
                    padding: 5px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                .details-row:last-child {
                    border-bottom: none;
                }
                .details-label { 
                    font-weight: 600; 
                    color: #666; 
                }
                .details-value { 
                    color: #333; 
                    font-weight: 500;
                }
                .highlight { 
                    background: linear-gradient(120deg, " . self::$brandColor . "20 0%, " . self::$brandColor . "20 100%);
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-weight: 600;
                }
                .footer { 
                    background: #f8f9fa; 
                    padding: 25px 30px; 
                    border-top: 1px solid #e9ecef; 
                }
                .link-fallback { 
                    word-break: break-all; 
                    background: #f8f9fa; 
                    padding: 15px; 
                    border-radius: 6px; 
                    font-family: 'Courier New', monospace; 
                    font-size: 12px; 
                    color: #495057; 
                    margin-top: 20px; 
                    border: 1px solid #e9ecef;
                }
                .code { 
                    background: " . self::$brandColor . "; 
                    color: white; 
                    padding: 8px 16px; 
                    border-radius: 6px; 
                    font-family: 'Courier New', monospace; 
                    font-size: 18px; 
                    font-weight: bold; 
                    letter-spacing: 2px;
                    display: inline-block;
                    margin: 10px 0;
                }
                @media only screen and (max-width: 600px) {
                    .email-container { margin: 10px; }
                    .content { padding: 20px 15px; }
                    .header { padding: 20px 15px; }
                    .header h1 { font-size: 24px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🏋️‍♂️ {$appName}</h1>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    {$footer}
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Password Reset Email Template
     */
    public static function passwordReset($username, $resetLink) {
        $appName = defined('APP_NAME') ? APP_NAME : 'Fitness Academy';
        
        $content = "
            <div class='greeting'>Hello <strong>" . htmlspecialchars($username) . "</strong>,</div>
            
            <div class='message'>
                We received a request to reset your password for your {$appName} account. 
                If you made this request, click the button below to set a new password:
            </div>
            
            <div class='button'>
                <a href='{$resetLink}'>Reset My Password</a>
            </div>
            
            <div class='alert alert-warning'>
                <strong>⚠️ Security Notice:</strong><br>
                • This link will expire in <span class='highlight'>1 hour</span> for your security<br>
                • If you didn't request this reset, please ignore this email<br>
                • Never share this link with anyone
            </div>
            
            <div class='link-fallback'>
                If the button doesn't work, copy and paste this link into your browser:<br>
                {$resetLink}
            </div>
        ";
        
        return self::getBaseTemplate("Password Reset - {$appName}", $content);
    }

    /**
     * Email Verification Template
     */
    public static function emailVerification($username, $verificationCode) {
        $appName = defined('APP_NAME') ? APP_NAME : 'Fitness Academy';
        
        $content = "
            <div class='greeting'>Welcome <strong>" . htmlspecialchars($username) . "</strong>!</div>
            
            <div class='message'>
                Thank you for joining {$appName}! To complete your registration and activate your account, 
                please verify your email address using the verification code below:
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <div class='code'>{$verificationCode}</div>
            </div>
            
            <div class='alert alert-info'>
                <strong>📝 Instructions:</strong><br>
                • Enter this code on the verification page<br>
                • This code will expire in <span class='highlight'>15 minutes</span><br>
                • If you didn't create this account, please ignore this email
            </div>
            
            <div class='message'>
                Once verified, you'll have full access to all our features including class bookings, 
                payment tracking, and exclusive member content.
            </div>
        ";
        
        return self::getBaseTemplate("Email Verification - {$appName}", $content);
    }

    /**
     * Welcome Email with Account Details
     */
    public static function welcomeWithCredentials($customerName, $email, $password, $planDetails = null) {
        $appName = defined('APP_NAME') ? APP_NAME : 'Fitness Academy';
        $loginUrl = defined('BASE_URL') ? BASE_URL . '/includes/login.php' : '#';
        
        $planSection = '';
        if ($planDetails) {
            $planSection = "
                <div class='details-box'>
                    <h3>🎯 Your Membership Plan</h3>
                    <div class='details-row'>
                        <span class='details-label'>Plan:</span>
                        <span class='details-value'>" . htmlspecialchars($planDetails['name']) . "</span>
                    </div>
                    <div class='details-row'>
                        <span class='details-label'>Duration:</span>
                        <span class='details-value'>" . htmlspecialchars($planDetails['duration'] ?? 'N/A') . "</span>
                    </div>
                    <div class='details-row'>
                        <span class='details-label'>Start Date:</span>
                        <span class='details-value'>" . date('F d, Y') . "</span>
                    </div>
                </div>
            ";
        }
        
        $content = "
            <div class='greeting'>Welcome to {$appName}, <strong>" . htmlspecialchars($customerName) . "</strong>!</div>
            
            <div class='message'>
                Congratulations! Your membership has been activated and your account is ready to use. 
                We're excited to have you as part of our fitness community!
            </div>
            
            {$planSection}
            
            <div class='details-box'>
                <h3>🔐 Your Account Details</h3>
                <div class='details-row'>
                    <span class='details-label'>Email:</span>
                    <span class='details-value'>" . htmlspecialchars($email) . "</span>
                </div>
                <div class='details-row'>
                    <span class='details-label'>Temporary Password:</span>
                    <span class='details-value code' style='color: white; background: " . self::$brandColor . ";'>{$password}</span>
                </div>
            </div>
            
            <div class='alert alert-warning'>
                <strong>🔒 Important Security Notice:</strong><br>
                Please log in and <span class='highlight'>change your password immediately</span> for security reasons.
            </div>
            
            <div class='button'>
                <a href='{$loginUrl}'>Login to Your Account</a>
            </div>
            
            <div class='message'>
                <strong>🚀 What's Next?</strong>
                <ol style='margin: 15px 0; padding-left: 20px;'>
                    <li>Log in using your credentials above</li>
                    <li>Change your password for security</li>
                    <li>Complete your profile information</li>
                    <li>Browse available classes and book your first session</li>
                    <li>Download our mobile app for easy access</li>
                </ol>
            </div>
        ";
        
        return self::getBaseTemplate("Welcome to {$appName}!", $content);
    }

    /**
     * Payment Receipt Email Template
     */
    public static function paymentReceipt($customerName, $transactionDetails) {
        $appName = defined('APP_NAME') ? APP_NAME : 'Fitness Academy';
        $receiptDate = date('F d, Y \a\t h:i A');
        
        $content = "
            <div class='greeting'>Thank you, <strong>" . htmlspecialchars($customerName) . "</strong>!</div>
            
            <div class='message'>
                Your payment has been processed successfully. Here are your transaction details:
            </div>
            
            <div class='details-box'>
                <h3>💳 Transaction Details</h3>
                <div class='details-row'>
                    <span class='details-label'>Transaction ID:</span>
                    <span class='details-value'>" . htmlspecialchars($transactionDetails['id']) . "</span>
                </div>
                <div class='details-row'>
                    <span class='details-label'>Date & Time:</span>
                    <span class='details-value'>{$receiptDate}</span>
                </div>
                <div class='details-row'>
                    <span class='details-label'>Plan:</span>
                    <span class='details-value'>" . htmlspecialchars($transactionDetails['plan']) . "</span>
                </div>
                <div class='details-row'>
                    <span class='details-label'>Amount:</span>
                    <span class='details-value highlight'>₱" . number_format($transactionDetails['amount'], 2) . "</span>
                </div>
                <div class='details-row'>
                    <span class='details-label'>Payment Method:</span>
                    <span class='details-value'>" . htmlspecialchars($transactionDetails['method']) . "</span>
                </div>
        ";
        
        if (isset($transactionDetails['cash_received'])) {
            $content .= "
                <div class='details-row'>
                    <span class='details-label'>Cash Received:</span>
                    <span class='details-value'>₱" . number_format($transactionDetails['cash_received'], 2) . "</span>
                </div>
                <div class='details-row'>
                    <span class='details-label'>Change Given:</span>
                    <span class='details-value'>₱" . number_format($transactionDetails['change'], 2) . "</span>
                </div>
            ";
        }
        
        $content .= "
            </div>
            
            <div class='alert alert-success'>
                <strong>✅ Payment Confirmed!</strong><br>
                Your membership is now active and you can start using our facilities immediately.
            </div>
            
            <div class='message'>
                <strong>📞 Need Help?</strong><br>
                If you have any questions or need assistance, please don't hesitate to contact us:
                <ul style='margin: 10px 0; padding-left: 20px;'>
                    <li>Visit us at our gym location</li>
                    <li>Call us at +63 917 145 9059</li>
                    <li>Email us at " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'support@fitnessacademy.com') . "</li>
                </ul>
            </div>
        ";
        
        return self::getBaseTemplate("Payment Receipt - {$appName}", $content);
    }

    /**
     * General Notification Email Template
     */
    public static function notification($title, $message, $username = null, $actionButton = null) {
        $appName = defined('APP_NAME') ? APP_NAME : 'Fitness Academy';
        
        $greeting = $username ? "Hello <strong>" . htmlspecialchars($username) . "</strong>," : "Hello,";
        
        $buttonHtml = '';
        if ($actionButton) {
            $buttonHtml = "
                <div class='button'>
                    <a href='" . htmlspecialchars($actionButton['url']) . "'>" . htmlspecialchars($actionButton['text']) . "</a>
                </div>
            ";
        }
        
        $content = "
            <div class='greeting'>{$greeting}</div>
            
            <div class='message'>
                {$message}
            </div>
            
            {$buttonHtml}
        ";
        
        return self::getBaseTemplate("{$title} - {$appName}", $content);
    }

    /**
     * Generate plain text version of email (for AltBody)
     */
    public static function getPlainTextVersion($htmlContent, $title) {
        $appName = defined('APP_NAME') ? APP_NAME : 'Fitness Academy';
        
        // Strip HTML tags and decode entities
        $text = html_entity_decode(strip_tags($htmlContent), ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return "{$title}\n\n{$text}\n\n--\n{$appName} Team\n© " . date('Y') . " {$appName}. All rights reserved.";
    }
}
?>
