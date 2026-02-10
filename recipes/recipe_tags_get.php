<?php
// 檔案位置：C:\MAMP\htdocs\recimo_api\recipes\recipe_tags_get.php

require_once '../config/cors.php';
require_once '../config/db_config.php';

// 設定回傳格式為 JSON
header('Content-Type: application/json; charset=utf-8');

/**
 * 功能 A：取得「特定食譜」的所有標籤
 */
function getRecipeTags($pdo, $recipe_id) {
    // 這裡單純抓取標籤名稱與類型，不涉及食譜狀態，交由外層邏輯或 API 調用者決定
    $sql = "SELECT rt.tag_id, t.tag_name, t.tag_type
            FROM recipe_tag rt
            JOIN tags t ON rt.tag_id = t.tag_id
            WHERE rt.recipe_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 功能 B：取得系統「所有」標籤 (供搜尋或標籤選擇器使用)
 */
function getAllTags($pdo) {
    $sql = "SELECT tag_id, tag_name, tag_type FROM tags ORDER BY tag_type ASC, tag_id ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    $recipe_id = $_GET['recipe_id'] ?? null;
    $mode = $_GET['mode'] ?? null;

    // 🏆 模式 1：取得系統所有可用標籤 (mode=all 或沒給 id)
    if ($mode === 'all' || !$recipe_id) {
        $tags = getAllTags($pdo);
        echo json_encode([
            'success' => true,
            'mode' => 'all_tags',
            'data' => $tags
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🏆 模式 2：取得特定食譜已經綁定的標籤
    $tags = getRecipeTags($pdo, $recipe_id);
    
    echo json_encode([
        'success' => true,
        'mode' => 'recipe_tags',
        'recipe_id' => $recipe_id,
        'data' => $tags
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>