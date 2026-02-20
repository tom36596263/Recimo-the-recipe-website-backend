<?php
// recimo_api/mealplans/admin_remove_template_item.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$item_id = $input['template_item_id'] ?? null; // 前端傳來的 key

if (!$item_id) {
    echo json_encode(["success" => false, "message" => "缺少 item_id"]);
    exit;
}

try {
    // 刪除指定項目 (注意：這裡的欄位是 item_id)
    $sql = "DELETE FROM meal_plan_template_items WHERE item_id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$item_id]);

    if ($result) {
        echo json_encode(["success" => true, "message" => "移除成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "移除失敗"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
