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
        
        // 💡 修正 1：統一改用 $_POST 拿資料，因為 Vue 傳的是 FormData
        $productId = $_POST['product_id'] ?? null;
        
        if (!$productId) {
            echo json_encode(['status' => 'error', 'message' => '缺少商品ID', 'debug_data' => $_POST]);
            exit;
        }

      $finalImages = [];
$maxExistingId = 0;

// 1. 先處理舊圖片，並找出目前最大的 ID
if (isset($_POST['existing_images'])) {
    foreach ($_POST['existing_images'] as $url) {
        // 這裡我們需要從原本的資料庫取出 ID，但因為 FormData 只傳了 URL
        // 建議前端 saveProductData 時把 id 也傳過來，或者這裡我們先假設一個遞增
        $maxExistingId++; 
        $finalImages[] = [
            'id' => $maxExistingId,
            'is_cover' => ($maxExistingId === 1),
            'image_url' => "img/mall/" . basename($url)
        ];
    }
}

// 2. 處理新上傳圖片
if (isset($_FILES['product_images'])) {
    $uploadDir = __DIR__ . '/../img/mall/'; 
    $imgCounter = $maxExistingId + 1; // 從舊有 ID 之後開始算

    foreach ($_FILES['product_images']['tmp_name'] as $key => $tmpName) {
        if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
            $fileExt = pathinfo($_FILES['product_images']['name'][$key], PATHINFO_EXTENSION);
            $newFileName = uniqid('prod_') . '.' . $fileExt;
            
            if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                $finalImages[] = [
                    'id' => $imgCounter++, // 這裡會接續下去
                    'is_cover' => (count($finalImages) === 0), 
                    'image_url' => "img/mall/" . $newFileName
                ];
            }
        }
    }
}

        $jsonImages = json_encode($finalImages, JSON_UNESCAPED_UNICODE);

        // 💡 修正 2：所有的繫結變數都改用 $_POST
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
                PRODUCT_REMINDER = :reminder,
                PRODUCT_IMAGE = :images
                WHERE PRODUCT_ID = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'         => $_POST['product_name'] ?? '',
            ':cat'          => $_POST['product_category'] ?? '',
            ':price'        => $_POST['product_price'] ?? 0,
            ':descr'        => $_POST['product_description'] ?? '',
            ':release'      => $_POST['product_release'] ?? 1,
            ':kcal'         => $_POST['product_kcal'] ?? 0,
            ':carbs'        => $_POST['product_carbs'] ?? 0,
            ':fat'          => $_POST['product_fat'] ?? 0,
            ':fiber'        => $_POST['product_fiber'] ?? 0,
            ':protein'      => $_POST['product_protein'] ?? 0,
            ':st_fat'       => $_POST['product_saturated_fat'] ?? 0, 
            ':sugar'        => $_POST['product_sugar'] ?? 0,
            ':sodium'       => $_POST['product_sodium'] ?? 0,
            ':ingredients'  => $_POST['product_ingredients'] ?? '',
            ':cooking'      => $_POST['product_cooking_method'] ?? '', 
            ':storage'      => $_POST['product_storage_method'] ?? '',
            ':reminder'     => $_POST['product_reminder'] ?? '',
            ':images'       => $jsonImages, // 💡 別忘了更新圖片欄位
            ':id'           => $productId
        ]);

        echo json_encode(['status' => 'success']);
        exit;
    }// --- 功能 C：刪除商品資料 (POST 或 DELETE) ---

    // else if ($method === 'POST' && $action === 'delete') {

    //     $data = json_decode(file_get_contents("php://input"), true);

       

    //     if (!isset($data['product_id'])) {

    //         echo json_encode(['status' => 'error', 'message' => '缺少商品ID']);

    //         exit;

    //     }



    //     $sql = "DELETE FROM products WHERE PRODUCT_ID = :id";

    //     $stmt = $pdo->prepare($sql);

    //     $stmt->execute([':id' => $data['product_id']]);



    //     // 檢查是否有實際刪除到資料

    //     if ($stmt->rowCount() > 0) {

    //         echo json_encode(['status' => 'success', 'message' => '商品已刪除']);

    //     } else {

    //         echo json_encode(['status' => 'error', 'message' => '找不到該商品或已被刪除']);

    //     }

    //     exit;

    // }



} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

}