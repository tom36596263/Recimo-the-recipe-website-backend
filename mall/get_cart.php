<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

// 假設您用 Session 存 user_id
session_start();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "請先登入"]);
    exit;
}

try {
    // 撈出該使用者的購物車，並關聯商品表拿圖片、價格、名稱
    $sql = "SELECT 
                c.quantity, 
                p.product_id, 
                p.product_name, 
                p.product_price, 
                p.product_image 
            FROM carts c
            JOIN products p ON c.product_id = p.product_id
            WHERE c.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    // 2. 直接抓取所有資料
    // PDO 的 fetchAll 如果沒資料，會直接回傳空陣列 []，非常方便
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 回傳結果
    echo json_encode([
        "status" => "success",
        "data" => $cartItems
    ]);
} catch (PDOException $e) {
    // 發生錯誤時回傳錯誤訊息
    echo json_encode(["status" => "error", "message" => "資料庫錯誤：" . $e->getMessage()]);
}
