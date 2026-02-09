<?php
// recimo_api/mealplans/admin_create_plan.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

// 您可能需要接收一些初始參數，例如標題
$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '新計畫 ' . date('Y-m-d H:i'); // 預設標題
$total_days = 7; // 預設天數
$description = ''; // 預設描述
$cover_template_id = 1; // 預設封面模板 ID (請確認資料庫有此 ID)
$is_active = 0; // 預設為下架狀態，編輯完再上架

try {
    // 插入新記錄到 meal_plan_templates 表
    $sql = "INSERT INTO meal_plan_templates (title, description, cover_template_id, total_days, created_at, is_active) 
            VALUES (?, ?, ?, ?, NOW(), ?)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$title, $description, $cover_template_id, $total_days, $is_active]);

    if ($result) {
        $newId = $pdo->lastInsertId(); // 取得剛插入的 ID
        echo json_encode([
            "status" => "success",
            "message" => "計畫建立成功",
            "template_id" => $newId
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "建立失敗"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
