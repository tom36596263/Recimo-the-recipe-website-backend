<?php
// 取得特定使用者的所有食譜、使用者資訊及其食材清單
require_once '../config/cors.php';
require_once '../config/db_config.php';


header('Content-Type: application/json; charset=utf-8');

// 僅允許 GET 方法
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode('Method Not Allowed', JSON_UNESCAPED_UNICODE);
    exit;
}

// 取得 user_id 參數
$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
    http_response_code(400);
    echo json_encode('缺少 user_id 參數', JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 查詢該作者的所有食譜，並帶出作者 user_name
    $sql = 'SELECT r.*, u.user_name, u.user_url FROM recipes r LEFT JOIN users u ON r.author_id = u.user_id WHERE r.author_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 查詢每道食譜的食材（含數量、單位、備註等）
    foreach ($recipes as &$recipe) {
        $recipe_id = $recipe['recipe_id'];
        $sql_ing = 'SELECT DISTINCT
            ri.ingredient_id,
            i.ingredient_name,
            i.main_category,
            i.sub_category,
            i.ingredient_image_url,
            ri.amount,
            ri.unit_name,
            ri.remark
        FROM recipe_ingredients ri
        JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
        WHERE ri.recipe_id = ?';
        $stmt_ing = $pdo->prepare($sql_ing);
        $stmt_ing->execute([$recipe_id]);
        $all_ingredients = $stmt_ing->fetchAll(PDO::FETCH_ASSOC);

        // 過濾重複 ingredient_id，只保留第一筆
        $unique_ingredients = [];
        $seen = [];
        foreach ($all_ingredients as $ing) {
            if (!in_array($ing['ingredient_id'], $seen)) {
                $unique_ingredients[] = $ing;
                $seen[] = $ing['ingredient_id'];
            }
        }
        $recipe['ingredients'] = $unique_ingredients;
    }

    echo json_encode($recipes, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode('資料庫錯誤: ' . $e->getMessage(), JSON_UNESCAPED_UNICODE);
    exit;
}
