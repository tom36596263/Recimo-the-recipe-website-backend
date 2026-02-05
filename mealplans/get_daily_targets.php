<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$plan_id = $_GET['plan_id'] ?? null;

if (!$plan_id) {
    http_response_code(400);
    echo json_encode(["error" => "缺少 plan_id"]);
    exit;
}

try {
    $sql = "SELECT target_date, target_kcal FROM meal_plan_daily_targets WHERE plan_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_id]);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 直接回傳陣列
    echo json_encode($targets);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
