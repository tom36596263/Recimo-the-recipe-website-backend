<?php
// recimo_api/log/get_user_cooked_recipes.php

// Debug 用 (上線前請註解)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json');

// 1. 獲取 user_id (假設從 GET 參數傳入，或者您可以改從 Session/Token 解析)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid User ID']);
    exit;
}

try {
    // 2. 查詢該使用者做過的所有食譜 (去重複)
    // 我們同時計算 log_count (做過幾次) 並抓取最後一次做的時間 (last_cooked_at) 用來排序
    $sql = "
        SELECT 
            r.recipe_id,
            r.recipe_title,
            r.recipe_image_url,
            r.recipe_description, 
            COUNT(cl.cooking_log_id) as log_count,
            MAX(cl.logged_at) as last_cooked_at
        FROM cooking_logs cl
        JOIN recipes r ON cl.recipe_id = r.recipe_id
        WHERE cl.user_id = ?
        GROUP BY r.recipe_id
        ORDER BY last_cooked_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 回傳資料
    echo json_encode([
        'status' => 'success',
        'recipes' => $recipes
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
