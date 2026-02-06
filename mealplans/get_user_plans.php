<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

// 從 GET 參數取得 user_id
$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "缺少使用者 ID"]);
    exit;
}

try {
    // 撈取該使用者的所有計畫，並依照建立日期由新到舊排序
    $sql = "SELECT * FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 回傳計畫陣列 (不包層)
    echo json_encode($plans);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫讀取失敗: " . $e->getMessage()]);
}
