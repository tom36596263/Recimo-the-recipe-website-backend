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
// 取得前端傳來的商品 ID 與「最終數量」
$input = json_decode(file_get_contents('php://input'), true);
// 前端傳來的 user_id 優先
$user_id = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);
$product_id = $input['product_id'] ?? null;
$quantity = $input['quantity'] ?? $input['count'] ?? 1; // 兩個都抓，萬無一失

if (!$user_id || !$product_id || $quantity === null) {
    echo json_encode(["status" => "error", "message" => "資料不完整"]);
    exit;
}

try {
    // 這裡是用覆蓋的方式 (直接更新為前端計算後的數量)
    $sql = "UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quantity, $user_id, $product_id]);

    echo json_encode(["status" => "success", "message" => "數量已更新"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "更新失敗"]);
}