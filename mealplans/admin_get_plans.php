<?php
// 檔案位置: recimo_api/mealplans/admin_get_plans.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

try {
    // 查詢官方模板表的所有資料
    // 我們需要 template_id, title, created_at, is_active
    $sql = "SELECT template_id, title, created_at, is_active 
            FROM meal_plan_templates 
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $templates
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
