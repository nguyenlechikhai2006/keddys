<?php
// ============================================================
// api/cart.php - API xử lý giỏ hàng Keddy Pet Shop (ĐÃ FIX)
// ============================================================

// ---- FIX QUAN TRỌNG: Session phải start TRƯỚC header ----
// Cho phép session cookie gửi qua fetch() từ trang .html
session_set_cookie_params([
    'lifetime' => 86400 * 7,   // 7 ngày
    'path'     => '/',
    'samesite' => 'Lax',       // FIX: cho phép fetch() gửi cookie
    'httponly' => true,
    'secure'   => false        // đặt true nếu dùng HTTPS
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
// FIX: credentials: 'include' cần Access-Control-Allow-Credentials
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowed = [
    'http://localhost',
    'http://localhost:5500',
    'http://127.0.0.1:5500',
];

if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ---- Kết nối DB ----
require_once __DIR__ . '/../Db.php'; // dùng Db.php chung

// ---- Session ID ----
if (empty($_SESSION['cart_session'])) {
    $_SESSION['cart_session'] = session_id();
}
$sessionId = $_SESSION['cart_session'];
$userId = $_SESSION['user_id'] ?? null;

// ---- Router ----
$action = $_GET['action'] ?? '';
$body   = [];

// Đọc JSON body nếu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $body = json_decode($raw, true) ?? [];
    }
    // fallback: form POST
    if (empty($body) && !empty($_POST)) {
        $body = $_POST;
    }
}

$action = $action ?: ($body['action'] ?? '');

switch ($action) {
    case 'get':      getCart($pdo, $sessionId, $userId);            break;
    case 'add':      addToCart($pdo, $sessionId, $userId, $body);   break;
    case 'update':   updateCart($pdo, $sessionId, $userId, $body);  break;
    case 'remove':   removeFromCart($pdo, $sessionId, $userId, $body); break;
    case 'clear':    clearCart($pdo, $sessionId, $userId);          break;
    case 'coupon':   applyCoupon($pdo, $body);                      break;
    case 'checkout': checkout($pdo, $sessionId, $userId, $body);    break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ: ' . htmlspecialchars($action)]);
}

// ============================================================
// LẤY GIỎ HÀNG
// ============================================================
function getCart($pdo, $sessionId, $userId) {
    $items   = fetchCartItems($pdo, $sessionId, $userId);
    $subtotal = array_sum(array_column($items, 'subtotal'));
    echo json_encode([
        'success'  => true,
        'items'    => $items,
        'count'    => count($items),
        'subtotal' => $subtotal,
        'total'    => $subtotal
    ]);
}

function fetchCartItems($pdo, $sessionId, $userId) {
    if ($userId) {
        $sql  = "SELECT c.id AS cart_id, c.quantity,
                        p.id AS product_id, p.name, p.price, p.original_price,
                        p.image, p.variant, p.stock
                 FROM cart c
                 JOIN products p ON c.product_id = p.id
                 WHERE c.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    } else {
        $sql  = "SELECT c.id AS cart_id, c.quantity,
                        p.id AS product_id, p.name, p.price, p.original_price,
                        p.image, p.variant, p.stock
                 FROM cart c
                 JOIN products p ON c.product_id = p.id
                 WHERE c.session_id = ? AND c.user_id IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sessionId]);
    }
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['subtotal'] = (int)$r['price'] * (int)$r['quantity'];
    }
    return $rows;
}

// ============================================================
// THÊM VÀO GIỎ
// ============================================================
function addToCart($pdo, $sessionId, $userId, $body) {
    $productId = (int)($body['product_id'] ?? 0);
    $qty       = max(1, (int)($body['quantity'] ?? 1));

    if (!$productId) {
        echo json_encode(['success' => false, 'message' => 'Thiếu product_id']);
        return;
    }

    // Kiểm tra sản phẩm tồn tại
    $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
        return;
    }
    if ($product['stock'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm đã hết hàng']);
        return;
    }

    // Kiểm tra đã có trong giỏ chưa
    if ($userId) {
        $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check->execute([$userId, $productId]);
    } else {
        $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ? AND user_id IS NULL");
        $check->execute([$sessionId, $productId]);
    }
    $existing = $check->fetch();

    if ($existing) {
        $newQty = min($existing['quantity'] + $qty, (int)$product['stock']);
        $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?")
            ->execute([$newQty, $existing['id']]);
    } else {
        $qty = min($qty, (int)$product['stock']);
        if ($userId) {
            $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)")
                ->execute([$userId, $productId, $qty]);
        } else {
            $pdo->prepare("INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, ?)")
                ->execute([$sessionId, $productId, $qty]);
        }
    }

    $items = fetchCartItems($pdo, $sessionId, $userId);
    echo json_encode([
        'success' => true,
        'message' => 'Đã thêm vào giỏ hàng',
        'count'   => count($items)
    ]);
}

// ============================================================
// CẬP NHẬT SỐ LƯỢNG
// ============================================================
function updateCart($pdo, $sessionId, $userId, $body) {
    $cartId = (int)($body['cart_id'] ?? 0);
    $qty    = max(1, (int)($body['quantity'] ?? 1));

    if (!$cartId) {
        echo json_encode(['success' => false, 'message' => 'Thiếu cart_id']);
        return;
    }

    if ($userId) {

    $stmt = $pdo->prepare("
        UPDATE cart
        SET quantity = ?
        WHERE id = ? AND user_id = ?
    ");

    $stmt->execute([$qty, $cartId, $userId]);

} else {

    $stmt = $pdo->prepare("
        UPDATE cart
        SET quantity = ?
        WHERE id = ?
        AND session_id = ?
        AND user_id IS NULL
    ");

    $stmt->execute([$qty, $cartId, $sessionId]);

}

    $items    = fetchCartItems($pdo, $sessionId, $userId);
    $subtotal = array_sum(array_column($items, 'subtotal'));
    echo json_encode(['success' => true, 'items' => $items, 'subtotal' => $subtotal, 'total' => $subtotal]);
}

// ============================================================
// XÓA SẢN PHẨM KHỎI GIỎ
// ============================================================
function removeFromCart($pdo, $sessionId, $userId, $body) {
    $cartId = (int)($body['cart_id'] ?? 0);
    if (!$cartId) {
        echo json_encode(['success' => false, 'message' => 'Thiếu cart_id']);
        return;
    }

    if ($userId) {

    $stmt = $pdo->prepare("
        DELETE FROM cart
        WHERE id = ? AND user_id = ?
    ");

    $stmt->execute([$cartId, $userId]);

} else {

    $stmt = $pdo->prepare("
        DELETE FROM cart
        WHERE id = ?
        AND session_id = ?
        AND user_id IS NULL
    ");

    $stmt->execute([$cartId, $sessionId]);

}

    $items    = fetchCartItems($pdo, $sessionId, $userId);
    $subtotal = array_sum(array_column($items, 'subtotal'));
    echo json_encode([
        'success'  => true,
        'items'    => $items,
        'subtotal' => $subtotal,
        'total'    => $subtotal,
        'count'    => count($items)
    ]);
}

// ============================================================
// XÓA TOÀN BỘ GIỎ
// ============================================================
function clearCart($pdo, $sessionId, $userId) {
    if ($userId) {
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
    } else {
        $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL")->execute([$sessionId]);
    }
    echo json_encode(['success' => true, 'message' => 'Đã xóa toàn bộ giỏ hàng', 'count' => 0]);
}

// ============================================================
// ÁP DỤNG MÃ GIẢM GIÁ
// ============================================================
function applyCoupon($pdo, $body) {
    $code     = strtoupper(trim($body['code'] ?? ''));
    $subtotal = (int)($body['subtotal'] ?? 0);

    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn']);
        return;
    }
    if ($coupon['expires_at'] && $coupon['expires_at'] < date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết hạn']);
        return;
    }
    if ($subtotal < $coupon['min_order']) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng tối thiểu ' . number_format($coupon['min_order']) . 'đ để dùng mã này']);
        return;
    }
    if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng']);
        return;
    }

    $discount = 0;
    if ($coupon['type'] === 'percent') {
        $discount = (int)round($subtotal * $coupon['value'] / 100);
        if ($coupon['max_discount']) {
            $discount = min($discount, (int)$coupon['max_discount']);
        }
    } else {
        $discount = (int)$coupon['value'];
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Áp dụng mã thành công! Giảm ' . ($coupon['type'] === 'percent' ? $coupon['value'] . '%' : number_format($discount) . 'đ'),
        'discount' => $discount,
        'coupon'   => $coupon['code']
    ]);
}

// ============================================================
// THANH TOÁN / TẠO ĐƠN HÀNG
// ============================================================
function checkout($pdo, $sessionId, $userId, $body) {
    $items = fetchCartItems($pdo, $sessionId, $userId);
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống']);
        return;
    }

    $name    = trim($body['name']    ?? '');
    $phone   = trim($body['phone']   ?? '');
    $email   = trim($body['email']   ?? '');
    $address = trim($body['address'] ?? '');
    $payment = $body['payment_method'] ?? 'cash';
    $coupon  = strtoupper(trim($body['coupon'] ?? ''));
    $note    = trim($body['note']    ?? '');

    if (!$name || !$phone || !$address) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ: Tên, SĐT, Địa chỉ']);
        return;
    }

    $subtotal = array_sum(array_column($items, 'subtotal'));
    $discount = max(0, (int)($body['discount'] ?? 0));
    $shipping = 0;
    $total    = max(0, $subtotal - $discount + $shipping);

    $orderCode = 'KD' . date('Ymd') . rand(1000, 9999);

    $pdo->beginTransaction();
    try {
$pdo->prepare("
    INSERT INTO orders
        (order_code, user_id, session_id, customer_name, customer_phone, customer_email,
         shipping_address, subtotal, shipping_fee, discount, coupon_code, total, payment_method, note, status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
")->execute([
    $orderCode, $userId, $sessionId,
    $name, $phone, $email ?: null,
    $address, $subtotal, $shipping, $discount,
    $coupon ?: null, $total, $payment, $note, 'pending'
]);
        $orderId = $pdo->lastInsertId();

        $insItem = $pdo->prepare("
            INSERT INTO order_items
                (order_id, product_id, product_name, product_image, variant, price, quantity, subtotal)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        foreach ($items as $item) {
            $insItem->execute([
                $orderId, $item['product_id'], $item['name'],
                $item['image'], $item['variant'],
                $item['price'], $item['quantity'], $item['subtotal']
            ]);
            $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?")
                ->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        }

        if ($coupon) {
            $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?")
                ->execute([$coupon]);
        }

        // Xóa giỏ hàng sau khi đặt hàng
        if ($userId) {
            $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
        } else {
            $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL")->execute([$sessionId]);
        }

        $pdo->commit();
        echo json_encode([
            'success'    => true,
            'message'    => 'Đặt hàng thành công!',
            'order_code' => $orderCode,
            'order_id'   => $orderId
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Lỗi xử lý đơn hàng: ' . $e->getMessage()]);
    }
}