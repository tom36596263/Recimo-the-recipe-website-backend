<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode('Method Not Allowed', JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$recipe_id = $input['recipe_id'] ?? '';
$user_id = $input['user_id'] ?? '';
$content = $input['content'] ?? '';

if (!$recipe_id || !$user_id || !$content) {
    http_response_code(400);
    echo json_encode('欄位不得為空', JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 對應你的資料庫欄位名
    $sql = 'INSERT INTO recipe_comments (recipe_id, user_id, comment_text, comment_at, is_display) 
            VALUES (?, ?, ?, NOW(), 1)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id, $user_id, $content]);

    // 回傳 success 給前端 handlePostComment 判斷
    echo json_encode(['success' => true, 'message' => '新增成功'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '資料庫錯誤: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}