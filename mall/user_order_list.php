<?php
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
    //取得前端傳來的 user_id
    $current_user_id = $_GET['user_id'] ?? null;

    if (!$current_user_id) {
        http_response_code(400);
        echo json_encode(["error" => "缺少使用者 ID"], JSON_UNESCAPED_UNICODE);
        exit;
    }


    $sql = "SELECT 
                order_id, 
                total_amount, 
                created, 
                order_status, 
                payment_status, 
                payment_method,
                recipient_name, 
                recipient_phone,
                logistics_id
            FROM orders 
            WHERE user_id = :uid 
            ORDER BY created DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $current_user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($orders ?: [], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "系統錯誤: " . $e->getMessage()]);
}
// // 在 $stmt->execute 之前加入
// file_put_contents('debug.txt', "收到前端傳來的 UID: " . $current_user_id . " | 類型: " . gettype($current_user_id) . "\n", FILE_APPEND);

