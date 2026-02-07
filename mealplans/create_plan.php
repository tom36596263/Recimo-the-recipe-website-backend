<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$user_id    = $input['user_id'] ?? null;
$title      = $input['title'] ?? null;
$start_date = $input['start_date'] ?? null;
$end_date   = $input['end_date'] ?? null;

if (!$user_id || !$title || !$start_date || !$end_date) {
    http_response_code(400);
    echo json_encode(["error" => "參數不足"]);
    exit;
}

try {
    // 建立新計畫，cover_type 預設給 1 (官方模板), cover_template_id 預設給 1
    $sql = "INSERT INTO meal_plans (user_id, title, start_date, end_date, cover_type, cover_template_id) 
            VALUES (?, ?, ?, ?, 1, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $title, $start_date, $end_date]);

    $new_id = $pdo->lastInsertId();

    echo json_encode(["success" => true, "plan_id" => $new_id]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
