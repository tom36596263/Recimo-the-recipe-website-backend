<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

$template_id = $_GET['template_id'] ?? null;

if (!$template_id) {
    echo json_encode(["status" => "error", "message" => "缺少 template_id"]);
    exit;
}

try {
    // 🟢 修正：在 SQL 中補上 protein, carbs, fat 三個欄位
    $sql = "SELECT 
                ti.item_id,           
                ti.day_number as day,
                ti.meal_type, 
                ti.recipe_id,
                r.recipe_title,
                r.recipe_image_url as recipe_cover_img, 
                r.recipe_kcal_per_100g,
                r.recipe_protein_per_100g, 
                r.recipe_carbs_per_100g,  
                r.recipe_fat_per_100g    
            FROM meal_plan_template_items ti
            LEFT JOIN recipes r ON ti.recipe_id = r.recipe_id
            WHERE ti.template_id = ?
            ORDER BY ti.day_number ASC, ti.meal_type ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$template_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 格式化資料
    $result = array_map(function ($item) {
        return [
            'item_id' => $item['item_id'],
            'day' => $item['day'],
            'meal_type' => $item['meal_type'],
            'recipe_id' => $item['recipe_id'],
            'detail' => [
                'recipe_title' => $item['recipe_title'],
                'recipe_cover_img' => $item['recipe_cover_img'],
                'recipe_kcal_per_100g' => $item['recipe_kcal_per_100g'],
                'recipe_protein_per_100g' => $item['recipe_protein_per_100g'],
                'recipe_carbs_per_100g' => $item['recipe_carbs_per_100g'],
                'recipe_fat_per_100g' => $item['recipe_fat_per_100g']
            ]
        ];
    }, $items);

    echo json_encode(["status" => "success", "data" => $result]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
