<?php

/**
 * RECIMO API 範例檔案
 * 作用：示範如何串接資料庫並回傳 JSON 資料
 */

// 1. 引入 CORS 處理 (務必放在最上方)
require_once '../config/cors.php';

// 2. 引入資料庫連線
require_once '../config/db_config.php';

try {
    // 3. 準備 SQL (這裡以 recipes 資料表為例)
    $sql = "SELECT recipe_id, recipe_name, recipe_description, recipe_image_url 
            FROM recipes 
            ORDER BY recipe_id DESC 
            LIMIT 10";

    $stmt = $pdo->query($sql);

    // 4. 取得資料並轉為關聯陣列
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. 設定 HTTP 狀態碼並回傳 JSON
    http_response_code(200);
    echo json_encode($data);
} catch (PDOException $e) {
    // 錯誤處理
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "資料庫查詢失敗: " . $e->getMessage()
    ]);
}
