<?php
// 更新個人資料（含頭像、封面圖、簡介等）
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 關閉錯誤顯示，避免 HTML 錯誤訊息混入 JSON 輸出
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 僅允許 POST 方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 取得基本參數
    $user_id = $_POST['user_id'] ?? '';
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => '缺少 user_id 參數'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 檢查使用者是否存在並取得舊圖片路徑
    $sql_check = "SELECT user_url, user_cover_image FROM users WHERE user_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$user_id]);
    $current_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$current_user) {
        http_response_code(404);
        echo json_encode(['error' => '使用者不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 保存舊圖片路徑（用於後續刪除）
    $old_avatar = $current_user['user_url'];
    $old_cover = $current_user['user_cover_image'] ?? null;

    // 初始化路徑變數（預設沿用舊的）
    $avatar_url = $old_avatar;
    $cover_url = $old_cover;

    // 處理頭像上傳
    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../img/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = 'user_' . $user_id . '_avatar_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
            $avatar_url = 'img/profile/' . $fileName;
        }
    }

    // 處理封面圖上傳
    if (!empty($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../img/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'user_' . $user_id . '_cover_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
            $cover_url = 'img/profile/' . $fileName;
        }
    }

    // 準備更新資料
    $user_name = $_POST['user_name'] ?? null;
    $user_bio = $_POST['user_bio'] ?? null;
    $user_phone = $_POST['user_phone'] ?? null;
    $user_address = $_POST['user_address'] ?? null;

    // 檢查資料庫欄位是否存在
    $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_bio'")->fetch();
    $has_bio_column = $columns_check !== false;
    
    $columns_check2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_cover_image'")->fetch();
    $has_cover_column = $columns_check2 !== false;

    // 組合 SQL（只更新有提供的欄位和存在的欄位）
    $updates = [];
    $params = [];

    if ($user_name !== null && $user_name !== '') {
        $updates[] = "user_name = ?";
        $params[] = $user_name;
    }
    if ($user_bio !== null && $user_bio !== '' && $has_bio_column) {
        $updates[] = "user_bio = ?";
        $params[] = $user_bio;
    }
    if ($user_phone !== null && $user_phone !== '') {
        $updates[] = "user_phone = ?";
        $params[] = $user_phone;
    }
    if ($user_address !== null && $user_address !== '') {
        $updates[] = "user_address = ?";
        $params[] = $user_address;
    }
    if ($avatar_url !== null && $avatar_url !== $current_user['user_url']) {
        $updates[] = "user_url = ?";
        $params[] = $avatar_url;
    }
    if ($cover_url !== null && $has_cover_column) {
        $updates[] = "user_cover_image = ?";
        $params[] = $cover_url;
    }

    if (empty($updates)) {
        echo json_encode([
            'success' => true, 
            'message' => '沒有資料需要更新'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 加入 WHERE 條件參數
    $params[] = $user_id;

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute($params);

    if ($success) {
        // 資料庫更新成功後，刪除舊圖片
        if ($avatar_url !== $old_avatar && $old_avatar && $old_avatar !== 'img/profile/1.png') {
            $old_file = '../' . $old_avatar;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
        if ($cover_url !== $old_cover && $old_cover && $old_cover !== 'img/profile/2.png') {
            $old_file = '../' . $old_cover;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // 回傳更新後的資料（只查詢存在的欄位）
        $select_fields = "user_id, user_name, user_phone, user_address, user_url";
        if ($has_bio_column) {
            $select_fields .= ", user_bio";
        }
        if ($has_cover_column) {
            $select_fields .= ", user_cover_image";
        }
        
        $sql_get = "SELECT " . $select_fields . " FROM users WHERE user_id = ?";
        $stmt_get = $pdo->prepare($sql_get);
        $stmt_get->execute([$user_id]);
        $updated_user = $stmt_get->fetch(PDO::FETCH_ASSOC);

        $response = [
            'success' => true,
            'message' => '更新成功',
            'data' => $updated_user
        ];
        
        if (!$has_bio_column || !$has_cover_column) {
            $response['note'] = '部分欄位不存在，請執行資料庫更新腳本';
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '更新失敗'], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
