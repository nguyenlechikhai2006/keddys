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
// GET — Lấy sản phẩm
// ============================================================
if ($method === 'GET') {

    // Lấy 1 sản phẩm theo id
    $id = intval($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.pet_type
                                FROM products p
                                LEFT JOIN categories c ON p.category_id = c.id
                                WHERE p.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    // ── Tham số ──
    $page     = max(1, intval($_GET['page']     ?? 1));
    $per_page = max(1, intval($_GET['per_page'] ?? intval($_GET['limit'] ?? 12)));
    $offset   = ($page - 1) * $per_page;
    $sort     = $_GET['sort']   ?? 'default';

    $pet    = trim($_GET['pet']    ?? '');
    $cat    = trim($_GET['cat']    ?? '');   // slug từ URL
    $search = trim($_GET['search'] ?? '');
    $filter = trim($_GET['filter'] ?? '');
    $minprice = intval($_GET['minprice'] ?? 0);
    $maxprice = intval($_GET['maxprice'] ?? 0);

    // ── Xây WHERE ──
    $where  = [];
    $params = [];
    $types  = '';

    // Tìm kiếm tên
    if ($search !== '') {
        $where[]  = "p.name LIKE ?";
        $params[] = '%' . $search . '%';
        $types   .= 's';
    }

    // Lọc theo pet_type (cho / meo)
    // Lấy tất cả category_id có pet_type = 'cho' hoặc 'meo'
    if ($pet !== '') {
        $where[]  = "(c.pet_type = ? OR c.pet_type = 'all')";
        $params[] = $pet;
        $types   .= 's';
        // Thực ra nếu pet=cho thì chỉ lấy pet_type='cho', không lấy 'all' (phụ kiện)
        // Tuỳ ý bạn — tôi để chỉ lọc đúng pet_type:
        $where  = array_filter($where, fn($w) => $w !== "(c.pet_type = ? OR c.pet_type = 'all')");
        $params = array_filter($params, fn($p, $i) => $i !== array_key_last($params), ARRAY_FILTER_USE_BOTH);
        $params = array_values($params);
        $types  = substr($types, 0, -1);

        $where[]  = "c.pet_type = ?";
        $params[] = $pet;
        $types   .= 's';
    }

    // Lọc theo category slug
    if ($cat !== '') {
        // Tìm category_id từ slug (hỗ trợ cả parent lẫn child)
        $stmtCat = $conn->prepare("SELECT id, parent_id FROM categories WHERE slug = ? LIMIT 1");
        $stmtCat->bind_param("s", $cat);
        $stmtCat->execute();
        $catRow = $stmtCat->get_result()->fetch_assoc();

        if ($catRow) {
            // Nếu là parent category → lấy tất cả con của nó
            $stmtChildren = $conn->prepare("SELECT id FROM categories WHERE id = ? OR parent_id = ?");
            $stmtChildren->bind_param("ii", $catRow['id'], $catRow['id']);
            $stmtChildren->execute();
            $childIds = array_column($stmtChildren->get_result()->fetch_all(MYSQLI_ASSOC), 'id');

            if ($childIds) {
                $placeholders = implode(',', array_fill(0, count($childIds), '?'));
                $where[]  = "p.category_id IN ($placeholders)";
                foreach ($childIds as $cid) { $params[] = $cid; $types .= 'i'; }
            }
        } else {
            // Slug không tìm thấy → không có sản phẩm nào
            $where[] = "1=0";
        }
    }

    // Lọc hàng mới
    if ($filter === 'new') {
        $where[] = "p.is_new = 1";
    }

    // Lọc giá
    if ($minprice > 0) { $where[] = "p.price >= ?"; $params[] = $minprice; $types .= 'i'; }
    if ($maxprice > 0) { $where[] = "p.price <= ?"; $params[] = $maxprice; $types .= 'i'; }

    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // ── ORDER BY ──
    $orderMap = [
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'newest'     => 'p.id DESC',
        'name_asc'   => 'p.name ASC',
        'default'    => 'p.id DESC',
    ];
    $orderSQL = $orderMap[$sort] ?? 'p.id DESC';

    // BASE query với JOIN categories
    $baseFrom = "FROM products p LEFT JOIN categories c ON p.category_id = c.id";

    // ── Đếm tổng ──
    $countSQL = "SELECT COUNT(*) AS cnt $baseFrom $whereSQL";
    if ($types !== '') {
        $stmtC = $conn->prepare($countSQL);
        $stmtC->bind_param($types, ...$params);
        $stmtC->execute();
        $total = $stmtC->get_result()->fetch_assoc()['cnt'];
    } else {
        $total = $conn->query($countSQL)->fetch_assoc()['cnt'];
    }

    $total_pages = max(1, ceil($total / $per_page));

    // ── Lấy data ──
    $dataSQL    = "SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.pet_type $baseFrom $whereSQL ORDER BY $orderSQL LIMIT ? OFFSET ?";
    $dataParams = [...$params, $per_page, $offset];
    $dataTypes  = $types . 'ii';

    $stmtD = $conn->prepare($dataSQL);
    $stmtD->bind_param($dataTypes, ...$dataParams);
    $stmtD->execute();
    $rows = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'ok'          => true,
        'products'    => $rows,
        'data'        => $rows,
        'total'       => (int)$total,
        'total_pages' => (int)$total_pages,
        'page'        => $page,
        'per_page'    => $per_page,
    ]);
    exit;
}

// ============================================================
// POST — Thêm sản phẩm (admin)
// ============================================================
if ($method === 'POST') {
    $name        = trim($input['name']        ?? '');
    $category_id = intval($input['category_id'] ?? intval($input['category'] ?? 0));
    $price       = intval($input['price']     ?? 0);
    $old_price   = intval($input['old_price'] ?? 0);
    $stock       = intval($input['stock']     ?? 0);
    $desc        = trim($input['description'] ?? '');
    $image       = trim($input['image']       ?? '');
    $is_new      = intval($input['is_new']    ?? 0);

    if (!$name || !$price) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu tên hoặc giá']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO products (name, category_id, price, old_price, stock, description, image, is_new)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("siiiissi", $name, $category_id, $price, $old_price, $stock, $desc, $image, $is_new);
    $stmt->execute();
    echo json_encode(['ok' => true, 'id' => $stmt->insert_id, 'msg' => 'Đã thêm sản phẩm']);
    exit;
}

// ============================================================
// PUT — Cập nhật sản phẩm (admin)
// ============================================================
if ($method === 'PUT') {
    $id          = intval($input['id']          ?? 0);
    $name        = trim($input['name']          ?? '');
    $category_id = intval($input['category_id'] ?? intval($input['category'] ?? 0));
    $price       = intval($input['price']       ?? 0);
    $old_price   = intval($input['old_price']   ?? 0);
    $stock       = intval($input['stock']       ?? 0);
    $desc        = trim($input['description']   ?? '');
    $image       = trim($input['image']         ?? '');
    $is_new      = intval($input['is_new']      ?? 0);

    if (!$id || !$name || !$price) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu dữ liệu']);
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE products SET name=?, category_id=?, price=?, old_price=?, stock=?, description=?, image=?, is_new=?
         WHERE id=?"
    );
    $stmt->bind_param("siiiissii", $name, $category_id, $price, $old_price, $stock, $desc, $image, $is_new, $id);
    $stmt->execute();
    echo json_encode(['ok' => true, 'msg' => 'Đã cập nhật sản phẩm']);
    exit;
}

// ============================================================
// DELETE — Xóa sản phẩm (admin)
// ============================================================
if ($method === 'DELETE') {
    $id = intval($input['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Thiếu ID']); exit; }
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['ok' => true, 'msg' => 'Đã xóa sản phẩm']);
    exit;
}

$conn->close();
?>