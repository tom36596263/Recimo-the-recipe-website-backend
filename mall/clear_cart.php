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

// 取得前端傳來的 JSON 資料
$input = json_decode(file_get_contents('php://input'), true);

// 前端傳參優先
$user_id = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "請先登入"]);
    exit;
}

// ---------------------------------------------------------
// 第四步：撰寫 SQL 語句
// ---------------------------------------------------------
try {
    // 刪除該使用者在購物車表中的所有紀錄
    $sql = "DELETE FROM carts WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    echo json_encode(["status" => "success", "message" => "購物車已清空"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫操作失敗"]);
}