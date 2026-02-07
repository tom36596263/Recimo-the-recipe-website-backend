<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$plan_id = $input['plan_id'] ?? null;
$user_id = $input['user_id'] ?? null;

if (!$plan_id || !$user_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "參數不足"]);
    exit;
}

try {
    // 刪除計畫，由於外鍵約束，建議先手動刪除相關項目（若資料庫沒設 Cascade Delete）
    $pdo->beginTransaction();

    // 1. 刪除相關食譜項目
    $stmt1 = $pdo->prepare("DELETE FROM meal_plan_items WHERE plan_id = ?");
    $stmt1->execute([$plan_id]);

    // 2. 刪除計畫本體
    $stmt2 = $pdo->prepare("DELETE FROM meal_plans WHERE plan_id = ? AND user_id = ?");
    $stmt2->execute([$plan_id, $user_id]);

    $pdo->commit();
    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
