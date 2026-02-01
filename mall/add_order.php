<?php
// 1. 開啟 Session 並引入設定 (必須在最上方)
session_start();
require_once '../config/cors.php';
require_once '../config/db_config.php'; // 這裡提供 $pdo

// 2. 設定回傳格式與處理 Session CORS
header("Content-Type: application/json; charset=UTF-8");
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

// 3. 權限檢查：確認用戶已登入
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "請先登入再結帳"]);
    exit;
}

// 4. 接收前端 Vue 傳來的 JSON 資料 (包含收件資訊與購物車內容)
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data || !isset($data['items']) || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(["error" => "購物車內容為空"]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 🔥 開始資料庫交易 (Transaction)
    // 這能保證主檔跟明細「同步成功」或「同步失敗」
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
    // 注意：created 使用資料庫系統時間 NOW()，order_status 預設 1 (待處理)，payment_status 預設 0 (未付款)

    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([
        ':uid'   => $user_id,
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
    $sql_item = "INSERT INTO order_product (
        order_id, product_id, product_name, snapshot_price, quantity, subtotal
    ) VALUES (
        :oid, :pid, :pname, :price, :qty, :sub
    )";
    
    $stmt_item = $pdo->prepare($sql_item);

    foreach ($data['items'] as $product) {
        $stmt_item->execute([
            ':oid'   => $new_order_id, // 關鍵：連結主表 ID
            ':pid'   => $product['product_id'],
            ':pname' => $product['product_name'],
            ':price' => $product['price'],
            ':qty'   => $product['quantity'],
            ':sub'   => $product['price'] * $product['quantity']
        ]);
    }

    // 🔥 提交交易：只有走到這行，上面的 SQL 才會真正寫入資料庫
    $pdo->commit();

    // 7. 回傳新 ID 給 Vue 進行頁面跳轉
    echo json_encode([
        "success"  => true,
        "message"  => "訂單已成功建立",
        "order_id" => $new_order_id
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // 萬一出錯，撤銷剛才所有沒存進去的動作
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => "結帳失敗: " . $e->getMessage()]);
}
