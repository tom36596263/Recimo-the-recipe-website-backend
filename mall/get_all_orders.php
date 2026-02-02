<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

try {
    // 獲取管理員 ID 進行初步權限驗證
    $admin_id = $_GET['admin_id'] ?? null;
    if (!$admin_id) {
        http_response_code(403);
        echo json_encode(["error" => "未授權訪問"]);
        exit;
    }

    // 查詢所有訂單，並關聯使用者名稱 (依建立日期降冪排序，最新的在上面)
    $sql = "SELECT o.*, u.user_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.user_id 
            ORDER BY o.created DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $orders
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "資料庫錯誤: " . $e->getMessage()]);
}