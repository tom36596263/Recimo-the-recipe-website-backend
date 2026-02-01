<?php
require_once '../config/cors.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db_config.php';
header("Content-Type: application/json; charset=UTF-8");
// 1. 取得 JSON 資料
$input = json_decode(file_get_contents('php://input'), true);
// 直接把收到的東西噴出來，然後去 Network 的 Response 看
// var_dump($input); 
// exit;
$product_id = $input['product_id'] ?? null;
$quantity = $input['quantity'] ?? 1;

// 2. 獲取 User ID (建議從 Session 拿，比較安全)

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || !$product_id) {
    echo json_encode([
        "status" => "error", 
        "message" => "資料不足或未登入",
        "debug" => ["uid" => $user_id, "pid" => $product_id] // 測試完可以拿掉
    ]);
    exit;
}

try {
    // 3. 核心邏輯：如果存在就更新 quantity，不存在就插入
    // 注意：這需要您的資料表對 user_id + product_id 有 UNIQUE 限制
    $sql = "INSERT INTO carts (user_id, product_id, quantity) 
            VALUES (:uid, :pid, :cnt) 
            ON DUPLICATE KEY UPDATE quantity = quantity + :cnt_update";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid' => $user_id,
        ':pid' => $product_id,
        ':cnt' => (int)$quantity,
        ':cnt_update' => (int)$quantity
    ]);

    echo json_encode(["status" => "success", "message" => "已同步至購物車"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫操作失敗: " . $e->getMessage()]);
}