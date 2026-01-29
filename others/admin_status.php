<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 僅允許 PATCH 方法
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode('Method Not Allowed', JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$admin_id = $input['admin_id'] ?? '';
$admin_level = isset($input['admin_level']) ? $input['admin_level'] : null;

if (!$admin_id || !isset($admin_level)) {
    http_response_code(400);
    echo json_encode('欄位不得為空', JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = 'UPDATE admins SET admin_level = ? WHERE admin_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_level, $admin_id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode('狀態更新成功', JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode('查無此管理員', JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode('資料庫錯誤: ' . $e->getMessage(), JSON_UNESCAPED_UNICODE);
    exit;
}
