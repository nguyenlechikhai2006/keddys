<?php
// ============================================================
//  api/coupon.php  —  Keddys Coupon API (ĐÃ FIX)
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$conn = new mysqli('localhost', 'root', '', 'keddy_petshop');
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database.']);
    exit;
}

// Tạo bảng coupon_usage nếu chưa có
$conn->query("
    CREATE TABLE IF NOT EXISTS coupon_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coupon_id INT NOT NULL,
        user_id INT NOT NULL DEFAULT 0,
        order_id INT NOT NULL DEFAULT 0,
        used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_coupon (coupon_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'validate': validateCoupon($conn); break;
    case 'use':      useCoupon($conn);      break;
    case 'list':     listCoupons($conn);    break;
    case 'create':   createCoupon($conn);   break;
    case 'delete':   deleteCoupon($conn);   break;
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ.']);
}

$conn->close();

// ============================================================
// 1. VALIDATE
// ============================================================
function validateCoupon($conn) {
    $data        = json_decode(file_get_contents('php://input'), true);
    $code        = strtoupper(trim($data['code']        ?? ''));
    $order_total = floatval($data['order_total']        ?? 0);
    $user_id     = intval($data['user_id']              ?? 0);

    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá.']);
        return;
    }

    // FIX: dùng đúng tên cột DB (type, value, min_order, usage_limit)
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không tồn tại hoặc đã bị vô hiệu.']);
        return;
    }

    // Kiểm tra hết hạn
    if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết hạn.']);
        return;
    }

    // Kiểm tra số lần dùng tổng (FIX: dùng cột usage_limit thay vì max_uses)
    if ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng.']);
        return;
    }

    // Kiểm tra mỗi user dùng tối đa 1 lần
    if ($user_id > 0) {
        $stmt2 = $conn->prepare("SELECT id FROM coupon_usage WHERE coupon_id = ? AND user_id = ? LIMIT 1");
        $stmt2->bind_param('ii', $coupon['id'], $user_id);
        $stmt2->execute();
        $already = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        if ($already) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã sử dụng mã này rồi.']);
            return;
        }
    }

    // Kiểm tra đơn tối thiểu (FIX: cột min_order)
    if ($order_total < $coupon['min_order']) {
        $min = number_format($coupon['min_order'], 0, ',', '.');
        echo json_encode(['success' => false, 'message' => "Đơn hàng tối thiểu {$min}đ để dùng mã này."]);
        return;
    }

    $discount = calcDiscount($coupon, $order_total);

    // FIX: trả về đúng tên field mà checkout.html đang đọc
    // checkout.html đọc: data.discount_type, data.discount_amount, data.coupon_id, data.code
    echo json_encode([
        'success'         => true,
        'message'         => 'Mã hợp lệ!',
        'coupon_id'       => $coupon['id'],
        'code'            => $coupon['code'],
        'discount_type'   => $coupon['type'],      // FIX: map 'type' → 'discount_type'
        'discount_value'  => $coupon['value'],
        'discount_amount' => $discount,
        'description'     => $coupon['description'] ?? '',
    ]);
}

// ============================================================
// 2. USE — đánh dấu đã dùng
// ============================================================
function useCoupon($conn) {
    $data      = json_decode(file_get_contents('php://input'), true);
    $coupon_id = intval($data['coupon_id'] ?? 0);
    $user_id   = intval($data['user_id']   ?? 0);
    $order_id  = intval($data['order_id']  ?? 0);

    if (!$coupon_id) {
        echo json_encode(['success' => false, 'message' => 'Thiếu coupon_id.']);
        return;
    }

    $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $stmt->close();

    if ($user_id > 0) {
        // FIX: bảng coupon_usage đã được tạo ở đầu file
        $stmt2 = $conn->prepare("INSERT IGNORE INTO coupon_usage (coupon_id, user_id, order_id, used_at) VALUES (?,?,?,NOW())");
        $stmt2->bind_param('iii', $coupon_id, $user_id, $order_id);
        $stmt2->execute();
        $stmt2->close();
    }

    echo json_encode(['success' => true, 'message' => 'Đã ghi nhận sử dụng mã.']);
}

// ============================================================
// 3. LIST — admin
// ============================================================
function listCoupons($conn) {
    $result  = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
    $coupons = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $coupons]);
}

// ============================================================
// 4. CREATE — admin (FIX: dùng đúng tên cột DB)
// ============================================================
function createCoupon($conn) {
    $data          = json_decode(file_get_contents('php://input'), true);
    $code          = strtoupper(trim($data['code']          ?? ''));
    $type          = $data['type']                          ?? 'percent';  // FIX: 'type' không phải 'discount_type'
    $value         = floatval($data['value']                ?? 0);         // FIX: 'value' không phải 'discount_value'
    $min_order     = floatval($data['min_order']            ?? 0);         // FIX: 'min_order'
    $max_discount  = floatval($data['max_discount']         ?? 0) ?: null;
    $usage_limit   = intval($data['usage_limit']            ?? 0) ?: null; // FIX: 'usage_limit'
    $expires_at    = $data['expires_at']                    ?? null;
    $is_active     = intval($data['is_active']              ?? 1);

    if (!$code || !$value) {
        echo json_encode(['success' => false, 'message' => 'Thiếu code hoặc value.']);
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO coupons (code, type, value, min_order, max_discount, usage_limit, is_active, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssdddiis', $code, $type, $value, $min_order, $max_discount, $usage_limit, $is_active, $expires_at);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tạo mã thành công.', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mã đã tồn tại hoặc lỗi DB: ' . $conn->error]);
    }
    $stmt->close();
}

// ============================================================
// 5. DELETE — admin
// ============================================================
function deleteCoupon($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = intval($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Thiếu id.']); return; }

    $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Đã xoá mã.']);
}

// ── Helper tính tiền giảm (FIX: dùng cột 'type' và 'value')
function calcDiscount($coupon, $order_total) {
    switch ($coupon['type']) {  // FIX: 'type' không phải 'discount_type'
        case 'percent':
            $d = $order_total * ($coupon['value'] / 100);  // FIX: 'value'
            if (!empty($coupon['max_discount']) && $coupon['max_discount'] > 0)
                $d = min($d, $coupon['max_discount']);
            return round($d);
        case 'fixed':
            return min(round($coupon['value']), $order_total);  // FIX: 'value'
        case 'freeship':
            return 0;
        default:
            return 0;
    }
}