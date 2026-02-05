<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

// 1. 取得 JSON 輸入
$input = json_decode(file_get_contents('php://input'), true);

$plan_id     = $input['plan_id'] ?? null;
$target_date = $input['date'] ?? null;
$target_kcal = $input['target_kcal'] ?? null;
$user_id     = $input['user_id'] ?? null; // 從前端傳入，或之後改從 Session 抓

// 2. 基礎驗證
if (!$plan_id || !$target_date || $target_kcal === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "參數不足"]);
    exit;
}

try {
    // 3. 安全檢查：確保該計畫真的屬於該使用者
    $checkSql = "SELECT plan_id FROM meal_plans WHERE plan_id = ? AND user_id = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$plan_id, $user_id]);

    if (!$checkStmt->fetch()) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "無權限修改此計畫"]);
        exit;
    }

    // 4. 執行更新或新增
    // 注意：前提是資料庫必須對 (plan_id, target_date) 建立 UNIQUE KEY
    $sql = "INSERT INTO meal_plan_daily_targets (plan_id, target_date, target_kcal) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE target_kcal = VALUES(target_kcal)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_id, $target_date, $target_kcal]);

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
