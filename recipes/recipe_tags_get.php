<?php
// C:\MAMP\htdocs\recimo_api\recipes\recipe_tags_get.php
require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

/**
 * 功能 A：取得食譜標籤 (支援 status 檢查)
 */
function getRecipeTags($pdo, $recipe_id) {
    $sql = "SELECT rt.tag_id, t.tag_name, t.tag_type
            FROM recipe_tag rt
            JOIN tags t ON rt.tag_id = t.tag_id
            JOIN recipes r ON rt.recipe_id = r.recipe_id
            WHERE rt.recipe_id = ? AND (r.status = 0 OR r.status IS NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 功能 B：取得系統所有標籤 (共用功能)
 */
function getAllTags($pdo) {
    $sql = "SELECT tag_id, tag_name, tag_type FROM tags ORDER BY tag_type ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $recipe_id = $_GET['recipe_id'] ?? null;
    $mode = $_GET['mode'] ?? null;

    // 🏆 模式 1：如果 mode 是 all，或者是沒給 recipe_id，就走「純標籤共用模式」
    if ($mode === 'all' || !$recipe_id) {
        $tags = getAllTags($pdo);
        echo json_encode([
            'success' => true,
            'mode' => 'all_tags',
            'data' => $tags
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🏆 模式 2：如果有給 recipe_id，就走「完整食譜詳情模式」
    $result = [];

    // 1. 取得主食譜與作者
    $sqlMain = "SELECT r.*, u.user_name as author_name, u.user_id as author_id, u.user_email, u.user_url as author_image
                FROM recipes r
                LEFT JOIN users u ON r.author_id = u.user_id 
                WHERE r.recipe_id = ? AND r.status = 0 LIMIT 1";
    $stmt = $pdo->prepare($sqlMain);
    $stmt->execute([$recipe_id]);
    $main = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$main) {
        throw new Exception('找不到該食譜或已下架', 404);
    }

    // 封裝作者資訊對接前端
    $main['author'] = [
        'author_id' => $main['author_id'],
        'author_name' => $main['author_name'],
        'author_image' => $main['author_image'],
        'user_email' => $main['user_email']
    ];
    $result['main'] = $main;

    // 2. 取得食材
    $sqlIng = "SELECT ri.*, i.ingredient_name, i.kcal_per_100g, i.protein_per_100g, i.fat_per_100g, i.carbs_per_100g, i.gram_conversion
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = ?";
    $stmt = $pdo->prepare($sqlIng);
    $stmt->execute([$recipe_id]);
    $result['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 取得步驟與步驟食材
    $sqlSteps = "SELECT *, TIME_TO_SEC(step_total_time) as total_seconds FROM steps WHERE recipe_id = ? ORDER BY step_order ASC";
    $stmt = $pdo->prepare($sqlSteps);
    $stmt->execute([$recipe_id]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($steps as &$step) {
        $stmtIng = $pdo->prepare("SELECT ingredient_id FROM step_ingredients WHERE step_id = ?");
        $stmtIng->execute([$step['step_id']]);
        $step['step_ingredients'] = $stmtIng->fetchAll(PDO::FETCH_COLUMN);
    }
    $result['steps'] = $steps;

    // 4. 取得標籤
    $result['tags'] = getRecipeTags($pdo, $recipe_id);

    // 5. 取得改編列表
    $sqlAdapt = "SELECT r.*, u.user_name as author_name, u.user_id as author_id, u.user_url as author_image
                 FROM recipes r LEFT JOIN users u ON r.author_id = u.user_id 
                 WHERE r.parent_recipe_id = ? AND r.recipe_id != ? AND r.status = 0";
    $stmtA = $pdo->prepare($sqlAdapt);
    $stmtA->execute([$recipe_id, $recipe_id]);
    $adaptations = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    foreach ($adaptations as &$child) {
        $child['author'] = [
            'author_id' => $child['author_id'],
            'author_name' => $child['author_name'],
            'author_image' => $child['author_image']
        ];
        $child['tags'] = getRecipeTags($pdo, $child['recipe_id']);
    }
    $result['adaptations'] = $adaptations;

    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}