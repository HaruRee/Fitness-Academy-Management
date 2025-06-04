# API Configuration Setup

This document explains how to set up API keys for the gym management system.

## Security Notice

⚠️ **IMPORTANT**: Never commit API keys to version control! The `api_config.php` file is excluded from Git.

## Setup Instructions

1. **Copy the template file:**
   ```bash
   copy config\api_config.template.php config\api_config.php
   ```

2. **Update API keys in `config/api_config.php`:**

### PayMongo Configuration
- **PAYMONGO_SECRET_KEY**: Your PayMongo secret key (starts with `sk_test_` for test mode or `sk_live_` for production)
- **PAYMONGO_PUBLIC_KEY**: Your PayMongo public key (starts with `pk_test_` for test mode or `pk_live_` for production)

### Microsoft Azure Computer Vision API
- **AZURE_CV_API_KEYS**: Array of Azure Computer Vision API keys for OCR functionality
- **AZURE_CV_API_URL**: Your Azure Computer Vision endpoint URL

## Files Using API Keys

The following files have been updated to use the centralized API configuration:

### PayMongo Integration Files:
- `includes/register.php`
- `includes/process_subscription.php`
- `includes/process_payment.php`
- `includes/process_membership_payment.php`
- `includes/payment_success.php`
- `includes/payment_failed.php`
- `includes/membership_payment_success.php`
- `includes/enroll_class.php`
- `includes/class_enrollment_success.php`

### Azure OCR Integration Files:
- `includes/ocr_service.php`

## Git Configuration

The `.gitignore` file has been updated to exclude:
- `config/api_config.php` (contains actual API keys)
- `config/database.php` (contains database credentials)

The template file `config/api_config.template.php` is safe to commit as it contains placeholder values.

## Testing vs Production

- **Test Mode**: Use API keys that start with `sk_test_` and `pk_test_`
- **Production Mode**: Use API keys that start with `sk_live_` and `pk_live_`

## Troubleshooting

1. **Missing API config error**: Make sure `config/api_config.php` exists and contains all required constants
2. **PayMongo errors**: Verify your API keys are correct and match your environment (test/live)
3. **Azure OCR errors**: Check that your Azure Computer Vision service is active and the keys are valid

## Security Best Practices

1. Never share API keys in code repositories
2. Use environment-specific keys (test for development, live for production)
3. Regularly rotate API keys
4. Monitor API usage for unusual activity
5. Keep the `config/api_config.php` file permissions restricted
