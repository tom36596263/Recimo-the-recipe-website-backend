<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';
session_start();
$user_id = $_SESSION['user_id'] ?? null;

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
$input = json_decode(file_get_contents('php://input'), true);
$product_id = $input['product_id'] ?? null;

if (!$user_id || !$product_id) {
    echo json_encode(["status" => "error", "message" => "缺少必要參數"]);
    exit;
}

try {
    $sql = "DELETE FROM carts WHERE user_id = ? AND product_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $product_id]);

    echo json_encode(["status" => "success", "message" => "商品已從購物車移除"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫刪除失敗"]);
}