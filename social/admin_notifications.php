<?php
/**
 * 後台通知 API 端點
 */

require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// 處理 _method 參數
if ($method === 'POST' && isset($_GET['_method'])) {
    $method = strtoupper($_GET['_method']);
}

try {
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '不支援的請求方法']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '伺服器錯誤: ' . $e->getMessage()
    ]);
}

function handleGet($pdo) {
    if (isset($_GET['id'])) {
        $notification_id = intval($_GET['id']);
        $sql = "SELECT * FROM notifications WHERE notification_id = ? AND sender_id = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$notification_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => '通知不存在']);
        }
    } else {
        $sql = "SELECT * FROM notifications WHERE sender_id = 1 ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $notifications]);
    }
}

function handleImageUpload($fieldName) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('圖片上傳失敗');
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('圖片大小不能超過 5MB');
    }

    // 使用文件擴展名驗證，不依賴 fileinfo 擴展
    $fileName = strtolower($file['name']);
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('只支持 JPG、PNG、GIF 格式的圖片');
    }

    // 環境判斷（與 admin_save_ingredient.php 一致）
    $isLocal = (str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || str_contains($_SERVER["HTTP_HOST"], "localhost"));

    // 根據環境決定專案根目錄
    if ($isLocal) {
        // 本地端：recimo_api 就是根目錄
        $projectRoot = dirname(__DIR__);
    } else {
        // 線上版：需要往上兩層（因為檔案在 g2/api/social/）
        $projectRoot = dirname(dirname(__DIR__));
    }

    // 設定相對路徑（存資料庫用）
    $relativeFolder = "img/notifications/";

    // 組合實體路徑
    $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFolder);

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('無法建立資料夾: ' . $uploadDir);
        }
    }

    $newFileName = 'notification_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // 回傳相對路徑（存資料庫）
        return $relativeFolder . $newFileName;
    } else {
        throw new Exception('圖片保存失敗，目標路徑: ' . $uploadPath);
    }
}

function handlePost($pdo) {
    $title = isset($_POST['notification_title']) ? trim($_POST['notification_title']) : '';
    $type = isset($_POST['notification_type']) ? trim($_POST['notification_type']) : '';
    $content = isset($_POST['notification_content']) ? trim($_POST['notification_content']) : '';
    $link_url = isset($_POST['link_url']) ? trim($_POST['link_url']) : '';
    
    // 發布給全部會員時，receiver_id 設為 1（系統通知）
    $receiver_id = 1;
    $sender_id = 1;

    error_log("POST - title: $title, type: $type, content: $content");

    if (!$title || !$type || !$content) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必要參數']);
        return;
    }

    $photo_url = '';
    try {
        $uploaded = handleImageUpload('notification_photo');
        if ($uploaded) {
            $photo_url = $uploaded;
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        return;
    }

    $sql = "INSERT INTO notifications
            (receiver_id, sender_id, notification_type, notification_title, notification_content, notification_photo_url, link_url, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";

    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$receiver_id, $sender_id, $type, $title, $content, $photo_url, $link_url])) {
        echo json_encode([
            'success' => true,
            'message' => '通知已發布',
            'notification_id' => $pdo->lastInsertId(),
            'photo_url' => $photo_url
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '發布通知失敗']);
    }
}

function handlePut($pdo) {
    // 從 $_POST 讀取參數
    $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    $title = isset($_POST['notification_title']) ? trim($_POST['notification_title']) : '';
    $type = isset($_POST['notification_type']) ? trim($_POST['notification_type']) : '';
    $content = isset($_POST['notification_content']) ? trim($_POST['notification_content']) : '';
    $link_url = isset($_POST['link_url']) ? trim($_POST['link_url']) : '';
    $current_photo_url = isset($_POST['notification_photo_url']) ? trim($_POST['notification_photo_url']) : '';

    error_log("PUT - id: $notification_id");
    error_log("PUT - title: $title");
    error_log("PUT - type: $type");
    error_log("PUT - content: $content");
    error_log("PUT - current_photo_url: $current_photo_url");

    // 驗證必要參數
    if (!$notification_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少通知 ID']);
        return;
    }

    if (!$title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少標題']);
        return;
    }

    if (!$type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少類別']);
        return;
    }

    if (!$content) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少內容']);
        return;
    }

    $photo_url = $current_photo_url;

    // 處理新圖片上傳
    try {
        $uploaded = handleImageUpload('notification_photo');
        if ($uploaded) {
            $photo_url = $uploaded;
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        return;
    }

    // 更新通知
    $sql = "UPDATE notifications
            SET notification_title = ?,
                notification_type = ?,
                notification_content = ?,
                notification_photo_url = ?,
                link_url = ?
            WHERE notification_id = ? AND sender_id = 1";

    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$title, $type, $content, $photo_url, $link_url, $notification_id])) {
        echo json_encode([
            'success' => true,
            'message' => '通知已更新',
            'photo_url' => $photo_url
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '更新通知失敗']);
    }
}

function handleDelete($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_id = isset($input['notification_id']) ? intval($input['notification_id']) : 0;

    if (!$notification_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少通知 ID']);
        return;
    }

    $sql = "DELETE FROM notifications WHERE notification_id = ? AND sender_id = 1";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$notification_id])) {
        echo json_encode(['success' => true, 'message' => '通知已刪除']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '刪除通知失敗']);
    }
}
