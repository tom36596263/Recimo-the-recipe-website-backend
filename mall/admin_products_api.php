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

// --- 功能 B：更新商品資料 (POST) ---
else if ($method === 'POST' && $action === 'update') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // 優先從 JSON 取值，若無則從 $_POST 取值
    $productId = $data['product_id'] ?? $_POST['product_id'] ?? null;
    $productRelease = $data['product_release'] ?? $_POST['product_release'] ?? null;
    $productName = $data['product_name'] ?? $_POST['product_name'] ?? null;

    if (!$productId) {
        echo json_encode(['status' => 'error', 'message' => '缺少商品ID']);
        exit;
    }

    // 💡 判斷是否為「快速上下架」模式
    $isFullUpdate = isset($productName);

    if (!$isFullUpdate) {
        // --- 模式 1：快速上下架 (僅更新狀態) ---
        $sql = "UPDATE products SET PRODUCT_RELEASE = :release WHERE PRODUCT_ID = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':release' => $productRelease ?? 1,
            ':id'      => $productId
        ]);
        echo json_encode(['status' => 'success', 'message' => '上下架成功']);
        exit; 
    } else {
        // --- 模式 2：完整更新商品資料 (包含圖片刪除與所有欄位) ---
        $uploadDir = __DIR__ . '/../img/mall/';

        // 1. 先從資料庫查出「原本的圖片」做為刪除對照
        $stmtSelect = $pdo->prepare("SELECT PRODUCT_IMAGE FROM products WHERE PRODUCT_ID = :id");
        $stmtSelect->execute([':id' => $productId]);
        $oldRow = $stmtSelect->fetch(PDO::FETCH_ASSOC);
        $oldImagesArr = json_decode($oldRow['PRODUCT_IMAGE'] ?? '[]', true);
        $oldFiles = array_map(fn($img) => basename($img['image_url']), $oldImagesArr);

        $finalImages = [];
        $keptFiles = []; 

        // 2. 處理前端傳過來「要保留」的舊圖片
        if (isset($_POST['existing_images']) && is_array($_POST['existing_images'])) {
            foreach ($_POST['existing_images'] as $index => $url) {
                $fileName = basename($url);
                $keptFiles[] = $fileName;
                $finalImages[] = [
                    'id' => $index + 1,
                    'is_cover' => ($index === 0),
                    'image_url' => "img/mall/" . $fileName
                ];
            }
        }

        // 3. 【真正刪除實體檔案】找出資料庫有，但前端沒傳過來的檔案
        $filesToDelete = array_diff($oldFiles, $keptFiles);
        foreach ($filesToDelete as $file) {
            $target = $uploadDir . $file;
            if (file_exists($target)) {
                unlink($target); 
            }
        }

        // 4. 處理新上傳圖片 (使用 uniqid 解決你換回舊圖存不進去的問題)
        if (isset($_FILES['product_images'])) {
            foreach ($_FILES['product_images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileExt = pathinfo($_FILES['product_images']['name'][$key], PATHINFO_EXTENSION);
                    $newFileName = uniqid('prod_') . '.' . $fileExt;
                    if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                        $finalImages[] = [
                            'id' => count($finalImages) + 1,
                            'is_cover' => (count($finalImages) === 0), 
                            'image_url' => "img/mall/" . $newFileName
                        ];
                    }
                }
            }
        }

        $jsonImages = json_encode($finalImages, JSON_UNESCAPED_UNICODE);

        // 5. 【恢復所有原本欄位】執行完整資料庫更新
        $sql = "UPDATE products SET 
                PRODUCT_NAME = :name, PRODUCT_CATEGORY = :cat, PRODUCT_PRICE = :price, 
                PRODUCT_DESCRIPTION = :descr, PRODUCT_RELEASE = :release, PRODUCT_KCAL = :kcal,
                PRODUCT_CARBS = :carbs, PRODUCT_FAT = :fat, PRODUCT_FIBER = :fiber,
                PRODUCT_PROTEIN = :protein, PRODUCT_SATURATED_FAT = :st_fat, PRODUCT_SUGAR = :sugar,
                PRODUCT_SODIUM = :sodium, PRODUCT_INGREDIENTS = :ingredients,
                PRODUCT_COOKING_METHOD = :cooking, PRODUCT_STORAGE_METHOD = :storage,
                PRODUCT_REMINDER = :reminder, PRODUCT_IMAGE = :images
                WHERE PRODUCT_ID = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'        => $_POST['product_name'] ?? '',
            ':cat'         => $_POST['product_category'] ?? '',
            ':price'       => $_POST['product_price'] ?? 0,
            ':descr'       => $_POST['product_description'] ?? '',
            ':release'     => $_POST['product_release'] ?? 1,
            ':kcal'        => $_POST['product_kcal'] ?? 0,
            ':carbs'       => $_POST['product_carbs'] ?? 0,
            ':fat'         => $_POST['product_fat'] ?? 0,
            ':fiber'       => $_POST['product_fiber'] ?? 0,
            ':protein'     => $_POST['product_protein'] ?? 0,
            ':st_fat'      => $_POST['product_saturated_fat'] ?? 0,
            ':sugar'       => $_POST['product_sugar'] ?? 0,
            ':sodium'      => $_POST['product_sodium'] ?? 0,
            ':ingredients' => $_POST['product_ingredients'] ?? '',
            ':cooking'     => $_POST['product_cooking_method'] ?? '',
            ':storage'     => $_POST['product_storage_method'] ?? '',
            ':reminder'    => $_POST['product_reminder'] ?? '',
            ':images'      => $jsonImages,
            ':id'          => $productId
        ]);

        echo json_encode(['status' => 'success', 'message' => '商品資料與圖片同步更新成功']);
        exit;
    }
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