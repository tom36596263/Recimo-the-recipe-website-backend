<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

session_start();
$user_id = $_SESSION['user_id'] ?? null;

// 取得前端傳來的商品 ID 與「最終數量」
$input = json_decode(file_get_contents('php://input'), true);
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