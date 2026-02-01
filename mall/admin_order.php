<?php
// ---------------------------------------------------------
// 第一步：開啟 Session (檢查管理員身分)
// ---------------------------------------------------------
session_start();

// ---------------------------------------------------------
// 第二步：引入設定
// ---------------------------------------------------------
require_once '../config/cors.php';
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：設定回傳格式與 CORS
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

// 處理預檢請求 (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    // ---------------------------------------------------------
    // 第四步：權限檢查 (確保是管理員)
    // ---------------------------------------------------------
    // 請確認你登入後台時設定的 Session Key 名稱
    if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
        http_response_code(403);
        echo json_encode(["error" => "權限不足，僅限管理員操作"]);
        exit;
    }

    // ---------------------------------------------------------
    // 第五步：獲取前端傳來的 JSON 資料
    // ---------------------------------------------------------
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    $order_id = $data['order_id'] ?? null;
    
    // 我們從前端接收兩個可能要修改的狀態
    $new_order_status = $data['order_status'] ?? null;     // 修改出貨/訂單進度
    $new_payment_status = $data['payment_status'] ?? null; // 確認訂單/付款狀態

    if (!$order_id) {
        http_response_code(400);
        echo json_encode(["error" => "未提供訂單 ID"]);
        exit;
    }

    // ---------------------------------------------------------
    // 第六步：動態構建 SQL 語句
    // ---------------------------------------------------------
    $fields = [];
    $params = [':oid' => $order_id];

    // 如果前端有傳 order_status，就加入修改清單
    if ($new_order_status !== null) {
        $fields[] = "order_status = :ostatus";
        $params[':ostatus'] = $new_order_status;
    }

    // 如果前端有傳 payment_status，就加入修改清單 (確認訂單)
    if ($new_payment_status !== null) {
        $fields[] = "payment_status = :pstatus";
        $params[':pstatus'] = $new_payment_status;
    }

    if (empty($fields)) {
        echo json_encode(["message" => "未提交任何更改內容"]);
        exit;
    }

    // 組裝 SQL：UPDATE orders SET col1 = :val1, col2 = :val2 WHERE order_id = :oid
    $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE order_id = :oid";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute($params);

    // ---------------------------------------------------------
    // 第七步：輸出結果
    // ---------------------------------------------------------
    if ($success) {
        echo json_encode([
            "success" => true,
            "message" => "訂單 #$order_id 狀態更新成功",
            "updated_fields" => array_keys($fields)
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["error" => "更新失敗，請稍後再試"]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫錯誤: " . $e->getMessage()]);
}