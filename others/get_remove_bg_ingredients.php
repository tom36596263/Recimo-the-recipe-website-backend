<?php
header('Access-Control-Allow-Origin: http://localhost:5173'); 
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

header('Content-Type: application/json');

require_once '../config/db_config.php';

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