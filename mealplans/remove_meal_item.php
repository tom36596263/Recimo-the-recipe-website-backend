<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$item_id = $input['item_id'] ?? null;
$user_id = $input['user_id'] ?? null;

try {
    // 透過 JOIN 確保該項目屬於此使用者
    $sql = "DELETE i FROM meal_plan_items i 
            JOIN meal_plans p ON i.plan_id = p.plan_id 
            WHERE i.item_id = ? AND p.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$item_id, $user_id]);

    echo json_encode(["success" => $stmt->rowCount() > 0]);
} catch (PDOException $e) {
    echo json_encode(["success" => false]);
}
