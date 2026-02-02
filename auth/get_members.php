<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// ---------------------------------------------------------
// 第四步：撰寫 SQL 語句(排除密碼敏感資訊)
// ---------------------------------------------------------
try {
    $sql = "SELECT 
                user_id, 
                user_name, 
                user_email, 
                user_phone, 
                user_address, 
                user_startdate, 
                user_url, 
                is_verified, 
                is_active 
            FROM users 
            ORDER BY user_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 處理資料格式：將資料庫的 1/0 轉為前端 el-switch 喜歡的布林值
    foreach ($members as &$member) {
        $member['is_active'] = (bool)$member['is_active'];
        $member['is_verified'] = (bool)$member['is_verified'];
    }

    echo json_encode($members);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "資料庫讀取失敗"]);
}