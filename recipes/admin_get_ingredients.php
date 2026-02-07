<?php
require_once '../config/cors.php';      
require_once '../config/db_config.php'; 

header('Content-Type: application/json; charset=utf-8');

//判斷環境
if (!isset($isLocal)) {
    $isLocal = (str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || str_contains($_SERVER["HTTP_HOST"], "localhost"));
}

//設定基礎網址 (注意：結尾【不要】加 img/，因為你資料庫已經有了)
$urlPrefix = $isLocal 
    ? 'http://' . $_SERVER['HTTP_HOST'] . '/recimo_api/' // 本地端 API 根目錄
    : 'https://tibamef2e.com/cjd102/g2/';         // 線上版 專案根目錄

try {
    $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

    if ($keyword) {
        // 搜尋模式
        $sql = "SELECT * FROM ingredients 
                WHERE ingredient_name LIKE :keyword 
                OR ingredient_id = :exact_id
                ORDER BY ingredient_id ASC"; 
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['keyword' => "%$keyword%", 'exact_id' => $keyword]);
    } else {
        // 一般模式
        $sql = "SELECT * FROM ingredients ORDER BY ingredient_id ASC";
        $stmt = $pdo->query($sql);
    }

    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    //針對資料庫圖片路徑做處理
    foreach ($ingredients as &$row) {
        // 確保欄位名稱正確 (依據你的截圖)
        // 為了保險，同時檢查小寫與大寫鍵值
        $dbPath = $row['ingredient_image_url'] ?? $row['INGREDIENT_IMAGE_URL'] ?? ''; 

        if ($dbPath) {
            // 聰明判斷：如果資料庫裡沒有 img/ 開頭，才幫它補
            // 已有 'img/'，所以會跑 else 那段，直接用原值
            if (!str_starts_with($dbPath, 'img/')) {
                $finalPath = 'img/' . $dbPath; 
            } else {
                $finalPath = $dbPath;
            }

            // 組合完整網址
            // 變成: https://.../recimo/ + img/ingredients/...
            $row['full_image_url'] = $urlPrefix . $finalPath;
        } else {
            $row['full_image_url'] = null; 
        }
    }
    unset($row); 
    // ---------------------------------------------------

    echo json_encode([
        'status' => 'success',
        'count' => count($ingredients),
        'data' => $ingredients
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '查詢失敗: ' . $e->getMessage()]);
}
