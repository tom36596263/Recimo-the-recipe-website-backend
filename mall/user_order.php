<?php
// ---------------------------------------------------------
// 第一步：開啟 Session (必須放在最上方，用於驗證會員身份)
// ---------------------------------------------------------
session_start();

// ---------------------------------------------------------
// 第二步：引入 CORS 與資料庫連線設定
// ---------------------------------------------------------
require_once '../config/cors.php';
require_once '../config/db_config.php'; // 提供 $pdo 變數

// ---------------------------------------------------------
// 第三步：設定回傳格式與處理 Session CORS 限制
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 當需要帶 Cookie/Session 時，Origin 不能為 *，必須指定來源
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

try {
    // ---------------------------------------------------------
    // 第四步：權限與參數檢查
    // ---------------------------------------------------------
    
    // 檢查 Session 是否有 user_id (請確認你登入時存的 key 名稱)
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "請先登入"]);
        exit;
    }

    $current_user_id = $_SESSION['user_id'];
    // 獲取前端傳來的訂單 ID (例如：user_order.php?id=111018)
    $order_id = $_GET['id'] ?? null;

    if (!$order_id) {
        http_response_code(400);
        echo json_encode(["error" => "未提供訂單 ID"]);
        exit;
    }

    // ---------------------------------------------------------
    // 第五步：查詢「訂單主檔」(orders 表)
    // 加上 user_id 檢查，防止 A 用戶查到 B 用戶的訂單
    // ---------------------------------------------------------
    $sql_order = "SELECT * FROM orders WHERE order_id = :oid AND user_id = :uid";
    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([':oid' => $order_id, ':uid' => $current_user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["error" => "找不到該筆訂單或無查看權限"]);
        exit;
    }

    // ---------------------------------------------------------
    // 第六步：查詢「訂單產品明細」(order_product 表)
    // ---------------------------------------------------------
    $sql_items = "SELECT product_id, product_name, snapshot_price, quantity, subtotal 
                  FROM order_products 
                  WHERE order_id = :oid";
    
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([':oid' => $order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------
    // 第七步：組裝結果並輸出
    // ---------------------------------------------------------
    $order['items'] = $items; // 將產品列表塞進訂單資料中
    
    echo json_encode($order, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "系統發生錯誤: " . $e->getMessage()]);
}

// 省略結尾標籤防止空白