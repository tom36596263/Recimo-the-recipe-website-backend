<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

// 1. 強制禁止瀏覽器快取 (避免讀到舊結構)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

$plan_id = $_GET['plan_id'] ?? null;

try {
    // 2. 修改 SQL：加入 r.recipe_servings
    $sql = "SELECT i.*, 
                   r.recipe_title, 
                   r.recipe_image_url, 
                   r.recipe_kcal_per_100g, 
                   r.recipe_servings 
            FROM meal_plan_items i
            JOIN recipes r ON i.recipe_id = r.recipe_id
            WHERE i.plan_id = ? 
            ORDER BY i.planned_date, i.meal_type";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $res = array_map(fn($it) => [
        "item_id" => $it['item_id'],
        "meal_type" => (int)$it['meal_type'],
        "planned_date" => $it['planned_date'],
        "recipe_id" => $it['recipe_id'],
        "detail" => [
            "recipe_title" => $it['recipe_title'],
            "recipe_image_url" => $it['recipe_image_url'],
            "recipe_kcal_per_100g" => $it['recipe_kcal_per_100g'],
            // 3. 加入份數欄位 (若資料庫是 NULL 或 0，預設回傳 1)
            "recipe_servings" => (isset($it['recipe_servings']) && (int)$it['recipe_servings'] > 0)
                ? (int)$it['recipe_servings']
                : 1
        ]
    ], $items);

    echo json_encode($res);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
