<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối DB']);
    exit;
}

// Tạo bảng reviews nếu chưa có
$conn->query("
    CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        reviewer_name VARCHAR(100) NOT NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$method = $_SERVER['REQUEST_METHOD'];

// GET — lấy reviews theo product_id
if ($method === 'GET') {
    $product_id = intval($_GET['product_id'] ?? 0);
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Thiếu product_id']);
        exit;
    }
    $stmt = $conn->prepare("SELECT * FROM reviews WHERE product_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Format ngày
    foreach ($rows as &$r) {
        $r['created_at'] = date('d/m/Y', strtotime($r['created_at']));
    }

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// POST — thêm review mới
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $product_id    = intval($input['product_id']    ?? 0);
    $reviewer_name = trim($input['reviewer_name']   ?? '');
    $rating        = intval($input['rating']        ?? 5);
    $comment       = trim($input['comment']         ?? '');

    if (!$product_id || !$reviewer_name || !$comment) {
        echo json_encode(['success' => false, 'message' => 'Thiếu thông tin!']);
        exit;
    }
    if ($rating < 1 || $rating > 5) $rating = 5;

    $stmt = $conn->prepare("INSERT INTO reviews (product_id, reviewer_name, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $product_id, $reviewer_name, $rating, $comment);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Đánh giá đã được gửi!']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method không hợp lệ']);
$conn->close();
?>