<?php
header('Access-Control-Allow-Origin: http://localhost:5173'); 
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

header('Content-Type: application/json');
require_once '../config/db_config.php';

try {
    $sql = "SELECT r.*, 
            GROUP_CONCAT(DISTINCT t.tag_name) AS tag_names,
            GROUP_CONCAT(DISTINCT ri.ingredient_id) AS ingredient_ids
            FROM recipes r
            LEFT JOIN recipe_tag rt ON r.recipe_id = rt.recipe_id
            LEFT JOIN tags t ON rt.tag_id = t.tag_id
            LEFT JOIN recipe_ingredients ri ON r.recipe_id = ri.recipe_id
            GROUP BY r.recipe_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 輸出 JSON
    echo json_encode([
        'status' => 'success',
        'data' => $recipes
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}