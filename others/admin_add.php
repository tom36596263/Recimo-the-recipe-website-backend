<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 僅允許 POST 方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode('Method Not Allowed', JSON_UNESCAPED_UNICODE);
    exit;
}

// 取得前端傳來的資料
$input = json_decode(file_get_contents('php://input'), true);
$admin_account = $input['admin_account'] ?? '';
$admin_password = $input['admin_password'] ?? '';
$admin_name = $input['admin_name'] ?? '';

if (!$admin_account || !$admin_password || !$admin_name) {
    http_response_code(400);
    echo json_encode('欄位不得為空', JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = 'INSERT INTO admins (admin_account, admin_password, admin_name, admin_level) VALUES (?, ?, ?, 1)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_account, $admin_password, $admin_name]);
    echo json_encode('新增成功', JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // 1062 是 MySQL duplicate entry 錯誤碼
    if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
        http_response_code(409);
        echo json_encode('帳號已存在，請更換帳號', JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode('資料庫錯誤: ' . $e->getMessage(), JSON_UNESCAPED_UNICODE);
    }
    exit;
}
