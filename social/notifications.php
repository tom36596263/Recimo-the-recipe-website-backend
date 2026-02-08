<?php
/**
 * 通知 API 端點
 * 路徑: /social/notifications.php
 * 
 * 功能：
 * - GET: 獲取通知列表 / 獲取未讀數量
 * - POST: 創建新通知 / 標記已讀 / 全部標記已讀
 * - PATCH: 標記已讀 / 全部標記已讀（已棄用，建議使用 POST）
 * - DELETE: 刪除通知
 */

require_once '../config/cors.php';
require_once '../config/db_config.php';
header('Content-Type: application/json; charset=utf-8');

// 開啟錯誤顯示（開發環境）
ini_set('display_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];

try {
    // 檢查 PDO 連接是否存在
    if (!isset($pdo)) {
        throw new Exception('資料庫連接失敗');
    }
    
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PATCH':
            handlePatch($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '不支援的請求方法']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '資料庫錯誤：' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '伺服器錯誤：' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

/**
 * GET 請求處理：獲取通知列表或未讀數量
 */
function handleGet($pdo) {
    $receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;
    
    if (!$receiver_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少 receiver_id']);
        return;
    }
    
    // 只獲取未讀數量
    if (isset($_GET['count_only']) && $_GET['count_only'] == 1) {
        $is_read = isset($_GET['is_read']) ? intval($_GET['is_read']) : 0;
        
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE receiver_id = ? AND is_read = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$receiver_id, $is_read]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'count' => intval($row['count'])
        ]);
        return;
    }
    
    // 獲取通知列表
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $is_read = isset($_GET['is_read']) ? intval($_GET['is_read']) : null;
    
    $sql = "SELECT 
                n.*,
                u.user_name as sender_name,
                u.user_url as sender_avatar
            FROM notifications n
            LEFT JOIN users u ON n.sender_id = u.user_id
            WHERE n.receiver_id = ?";
    
    $params = [$receiver_id];
    
    if ($is_read !== null) {
        $sql .= " AND n.is_read = ?";
        $params[] = $is_read;
    }
    
    // LIMIT 不能用參數綁定，直接拼接（已確保是整數，安全）
    $sql .= " ORDER BY n.created_at DESC LIMIT " . $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
}

/**
 * POST 請求處理：創建新通知 / 標記已讀
 */
function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 判斷操作類型
    $action = isset($input['action']) ? trim($input['action']) : 'create';
    
    // 標記全部為已讀
    if ($action === 'mark_all_read') {
        $receiver_id = isset($input['receiver_id']) ? intval($input['receiver_id']) : 0;
        
        if (!$receiver_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少 receiver_id']);
            return;
        }
        
        $sql = "UPDATE notifications SET is_read = 1 WHERE receiver_id = ? AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$receiver_id])) {
            echo json_encode([
                'success' => true,
                'message' => '已全部標記為已讀',
                'affected_rows' => $stmt->rowCount()
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '標記失敗']);
        }
        return;
    }
    
    // 標記單個為已讀
    if ($action === 'mark_read') {
        $notification_id = isset($input['notification_id']) ? intval($input['notification_id']) : 0;
        $is_read = isset($input['is_read']) ? intval($input['is_read']) : 1;
        
        if (!$notification_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少 notification_id']);
            return;
        }
        
        $sql = "UPDATE notifications SET is_read = ? WHERE notification_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$is_read, $notification_id])) {
            echo json_encode([
                'success' => true,
                'message' => '已標記為已讀'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '標記失敗']);
        }
        return;
    }
    
    // 創建新通知（預設操作）
    $receiver_id = isset($input['receiver_id']) ? intval($input['receiver_id']) : 0;
    $type = isset($input['notification_type']) ? trim($input['notification_type']) : '';
    $title = isset($input['notification_title']) ? trim($input['notification_title']) : '';
    $content = isset($input['notification_content']) ? trim($input['notification_content']) : '';
    $photo_url = isset($input['notification_photo_url']) ? trim($input['notification_photo_url']) : '';
    $link_url = isset($input['link_url']) ? trim($input['link_url']) : '';
    $sender_id = isset($input['sender_id']) ? intval($input['sender_id']) : null;
    
    if (!$receiver_id || !$type || !$title || !$content) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必要參數']);
        return;
    }
    
    $sql = "INSERT INTO notifications 
            (receiver_id, sender_id, notification_type, notification_title, notification_content, notification_photo_url, link_url, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$receiver_id, $sender_id, $type, $title, $content, $photo_url, $link_url])) {
        echo json_encode([
            'success' => true,
            'message' => '通知已創建',
            'notification_id' => $pdo->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '創建通知失敗']);
    }
}

/**
 * PATCH 請求處理：標記已讀或全部標記已讀
 */
function handlePatch($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 全部標記為已讀
    if (isset($input['mark_all']) && $input['mark_all'] == 1) {
        $receiver_id = isset($input['receiver_id']) ? intval($input['receiver_id']) : 0;
        
        if (!$receiver_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少 receiver_id']);
            return;
        }
        
        $sql = "UPDATE notifications SET is_read = 1 WHERE receiver_id = ? AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$receiver_id])) {
            echo json_encode([
                'success' => true,
                'message' => '已全部標記為已讀',
                'affected_rows' => $stmt->rowCount()
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '標記失敗']);
        }
        return;
    }
    
    // 標記單個為已讀
    $notification_id = isset($input['notification_id']) ? intval($input['notification_id']) : 0;
    $is_read = isset($input['is_read']) ? intval($input['is_read']) : 1;
    
    if (!$notification_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少 notification_id']);
        return;
    }
    
    $sql = "UPDATE notifications SET is_read = ? WHERE notification_id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$is_read, $notification_id])) {
        echo json_encode([
            'success' => true,
            'message' => '已標記為已讀'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '標記失敗']);
    }
}

/**
 * DELETE 請求處理：刪除通知
 */
function handleDelete($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $notification_id = isset($input['notification_id']) ? intval($input['notification_id']) : 0;
    
    if (!$notification_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少 notification_id']);
        return;
    }
    
    $sql = "DELETE FROM notifications WHERE notification_id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$notification_id])) {
        echo json_encode([
            'success' => true,
            'message' => '通知已刪除'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '刪除失敗']);
    }
}
?>