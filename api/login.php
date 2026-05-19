<?php
session_start();
header('Content-Type: application/json');
require_once '../db.php';

$data = json_decode(file_get_contents("php://input"), true);

$email    = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (!$email || !$password) {
    echo json_encode(['error' => 'Thiếu dữ liệu']);
    exit;
}

$sql = "SELECT * FROM users WHERE email = :e";
$stmt = $pdo->prepare($sql);
$stmt->execute([':e' => $email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['error' => 'Sai email hoặc mật khẩu']);
    exit;
}

// lưu session
$_SESSION['user'] = [
    'id' => $user['id'],
    'name' => $user['username'],
    'role' => $user['role']
];

// QUAN TRỌNG
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

echo json_encode([
    'success'  => true,
    'username' => $user['username'], 
    'role'     => $user['role']
]);