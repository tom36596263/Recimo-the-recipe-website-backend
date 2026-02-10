<?php
// recimo_api/log/get_recipe_logs.php

// Debug 用
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json');

// 1. 獲取參數
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$recipe_id = isset($_GET['recipe_id']) ? intval($_GET['recipe_id']) : 0;

if ($user_id <= 0 || $recipe_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

try {
    // 2. 查詢該使用者針對特定食譜的所有日誌
    $sql = "
        SELECT 
            cooking_log_id,
            actual_time,
            satisfaction_rating,
            technique_rating,
            complexity_rating,
            log_summary,
            log_image_url,
            logged_at
        FROM cooking_logs
        WHERE user_id = ? AND recipe_id = ?
        ORDER BY logged_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $recipe_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 回傳資料
    echo json_encode([
        'status' => 'success',
        'logs' => $logs
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
