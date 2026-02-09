<?php
// recimo_api/mealplans/admin_get_template_details.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$template_id = $_GET['template_id'] ?? null;

if (!$template_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "缺少 template_id"]);
    exit;
}

try {
    // 撈取模板基本資料 + 封面圖片網址
    // 注意：這裡假設 meal_plan_templates 有 cover_template_id 欄位
    $sql = "SELECT t.*, c.template_url 
            FROM meal_plan_templates t
            LEFT JOIN meal_plan_cover_template c ON t.cover_template_id = c.cover_template_id
            WHERE t.template_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$template_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode(["status" => "success", "data" => $data]);
    } else {
        // 如果查不到，回傳錯誤讓前端知道
        echo json_encode(["status" => "error", "message" => "查無此模板"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
