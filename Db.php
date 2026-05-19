<?php
$host = 'sql205.infinityfree.com';
$user = 'if0_41962079';
$pass = '1234567890dien'; // password trong infinityfree
$db   = 'if0_41962079_keddy_petshop';
$port = 3306;

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