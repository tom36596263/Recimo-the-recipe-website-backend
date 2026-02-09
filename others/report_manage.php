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
            /* 1. 留言檢舉：從留言表 c 抓 recipe_id */
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
                c.recipe_id AS recipe_id  -- 🏆 新增：抓取留言所屬食譜
            FROM reported_comments rc
            LEFT JOIN recipe_comments c ON rc.comment_id = c.comment_id

            UNION ALL

            /* 2. 成品照檢舉：從成品照表 gal 抓 recipe_id */
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
                gal.recipe_id AS recipe_id -- 🏆 新增：抓取成品照所屬食譜
            FROM reported_galleries rg
            LEFT JOIN recipe_gallery gal ON rg.gallery_id = gal.gallery_id

            UNION ALL

            /* 3. 食譜檢舉：target_id 本身就是 recipe_id */
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
                rr.recipe_id AS recipe_id -- 🏆 新增：食譜檢舉的食譜 ID 就是它自己
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
?>