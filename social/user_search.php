<?php
// 搜尋使用者 API（模糊搜尋名稱或 email）
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 關閉錯誤顯示，避免 HTML 錯誤訊息混入 JSON 輸出
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 僅允許 GET 方法
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 取得搜尋關鍵字
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $current_user_id = isset($_GET['current_user_id']) ? intval($_GET['current_user_id']) : 0;
    
    if (empty($query)) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 檢查 user_bio 欄位是否存在
    $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_bio'")->fetch();
    $has_bio_column = $columns_check !== false;
    
    // 檢查 is_active 欄位是否存在
    $columns_check2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'")->fetch();
    $has_active_column = $columns_check2 !== false;
    
    // 檢查食譜表的狀態欄位
    $recipe_columns = $pdo->query("SHOW COLUMNS FROM recipes LIKE 'recipe_status'")->fetch();
    $has_recipe_status = $recipe_columns !== false;
    $recipe_status_field = $has_recipe_status ? 'recipe_status' : 'status';
    
    // 動態組合 SELECT 欄位
    $select_bio = $has_bio_column ? ", u.user_bio" : "";
    
    // 動態組合 WHERE 條件
    $where_active = $has_active_column ? "AND u.is_active = 1" : "";
    
    // 搜尋使用者（模糊搜尋名稱或 email）
    $sql = "SELECT 
                u.user_id,
                u.user_name,
                u.user_url{$select_bio},
                COUNT(DISTINCT r.recipe_id) as recipe_count,
                COUNT(DISTINCT f.follower_id) as follower_count,
                EXISTS(
                    SELECT 1 FROM follows 
                    WHERE follower_id = ? 
                    AND followed_id = u.user_id
                ) as is_following
            FROM users u
            LEFT JOIN recipes r ON u.user_id = r.author_id AND r.{$recipe_status_field} = 1
            LEFT JOIN follows f ON u.user_id = f.followed_id
            WHERE (u.user_name LIKE ? OR u.user_email LIKE ?)
            {$where_active}
            GROUP BY u.user_id
            ORDER BY 
                CASE WHEN u.user_id = ? THEN 0 ELSE 1 END,
                follower_count DESC,
                recipe_count DESC
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $searchParam = '%' . $query . '%';
    $stmt->execute([$current_user_id, $searchParam, $searchParam, $current_user_id]);
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 格式化資料
    $formatted_users = array_map(function($user) use ($has_bio_column) {
        $result = [
            'user_id' => (int)$user['user_id'],
            'user_name' => $user['user_name'],
            'user_url' => $user['user_url'],
            'recipe_count' => (int)$user['recipe_count'],
            'follower_count' => (int)$user['follower_count'],
            'is_following' => (bool)$user['is_following']
        ];
        
        if ($has_bio_column && isset($user['user_bio'])) {
            $result['user_bio'] = $user['user_bio'];
        }
        
        return $result;
    }, $users);
    
    echo json_encode($formatted_users, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
