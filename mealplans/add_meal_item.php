<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

try {
    $sql = "INSERT INTO meal_plan_items (plan_id, recipe_id, planned_date, meal_type, sort_order) 
            VALUES (?, ?, ?, ?, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$input['plan_id'], $input['recipe_id'], $input['date'], $input['meal_type']]);

    echo json_encode(["success" => true, "new_id" => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(["success" => false]);
}
