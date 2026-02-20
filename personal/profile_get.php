<?php
// 取得個人完整資料（含統計數據）
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

// 取得 user_id 參數
$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => '缺少 user_id 參數'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 取得當前登入使用者 ID（用於判斷追蹤關係）
$current_user_id = $_GET['current_user_id'] ?? null;

try {
    // 檢查資料庫欄位是否存在
    $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_bio'")->fetch();
    $has_bio_column = $columns_check !== false;
    
    $columns_check2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_cover_image'")->fetch();
    $has_cover_column = $columns_check2 !== false;

    // 查詢使用者基本資料（動態組合欄位）
    $select_fields = "user_id, user_name, user_email, user_phone, user_address, user_url, user_startdate, is_verified, is_active";
    if ($has_bio_column) {
        $select_fields .= ", user_bio";
    }
    if ($has_cover_column) {
        $select_fields .= ", user_cover_image";
    }
    
    $sql = "SELECT {$select_fields} FROM users WHERE user_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => '使用者不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 查詢食譜數量（只計算公開的）
    $sql_recipes = "SELECT COUNT(*) as count FROM recipes WHERE author_id = ? AND status = 0";
    $stmt_recipes = $pdo->prepare($sql_recipes);
    $stmt_recipes->execute([$user_id]);
    $recipes_count = $stmt_recipes->fetch(PDO::FETCH_ASSOC)['count'];

    // 查詢追蹤數量（我追蹤的人）
    $sql_following = "SELECT COUNT(*) as count FROM follows WHERE follower_id = ?";
    $stmt_following = $pdo->prepare($sql_following);
    $stmt_following->execute([$user_id]);
    $following_count = $stmt_following->fetch(PDO::FETCH_ASSOC)['count'];

    // 查詢粉絲數量（追蹤我的人）
    $sql_followers = "SELECT COUNT(*) as count FROM follows WHERE followed_id = ?";
    $stmt_followers = $pdo->prepare($sql_followers);
    $stmt_followers->execute([$user_id]);
    $followers_count = $stmt_followers->fetch(PDO::FETCH_ASSOC)['count'];

    // 查詢當前使用者是否已追蹤此使用者（當查看他人主頁時）
    $is_following = false;
    if ($current_user_id && $current_user_id != $user_id) {
        $sql_is_following = "SELECT COUNT(*) as count FROM follows WHERE follower_id = ? AND followed_id = ?";
        $stmt_is_following = $pdo->prepare($sql_is_following);
        $stmt_is_following->execute([$current_user_id, $user_id]);
        $is_following = $stmt_is_following->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    // 組合回傳資料
    $response = [
        'user_id' => $user['user_id'],
        'user_name' => $user['user_name'],
        'user_email' => $user['user_email'],
        'user_phone' => $user['user_phone'],
        'user_address' => $user['user_address'],
        'user_url' => $user['user_url'] ?: 'img/site/None_avatar.svg',
        'user_bio' => isset($user['user_bio']) ? $user['user_bio'] : '無內容',
        'user_cover_image' => isset($user['user_cover_image']) ? $user['user_cover_image'] : 'img/profile/2.png',
        'user_startdate' => $user['user_startdate'],
        'is_verified' => (bool)$user['is_verified'],
        'is_active' => (bool)$user['is_active'],
        'is_following' => $is_following,
        'stats' => [
            'recipes' => (int)$recipes_count,
            'following' => (int)$following_count,
            'followers' => (int)$followers_count
        ]
    ];
    
    // 如果欄位不存在，加入提示訊息
    if (!$has_bio_column || !$has_cover_column) {
        $response['note'] = '部分欄位不存在，請執行資料庫更新腳本：database/updates/add_follows_and_profile_fields.sql';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
