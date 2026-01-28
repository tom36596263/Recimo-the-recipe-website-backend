<?php
// get_ingredients.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允許所有網域請求（解決跨域 CORS 問題）

require_once '../config/db_config.php';

// connect_tibamefe_carlos_test1.php

try {
    // 準備 SQL 查詢
    $sql = "SELECT id, url, recipe_name, recipe_image_url FROM remove_bg_ingredients";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // 取得所有資料
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 回傳成功結果
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);

} catch (PDOException $e) {
    // 回傳錯誤訊息
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>