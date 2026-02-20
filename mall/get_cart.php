<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 用 Session 存 user_id
session_start();
// 優先從 GET 參數拿，拿不到才看 Session
$user_id = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? null);

if (!$user_id) {
    // 如果沒登入，回傳 status: success 但 data: [] 
    // 這樣前端才不會噴 error，而是顯示「購物車目前沒有商品」
    echo json_encode(["status" => "success", "data" => []]);
    exit;
}

// ---------------------------------------------------------
// 第四步：撰寫 SQL 語句
// ---------------------------------------------------------
try {
    // 撈出該使用者的購物車，並關聯商品表拿圖片、價格、名稱
    $sql = "SELECT 
                c.quantity, 
                p.product_id, 
                p.product_name, 
                p.product_price, 
                p.product_image 
            FROM carts c
            JOIN products p ON c.product_id = p.product_id
            WHERE c.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    // 直接抓取所有資料
    // PDO 的 fetchAll 如果沒資料，會直接回傳空陣列 []
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 回傳結果
    echo json_encode([
        "status" => "success",
        "data" => $cartItems
    ]);
} catch (PDOException $e) {
    // 發生錯誤時回傳錯誤訊息
    echo json_encode(["status" => "error", "message" => "資料庫錯誤：" . $e->getMessage()]);
}
