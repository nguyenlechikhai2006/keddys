<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = intval($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    $search = '%' . trim($_GET['search'] ?? '') . '%';

    $where  = "WHERE u.role != 'admin'";
    $params = [];
    $types  = '';

    if (trim($_GET['search'] ?? '') !== '') {
        $where .= " AND (u.username LIKE ? OR u.email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $types   .= 'ss';
    }

    // Đếm tổng
    $countSQL = "SELECT COUNT(*) AS c FROM users u $where";
    if ($types) {
        $stmtC = $conn->prepare($countSQL);
        $stmtC->bind_param($types, ...$params);
        $stmtC->execute();
        $total = $stmtC->get_result()->fetch_assoc()['c'];
    } else {
        $total = $conn->query($countSQL)->fetch_assoc()['c'];
    }

    // Lấy users + đếm đơn hàng + tổng chi tiêu theo email
    $dataSQL = "
        SELECT u.*,
            COUNT(o.id) AS order_count,
            COALESCE(SUM(o.total), 0) AS total_spent
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id OR o.customer_email = u.email
        $where
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt = $conn->prepare($dataSQL);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Ẩn password
    foreach ($rows as &$r) unset($r['password']);

    echo json_encode(['ok' => true, 'data' => $rows, 'total' => (int)$total]);
}

elseif ($method === 'PUT') {
    $id        = intval($input['id'] ?? 0);
    $is_active = intval($input['is_active'] ?? 0);

    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Thiếu ID']); exit; }

    // Thêm cột is_active nếu chưa có
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT DEFAULT 1");

    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_active, $id);
    $stmt->execute();
    echo json_encode(['ok' => true, 'msg' => 'Đã cập nhật tài khoản']);
}

$conn->close();
?>