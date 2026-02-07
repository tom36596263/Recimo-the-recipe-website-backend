<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // ... GET 部分保持不變 ...
    try {
        $content_col_name = 'NULL';
        $check_col = $pdo->query("DESCRIBE recipe_comments");
        $columns = $check_col->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('comment_content', $columns)) { $content_col_name = 'c.comment_content'; }
        elseif (in_array('content', $columns)) { $content_col_name = 'c.content'; }
        elseif (in_array('comment_text', $columns)) { $content_col_name = 'c.comment_text'; }

        $type_translator = "
            CASE 
                WHEN report_type = '1' THEN '垃圾訊息 / 廣告'
                WHEN report_type = '2' THEN '仇恨或攻擊言論'
                WHEN report_type = '3' THEN '色情或不當內容'
                WHEN report_type = '4' THEN '不實資訊'
                WHEN report_type = '5' THEN '內容侵權'
                ELSE '其他原因' 
            END COLLATE utf8mb4_unicode_ci AS type_text
        ";

        $sql = "
            SELECT 
                CONCAT('comment_', rc.reported_comment_id) COLLATE utf8mb4_unicode_ci AS report_id, 
                'comment' COLLATE utf8mb4_unicode_ci AS report_type, 
                $type_translator,
                rc.report_reason COLLATE utf8mb4_unicode_ci AS reason, 
                rc.reporter_id AS user_id,
                CASE rc.status WHEN 0 THEN 'pending' WHEN 1 THEN 'resolved' WHEN 2 THEN 'ignored' ELSE 'pending' END COLLATE utf8mb4_unicode_ci AS status,
                rc.reported_at AS report_at,
                rc.update_at AS update_at,
                NULL AS report_img,
                rc.comment_id AS target_id,
                $content_col_name COLLATE utf8mb4_unicode_ci AS display_text,
                '留言內容' COLLATE utf8mb4_unicode_ci AS display_title
            FROM reported_comments rc
            LEFT JOIN recipe_comments c ON rc.comment_id = c.comment_id
            UNION ALL
            SELECT 
                CONCAT('gallery_', rg.reported_gallery_id) COLLATE utf8mb4_unicode_ci AS report_id, 
                'gallery' COLLATE utf8mb4_unicode_ci AS report_type, 
                $type_translator,
                rg.report_reason COLLATE utf8mb4_unicode_ci AS reason, 
                rg.reporter_id AS user_id,
                CASE rg.status WHEN 0 THEN 'pending' WHEN 1 THEN 'resolved' WHEN 2 THEN 'ignored' ELSE 'pending' END COLLATE utf8mb4_unicode_ci AS status,
                rg.reported_at AS report_at,
                rg.update_at AS update_at,
                gal.gallery_url COLLATE utf8mb4_unicode_ci AS report_img,
                rg.gallery_id AS target_id,
                gal.gallery_text COLLATE utf8mb4_unicode_ci AS display_text,
                '成品照描述' COLLATE utf8mb4_unicode_ci AS display_title
            FROM reported_galleries rg
            LEFT JOIN recipe_gallery gal ON rg.gallery_id = gal.gallery_id
            UNION ALL
            SELECT 
                CONCAT('recipe_', rr.reported_recipe_id) COLLATE utf8mb4_unicode_ci AS report_id, 
                'recipe' COLLATE utf8mb4_unicode_ci AS report_type, 
                $type_translator,
                rr.report_reason COLLATE utf8mb4_unicode_ci AS reason, 
                rr.reporter_id AS user_id,
                CASE rr.status WHEN 0 THEN 'pending' WHEN 1 THEN 'resolved' WHEN 2 THEN 'ignored' ELSE 'pending' END COLLATE utf8mb4_unicode_ci AS status,
                rr.reported_at AS report_at,
                rr.update_at AS update_at,
                r.recipe_image_url COLLATE utf8mb4_unicode_ci AS report_img,
                rr.recipe_id AS target_id,
                r.recipe_description COLLATE utf8mb4_unicode_ci AS display_text,
                r.recipe_title COLLATE utf8mb4_unicode_ci AS display_title
            FROM reported_recipes rr
            LEFT JOIN recipes r ON rr.recipe_id = r.recipe_id
            ORDER BY report_at DESC";

        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'count' => count($data), 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'GET Error: ' . $e->getMessage()]);
    }
} 
else if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $report_id_raw = $input['report_id'] ?? null;
    $report_id = preg_replace('/^[a-z]+_/', '', $report_id_raw); 
    $report_type = $input['report_type'] ?? null; 
    $new_status = $input['status'] ?? null; 
    $target_id = $input['target_id'] ?? null;

    if (!$report_id || !$report_type || !$new_status) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
        exit;
    }

    $status_int = ($new_status === 'resolved') ? 1 : (($new_status === 'ignored') ? 2 : 0);

    try {
        $pdo->beginTransaction();

        $table_map = [
            'comment' => ['rep' => 'reported_comments', 'rep_pk' => 'reported_comment_id', 'cont' => 'recipe_comments', 'cont_pk' => 'comment_id'],
            'gallery' => ['rep' => 'reported_galleries', 'rep_pk' => 'reported_gallery_id', 'cont' => 'recipe_gallery', 'cont_pk' => 'gallery_id'],
            'recipe'  => ['rep' => 'reported_recipes', 'rep_pk' => 'reported_recipe_id', 'cont' => 'recipes', 'cont_pk' => 'recipe_id']
        ];

        if (!isset($table_map[$report_type])) { throw new Exception("Unsupported type"); }
        $map = $table_map[$report_type];

        // --- 1. 更新【原始內容表】 (先更新內容，因為它最容易出錯) ---
        if ($target_id) {
            $target_status = ($new_status === 'resolved') ? 1 : 0;
            $sql_content = "UPDATE {$map['cont']} SET status = :t_status WHERE {$map['cont_pk']} = :target_id";
            $stmt2 = $pdo->prepare($sql_content);
            $stmt2->execute([':t_status' => $target_status, ':target_id' => $target_id]);
        }

        // --- 2. 更新【檢舉紀錄表】 ---
        // 🏆 修正：檢查是否有 update_at 欄位防止更新失敗
        $check_update_at = $pdo->query("DESCRIBE {$map['rep']}");
        $rep_cols = $check_update_at->fetchAll(PDO::FETCH_COLUMN);
        
        $update_sql_part = in_array('update_at', $rep_cols) ? ", update_at = NOW()" : "";
        
        $sql_report = "UPDATE {$map['rep']} SET status = :status $update_sql_part WHERE {$map['rep_pk']} = :id";
        $stmt1 = $pdo->prepare($sql_report);
        $stmt1->execute([':status' => $status_int, ':id' => $report_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "成功更新為 $new_status"]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'message' => '更新失敗：' . $e->getMessage()]);
    }
}
?>