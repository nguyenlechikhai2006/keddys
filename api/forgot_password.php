<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối DB']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

// ============================================================
// ACTION: send_otp — kiểm tra email tồn tại, trả về OTP giả
// ============================================================
if ($action === 'send_otp') {
    $email = trim($input['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ!']);
        exit;
    }

    // Kiểm tra email có trong DB không
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Email không tồn tại trong hệ thống!']);
        exit;
    }

    // Tạo OTP 6 số ngẫu nhiên
    $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

    // Lưu OTP vào DB (cột otp_code + otp_expires)
    // Nếu bảng users chưa có cột này thì tạo tạm trong session hoặc trả thẳng về
    // Ở đây trả thẳng về (vì OTP giả, không gửi email thật)
    echo json_encode(['success' => true, 'otp' => $otp]);
    exit;
}

// ============================================================
// ACTION: reset_password — cập nhật mật khẩu mới
// ============================================================
if ($action === 'reset_password') {
    $email    = trim($input['email']        ?? '');
    $newPass  = trim($input['new_password'] ?? '');

    if (!$email || !$newPass) {
        echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu!']);
        exit;
    }

    if (strlen($newPass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu quá ngắn!']);
        exit;
    }

    // Hash mật khẩu giống lúc đăng ký
    $hashed = password_hash($newPass, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed, $email);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản!']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
$conn->close();
?>