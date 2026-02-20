<?php
// 1. 引入 CORS 和 資料庫設定
require_once '../config/cors.php';
require_once '../config/db_config.php';

// 2. 禁止快取 (確保上下架狀態即時更新)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

try {
    $sql = "SELECT * FROM meal_plan_templates 
            WHERE is_active = 1 
            ORDER BY template_id ASC";

    $stmt = $pdo->query($sql);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 回傳 JSON
    echo json_encode($templates);
} catch (PDOException $e) {
    // 錯誤處理
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
