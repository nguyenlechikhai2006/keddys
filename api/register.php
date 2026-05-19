<?php
header('Content-Type: application/json');
require_once '../db.php';

$data = json_decode(file_get_contents("php://input"), true);

$username = trim($data['username'] ?? '');
$email    = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (!$username || !$email || !$password) {
    echo json_encode(['error' => 'Thiếu dữ liệu']);
    exit;
}

// hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

try {
    $sql = "INSERT INTO users (username, email, password) 
            VALUES (:u, :e, :p)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':u' => $username,
        ':e' => $email,
        ':p' => $hashed
    ]);

    echo json_encode(
        ['success' => 'Đăng ký thành công']);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Email đã tồn tại']);
}