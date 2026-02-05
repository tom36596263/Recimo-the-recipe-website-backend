<?php
// 追蹤/粉絲相關 API（查詢追蹤列表、粉絲列表、追蹤/取消追蹤）
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 關閉錯誤顯示，避免 HTML 錯誤訊息混入 JSON 輸出
ini_set('display_errors', 0);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    // 檢查 follows 表是否存在
    $table_check = $pdo->query("SHOW TABLES LIKE 'follows'")->fetch();
    if (!$table_check) {
        http_response_code(503);
        echo json_encode([
            'error' => 'follows 表不存在',
            'message' => '請先執行資料庫更新腳本：database/updates/add_follows_and_profile_fields.sql'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 檢查 user_bio 欄位是否存在
    $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_bio'")->fetch();
    $has_bio_column = $columns_check !== false;
    
    // ==================== GET：查詢追蹤列表或粉絲列表 ====================
    if ($method === 'GET') {
        $user_id = $_GET['user_id'] ?? '';
        $type = $_GET['type'] ?? 'following'; // following 或 followers

        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['error' => '缺少 user_id 參數'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($type === 'following') {
            // 查詢我追蹤的人
            $select_bio = $has_bio_column ? ", u.user_bio" : "";
            $group_bio = $has_bio_column ? ", u.user_bio" : "";
            
            $sql = "SELECT 
                        u.user_id,
                        u.user_name,
                        u.user_url{$select_bio},
                        COUNT(DISTINCT r.recipe_id) as recipes_count,
                        COUNT(DISTINCT f2.follower_id) as followers_count,
                        1 as is_following
                    FROM follows f
                    JOIN users u ON f.followed_id = u.user_id
                    LEFT JOIN recipes r ON u.user_id = r.author_id AND r.status = 0
                    LEFT JOIN follows f2 ON u.user_id = f2.followed_id
                    WHERE f.follower_id = ?
                    GROUP BY u.user_id, u.user_name, u.user_url{$group_bio}
                    ORDER BY f.followed_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            // 查詢追蹤我的人（粉絲）
            $select_bio = $has_bio_column ? ", u.user_bio" : "";
            $group_bio = $has_bio_column ? ", u.user_bio" : "";
            
            $sql = "SELECT 
                        u.user_id,
                        u.user_name,
                        u.user_url{$select_bio},
                        COUNT(DISTINCT r.recipe_id) as recipes_count,
                        COUNT(DISTINCT f2.follower_id) as followers_count,
                        CASE 
                            WHEN f_check.follow_id IS NOT NULL THEN 1
                            ELSE 0
                        END as is_following
                    FROM follows f
                    JOIN users u ON f.follower_id = u.user_id
                    LEFT JOIN recipes r ON u.user_id = r.author_id AND r.status = 0
                    LEFT JOIN follows f2 ON u.user_id = f2.followed_id
                    LEFT JOIN follows f_check ON f_check.follower_id = ? AND f_check.followed_id = u.user_id
                    WHERE f.followed_id = ?
                    GROUP BY u.user_id, u.user_name, u.user_url{$group_bio}, f_check.follow_id
                    ORDER BY f.followed_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 格式化資料
        $formatted_results = array_map(function($item) {
            return [
                'id' => (int)$item['user_id'],
                'name' => $item['user_name'],
                'avatar' => $item['user_url'] ?: 'img/profile/1.png',
                'bio' => isset($item['user_bio']) ? $item['user_bio'] : '無內容',
                'recipes' => (int)$item['recipes_count'],
                'followers' => (int)$item['followers_count'],
                'isFollowing' => (bool)$item['is_following']
            ];
        }, $results);

        echo json_encode($formatted_results, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==================== POST：追蹤或取消追蹤 ====================
    if ($method === 'POST') {
        $follower_id = $_POST['follower_id'] ?? ''; // 執行追蹤動作的人
        $followed_id = $_POST['followed_id'] ?? ''; // 被追蹤的人
        $action = $_POST['action'] ?? 'toggle'; // toggle, follow, unfollow

        if (!$follower_id || !$followed_id) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要參數'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($follower_id === $followed_id) {
            http_response_code(400);
            echo json_encode(['error' => '不能追蹤自己'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 檢查目前追蹤狀態
        $sql_check = "SELECT follow_id FROM follows WHERE follower_id = ? AND followed_id = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$follower_id, $followed_id]);
        $is_following = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($action === 'toggle' || ($action === 'unfollow' && $is_following)) {
            if ($is_following) {
                // 取消追蹤
                $sql_delete = "DELETE FROM follows WHERE follower_id = ? AND followed_id = ?";
                $stmt_delete = $pdo->prepare($sql_delete);
                $stmt_delete->execute([$follower_id, $followed_id]);

                echo json_encode([
                    'success' => true,
                    'message' => '已取消追蹤',
                    'is_following' => false
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($action === 'toggle' || ($action === 'follow' && !$is_following)) {
            if (!$is_following) {
                // 新增追蹤
                $sql_insert = "INSERT INTO follows (follower_id, followed_id, followed_at) VALUES (?, ?, NOW())";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([$follower_id, $followed_id]);

                echo json_encode([
                    'success' => true,
                    'message' => '追蹤成功',
                    'is_following' => true
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // 如果狀態沒有改變
        echo json_encode([
            'success' => true,
            'message' => $is_following ? '已經在追蹤中' : '尚未追蹤',
            'is_following' => (bool)$is_following
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ==================== DELETE：取消追蹤 ====================
    if ($method === 'DELETE') {
        // 支援 RESTful DELETE 方法
        parse_str(file_get_contents("php://input"), $_DELETE);
        
        $follower_id = $_DELETE['follower_id'] ?? '';
        $followed_id = $_DELETE['followed_id'] ?? '';

        if (!$follower_id || !$followed_id) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要參數'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql_delete = "DELETE FROM follows WHERE follower_id = ? AND followed_id = ?";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([$follower_id, $followed_id]);

        echo json_encode([
            'success' => true,
            'message' => '已取消追蹤',
            'is_following' => false
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 不支援的方法
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
