<?php
$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '1234567890';
$db   = getenv('MYSQLDATABASE') ?: 'keddy_petshop';
$port = getenv('MYSQLPORT')     ?: 3306;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Lỗi DB']); exit;
}
?>