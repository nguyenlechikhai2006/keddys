<?php
// Kết nối XAMPP MySQL
$host = 'localhost';
$dbname = 'keddy_petshop';
$username = 'root';
$password = '1234567890'; // Mặc định XAMPP để trống

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Kết nối thất bại: ' . $e->getMessage()]));
}
?>