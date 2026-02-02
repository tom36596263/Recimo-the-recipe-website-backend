<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

// 1. CORS 處理
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true) ?? [];

    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : null;
    $order_id = isset($data['order_id']) ? (int)$data['order_id'] : null;

    if (!$user_id || !$order_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "缺少必要資訊"]);
        exit;
    }

    // --- 修正點：先檢查，再開 Transaction，或者確保只開一次 ---
    $pdo->beginTransaction();

    $sql_check = "SELECT order_id FROM orders 
                  WHERE order_id = :oid AND user_id = :uid 
                  AND order_status IN (0, 1) FOR UPDATE"; // 加上 FOR UPDATE 鎖定該列
    
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':oid' => $order_id, ':uid' => $user_id]);
    $order = $stmt_check->fetch();

    if (!$order) {
        // 找不到資料，立刻回滾並結束
        $pdo->rollBack(); 
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "訂單狀態已改變，無法取消"]);
        exit;
    }

    // 執行更新
    $sql_update = "UPDATE orders SET order_status = -1 WHERE order_id = :oid";
    $stmt_up = $pdo->prepare($sql_update);
    $stmt_up->execute([':oid' => $order_id]);

    // 提交事務
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "訂單已成功取消"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // 只有在事務還活著的時候才回滾
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "系統錯誤: " . $e->getMessage()]);
}