<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$plan_id = $_GET['plan_id'] ?? null;

try {
    $sql = "SELECT i.*, r.recipe_title, r.recipe_image_url, r.recipe_kcal_per_100g 
            FROM meal_plan_items i
            JOIN recipes r ON i.recipe_id = r.recipe_id
            WHERE i.plan_id = ? ORDER BY i.planned_date, i.meal_type";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $res = array_map(fn($it) => [
        "item_id" => $it['item_id'],
        "meal_type" => (int)$it['meal_type'],
        "planned_date" => $it['planned_date'],
        "recipe_id" => $it['recipe_id'],
        "detail" => ["recipe_title" => $it['recipe_title'], "recipe_image_url" => $it['recipe_image_url'], "recipe_kcal_per_100g" => $it['recipe_kcal_per_100g']]
    ], $items);

    echo json_encode($res);
} catch (PDOException $e) {
    echo json_encode([]);
}
