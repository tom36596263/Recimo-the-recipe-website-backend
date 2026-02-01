<?php
// ---------------------------------------------------------
// 1. 引入設定與 CORS 處理
// ---------------------------------------------------------
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    // ---------------------------------------------------------
    // 2. 獲取前端資料 (相容 JSON POST 與 URL GET)
    // ---------------------------------------------------------
    $json = file_get_contents("php://input");
    $data = json_decode($json, true) ?? [];

    // 根據資料庫截圖，必填參數為 admin_id 與 order_id
    $admin_id = $data['admin_id'] ?? $_GET['admin_id'] ?? null;
    $order_id = $data['order_id'] ?? $_GET['order_id'] ?? $_GET['id'] ?? null;
    
    // 欲更新的狀態欄位
    $new_order_status = $data['order_status'] ?? $_GET['order_status'] ?? null;
    $new_payment_status = $data['payment_status'] ?? $_GET['payment_status'] ?? null;

    if (!$admin_id || !$order_id) {
        http_response_code(400);
        echo json_encode(["error" => "缺少必要資訊 (order_id 或 admin_id)"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------------------------------------------------------
    // 3. 執行更新邏輯 (如果有傳入新狀態)
    // ---------------------------------------------------------
    $update_message = "";
    if ($new_order_status !== null || $new_payment_status !== null) {
        $fields = [];
        $params = [':oid' => $order_id];

        if ($new_order_status !== null) {
            $fields[] = "order_status = :ostatus";
            $params[':ostatus'] = $new_order_status;
        }
        if ($new_payment_status !== null) {
            $fields[] = "payment_status = :pstatus";
            $params[':pstatus'] = $new_payment_status;
        }

        // 執行更新
        $sql_update = "UPDATE orders SET " . implode(', ', $fields) . " WHERE order_id = :oid";
        $stmt_up = $pdo->prepare($sql_update);
        $stmt_up->execute($params);
        $update_message = "訂單 #$order_id 狀態更新成功。";
    }

    // ---------------------------------------------------------
    // 4. 查詢完整訂單資料 (主檔 + 明細)
    // ---------------------------------------------------------
    
    // A. 查詢訂單主檔 (根據截圖欄位：order_id, user_id, total_amount, created, recipient_name 等)
    $sql_main = "SELECT * FROM orders WHERE order_id = :oid";
    $stmt_main = $pdo->prepare($sql_main);
    $stmt_main->execute([':oid' => $order_id]);
    $order_info = $stmt_main->fetch(PDO::FETCH_ASSOC);

    if (!$order_info) {
        echo json_encode(["error" => "找不到訂單編號為 $order_id 的資料"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // B. 查詢訂單產品明細 (根據截圖欄位：order_id, product_name, snapshot_price, quantity, subtotal)
    $sql_items = "SELECT * FROM order_products WHERE order_id = :oid";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([':oid' => $order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------
    // 5. 輸出整合結果
    // ---------------------------------------------------------
    echo json_encode([
        "success" => true,
        "message" => $update_message ?: "成功讀取訂單詳情",
        "admin_id" => $admin_id,
        "order_master" => $order_info, // 包含收件人、總額、狀態等
        "order_details" => $items      // 包含購買的產品列表
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫錯誤: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}