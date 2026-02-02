<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            $recipe_id = $_GET['recipe_id'] ?? '';
            if (!$recipe_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => '缺少食譜 ID']);
                exit;
            }
            $sql = 'SELECT * FROM recipe_comments WHERE recipe_id = ? ORDER BY comment_at DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$recipe_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
            break;

        case 'POST':
            $action = $input['action'] ?? 'post';

            if ($action === 'like') {
                $comment_id = $input['comment_id'] ?? null;
                $like_type = $input['type'] ?? 'like';
                if (!$comment_id) throw new Exception('缺少留言 ID');

                if ($like_type === 'like') {
                    $sql = "UPDATE recipe_comments SET like_count = like_count + 1 WHERE comment_id = ?";
                    $msg = '點讚成功';
                } else {
                    $sql = "UPDATE recipe_comments SET like_count = GREATEST(0, like_count - 1) WHERE comment_id = ?";
                    $msg = '已取消點讚';
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$comment_id]);
                echo json_encode(['success' => true, 'message' => $msg]);

            } else {
                $recipe_id = $input['recipe_id'] ?? '';
                $user_id = $input['user_id'] ?? '';
                $content = $input['content'] ?? '';

                if (!$recipe_id || !$user_id || !$content) throw new Exception('欄位不得為空');

                // 補上 like_count = 0 確保嚴格模式下也能成功插入
                $sql = 'INSERT INTO recipe_comments (recipe_id, user_id, comment_text, comment_at, is_display, like_count) 
                        VALUES (?, ?, ?, NOW(), 1, 0)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$recipe_id, $user_id, $content]);
                echo json_encode(['success' => true, 'message' => '新增成功']);
            }
            break;

        case 'DELETE':
            $comment_id = $_GET['comment_id'] ?? $input['comment_id'] ?? null;
            $user_id = $_GET['user_id'] ?? $input['user_id'] ?? null;

            if (!$comment_id || !$user_id) throw new Exception('缺少必要參數');

            $sql = "DELETE FROM recipe_comments WHERE comment_id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$comment_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => '留言已刪除']);
            } else {
                echo json_encode(['success' => false, 'message' => '刪除失敗，權限不足或不存在']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    // 輸出具體錯誤訊息，方便你除錯
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}