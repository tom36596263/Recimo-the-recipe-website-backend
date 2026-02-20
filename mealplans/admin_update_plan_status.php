<?php
// recimo_api/mealplans/admin_update_plan_status.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$template_id = $input['plan_id'] ?? null;
// 注意：這裡接收到的可能是 boolean (true/false) 或 number (1/0)，視前端傳什麼而定
// 我們統一轉成整數 1 或 0
$is_active = isset($input['is_active']) ? (int)$input['is_active'] : null;

if (!$template_id || $is_active === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "參數錯誤"]);
    exit;
}

try {
    $sql = "UPDATE meal_plan_templates SET is_active = ? WHERE template_id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$is_active, $template_id]);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "狀態更新成功"]);
    } else {
        echo json_encode(["status" => "error", "message" => "更新失敗"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
