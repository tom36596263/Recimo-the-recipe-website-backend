<?php
// 1. 移除 session_start();
require_once '../config/cors.php';
require_once '../config/db_config.php'; 

// 2. 設定回傳格式與處理 CORS (移除 Credentials 限制，因為不用 Session)
header("Content-Type: application/json; charset=UTF-8");
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

// 3. 接收前端 Vue 傳來的 JSON 資料
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// 4. 從 JSON 內容中取得 user_id 與資料檢查
$user_id = $data['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "缺少使用者 ID，無法結帳"]);
    exit;
}

if (!$data || !isset($data['items']) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(["error" => "購物車內容為空"]);
    exit;
}

try {
    // 🔥 開始資料庫交易 (Transaction)
    $pdo->beginTransaction();

    // 5. 第一步：新增「訂單主檔」(orders 表)
    $sql_order = "INSERT INTO orders (
        user_id, subtotal, discount_amount, shipping_fee, total_amount, 
        recipient_name, recipient_phone, shipping_address, 
        order_status, payment_method, payment_status, created
    ) VALUES (
        :uid, :sub, :dis, :fee, :total, 
        :name, :phone, :addr, 
        1, :pay_m, 0, NOW()
    )";

    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([
        ':uid'   => $user_id, // 改由前端 JSON 傳入的 ID
        ':sub'   => $data['subtotal'],
        ':dis'   => $data['discount_amount'] ?? 0,
        ':fee'   => $data['shipping_fee'] ?? 0,
        ':total' => $data['total_amount'],
        ':name'  => $data['recipient_name'],
        ':phone' => $data['recipient_phone'],
        ':addr'  => $data['shipping_address'],
        ':pay_m' => $data['payment_method'] ?? 1
    ]);

    // 🔥 獲取剛才自動產生的 order_id
    $new_order_id = $pdo->lastInsertId();

    // 6. 第二步：迴圈新增「訂單產品明細」(order_product 表)
    $sql_item = "INSERT INTO order_products (
        order_id, product_id, product_name, snapshot_price, quantity, subtotal
    ) VALUES (
        :oid, :pid, :pname, :price, :qty, :sub
    )";
    
    $stmt_item = $pdo->prepare($sql_item);

    foreach ($data['items'] as $product) {
        $stmt_item->execute([
            ':oid'   => $new_order_id,
            ':pid'   => $product['product_id'],
            ':pname' => $product['product_name'],
            ':price' => $product['price'],
            ':qty'   => $product['quantity'],
            ':sub'   => $product['price'] * $product['quantity']
        ]);
    }

    // 🔥 提交交易
    $pdo->commit();

    // 7. 回傳結果
    echo json_encode([
        "success"  => true,
        "message"  => "訂單已成功建立",
        "order_id" => $new_order_id
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => "結帳失敗: " . $e->getMessage()]);
}