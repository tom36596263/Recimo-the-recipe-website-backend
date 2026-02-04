<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// ---------------------------------------------------------
// 第四步：撰寫 SQL 語句
// ---------------------------------------------------------
// 取得 JSON 資料
$input = json_decode(file_get_contents('php://input'), true);
// 直接把收到的東西噴出來，然後去 Network 的 Response 看
// var_dump($input); 
// exit;
$product_id = $input['product_id'] ?? null;
$quantity = $input['quantity'] ?? 1;

// 優先抓取前端傳過來的 user_id，沒有的話再看 Session
$user_id = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);

if (!$user_id || !$product_id) {
    echo json_encode([
        "status" => "error", 
        "message" => "資料不足或未登入",
        // "debug" => ["uid" => $user_id, "pid" => $product_id] // 測試完可以拿掉
    ]);
    exit;
}

try {
    // 3. 如果存在就更新 quantity，不存在就插入
    // 需要資料表對 user_id + product_id 有 UNIQUE 限制
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