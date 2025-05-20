<?php
$name = "root";
$pass = "";
$host = "localhost";
$database = "employee_management_db";

try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $name, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>