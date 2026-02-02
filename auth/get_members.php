<?php
// 1. 引入設定
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");

try {
    // 2. 撰寫 SQL (排除密碼敏感資訊)
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

    // 3. 處理資料格式：將資料庫的 1/0 轉為前端 el-switch 喜歡的布林值
    foreach ($members as &$member) {
        $member['is_active'] = (bool)$member['is_active'];
        $member['is_verified'] = (bool)$member['is_verified'];
    }

    echo json_encode($members);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "資料庫讀取失敗"]);
}