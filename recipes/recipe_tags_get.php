<?php
// C:\MAMP\htdocs\recimo_api\recipes\recipe_tags_get.php

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * 修正後的功能 A：取得「特定食譜」已有的標籤
 * 加上 status 檢查，確保下架的食譜不會外洩標籤資料
 */
function getRecipeTags($pdo, $recipe_id) {
    // 🏆 關鍵修正：JOIN recipes 表來檢查 status
    $sql = "SELECT rt.tag_id, t.tag_name, t.tag_type
            FROM recipe_tag rt
            JOIN tags t ON rt.tag_id = t.tag_id
            JOIN recipes r ON rt.recipe_id = r.recipe_id
            WHERE rt.recipe_id = ? AND (r.status = 0 OR r.status IS NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllTags($pdo) {
    $sql = "SELECT tag_id, tag_name, tag_type FROM tags ORDER BY tag_type ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $recipe_id = $_GET['recipe_id'] ?? null;

    if ($recipe_id) {
        $tags = getRecipeTags($pdo, $recipe_id);
        
        // 如果抓不到標籤，有可能是食譜被下架了
        echo json_encode([
            'success' => true,
            'mode' => 'specific_recipe',
            'data' => $tags
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $tags = getAllTags($pdo);
        echo json_encode([
            'success' => true,
            'mode' => 'all_tags',
            'data' => $tags
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '伺服器錯誤：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;