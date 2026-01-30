<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

// 強制讓 PDO 錯誤顯示，方便除錯
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($isLocal)) {
    $isLocal = (str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || str_contains($_SERVER["HTTP_HOST"], "localhost"));
}

$imgBaseUrl = $isLocal 
    ? 'http://' . $_SERVER['HTTP_HOST'] . '/recimo_api/img/mall/' 
    : 'https://tibamef2e.com/cjd102/g2/recimo/uploads/mall/';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // --- 功能 A：讀取商品資料 (GET) ---
    if ($method === 'GET' && $action === 'read') {
        $sql = "SELECT * FROM products ORDER BY PRODUCT_ID";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [];
        foreach ($rows as $row) {
            // 【關鍵修正】將所有欄位 Key 轉為大寫，避免 MAMP 環境大小寫不一致問題
            $row = array_change_key_case($row, CASE_UPPER);

            // 處理圖片路徑
            $imgData = json_decode($row['PRODUCT_IMAGE'] ?? '[]', true);
            $allImages = [];
            if (is_array($imgData)) {
                foreach ($imgData as $item) {
                    $fileName = isset($item['image_url']) ? basename($item['image_url']) : null;
                    if ($fileName) $allImages[] = $imgBaseUrl . $fileName;
                }
            }

            // 組合回傳資料（對應 Vue 的欄位結構）
            $response[] = [
                'id'                  => (int)($row['PRODUCT_ID'] ?? 0),
                'product_id'          => (int)($row['PRODUCT_ID'] ?? 0),
                'product_name'        => $row['PRODUCT_NAME'] ?? '',
                'product_category'    => $row['PRODUCT_CATEGORY'] ?? '',
                'product_price'       => (int)($row['PRODUCT_PRICE'] ?? 0),
                'product_description' => $row['PRODUCT_DESCRIPTION'] ?? '',
                'images'              => $allImages,
                'tags'                => [
                    'product_kcal'           => (float)($row['PRODUCT_KCAL'] ?? 0), 
                    'product_carbs'          => (float)($row['PRODUCT_CARBS'] ?? 0),
                    'product_fat'            => (float)($row['PRODUCT_FAT'] ?? 0),
                    'product_fiber'          => (float)($row['PRODUCT_FIBER'] ?? 0),
                    'product_protein'        => (float)($row['PRODUCT_PROTEIN'] ?? 0),
                    'product_saturated_fat'  => (float)($row['PRODUCT_SATURATED_FAT'] ?? 0),
                    'product_sugar'          => (float)($row['PRODUCT_SUGAR'] ?? 0),
                    'product_sodium'         => (float)($row['PRODUCT_SODIUM'] ?? 0),
                    'product_cooking_method' => $row['PRODUCT_COOKING_METHOD'] ?? '',
                    'product_ingredients'    => $row['PRODUCT_INGREDIENTS'] ?? '',
                    'product_storage_method' => $row['PRODUCT_STORAGE_METHOD'] ?? '',
                    'product_reminder'       => $row['PRODUCT_REMINDER'] ?? '',
                    'product_release'        => (int)($row['PRODUCT_RELEASE'] ?? 1)
                ]
            ];
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 功能 B：更新商品資料 (POST) ---
    else if ($method === 'POST' && $action === 'update') {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['product_id'])) {
            echo json_encode(['status' => 'error', 'message' => '缺少商品ID']);
            exit;
        }

        // 更新語法，確保與資料庫大寫欄位名稱一致
        $sql = "UPDATE products SET 
                PRODUCT_NAME = :name, 
                PRODUCT_CATEGORY = :cat, 
                PRODUCT_PRICE = :price, 
                PRODUCT_DESCRIPTION = :descr,
                PRODUCT_RELEASE = :release,
                PRODUCT_KCAL = :kcal,
                PRODUCT_CARBS = :carbs,
                PRODUCT_FAT = :fat,
                PRODUCT_FIBER = :fiber,
                PRODUCT_PROTEIN = :protein,
                PRODUCT_SATURATED_FAT = :st_fat, 
                PRODUCT_SUGAR = :sugar,
                PRODUCT_SODIUM = :sodium,
                PRODUCT_INGREDIENTS = :ingredients,
                PRODUCT_COOKING_METHOD = :cooking,
                PRODUCT_STORAGE_METHOD = :storage,
                PRODUCT_REMINDER = :reminder
                WHERE PRODUCT_ID = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'         => $data['product_name'] ?? '',
            ':cat'          => $data['product_category'] ?? '',
            ':price'        => $data['product_price'] ?? 0,
            ':descr'        => $data['product_description'] ?? '',
            ':release'      => $data['product_release'] ?? 1,
            ':kcal'         => $data['product_kcal'] ?? 0,
            ':carbs'        => $data['product_carbs'] ?? 0,
            ':fat'          => $data['product_fat'] ?? 0,
            ':fiber'        => $data['product_fiber'] ?? 0,
            ':protein'      => $data['product_protein'] ?? 0,
            ':st_fat'       => $data['product_saturated_fat'] ?? 0, 
            ':sugar'        => $data['product_sugar'] ?? 0,
            ':sodium'       => $data['product_sodium'] ?? 0,
            ':ingredients'  => $data['product_ingredients'] ?? '',
            ':cooking'      => $data['product_cooking_method'] ?? '', 
            ':storage'      => $data['product_storage_method'] ?? '',
            ':reminder'     => $data['product_reminder'] ?? '',
            ':id'           => $data['product_id']
        ]);

        echo json_encode(['status' => 'success']);
        exit;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>