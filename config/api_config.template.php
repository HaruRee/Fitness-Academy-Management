<?php
/**
 * API Configuration Template File
 * 
 * Copy this file to api_config.php and update with your actual API keys.
 * This template file can be safely committed to version control.
 * 
 * Includes configurations for:
 * - PayMongo payment processing
 * - Azure Computer Vision OCR
 * - AI recommendation system (OpenRouter/DeepSeek)
 * - SMTP email settings
 */

// Domain Configuration
define('APP_DOMAIN', 'https://yourdomain.com'); // Change this to your actual domain
define('APP_NAME', 'Your App Name'); // Change this to your app name

// PayMongo API Configuration
define('PAYMONGO_SECRET_KEY', 'your_paymongo_secret_key_here');
define('PAYMONGO_PUBLIC_KEY', 'your_paymongo_public_key_here');

// Microsoft Azure Computer Vision API Configuration
define('AZURE_CV_API_KEYS', [
    'your_azure_cv_api_key_1_here', // Azure Key 1
    'your_azure_cv_api_key_2_here'  // Azure Key 2
]);
define('AZURE_CV_API_URL', 'https://your-region.cognitiveservices.azure.com/vision/v3.2/read/analyze');

// AI Recommendation System Configuration
define('OPENROUTER_API_KEY', 'your_openrouter_api_key_here');
define('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1/chat/completions');
define('AI_MODEL', 'deepseek/deepseek-chat-v3-0324:free'); // AI model for recommendations

// Add other API keys here as needed
// define('OTHER_API_KEY', 'your_api_key_here');

// Email Configuration (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password_here');
define('SMTP_PORT', 465);
define('SMTP_FROM_EMAIL', 'your_email@gmail.com');
define('SMTP_FROM_NAME', 'Your App Name');

// Ensure this file is only included once
if (!defined('API_CONFIG_LOADED')) {
    define('API_CONFIG_LOADED', true);
}
?>