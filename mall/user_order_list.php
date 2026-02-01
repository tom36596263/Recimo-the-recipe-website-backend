<?php
session_start();
require_once '../config/cors.php';
require_once '../config/db_config.php'; 

header("Content-Type: application/json; charset=UTF-8");

// Session CORS 處理
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

try {
    // 1. 權限檢查
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "請先登入"]);
        exit;
    }

    $current_user_id = $_SESSION['user_id'];

    // 2. 撰寫 SQL：撈出該會員的所有訂單，最新的排在最前面
    // 這裡只需要撈 orders 表就好，不需要撈產品明細
    $sql = "SELECT order_id, total_amount, created, order_status, payment_status 
            FROM orders 
            WHERE user_id = :uid 
            ORDER BY created DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $current_user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 輸出結果
    echo json_encode($orders, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "無法取得訂單列表: " . $e->getMessage()]);
}