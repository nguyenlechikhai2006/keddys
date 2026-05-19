<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli("localhost", "root", "1234567890", "keddy_petshop");
$conn->set_charset("utf8mb4");

$month = date('m');
$year  = date('Y');

// Doanh thu tháng này (đơn hoàn thành)
$res = $conn->query("
    SELECT COALESCE(SUM(total), 0) AS total
    FROM orders
    WHERE status IN ('done', 'Hoàn thành')
    AND MONTH(created_at) = $month
    AND YEAR(created_at) = $year
");
$month_total = $res->fetch_assoc()['total'] ?? 0;

// Doanh thu năm nay
$res = $conn->query("
    SELECT COALESCE(SUM(total), 0) AS total
    FROM orders
    WHERE status IN ('done', 'Hoàn thành')
    AND YEAR(created_at) = $year
");
$year_total = $res->fetch_assoc()['total'] ?? 0;

// Giá trị đơn trung bình
$res = $conn->query("SELECT COALESCE(AVG(total), 0) AS avg FROM orders WHERE status IN ('done', 'Hoàn thành')");
$avg_order = $res->fetch_assoc()['avg'] ?? 0;

// Đơn hủy tháng này
$res = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM orders
    WHERE status IN ('cancelled', 'Đã hủy')
    AND MONTH(created_at) = $month
    AND YEAR(created_at) = $year
");
$cancelled_count = $res->fetch_assoc()['cnt'] ?? 0;

// Doanh thu theo tháng (12 tháng gần nhất, để vẽ chart sau)
$monthly = [];
for ($m = 1; $m <= 12; $m++) {
    $res = $conn->query("
        SELECT COALESCE(SUM(total), 0) AS total
        FROM orders
        WHERE status IN ('done', 'Hoàn thành')
        AND MONTH(created_at) = $m
        AND YEAR(created_at) = $year
    ");
    $monthly[$m] = (float)($res->fetch_assoc()['total'] ?? 0);
}

echo json_encode([
    'ok'              => true,
    'month_total'     => (float)$month_total,
    'year_total'      => (float)$year_total,
    'avg_order'       => (float)$avg_order,
    'cancelled_count' => (int)$cancelled_count,
    'monthly'         => $monthly,
]);

$conn->close();
?>