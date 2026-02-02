<?php
// ---------------------------------------------------------
// 第一步：引入設定 (移除 session_start，改用純傳參)
// ---------------------------------------------------------
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

try {
    // ---------------------------------------------------------
    // 第二步：從 URL 獲取參數 (order_id 與 admin_id)
    // ---------------------------------------------------------
    $order_id = $_GET['id'] ?? null;
    $admin_id = $_GET['admin_id'] ?? null; // 從前端傳過來的管理員 ID

    // 權限初步檢查：確保有傳 admin_id
    if (!$admin_id) {
        http_response_code(403);
        echo json_encode(["error" => "權限不足，未提供管理員 ID"]);
        exit;
    }

    if (!$order_id) {
        http_response_code(400);
        echo json_encode(["error" => "未提供訂單 ID"]);
        exit;
    }

    // ---------------------------------------------------------
    // 第三步：查詢「訂單主檔」
    // ---------------------------------------------------------
    //管理員可以查看所有人的訂單
    $sql_order = "SELECT o.*, u.user_name, u.user_email 
                  FROM orders o
                  JOIN users u ON o.user_id = u.user_id
                  WHERE o.order_id = :oid";
    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([':oid' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["error" => "找不到該筆訂單"]);
        exit;
    }

  
    // 查詢「訂單產品明細」
    $sql_items = "SELECT * FROM order_products WHERE order_id = :oid";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([':oid' => $order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

 
    //回傳結果 (可選擇性帶回 admin_id 確認)
    $order['items'] = $items;
    $order['accessed_by_admin'] = $admin_id; // 讓前端知道是誰在讀取

    echo json_encode($order, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "系統錯誤: " . $e->getMessage()]);
}