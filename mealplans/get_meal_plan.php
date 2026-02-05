<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$plan_id = $_GET['plan_id'] ?? null;
$user_id = $_GET['user_id'] ?? null;

if (!$plan_id || !$user_id) {
    http_response_code(400);
    echo json_encode(["error" => "缺少必要參數"]);
    exit;
}

try {
    $sql = "SELECT p.*, t.template_url FROM meal_plans p
            LEFT JOIN meal_plan_cover_template t ON p.cover_template_id = t.cover_template_id
            WHERE p.plan_id = ? AND p.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_id, $user_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    echo $plan ? json_encode($plan) : json_encode(["error" => "查無計畫"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
