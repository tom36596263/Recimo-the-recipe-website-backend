<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// 取得輸入資料
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            /**
             * 動作：抓取所有檢舉清單
             * 邏輯：
             * 1. JOIN users 取得檢舉人姓名
             * 2. 使用 CASE WHEN 根據 report_type 決定要顯示留言內容還是成品照文字
             */
            $sql = "SELECT 
                        r.*, 
                        u.user_name AS reporter_name,
                        CASE 
                            WHEN r.report_type = 'comment' THEN c.comment_text
                            WHEN r.report_type = 'gallery' THEN g.gallery_text
                            WHEN r.report_type = 'recipe' THEN rec.recipe_description
                            ELSE '未知內容'
                        END AS target_content,
                        CASE 
                            WHEN r.report_type = 'gallery' THEN g.gallery_url
                            WHEN r.report_type = 'recipe' THEN rec.recipe_image_url
                            ELSE NULL
                        END AS target_image
                    FROM recipe_report r
                    LEFT JOIN users u ON r.user_id = u.user_id
                    LEFT JOIN recipe_comments c ON r.target_id = c.comment_id AND r.report_type = 'comment'
                    LEFT JOIN recipe_gallery g ON r.target_id = g.gallery_id AND r.report_type = 'gallery'
                    LEFT JOIN recipes rec ON r.target_id = rec.recipe_id AND r.report_type = 'recipe'
                    ORDER BY r.report_at DESC";

            $result = $pdo->query($sql);
            $reports = $result->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $reports
            ]);
            break;

        case 'POST':
            /**
             * 動作：更新檢舉狀態 (例如從 pending 改為 resolved)
             * 參數：report_id, status (resolved / ignored)
             */
            $report_id = $input['report_id'] ?? null;
            $status = $input['status'] ?? 'resolved';

            if (!$report_id) {
                throw new Exception("缺少檢舉編號");
            }

            $stmt = $pdo->prepare("UPDATE recipe_report SET status = ? WHERE report_id = ?");
            $stmt->execute([$status, $report_id]);

            echo json_encode([
                'success' => true,
                'message' => '檢舉狀態已更新'
            ]);
            break;

        case 'DELETE':
            /**
             * 動作：這是在「處置」內容，管理員確認檢舉屬實後，直接刪除違規內容
             * 參數：report_type, target_id
             */
            $type = $_GET['report_type'] ?? '';
            $target_id = $_GET['target_id'] ?? 0;

            if ($type === 'comment') {
                $stmt = $pdo->prepare("DELETE FROM recipe_comments WHERE comment_id = ?");
            } elseif ($type === 'gallery') {
                $stmt = $pdo->prepare("DELETE FROM recipe_gallery WHERE gallery_id = ?");
            } else {
                throw new Exception("不支援的刪除類型");
            }

            $stmt->execute([$target_id]);

            echo json_encode([
                'success' => true,
                'message' => '違規內容已成功移除'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '不支援的請求方法']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '伺服器錯誤：' . $e->getMessage()
    ]);
}
?>