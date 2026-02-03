<?php
// recipes/get_ingredients.php (前台用)
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

try {
    // 接收篩選參數
    $category = isset($_GET['category']) ? $_GET['category'] : '';

    if ($category) {
        // --- 有分類的情況 ---
        $sql = "SELECT ingredient_id, ingredient_name, main_category, sub_category, 
                       ingredient_image_url, kcal_per_100g, fat_per_100g, unit_name 
                FROM ingredients 
                WHERE main_category = :category 
                AND is_active = 1"; 
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['category' => $category]);
    } else {
        $sql = "SELECT ingredient_id, ingredient_name, main_category, sub_category, 
                       ingredient_image_url, kcal_per_100g, fat_per_100g, unit_name 
                FROM ingredients
                WHERE is_active = 1";
        
        $stmt = $pdo->query($sql);
    }

    $ingredients = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'count' => count($ingredients),
        'data' => $ingredients
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => '查詢失敗: ' . $e->getMessage()
    ]);
}
