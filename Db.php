<?php
$host = 'sql205.infinityfree.com'; // host do InfinityFree cấp
$dbname = 'if0_41962079_keddy_petshop'; // tên DB trên InfinityFree
$username = 'if0_41962079';             // username InfinityFree cấp
$password = '1234567890dien';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Kết nối thất bại: ' . $e->getMessage()]));
}
?>$host = 'sql[số].infinityfree.com'; // host do InfinityFree cấp
$dbname = 'if0_xxxxx_keddy_petshop'; // tên DB trên InfinityFree
$username = 'if0_xxxxx';             // username InfinityFree cấp
$password = 'mật_khẩu_của_bạn';