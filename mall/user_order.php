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
    $order_id = $_GET['id'] ?? null;
    $current_user_id = $_GET['user_id'] ?? null;

    if (!$order_id || !$current_user_id) {
        http_response_code(400);
        echo json_encode(["error" => "缺少參數"]);
        exit;
    }

    // 只負責查詢，不負責任何 UPDATE 動作
    $sql_order = "SELECT o.*, u.user_name FROM orders o 
                  LEFT JOIN users u ON o.user_id = u.user_id 
                  WHERE o.order_id = :oid AND o.user_id = :uid";
    
    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([':oid' => $order_id, ':uid' => $current_user_id]); // 確保這裡綁定兩個參數
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(403);
        echo json_encode(["error" => "無權限"]);
        exit;
    }

    // 抓明細
    $sql_items = "SELECT * FROM order_products WHERE order_id = :oid";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([':oid' => $order_id]);
    $order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($order, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}