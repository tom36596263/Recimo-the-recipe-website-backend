<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$plan_id           = $input['plan_id'] ?? null;
$user_id           = $input['user_id'] ?? null;
$cover_type        = $input['cover_type'] ?? null; // 1 或 2
$cover_template_id = $input['cover_template_id'] ?? null;
$custom_cover_url  = $input['custom_cover_url'] ?? null;

if (!$plan_id || !$user_id || $cover_type === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "參數不足"]);
    exit;
}

try {
    // 💡 這裡是單純的模式切換，不需要動實體檔案
    $sql = "UPDATE meal_plans 
            SET cover_type = ?, 
                cover_template_id = ?, 
                custom_cover_url = ? 
            WHERE plan_id = ? AND user_id = ?";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $cover_type,
        $cover_template_id,
        $custom_cover_url, // 這裡是前端傳來的現有路徑字串
        $plan_id,
        $user_id
    ]);

    if ($result) {
        echo json_encode(["success" => true]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
