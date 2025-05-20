<?php
$name = "root";
$pass = "";
$host = "localhost";
$database = "employee_management_db";

try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $name, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create notifications table if not exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('leave', 'attendance', 'payroll', 'system') NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>