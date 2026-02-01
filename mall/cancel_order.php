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
    // 2. 獲取前端資料
    $json = file_get_contents("php://input");
    $data = json_decode($json, true) ?? [];

    $user_id = $data['user_id'] ?? null;
    $order_id = $data['order_id'] ?? null;

    if (!$user_id || !$order_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "缺少必要資訊"]);
        exit;
    }

    $pdo->beginTransaction();

    // ---------------------------------------------------------
    // 3. 修改處：檢查狀態是否為 0 (新訂單) 或 1 (已確認)
    // ---------------------------------------------------------
    $sql_check = "SELECT order_id FROM orders 
                  WHERE order_id = :oid AND user_id = :uid 
                  AND order_status IN (0, 1)";
    
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':oid' => $order_id, ':uid' => $user_id]);
    
    $order = $stmt_check->fetch();

    if (!$order) {
        // 如果找不到，可能是訂單不存在，或是狀態已經變成 2(已出貨) 以上了
        http_response_code(403);
        echo json_encode([
            "success" => false, 
            "error" => "訂單目前狀態不可取消（可能已進入出貨程序）"
        ], JSON_UNESCAPED_UNICODE);
        $pdo->rollBack();
        exit;
    }

    // 4. 執行取消 (更新狀態為 -1)
    $sql_update = "UPDATE orders SET order_status = -1 WHERE order_id = :oid";
    $stmt_up = $pdo->prepare($sql_update);
    $stmt_up->execute([':oid' => $order_id]);

    // 5. 提交
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "訂單已成功取消"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "系統錯誤: " . $e->getMessage()]);
}