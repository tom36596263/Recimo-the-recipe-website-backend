<?php
die("我是正確的檔案！"); // 👈 加入這行測試
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json');

try {
    // 查詢所有母食譜 (parent_recipe_id 為 NULL)
    $sql = "SELECT * FROM recipes WHERE parent_recipe_id IS NULL ORDER BY recipe_created_at DESC";
    $stmt = $pdo->query($sql);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 格式化資料
    $formatted = array_map(function ($r) {
        // 處理份數：確保有值且大於 0，否則預設為 1 (避免前端除以 0 壞掉)
        $servings = (isset($r['recipe_servings']) && (int)$r['recipe_servings'] > 0)
            ? (int)$r['recipe_servings']
            : 1;

        return [
            "recipe_id" => (int)$r['recipe_id'],
            "recipe_title" => $r['recipe_title'],
            "recipe_image_url" => $r['recipe_image_url'],

            // 🟢 關鍵新增：回傳份數
            "recipe_servings" => $servings,

            // 營養素 (雖然欄位名寫 per_100g，但根據您的需求這可能是總熱量)
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
