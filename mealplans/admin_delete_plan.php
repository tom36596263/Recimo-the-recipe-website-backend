<?php
// recimo_api/mealplans/admin_delete_plan.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$template_id = $input['template_id'] ?? null;

if (!$template_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "缺少 ID"]);
    exit;
}

try {
    $pdo->beginTransaction(); // 開啟交易模式，確保兩個刪除動作都成功

    // 1. 先刪除該計畫底下的所有食譜項目 (meal_plan_template_items)
    $sql_items = "DELETE FROM meal_plan_template_items WHERE template_id = ?";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$template_id]);

    // 2. 再刪除計畫本身 (meal_plan_templates)
    $sql_plan = "DELETE FROM meal_plan_templates WHERE template_id = ?";
    $stmt_plan = $pdo->prepare($sql_plan);
    $stmt_plan->execute([$template_id]);

    $pdo->commit(); // 提交交易
    echo json_encode(["status" => "success", "message" => "刪除成功"]);
} catch (PDOException $e) {
    $pdo->rollBack(); // 如果出錯，回復操作
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
