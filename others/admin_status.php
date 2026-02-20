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
$admin_level = $input['admin_level'] ?? null;
$admin_account = $input['admin_account'] ?? null;
$admin_password = $input['admin_password'] ?? null;
$admin_name = $input['admin_name'] ?? null;

if (!$admin_id) {
    http_response_code(400);
    echo json_encode('管理員 ID 不得為空', JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 動態建立 SQL 語句
    $updateFields = [];
    $params = [];
    
    if (isset($admin_level)) {
        $updateFields[] = 'admin_level = ?';
        $params[] = $admin_level;
    }
    
    if ($admin_account !== null) {
        // 檢查帳號是否已被其他管理員使用
        $checkSql = 'SELECT admin_id FROM admins WHERE admin_account = ? AND admin_id != ?';
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$admin_account, $admin_id]);
        if ($checkStmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode('帳號已存在，請更換帳號', JSON_UNESCAPED_UNICODE);
            exit;
        }
        $updateFields[] = 'admin_account = ?';
        $params[] = $admin_account;
    }
    
    if ($admin_password !== null) {
        $updateFields[] = 'admin_password = ?';
        $params[] = $admin_password;
    }
    
    if ($admin_name !== null) {
        $updateFields[] = 'admin_name = ?';
        $params[] = $admin_name;
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode('沒有可更新的欄位', JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $params[] = $admin_id;
    $sql = 'UPDATE admins SET ' . implode(', ', $updateFields) . ' WHERE admin_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode('更新成功', JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode('查無此管理員或資料無變更', JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode('資料庫錯誤: ' . $e->getMessage(), JSON_UNESCAPED_UNICODE);
    exit;
}
