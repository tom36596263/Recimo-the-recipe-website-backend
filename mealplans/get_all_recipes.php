<?php
// 1. 引入 CORS 設定
require_once '../config/cors.php';

// 2. 加入禁止瀏覽器快取的 Header (解決您看不到更新欄位的問題)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// 3. 連接資料庫
require_once '../config/db_config.php';

try {
    $sql = "SELECT * FROM recipes WHERE parent_recipe_id IS NULL ORDER BY recipe_created_at DESC";
    $stmt = $pdo->query($sql);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function ($r) {
        $servings = (isset($r['recipe_servings']) && (int)$r['recipe_servings'] > 0)
            ? (int)$r['recipe_servings']
            : 1;

        return [
            "recipe_id" => (int)$r['recipe_id'],
            "recipe_title" => $r['recipe_title'],
            "recipe_image_url" => $r['recipe_image_url'],
            "recipe_servings" => $servings,
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
