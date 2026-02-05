<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

try {
    // 🔴 依照要求：僅顯示 parent_recipe_id 為空的食譜
    $sql = "SELECT * FROM recipes WHERE parent_recipe_id IS NULL ORDER BY recipe_created_at DESC";
    $stmt = $pdo->query($sql);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 格式化數值型態 (避免 JSON 出現字串類型的數字)
    $formatted = array_map(function ($r) {
        return [
            "recipe_id" => (int)$r['recipe_id'],
            "recipe_title" => $r['recipe_title'],
            "recipe_image_url" => $r['recipe_image_url'],
            "recipe_kcal_per_100g" => (float)$r['recipe_kcal_per_100g'],
            "recipe_protein_per_100g" => (float)$r['recipe_protein_per_100g'],
            "recipe_fat_per_100g" => (float)$r['recipe_fat_per_100g'],
            "recipe_carbs_per_100g" => (float)$r['recipe_carbs_per_100g'],
            "recipe_difficulty" => (float)$r['recipe_difficulty']
        ];
    }, $recipes);

    echo json_encode($formatted);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
