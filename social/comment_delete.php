<?php
    require_once '../config/cors.php';
    require_once '../config/db_config.php';

    header('Content-Type: application/json; charset=utf-8');

    // 取得前端傳來的 JSON 資料
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $comment_id = $data['comment_id'] ?? null;
    $user_id = $data['user_id'] ?? null; // 🏆 之後從 Login 狀態取得

    if ($comment_id && $user_id) {
        try {
            // 執行刪除，限制必須是該使用者的留言
            $sql = "DELETE FROM recipe_comments WHERE comment_id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$comment_id, $user_id]);

            // 檢查是否有資料被刪除 (rowCount > 0 代表成功刪除)
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => '留言已刪除']);
            } else {
                echo json_encode(['success' => false, 'message' => '刪除失敗，可能是權限不足或留言不存在']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '缺少必要參數']);
    }
?>