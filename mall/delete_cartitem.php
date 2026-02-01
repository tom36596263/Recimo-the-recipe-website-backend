<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

session_start();
$user_id = $_SESSION['user_id'] ?? null;

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