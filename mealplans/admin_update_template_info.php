<?php
// recimo_api/mealplans/admin_update_template_info.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$template_id = $input['template_id'] ?? null;
$title = $input['title'] ?? null;
$total_days = $input['total_days'] ?? null;
$description = $input['description'] ?? '';

// 簡單驗證
if (!$template_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "缺少 template_id"]);
    exit;
}

try {
    // 這裡我們不檢查 title 或 total_days 是否為空，因為有可能只更新其中一個
    // 但為了安全起見，SQL 語法會全部更新，所以前端必須傳送完整資料（您的前端代碼已經做到了）

    $sql = "UPDATE meal_plan_templates 
            SET title = ?, total_days = ?, description = ? 
            WHERE template_id = ?";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$title, $total_days, $description, $template_id]);

    if ($result) {
        // 如果減少天數，我們需要順便把多餘天數的食譜刪掉嗎？
        // 通常建議由資料庫 FK 設定 ON DELETE CASCADE 或在此手動刪除
        // 這裡示範手動刪除「超過新天數」的食譜 (非必要，但建議加上)
        if ($total_days) {
            $delSql = "DELETE FROM meal_plan_template_items WHERE template_id = ? AND day_number > ?";
            $delStmt = $pdo->prepare($delSql);
            $delStmt->execute([$template_id, $total_days]);
        }

        echo json_encode(["status" => "success", "message" => "更新成功"]);
    } else {
        echo json_encode(["status" => "error", "message" => "資料庫更新失敗"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
