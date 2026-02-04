<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

// 偵錯機制：確保 PDO 連線存在
if (!isset($pdo)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed.',
        'debug_info' => 'Check your db_config.php file and database server status.'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: 獲取所有檢舉清單 ---
if ($method === 'GET') {
    try {
        $sql = "
            -- 1. 留言 (不帶圖)
            SELECT 
                reported_comment_id AS report_id, 
                'comment' AS report_type, 
                CASE report_type 
                    WHEN 1 THEN '垃圾訊息' WHEN 2 THEN '仇恨言論' 
                    WHEN 3 THEN '色情內容' WHEN 4 THEN '不實資訊' 
                    ELSE '其他' END AS type_text,
                report_reason AS reason, 
                reporter_id AS user_id,
                CASE status WHEN 0 THEN 'pending' WHEN 1 THEN 'resolved' WHEN 2 THEN 'ignored' ELSE 'pending' END AS status,
                reported_at AS report_at,
                NULL AS report_img,             -- 留言固定無圖
                reported_comment_id AS target_id
            FROM reported_comments
            
            UNION ALL
            
            -- 2. 成品 (只有這段 JOIN 圖片表)
            SELECT 
                rg.reported_gallery_id AS report_id, 
                'gallery' AS report_type, 
                CASE rg.report_type 
                    WHEN 1 THEN '垃圾訊息' WHEN 2 THEN '色情內容' 
                    WHEN 3 THEN '內容侵權' WHEN 4 THEN '仇恨言論' 
                    ELSE '其他' END AS type_text,
                rg.report_reason AS reason, 
                rg.reporter_id AS user_id,
                CASE rg.status WHEN 0 THEN 'pending' WHEN 1 THEN 'resolved' WHEN 2 THEN 'ignored' ELSE 'pending' END AS status,
                rg.reported_at AS report_at,
                gal.gallery_url AS report_img,   -- 🎖️ 只有成品帶圖
                rg.gallery_id AS target_id
            FROM reported_galleries rg
            LEFT JOIN recipe_gallery gal ON rg.gallery_id = gal.gallery_id
            
            UNION ALL
            
            -- 3. 食譜 (不帶圖)
            SELECT 
                reported_recipe_id AS report_id, 
                'recipe' AS report_type, 
                CASE report_type 
                    WHEN 1 THEN '垃圾訊息' WHEN 2 THEN '內容侵權' 
                    WHEN 3 THEN '仇恨言論' WHEN 4 THEN '不實資訊' 
                    ELSE '其他' END AS type_text,
                report_reason AS reason, 
                reporter_id AS user_id,
                CASE status WHEN 0 THEN 'pending' WHEN 1 THEN 'resolved' WHEN 2 THEN 'ignored' ELSE 'pending' END AS status,
                reported_at AS report_at,
                NULL AS report_img,             -- 食譜固定無圖
                reported_recipe_id AS target_id
            FROM reported_recipes
            
            ORDER BY report_at DESC";

        $stmt = $pdo->query($sql);
        
        // 偵錯機制：檢查查詢是否成功執行
        if (!$stmt) {
            $errorInfo = $pdo->errorInfo();
            throw new PDOException("Query failed: " . $errorInfo[2]);
        }

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 如果完全沒資料，回傳一個友善的 empty array 而不是報錯
        echo json_encode([
            'success' => true, 
            'count' => count($data),
            'data' => $data
        ]);

    } catch (PDOException $e) {
        // 這裡會抓到包含 SQL 語法錯誤在內的詳細資訊
        echo json_encode([
            'success' => false, 
            'message' => 'SQL execution error.',
            'error_detail' => $e->getMessage() 
        ]);
    }
}

// --- POST: 更新檢舉處理狀態 ---
else if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 偵錯機制：檢查 JSON 解析
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
        exit;
    }

    $report_id   = $input['report_id']   ?? null;
    $report_type = $input['report_type'] ?? null; 
    $new_status  = $input['status']      ?? null; 

    if (!$report_id || !$report_type || !$new_status) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters.', 'received' => $input]);
        exit;
    }

    $status_int = ($new_status === 'resolved') ? 1 : 2;

    try {
        $table_map = [
            'comment' => ['table' => 'reported_comments',  'id' => 'reported_comment_id'],
            'gallery' => ['table' => 'reported_galleries', 'id' => 'reported_gallery_id'],
            'recipe'  => ['table' => 'reported_recipes',   'id' => 'reported_recipe_id']
        ];

        if (!isset($table_map[$report_type])) {
            throw new Exception("Unsupported report type: " . $report_type);
        }

        $target = $table_map[$report_type];
        
        // 更新時建議檢查 update_at 欄位是否存在，若報錯可移除 update_at = NOW()
        $sql = "UPDATE {$target['table']} SET status = :status, update_at = NOW() WHERE {$target['id']} = :id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':status' => $status_int, ':id' => $report_id]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => true, 'message' => 'No rows updated. (Maybe ID does not exist or status is same)']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>