<?php
// recimo_api/mealplans/admin_add_template_item.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

// 接收前端傳來的 JSON 資料
$input = json_decode(file_get_contents('php://input'), true);

$template_id = $input['template_id'] ?? null;
$recipe_id   = $input['recipe_id'] ?? null;
$day         = $input['day'] ?? null;
$meal_type   = $input['meal_type'] ?? null;

// 1. 檢查必要參數
if (!$template_id || !$recipe_id || !$day || $meal_type === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "參數不足"]);
    exit;
}

try {
    // 🟢 修正：加入 sort_order 欄位
    // 我們先預設為 0，讓資料庫可以成功寫入
    $sort_order = 0;

    $sql = "INSERT INTO meal_plan_template_items (template_id, recipe_id, day_number, meal_type, sort_order) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    // 記得在 execute 陣列最後補上 $sort_order
    $result = $stmt->execute([$template_id, $recipe_id, $day, $meal_type, $sort_order]);

    if ($result) {
        $newItemId = $pdo->lastInsertId();
        echo json_encode([
            "success" => true,
            "message" => "新增成功",
            "item_id" => $newItemId
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "寫入資料庫失敗"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "資料庫錯誤: " . $e->getMessage()]);
}
