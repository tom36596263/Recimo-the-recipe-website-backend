<?php
// 1. 移除 session_start(); 因為不再需要讀取伺服器的 session
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

// CORS 處理
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

try {
    // 2. 取得前端傳來的 user_id
    // 這裡我們同時相容 GET 或 POST 傳過來的資料
    $current_user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;

    // 3. 檢查是否有傳入 user_id
    if (!$current_user_id) {
        http_response_code(400); // 錯誤請求
        echo json_encode(["error" => "缺少使用者 ID (user_id)"]);
        exit;
    }

    // 4. 撰寫 SQL：撈出該會員的所有訂單
    $sql = "SELECT order_id, total_amount, created, order_status, payment_status 
            FROM orders 
            WHERE user_id = :uid 
            ORDER BY created DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $current_user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. 輸出結果
    echo json_encode($orders, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "無法取得訂單列表: " . $e->getMessage()]);
}