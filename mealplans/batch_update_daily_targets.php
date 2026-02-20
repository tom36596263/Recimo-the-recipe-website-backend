<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$plan_id     = $input['plan_id'] ?? null;
$target_kcal = $input['target_kcal'] ?? null;
$user_id     = $input['user_id'] ?? null;

if (!$plan_id || $target_kcal === null || !$user_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "參數不足"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. 安全檢查並取得計畫日期範圍
    $planSql = "SELECT start_date, end_date FROM meal_plans WHERE plan_id = ? AND user_id = ?";
    $planStmt = $pdo->prepare($planSql);
    $planStmt->execute([$plan_id, $user_id]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception("找不到計畫或無權限");
    }

    // 2. 準備插入語句
    $sql = "INSERT INTO meal_plan_daily_targets (plan_id, target_date, target_kcal) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE target_kcal = VALUES(target_kcal)";
    $stmt = $pdo->prepare($sql);

    // 3. 遍歷日期範圍並執行
    $start = new DateTime($plan['start_date']);
    $end   = new DateTime($plan['end_date']);
    $end->modify('+1 day'); // 確保包含結束當天

    $interval = new DateInterval('P1D');
    $period   = new DatePeriod($start, $interval, $end);

    foreach ($period as $date) {
        $stmt->execute([$plan_id, $date->format('Y-m-d'), $target_kcal]);
    }

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "已套用至全計畫"]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
