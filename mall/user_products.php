<?php
// // 1. 允許 Vite 的來源 (localhost:5175)
// header("Access-Control-Allow-Origin: http://localhost:5175");

// // 2. 允許 Cookie (如果你之後要處理購物車或登入，這行必備)
// header("Access-Control-Allow-Credentials: true");

// // 3. 允許的方法
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// // 4. 允許的 Header
// header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// // 5. 處理 OPTIONS 請求（瀏覽器的預檢）
// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     http_response_code(200);
//     exit;
// }
// ---------------------------------------------------------
// 1. 引入 CORS 權限與資料庫連線
// ---------------------------------------------------------
require_once '../config/cors.php';
require_once '../config/db_config.php';

// ---------------------------------------------------------
// 2. 設定回傳格式為 JSON
// ---------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------
// 3. 判斷環境 (Local 或 正式伺服器)
// ---------------------------------------------------------
if (!isset($isLocal)) {
    $isLocal = (
        str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || 
        str_contains($_SERVER["HTTP_HOST"], "localhost")
    );
}

// ---------------------------------------------------------
// 4. 定義圖片基礎路徑
// ---------------------------------------------------------
$imgBaseUrl = $isLocal 
    ? 'http://' . $_SERVER['HTTP_HOST'] . '/recimo_api/img/mall/' 
    : 'https://tibamef2e.com/cjd102/g2/recimo/uploads/mall/';

try {
    // ---------------------------------------------------------
    // 5. 執行 SQL 查詢
    // ---------------------------------------------------------
    $sql = "SELECT * FROM products WHERE PRODUCT_RELEASE = 1 ORDER BY PRODUCT_ID";
    $stmt = $pdo->query($sql);
    
    // 確保只回傳欄位名稱的陣列
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [];

    // ---------------------------------------------------------
    // 6. 迴圈處理每一筆資料
    // ---------------------------------------------------------
    foreach ($rows as $row) {
        
        // 【關鍵修正 1】統一將所有欄位 Key 轉為大寫，避免資料庫大小寫不一的問題
        $row = array_change_key_case($row, CASE_UPPER);

        // 【關鍵修正 2】處理 PRODUCT_IMAGE，若為 null 或空字串則給予空陣列字串
        $jsonImage = $row['PRODUCT_IMAGE'] ?? '[]';
        $imgData = json_decode($jsonImage, true);
        
        $allImages = [];
        $mainImage = null;

        if (is_array($imgData)) {
            foreach ($imgData as $index => $item) {
                // 取得檔名並組合完整路徑
                $fileName = isset($item['image_url']) ? basename($item['image_url']) : null;
                
                if ($fileName) {
                    $fullPath = $imgBaseUrl . $fileName;
                    $allImages[] = $fullPath;

                    // 邏輯：第一張或是標記為 is_cover 則為主圖
                    if ($index === 0 || (isset($item['is_cover']) && $item['is_cover'] === true)) {
                        $mainImage = $fullPath;
                    }
                }
            }
        }

        // 【關鍵修正 3】使用 ?? 運算子確保欄位不存在時不會噴 Warning，而是給預設值
        $response[] = [
            'id'                  => (int)($row['PRODUCT_ID'] ?? 0),
            'product_id'          => (int)($row['PRODUCT_ID'] ?? 0),
            'product_name'        => $row['PRODUCT_NAME'] ?? '未命名商品',
            'product_category'    => $row['PRODUCT_CATEGORY'] ?? '一般',
            'product_price'       => (int)($row['PRODUCT_PRICE'] ?? 0),
            'product_description' => $row['PRODUCT_DESCRIPTION'] ?? '',
            'image_url'           => $mainImage,
            'images'              => $allImages,
            'tags'                => [
                'product_kcal'          => round((float)($row['PRODUCT_KCAL'] ?? 0), 1),
                'product_carbs'         => round((float)($row['PRODUCT_CARBS'] ?? 0), 1),
                'product_fat'           => round((float)($row['PRODUCT_FAT'] ?? 0), 1), 
                'product_fiber'         => round((float)($row['PRODUCT_FIBER'] ?? 0), 1),
                'product_protein'       => round((float)($row['PRODUCT_PROTEIN'] ?? 0), 1),
                'product_saturated_fat' => round((float)($row['PRODUCT_SATURATED_FAT'] ?? 0), 1),
                'product_sugar'         => round((float)($row['PRODUCT_SUGAR'] ?? 0), 1),
                'product_sodium'        => round((float)($row['PRODUCT_SODIUM'] ?? 0), 1),

                'product_net_weight'     => (float)($row['PRODUCT_NET_WEIGHT'] ?? 0),
                'product_ingredients'    => $row['PRODUCT_INGREDIENTS'] ?? '',
                'product_cooking_method' => $row['PRODUCT_COOKING_METHOD'] ?? '',
                'product_storage_method' => $row['PRODUCT_STORAGE_METHOD'] ?? '',
                'product_reminder'       => $row['PRODUCT_REMINDER'] ?? '',
                'product_release'        => (int)($row['PRODUCT_RELEASE'] ?? 0) === 1,
                'product_is_hot'        => (int)($row['PRODUCT_IS_HOT'] ?? 0) === 1
            ]
        ];
    }

    // ---------------------------------------------------------
    // 7. 輸出最終結果
    // ---------------------------------------------------------
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    // ---------------------------------------------------------
    // 8. 錯誤處理
    // ---------------------------------------------------------
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '資料庫連線或查詢失敗',
        'dev_error' => $e->getMessage() // 正式上線前可以把這行拿掉
    ]);
}
