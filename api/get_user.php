<?php
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");

if ($conn->connect_error) {
    echo json_encode(['error' => 'Kết nối thất bại']);
    exit;
}

$username = $_GET['username'] ?? '';

$stmt = $conn->prepare("SELECT username, email, phone, address FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Không tìm thấy user']);
}

$conn->close();
?>