<?php
// 1. 引入門衛與連線
require_once '../config/cors.php';
require_once '../config/db_config.php';

// 2. 設定回傳格式
header('Content-Type: application/json; charset=utf-8');

/**
 * 3. 強制重新判斷環境 (預防 $isLocal 遺失)
 */
if (!isset($isLocal)) {
    $isLocal = (
        str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || 
        str_contains($_SERVER["HTTP_HOST"], "localhost")
    );
}

// 4. 定義圖片基礎路徑
// 這裡根據你提供的截圖結構：recimo_api -> img -> mall
$imgBaseUrl = $isLocal 
    ? 'http://' . $_SERVER['HTTP_HOST'] . '/recimo_api/img/mall/' 
    : 'https://tibamef2e.com/cjd102/g2/recimo/uploads/mall/';

try {
    // 5. 執行 SQL
    $sql = "SELECT * FROM products WHERE PRODUCT_RELEASE = 1 ORDER BY PRODUCT_ID ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    $response = [];

    foreach ($rows as $row) {
        // 6. 處理圖片 JSON
        $imgData = json_decode($row['PRODUCT_IMAGE'], true);
        $finalFileName = null;

        if (is_array($imgData) && !empty($imgData)) {
            $firstItem = $imgData[0];
            if (is_string($firstItem)) {
                $finalFileName = basename($firstItem); 
            } elseif (is_array($firstItem) && isset($firstItem['image_url'])) {
                $finalFileName = basename($firstItem['image_url']);
            }
        }

        // 7. 組裝資料結構
        $response[] = [
            'product_id'          => (int)$row['PRODUCT_ID'],
            'product_name'        => $row['PRODUCT_NAME'],
            'product_category'    => $row['PRODUCT_CATEGORY'],
            'product_price'       => (int)$row['PRODUCT_PRICE'],
            'product_description' => $row['PRODUCT_DESCRIPTION'],
            'image_url'           => $finalFileName ? $imgBaseUrl . $finalFileName : null,
            'tags'                => [
                'kcal'    => (float)$row['PRODUCT_KCAL'],
                'protein' => (float)$row['PRODUCT_PROTEIN'],
                'fat'     => (float)$row['PRODUCT_FAT']
            ]
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
}
?>