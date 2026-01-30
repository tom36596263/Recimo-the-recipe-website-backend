<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 這是「取得留言」功能
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 🚀 修正點：將 'id' 改為 'recipe_id'
$recipe_id = $_GET['recipe_id'] ?? '';

if (!$recipe_id) {
    http_response_code(400);
    echo json_encode(['message' => '缺少食譜 ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 這裡 SQL 語句裡的欄位名稱 recipe_id 是對應資料庫欄位，不用動
    $sql = 'SELECT * FROM recipe_comments WHERE recipe_id = ? ORDER BY comment_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($comments, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => '資料庫錯誤: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}