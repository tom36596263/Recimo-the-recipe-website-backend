<?php
    require_once '../config/cors.php';
    require_once '../config/db_config.php';

    header('Content-Type: application/json; charset=utf-8');

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $comment_id = $data['comment_id'] ?? null;
    // 🏆 新增：接收前端傳來的動作 (like 或 dislike)
    $action = $data['action'] ?? 'like'; 

    if ($comment_id) {
        try {
            if ($action === 'like') {
                // 執行點讚：+1
                $sql = "UPDATE recipe_comments SET like_count = like_count + 1 WHERE comment_id = ?";
                $msg = '點讚成功';
            } else {
                // 執行取消：-1 (使用 GREATEST 確保數字最低是 0，不會變成負數)
                $sql = "UPDATE recipe_comments SET like_count = GREATEST(0, like_count - 1) WHERE comment_id = ?";
                $msg = '已取消點讚';
            }
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$comment_id]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => $msg,
                    'new_action' => $action // 回傳現在的動作給前端確認
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '沒收到留言 ID']);
    }
?>