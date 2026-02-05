<?php
//開啟錯誤顯示，方便除錯
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/cors.php';
require_once '../config/db_config.php'; 

header("Content-Type: application/json; charset=UTF-8");

// CORS
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$json = file_get_contents("php://input");
$data = json_decode($json, true);

$user_id = $data['user_id'] ?? null;
$raw_payment_method = $data['payment_method'] ?? 'cod'; // 取得前端傳來的文字 'card' 或 'cod'

//將文字轉成資料庫要的數字
// 假設定義：1 = 信用卡 (card), 2 = 貨到付款 (cod)
$payment_method_int = 2; // 預設為貨到付款
$payment_status_int = 0; // 預設未付款

if ($raw_payment_method === 'card') {
    $payment_method_int = 1;
    $payment_status_int = 1; // 信用卡視為已付款
} else {
    $payment_method_int = 2; // 貨到付款
    $payment_status_int = 0; // 貨到付款視為未付款
}

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "User ID missing"]);
    exit;
}

if (empty($data['items'])) {
    http_response_code(400);
    echo json_encode(["error" => "Cart is empty"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2新增訂單
    // 注意 :pay_m 和 :pay_s 現在使用轉換後的數字
   // 修改 SQL 語句：加入 logistics_id
$sql_order = "INSERT INTO orders (
    user_id, logistics_id, subtotal, discount_amount, shipping_fee, total_amount, 
    recipient_name, recipient_phone, shipping_address, 
    order_status, payment_method, payment_status, created
) VALUES (
    :uid, :lid, :sub, :dis, :fee, :total, 
    :name, :phone, :addr, 
    0, :pay_m, :pay_s, NOW()
)";
$random_logistics_id = rand(1000000, 9999999);
    $stmt = $pdo->prepare($sql_order);
$stmt->execute([
    ':uid'   => $user_id,
    ':lid'   => $random_logistics_id, //接收前端傳來的 logistics_id
    ':sub'   => $data['subtotal'],
    ':dis'   => $data['discount_amount'] ?? 0,
    ':fee'   => $data['shipping_fee'] ?? 0,
    ':total' => $data['total_amount'],
    ':name'  => $data['recipient_name'],
    ':phone' => $data['recipient_phone'],
    ':addr'  => $data['shipping_address'],
    ':pay_m' => $payment_method_int,
    ':pay_s' => $payment_status_int
]);

    $new_order_id = $pdo->lastInsertId();

    //新增明細
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

    // 清空購物車
    // 這裡要確認你的資料表是 carts 還是 cart
    // 如果之前 debug_db.php 顯示是 cart (沒有s)，請把下面改成 FROM cart
    $sql_clear = "DELETE FROM carts WHERE user_id = :uid";
    $stmt_clear = $pdo->prepare($sql_clear);
    $stmt_clear->execute([':uid' => $user_id]);

    $pdo->commit();

    echo json_encode([
        "success"  => true,
        "message"  => "Success",
        "order_id" => $new_order_id,
        "payment_method" => $raw_payment_method
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>