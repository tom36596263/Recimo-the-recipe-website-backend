<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");
// 🏆 修正 1：強制瀏覽器與伺服器不緩存 GET 結果
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// --- GET：獲取檢舉清單 ---
if ($method === 'GET') {
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
            /* 1. 留言檢舉 */
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
                '留言內容' COLLATE utf8mb4_unicode_ci AS display_title,
                c.recipe_id AS recipe_id
            FROM reported_comments rc
            LEFT JOIN recipe_comments c ON rc.comment_id = c.comment_id

            UNION ALL

            /* 2. 成品照檢舉 */
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
                '成品照描述' COLLATE utf8mb4_unicode_ci AS display_title,
                gal.recipe_id AS recipe_id
            FROM reported_galleries rg
            LEFT JOIN recipe_gallery gal ON rg.gallery_id = gal.gallery_id

            UNION ALL

            /* 3. 食譜檢舉 */
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
                r.recipe_title COLLATE utf8mb4_unicode_ci AS display_title,
                rr.recipe_id AS recipe_id
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

// --- POST：處理審核操作 ---
elseif ($method === 'POST') {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        $report_full_id = $input['report_id'] ?? null;
        $report_type = $input['report_type'] ?? '';    
        $target_id = $input['target_id'] ?? null;      
        $new_status_text = $input['status'] ?? 'pending'; 

        if (!$report_full_id || !$report_type || !$target_id) {
            throw new Exception("Missing required parameters.");
        }

        $pure_report_id = preg_replace('/[^\d]/', '', $report_full_id);

        $config_map = [
            'recipe' => [
                'report_table' => 'reported_recipes',
                'report_pk' => 'reported_recipe_id',
                'content_table' => 'recipes',
                'content_pk' => 'recipe_id'
            ],
            'comment' => [
                'report_table' => 'reported_comments',
                'report_pk' => 'reported_comment_id',
                'content_table' => 'recipe_comments',
                'content_pk' => 'comment_id'
            ],
            'gallery' => [
                'report_table' => 'reported_galleries',
                'report_pk' => 'reported_gallery_id',
                'content_table' => 'recipe_gallery',
                'content_pk' => 'gallery_id'
            ]
        ];

        if (!isset($config_map[$report_type])) {
            throw new Exception("Invalid report type: " . $report_type);
        }

        $cfg = $config_map[$report_type];

        $status_val = 0;
        if ($new_status_text === 'resolved') $status_val = 1;
        elseif ($new_status_text === 'ignored') $status_val = 2;

        $pdo->beginTransaction();

        // 1. 更新檢舉紀錄表
        $sql_report = "UPDATE {$cfg['report_table']} 
                       SET status = :status, update_at = NOW() 
                       WHERE {$cfg['report_pk']} = :id";
        $stmt1 = $pdo->prepare($sql_report);
        $stmt1->execute([':status' => $status_val, ':id' => $pure_report_id]);

        // 2. 同步更新內容顯示狀態 (0=正常, 1=下架)
        $target_display_status = ($new_status_text === 'resolved') ? 1 : 0;
        
        $sql_content = "UPDATE {$cfg['content_table']} 
                        SET status = :t_status 
                        WHERE {$cfg['content_pk']} = :t_id";
        $stmt2 = $pdo->prepare($sql_content);
        $stmt2->execute([':t_status' => $target_display_status, ':t_id' => $target_id]);

        $pdo->commit();

        // 🏆 修正 2：回傳影響行數，方便確認檢舉紀錄表是否真的有被更新
        echo json_encode([
            'success' => true, 
            'report_updated' => $stmt1->rowCount(),
            'content_updated' => $stmt2->rowCount(),
            'message' => '狀態更新成功'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'POST Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
?>