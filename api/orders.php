<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
        if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("
            SELECT o.*, u.username, u.email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            if (empty($row['username'])) $row['username'] = $row['customer_name'] ?? '—';
            if (empty($row['email']))    $row['email']    = $row['customer_email'] ?? '—';
            if (empty($row['address']))  $row['address']  = $row['shipping_address'] ?? '—';
            echo json_encode(['ok' => true, 'data' => [$row]]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy đơn hàng']);
        }
        exit;
    }
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = 10;
    $offset = ($page - 1) * $limit;
    $status = trim($_GET['status'] ?? '');
    $search = '%' . trim($_GET['search'] ?? '') . '%';

    // WHERE động
    $where  = [];
    $params = [];
    $types  = '';

    // Tìm theo mã đơn hoặc tên khách
    if (trim($_GET['search'] ?? '') !== '') {
        $where[]  = "(o.order_code LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types   .= 'sss';
    }

    // Lọc theo trạng thái
    if ($status !== '') {
        $where[]  = "o.status = ?";
        $params[] = $status;
        $types   .= 's';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Đếm tổng
    $countParams = $params;
    $countTypes  = $types;
    $countSQL = "SELECT COUNT(*) AS c FROM orders o $whereSQL";
    if ($countTypes) {
        $stmtC = $conn->prepare($countSQL);
        $stmtC->bind_param($countTypes, ...$countParams);
        $stmtC->execute();
        $total = $stmtC->get_result()->fetch_assoc()['c'];
    } else {
        $total = $conn->query($countSQL)->fetch_assoc()['c'];
    }

    // Lấy data — LEFT JOIN users để lấy username nếu có
    $dataSQL = "SELECT o.*, u.username
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                $whereSQL
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt = $conn->prepare($dataSQL);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Nếu username null thì dùng customer_name
    foreach ($rows as &$row) {
        if (empty($row['username'])) {
            $row['username'] = $row['customer_name'] ?? '—';
        }
    }

    echo json_encode(['data' => $rows, 'total' => (int)$total, 'ok' => true]);
}

elseif ($method === 'PUT') {
    $id     = intval($input['id'] ?? 0);
    $status = trim($input['status'] ?? '');
$statusMap = [
    'Chờ xử lý'  => 'pending',
    'Đang giao'  => 'shipping',
    'Hoàn thành' => 'done',
    'Đã hủy'     => 'cancelled',
    // Cho phép cả tiếng Anh
    'pending'    => 'pending',
    'shipping'   => 'shipping',
    'done'       => 'done',
    'cancelled'  => 'cancelled',
];

if (!$id || !isset($statusMap[$status])) {
    echo json_encode(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ']); exit;
}
$status = $statusMap[$status]; // convert sang tiếng Anh

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    echo json_encode(['ok' => true, 'msg' => 'Đã cập nhật trạng thái']);
}

elseif ($method === 'DELETE') {
    $id = intval($input['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Thiếu ID']); exit; }

    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['ok' => true, 'msg' => 'Đã xóa đơn hàng']);
}

$conn->close();
?>