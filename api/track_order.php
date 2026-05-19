<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối DB']);
    exit;
}

$order_code = trim($_GET['order_code'] ?? '');
$phone      = trim($_GET['phone']      ?? '');

if (!$order_code || !$phone) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã đơn và số điện thoại']);
    exit;
}

$stmt = $conn->prepare("
    SELECT order_code, customer_name, customer_phone, customer_email,
           shipping_address, total, payment_method, status, note, created_at
    FROM orders
    WHERE order_code = ? AND customer_phone = ?
    LIMIT 1
");
$stmt->bind_param("ss", $order_code, $phone);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng. Vui lòng kiểm tra lại mã đơn và số điện thoại!']);
    exit;
}

// Lấy danh sách sản phẩm trong đơn
$stmt2 = $conn->prepare("
    SELECT oi.product_name, oi.product_image, oi.variant, oi.price, oi.quantity, oi.subtotal
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_code = ?
");
$stmt2->bind_param("s", $order_code);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$order['items'] = $items;

echo json_encode(['success' => true, 'data' => $order]);
$conn->close();
?>