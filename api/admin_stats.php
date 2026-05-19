<?php
header('Content-Type: application/json; charset=utf-8');
$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

$data = [];

// Tổng đơn
$res = $conn->query("SELECT COUNT(*) as total FROM orders");
$data['orders'] = $res->fetch_assoc()['total'] ?? 0;

// Doanh thu
$res = $conn->query("SELECT SUM(total) as revenue FROM orders WHERE status='done'");
$data['revenue'] = $res->fetch_assoc()['revenue'] ?? 0;

// Khách hàng
$res = $conn->query("SELECT COUNT(*) as total FROM users");
$data['users'] = $res->fetch_assoc()['total'] ?? 0;

// Sản phẩm
$res = $conn->query("SELECT COUNT(*) as total FROM products");
$data['products'] = $res->fetch_assoc()['total'] ?? 0;

echo json_encode($data);