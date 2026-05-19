<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'msg' => 'Lỗi kết nối DB']); exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$username || !$password) {
    echo json_encode(['ok' => false, 'msg' => 'Thiếu thông tin']); exit;
}

$stmt = $conn->prepare("SELECT id, username, email, password FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row && password_verify($password, $row['password'])) {
    echo json_encode([
        'ok'       => true,
        'username' => $row['username'],
        'email'    => $row['email'],
        'id'       => $row['id'],
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Sai tên đăng nhập hoặc mật khẩu']);
}

$stmt->close();
$conn->close();
?>