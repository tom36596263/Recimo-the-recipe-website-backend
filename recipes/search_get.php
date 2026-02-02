<?php
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
require_once '../config/db_config.php';

try {
    // 抓取食譜
    $recipes = $pdo->query("SELECT * FROM recipes")->fetchAll(PDO::FETCH_ASSOC);
    // 抓取商品
    $products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
    // 抓取關聯表
    $recipe_tag = $pdo->query("SELECT * FROM recipe_tag")->fetchAll(PDO::FETCH_ASSOC);
    // 抓取標籤定義
    $tags = $pdo->query("SELECT * FROM tags")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'recipes' => $recipes,
            'products' => $products,
            'recipe_tag' => $recipe_tag,
            'tags' => $tags
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}