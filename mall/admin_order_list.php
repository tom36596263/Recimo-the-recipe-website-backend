<?php
session_start();
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

// 後台通常不一定需要 Credentials，但如果管理員也是用 Session 登入則需要
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

try {
    // ---------------------------------------------------------
    // 1. 權限檢查：檢查是否為管理員
    // ---------------------------------------------------------
    // 請確認你登入後台時存的 Session Key (例如 isAdmin 或 admin_id)
    if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
        http_response_code(403);
        echo json_encode(["error" => "權限不足，僅限管理員存取"]);
        exit;
    }

    $order_id = $_GET['id'] ?? null;
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(["error" => "未提供訂單 ID"]);
        exit;
    }

    // ---------------------------------------------------------
    // 2. 查詢「訂單主檔」 (注意：不需要加 user_id = :uid，管理員全都能看)
    // ---------------------------------------------------------
    $sql_order = "SELECT o.*, u.user_name, u.user_email 
                  FROM orders o
                  JOIN users u ON o.user_id = u.user_id
                  WHERE o.order_id = :oid";
    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([':oid' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["error" => "找不到該筆訂單"]);
        exit;
    }

    // ---------------------------------------------------------
    // 3. 查詢「訂單產品明細」
    // ---------------------------------------------------------
    $sql_items = "SELECT * FROM order_products WHERE order_id = :oid";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([':oid' => $order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $order['items'] = $items;
    echo json_encode($order, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "系統錯誤: " . $e->getMessage()]);
}