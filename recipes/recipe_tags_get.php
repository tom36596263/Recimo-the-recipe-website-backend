<?php
// C:\MAMP\htdocs\recimo_api\recipes\recipe_tags_get.php

require_once '../config/cors.php';
require_once '../config/db_config.php';

// 設定標頭，確保瀏覽器正確解析 JSON
header('Content-Type: application/json; charset=utf-8');

/**
 * 功能 A：取得「特定食譜」已有的標籤
 */
function getRecipeTags($pdo, $recipe_id) {
    $sql = "SELECT rt.tag_id, t.tag_name, t.tag_type
            FROM recipe_tag rt
            JOIN tags t ON rt.tag_id = t.tag_id
            WHERE rt.recipe_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 功能 B：取得「所有」可用標籤（供燈箱選擇使用）
 */
function getAllTags($pdo) {
    $sql = "SELECT tag_id, tag_name, tag_type FROM tags ORDER BY tag_type ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- 邏輯開始 ---

try {
    // 檢查是否有傳入食譜 ID
    $recipe_id = $_GET['recipe_id'] ?? null;

    if ($recipe_id) {
        // 模式 1：給食譜詳情頁用的標籤
        $tags = getRecipeTags($pdo, $recipe_id);
        echo json_encode([
            'success' => true,
            'mode' => 'specific_recipe',
            'data' => $tags
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // 模式 2：給燈箱清單用的所有標籤
        $tags = getAllTags($pdo);
        echo json_encode([
            'success' => true,
            'mode' => 'all_tags',
            'data' => $tags
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    // 錯誤處理
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '資料庫連線或查詢失敗：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// 結束執行，避免任何意外的輸出破壞 JSON 格式
exit;