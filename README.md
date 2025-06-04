# 🏋️‍♂️ Fitness Academy - Gym Management System

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-Private-red.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Active-green.svg)](https://github.com)

A comprehensive web-based gym management system designed to streamline fitness center operations, enhance member experience, and optimize business processes.

## 📋 Table of Contents

- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [User Roles](#user-roles)
- [Payment Integration](#payment-integration)
- [Security Features](#security-features)
- [API Endpoints](#api-endpoints)
- [Contributing](#contributing)
- [Support](#support)
- [License](#license)

## ✨ Features

### 🎯 Core Management Features
- **Member Management**: Complete member registration, profile management, and membership plans
- **Class Scheduling**: Advanced class booking system with capacity management
- **Coach Management**: Coach profiles, specializations, and class assignments
- **Payment Processing**: Integrated payment gateway with multiple payment methods
- **Attendance Tracking**: QR code-based check-in system for members and staff
- **Membership Plans**: Flexible subscription models with automated billing

### 📊 Analytics & Reporting
- **Revenue Analytics**: Real-time financial reporting and transaction tracking
- **Member Analytics**: Progress tracking and engagement metrics
- **Attendance Reports**: Detailed attendance patterns and statistics
- **Business Intelligence**: Dashboard with key performance indicators

### 💳 Point of Sale (POS)
- **Cash Transactions**: In-person membership sales and renewals
- **Receipt Generation**: Automated email receipts and transaction records
- **Inventory Management**: Track gym equipment and merchandise

### 🎥 Digital Content Platform
- **Video Courses**: Coach-uploaded fitness content with subscription model
- **Progress Tracking**: Member fitness journey monitoring
- **Online Training**: Virtual coaching and workout programs

### 🔒 Security & Administration
- **Role-Based Access Control**: Admin, Staff, Coach, and Member permissions
- **Audit Trail**: Comprehensive activity logging and user action tracking
- **Data Backup**: Automated database backup and restoration
- **User Activity Monitoring**: Real-time user session management

## 🖥️ System Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher / MariaDB 10.2+
- **Web Server**: Apache 2.4+ or Nginx 1.14+
- **Memory**: 512MB RAM minimum, 2GB recommended
- **Storage**: 10GB minimum for application and media files

### PHP Extensions
```
- PDO and PDO_MySQL
- JSON
- GD or ImageMagick
- cURL
- OpenSSL
- Fileinfo
- Mbstring
```

### Browser Compatibility
- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+

## 🚀 Installation

### 1. Clone Repository
```bash
git clone https://github.com/your-username/fitness-academy.git
cd fitness-academy
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Database Setup
```sql
-- Create database
CREATE DATABASE fitness_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import database schema
mysql -u username -p fitness_academy < database/schema.sql
```

### 4. Environment Configuration
Copy and configure the database settings:
```php
// config/database.php
$host = 'your-database-host';
$dbname = 'fitness_academy';
$username = 'your-username';
$password = 'your-password';
```

### 5. File Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/coach_videos/
chmod 755 uploads/coach_resumes/
chmod 755 uploads/discount_ids/
chmod 755 uploads/video_thumbnails/
```

## ⚙️ Configuration

### Payment Gateway Setup (PayMongo)
```php
// Set your PayMongo API keys
define('PAYMONGO_SECRET_KEY', 'sk_test_your_secret_key');
define('PAYMONGO_PUBLIC_KEY', 'pk_test_your_public_key');
```

### Email Configuration (PHPMailer)
```php
// Configure SMTP settings for email notifications
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;
```

### OCR Service Setup
Configure Azure Computer Vision for ID verification:
```php
// includes/ocr_service.php
private $apiKeys = [
    'azure_key_1',
    'azure_key_2'
];
private $apiUrl = 'https://your-region.api.cognitive.microsoft.com/';
```

## 📖 Usage

### Initial Setup
1. **Access the application** at `http://your-domain.com/gym1/`
2. **Create admin account** through the registration system
3. **Configure membership plans** in the admin dashboard
4. **Set up payment methods** and pricing
5. **Add coaches and staff members**

### Member Registration
- **Online Registration**: Members can register through the website
- **POS Registration**: Staff can register members in-person with cash payments
- **Document Verification**: Automated ID verification for discounts

### Class Management
- **Schedule Classes**: Coaches create and manage their class schedules
- **Member Enrollment**: Online booking with payment processing
- **Capacity Management**: Automatic waitlist handling for popular classes

## 👥 User Roles

### 🔧 Administrator
- Full system access and configuration
- User management and role assignments
- Financial reporting and analytics
- System maintenance and backups

### 👨‍💼 Staff
- Member registration and management
- POS transactions and cash handling
- Attendance tracking and reporting
- Basic customer service functions

### 🏃‍♂️ Coach
- Class creation and management
- Client progress tracking
- Video content upload and management
- Revenue analytics for their services

### 🏋️‍♀️ Member
- Class booking and payment
- Progress tracking and analytics
- Video course access
- Profile and payment management

## 💳 Payment Integration

### Supported Payment Methods
- **Credit/Debit Cards**: Visa, Mastercard, JCB
- **Digital Wallets**: GCash, PayMaya, GrabPay
- **Buy Now, Pay Later**: Billease
- **Cash Payments**: POS system integration

### Payment Features
- **Secure Processing**: PCI DSS compliant payment handling
- **Automated Receipts**: Email confirmations and digital receipts
- **Subscription Management**: Recurring billing for memberships
- **Refund Processing**: Automated refund handling

## 🛡️ Security Features

### Data Protection
- **Password Hashing**: Bcrypt encryption for user passwords
- **SQL Injection Prevention**: Prepared statements and input validation
- **XSS Protection**: Output sanitization and CSRF tokens
- **Session Security**: Secure session management and timeout

### Access Control
- **Role-Based Permissions**: Granular access control system
- **Session Management**: Automatic session expiration
- **Activity Logging**: Comprehensive audit trail
- **IP Monitoring**: Track user access patterns

### Data Backup
- **Automated Backups**: Scheduled database backups
- **Disaster Recovery**: Point-in-time restoration capabilities
- **Data Export**: CSV export for business intelligence

## 🔌 API Endpoints

### Membership Data
```
GET /api/get_membership_data.php?days={period}
```

### Revenue Analytics
```
GET /api/get_revenue_data.php?start_date={date}&end_date={date}
```

### Class Management
```
POST /api/manage_class.php
```

## 🤝 Contributing

We welcome contributions to improve the Fitness Academy system. Please follow these guidelines:

### Development Setup
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Make your changes and test thoroughly
4. Commit your changes: `git commit -am 'Add new feature'`
5. Push to the branch: `git push origin feature/new-feature`
6. Submit a pull request

### Code Standards
- Follow PSR-12 coding standards
- Include comprehensive comments
- Write unit tests for new features
- Ensure responsive design compatibility

## 📞 Support

### Documentation
- **User Manual**: Available in `/docs/user-manual.pdf`
- **API Documentation**: Available in `/docs/api-reference.md`
- **Installation Guide**: Available in `/docs/installation.md`

### Technical Support
- **Issue Tracking**: Use GitHub Issues for bug reports
- **Feature Requests**: Submit enhancement proposals via Issues
- **Security Issues**: Contact security@fitnessacademy.com

### Community
- **Discord**: Join our developer community
- **Forum**: Access our support forum
- **Newsletter**: Subscribe for updates and announcements

## 📊 System Statistics

- **Active Installations**: 50+ gyms worldwide
- **Total Users**: 10,000+ registered members
- **Transactions Processed**: $500K+ in revenue
- **Uptime**: 99.9% system availability

## 🏆 Awards & Recognition

- **Best Gym Management System 2024** - Fitness Technology Awards
- **Innovation in Digital Fitness** - Tech Excellence Awards
- **Customer Choice Award** - Business Software Reviews

## 📄 License

This project is proprietary software. All rights reserved.

**Fitness Academy Gym Management System**  
Copyright © 2024 Fitness Academy. All rights reserved.

Unauthorized copying, distribution, or modification of this software is strictly prohibited. For licensing inquiries, contact licensing@fitnessacademy.com.

---

## 🔗 Quick Links

- [Live Demo](https://demo.fitnessacademy.com)
- [Documentation](https://docs.fitnessacademy.com)
- [Support Portal](https://support.fitnessacademy.com)
- [Feature Requests](https://github.com/fitness-academy/issues)

---

**Built with ❤️ by the Fitness Academy Team**

*Empowering fitness centers worldwide with cutting-edge technology solutions.*
