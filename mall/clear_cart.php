<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

session_start();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "請先登入"]);
    exit;
}

try {
    // 刪除該使用者在購物車表中的所有紀錄
    $sql = "DELETE FROM carts WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    echo json_encode(["status" => "success", "message" => "購物車已清空"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫操作失敗"]);
}