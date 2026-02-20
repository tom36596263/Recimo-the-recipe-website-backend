<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$plan_id    = $input['plan_id'] ?? null;
$user_id    = $input['user_id'] ?? null;
$title      = $input['title'] ?? null;
$start_date = $input['start_date'] ?? null;
$end_date   = $input['end_date'] ?? null;

if (!$plan_id || !$user_id || !$start_date || !$end_date) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "參數不足"]);
    exit;
}

try {
    $sql = "UPDATE meal_plans 
            SET title = ?, start_date = ?, end_date = ? 
            WHERE plan_id = ? AND user_id = ?";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$title, $start_date, $end_date, $plan_id, $user_id]);

    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        $error = $stmt->errorInfo();
        echo json_encode(["success" => false, "error" => $error[2]]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
