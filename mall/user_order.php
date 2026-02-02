<?php

// 1. 移除 session_start(); 不再使用伺服器 Session

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

    // 2. 從前端傳來的參數中取得 user_id 與 order_id

    // 通常詳情頁會用 GET：?id=111018&user_id=12

    $current_user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;

    $order_id = $_GET['id'] ?? null;



    // 3. 檢查參數是否完整

    if (!$current_user_id) {

        http_response_code(400);

        echo json_encode(["error" => "缺少使用者 ID (user_id)"]);

        exit;

    }



    if (!$order_id) {

        http_response_code(400);

        echo json_encode(["error" => "未提供訂單 ID"]);

        exit;

    }



    // 4. 查詢「訂單主檔」

    // 這裡依然保留 user_id = :uid 的檢查，確保該訂單真的屬於該使用者

    $sql_order = "SELECT * FROM orders WHERE order_id = :oid AND user_id = :uid";

    $stmt = $pdo->prepare($sql_order);

    $stmt->execute([':oid' => $order_id, ':uid' => $current_user_id]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);



    if (!$order) {

        http_response_code(404);

        echo json_encode(["error" => "找不到該筆訂單或無查看權限"]);

        exit;

    }



    // 5. 查詢「訂單產品明細」

    $sql_items = "SELECT product_id, product_name, snapshot_price, quantity, subtotal

                  FROM order_products

                  WHERE order_id = :oid";

   

    $stmt_items = $pdo->prepare($sql_items);

    $stmt_items->execute([':oid' => $order_id]);

    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);



    // 6. 組裝結果並輸出

    $order['items'] = $items;

   

    echo json_encode($order, JSON_UNESCAPED_UNICODE);

   

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode(["error" => "系統發生錯誤: " . $e->getMessage()]);

}