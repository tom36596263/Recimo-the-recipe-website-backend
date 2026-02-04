<?php
// 載入共用設定
require_once '../config/cors.php';
require_once '../config/db_config.php';

// 設定回傳格式為 JSON
header('Content-Type: application/json');

// 1. 取得前端傳入的 plan_id
$plan_id = $_GET['plan_id'] ?? null;

// 測試用：目前先寫死 user_id 為 2，後續串接登入功能後再改為動態
$user_id = 2;

if (!$plan_id) {
    http_response_code(400);
    echo json_encode(["error" => "缺少計畫 ID (plan_id)"]);
    exit;
}

try {
    // 2. 撰寫 SQL 語句
    // 透過 LEFT JOIN 關聯封面模板表，一次撈出模板圖片路徑
    $sql = "SELECT 
                p.plan_id, 
                p.user_id, 
                p.title, 
                p.cover_type, 
                p.custom_cover_url, 
                p.cover_template_id, 
                p.start_date, 
                p.end_date, 
                p.created_at,
                t.template_url,
                t.template_name
            FROM meal_plans p
            LEFT JOIN meal_plan_cover_template t ON p.cover_template_id = t.cover_template_id
            WHERE p.plan_id = ? AND p.user_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_id, $user_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. 檢查是否有資料
    if ($plan) {
        // 成功回傳資料
        echo json_encode([
            "success" => true,
            "data" => $plan
        ]);
    } else {
        // 找不到該計畫或該計畫不屬於此使用者
        http_response_code(404);
        echo json_encode(["error" => "找不到對應的計畫資訊"]);
    }
} catch (PDOException $e) {
    // 資料庫錯誤處理
    http_response_code(500);
    echo json_encode(["error" => "資料庫讀取失敗: " . $e->getMessage()]);
}
