<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host   = 'localhost';
$db     = 'keddy_petshop';
$user   = 'root';
$pass   = '1234567890';
$charset = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$username = $_GET['username'] ?? '';
if (!$username) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing username']);
    exit;
}

// Lấy email từ bảng users theo username
$stmt = $pdo->prepare("SELECT id, email FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$userId    = $userRow['id'];
$userEmail = $userRow['email'];

// Lấy đơn hàng theo user_id HOẶC customer_email
// (vì một số đơn có user_id NULL, chỉ lưu email)
$stmt = $pdo->prepare("
    SELECT 
        o.id,
        o.order_code,
        o.customer_name,
        o.customer_phone,
        o.customer_email,
        o.shipping_address AS address,
        o.total,
        o.status,
        o.created_at
    FROM orders o
    WHERE o.user_id = ?
       OR o.customer_email = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId, $userEmail]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $orders]);