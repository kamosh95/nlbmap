<?php
// Database Configuration
$host = 'localhost';
$dbname = 'seller_map';
$username = 'root';
$password = 'SriLanka_4321';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET names utf8mb4");
} catch (PDOException $e) {
    die("Critical Error: Could not connect to the database. Please check your configuration.");
}
?>