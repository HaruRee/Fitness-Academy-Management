<?php
// Azure MySQL Database Configuration Template
// Copy this file to database.php and update with your actual credentials

$host = 'your-azure-mysql-host.mysql.database.azure.com';
$dbname = 'your-database-name';
$username = 'your-username';
$password = 'your-password';

try {
    // For Azure MySQL, we don't need the socket parameter
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;port=3306;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_SSL_CA => true,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please check your database configuration.");
}
?>
