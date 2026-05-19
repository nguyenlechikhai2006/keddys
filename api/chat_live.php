<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../Db.php';
session_start();

$action = $_GET['action'] ?? '';

// Lấy user từ session hoặc body
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // Khách mở/lấy session chat
    case 'get_session':
        $user_id = $input['user_id'] ?? 0;
        if (!$user_id) { echo json_encode(['error' => 'Chưa đăng nhập']); exit; }

        $stmt = $pdo->prepare("SELECT id FROM chat_sessions WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            $stmt = $pdo->prepare("INSERT INTO chat_sessions (user_id) VALUES (?)");
            $stmt->execute([$user_id]);
            $session_id = $pdo->lastInsertId();
        } else {
            $session_id = $session['id'];
        }
        echo json_encode(['session_id' => $session_id]);
        break;

    // Gửi tin nhắn
    case 'send':
        $session_id = $input['session_id'] ?? 0;
        $sender     = $input['sender'] ?? 'user'; // 'user' hoặc 'admin'
        $message    = trim($input['message'] ?? '');

        if (!$session_id || !$message) { echo json_encode(['error' => 'Thiếu dữ liệu']); exit; }

        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender, message) VALUES (?, ?, ?)");
        $stmt->execute([$session_id, $sender, $message]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    // Lấy tin nhắn mới (polling)
    case 'get_messages':
        $session_id  = $_GET['session_id'] ?? 0;
        $last_id     = $_GET['last_id'] ?? 0;

        $stmt = $pdo->prepare("SELECT id, sender, message, created_at FROM chat_messages WHERE session_id = ? AND id > ? ORDER BY id ASC");
        $stmt->execute([$session_id, $last_id]);
        echo json_encode(['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // Admin: lấy danh sách tất cả session
    case 'get_all_sessions':
        $stmt = $pdo->query("
            SELECT cs.id, cs.user_id, cs.status, cs.created_at,
                   u.username, u.email,
                   (SELECT COUNT(*) FROM chat_messages cm WHERE cm.session_id = cs.id AND cm.sender = 'user' AND cm.is_read = 0) as unread
            FROM chat_sessions cs
            JOIN users u ON u.id = cs.user_id
            ORDER BY cs.created_at DESC
        ");
        echo json_encode(['sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // Admin: đánh dấu đã đọc
    case 'mark_read':
        $session_id = $input['session_id'] ?? 0;
        $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE session_id = ? AND sender = 'user'")->execute([$session_id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Action không hợp lệ']);
}
?>