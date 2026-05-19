<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'msg' => 'Lỗi kết nối DB']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================================
// GET — Lấy danh sách danh mục
// ============================================================
if ($method === 'GET') {
    $rows = $conn->query("
        SELECT c.*, COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id
        ORDER BY c.id ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['ok' => true, 'data' => $rows]);
    exit;
}

// ============================================================
// POST — Thêm danh mục
// ============================================================
if ($method === 'POST') {
    $name     = trim($input['name']     ?? '');
    $slug     = trim($input['slug']     ?? '');
    $pet_type = trim($input['pet_type'] ?? 'all');
    $icon     = trim($input['icon']     ?? '');

    if (!$name) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu tên danh mục']);
        exit;
    }

    if (!$slug) {
        $slug = strtolower(preg_replace('/\s+/', '-', $name));
        $slug = preg_replace('/[^\w-]/', '', $slug);
    }

    $stmt = $conn->prepare(
        "INSERT INTO categories (name, slug, pet_type) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sss", $name, $slug, $pet_type);
    $stmt->execute();

    echo json_encode(['ok' => true, 'id' => $stmt->insert_id, 'msg' => 'Đã thêm danh mục']);
    exit;
}

// ============================================================
// PUT — Cập nhật danh mục
// ============================================================
if ($method === 'PUT') {
    $id       = intval($input['id']       ?? 0);
    $name     = trim($input['name']       ?? '');
    $slug     = trim($input['slug']       ?? '');
    $pet_type = trim($input['pet_type']   ?? 'all');

    if (!$id || !$name) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu dữ liệu']);
        exit;
    }

    if (!$slug) {
        $slug = strtolower(preg_replace('/\s+/', '-', $name));
        $slug = preg_replace('/[^\w-]/', '', $slug);
    }

    $stmt = $conn->prepare(
        "UPDATE categories SET name = ?, slug = ?, pet_type = ? WHERE id = ?"
    );
    $stmt->bind_param("sssi", $name, $slug, $pet_type, $id);
    $stmt->execute();

    echo json_encode(['ok' => true, 'msg' => 'Đã cập nhật danh mục']);
    exit;
}

// ============================================================
// DELETE — Xóa danh mục
// ============================================================
if ($method === 'DELETE') {
    $id = intval($input['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu ID']);
        exit;
    }

    // Kiểm tra còn sản phẩm không
    $check = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE category_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $count = $check->get_result()->fetch_assoc()['c'];

    if ($count > 0) {
        echo json_encode(['ok' => false, 'msg' => "Không thể xóa — danh mục đang có $count sản phẩm"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode(['ok' => true, 'msg' => 'Đã xóa danh mục']);
    exit;
}

$conn->close();
?>