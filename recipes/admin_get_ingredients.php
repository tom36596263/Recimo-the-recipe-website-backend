<?php
require_once '../config/cors.php';      
require_once '../config/db_config.php'; 

header('Content-Type: application/json; charset=utf-8');

try {
    //接收搜尋關鍵字 (透過網址參數 ?keyword=...)
    $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

    if ($keyword) {
        // --- 搜尋模式 ---
        // 搜尋名稱有包含關鍵字，或是 ID 等於關鍵字的
        $sql = "SELECT * FROM ingredients 
                WHERE ingredient_name LIKE :keyword 
                OR ingredient_id = :exact_id
                ORDER BY ingredient_id ASC"; 
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'keyword' => "%$keyword%", 
            'exact_id' => $keyword
        ]);
    } else {
        // --- 一般模式 (抓全部) ---
        $sql = "SELECT * FROM ingredients ORDER BY ingredient_id ASC";
        $stmt = $pdo->query($sql);
    }

    $ingredients = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'count' => count($ingredients),
        'data' => $ingredients
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '查詢失敗: ' . $e->getMessage()]);
}
