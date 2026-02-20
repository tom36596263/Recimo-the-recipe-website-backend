<?php
// ---------------------------------------------------------
// 第一步：引入 CORS 權限設定 (必須放在程式碼最上方)
// ---------------------------------------------------------
require_once '../config/cors.php';

// ---------------------------------------------------------
// 第二步：引入資料庫連線設定
// ---------------------------------------------------------
require_once '../config/db_config.php';
session_start();

// ---------------------------------------------------------
// 第三步：補強設定 - 宣告回傳格式為 JSON (讓前端 Axios 自動解析)
// ---------------------------------------------------------
header("Content-Type: application/json; charset=UTF-8");

// 提高伺服器耐受度 (處理大圖)
ini_set('memory_limit', '512M');

// 強制讓 PDO 錯誤顯示，方便除錯
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 環境與路徑設定(參考admin_products_api.php)
$isLocal = (str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || str_contains($_SERVER["HTTP_HOST"], "localhost"));

if ($isLocal) {
    // 本機開發環境
    $upload_dir = __DIR__ . '..api/img/mall/';
} else {
    // 伺服器部署環境 (使用絕對路徑)
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/cjd102/g2/api/img/mall/';
}

// 檢查資料夾是否存在，不存在就建立
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 解析輸入資料
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!$input) {
    echo json_encode(["status" => "error", "message" => "無效的請求"]);
    exit;
}

try {
    $db_image_list = [];

    // 處理 Base64 圖片
    if (!empty($input['recipe_images'])) {
        foreach ($input['recipe_images'] as $img) {
            $base64_string = $img['url'] ?? '';
            if (empty($base64_string)) continue;

            if (preg_match('/^data:image\/([^;]+);base64,/', $base64_string, $matches)) {
                $raw_type = $matches[1]; 
                
                // 統一副檔名邏輯
                $extension = 'jpg'; // 預設
                if (strpos($raw_type, 'svg') !== false) {
                    $extension = 'svg';
                } elseif (strpos($raw_type, 'png') !== false) {
                    $extension = 'png';
                } elseif (strpos($raw_type, 'webp') !== false) {
                    $extension = 'webp';
                }

                $data = base64_decode(substr($base64_string, strpos($base64_string, ',') + 1));
                // 使用 uniqid 確保檔名唯一性，並加上 prod 前綴
                $file_name = uniqid('prod_') . '.' . $extension;
                $file_path = $upload_dir . $file_name;

                if (file_put_contents($file_path, $data)) {
                    $db_image_list[] = [
                        "id" => count($db_image_list) + 1,
                        "image_url" => "img/mall/" . $file_name, // 儲存相對路徑
                        "is_cover" => (count($db_image_list) === 0)
                    ];
                }
            }
        }
    }

    // 轉為 JSON 字串存入資料庫
    $image_json_to_save = json_encode($db_image_list, JSON_UNESCAPED_UNICODE);

    // 營養資訊處理
    $nutrition = [];
    $all_info = array_merge($input['nutrition_info'] ?? [], $input['nutrition_info_right'] ?? []);
    foreach ($all_info as $item) {
        if (isset($item['name'])) $nutrition[$item['name']] = $item['value'] ?? 0;
    }

    // ---------------------------------------------------------
    // 第四步：撰寫 SQL 語句---------------------------------------------------------
    $sql = "INSERT INTO `products` (
        `product_name`, `product_category`, `product_image`, `product_description`, 
        `product_price`, `product_kcal`, `product_carbs`, `product_fat`, `product_fiber`, 
        `product_protein`, `product_saturated_fat`, `product_sugar`, `product_sodium`, 
        `product_net_weight`, `product_ingredients`, `product_cooking_method`, 
        `product_storage_method`, `product_reminder`, `product_release`
    ) VALUES (
        :name, :category, :image, :description, 
        :price, :kcal, :carbs, :fat, :fiber, 
        :protein, :saturated_fat, :sugar, :sodium, 
        :net_weight, :ingredients, :cooking_method, 
        :storage_method, :reminder, :release
    )";

    $stmt = $pdo->prepare($sql);
    $productName = $input['product_name'] ?? '未命名';
    
    $params = [
        ':name'           => $productName,
        ':category'       => $input['product_category'] ?? '',
        ':image'          => $image_json_to_save,
        ':description'    => $input['product_description'] ?? '',
        ':price'          => (float)($input['product_price'] ?? 0),
        ':kcal'           => (float)($nutrition['熱量'] ?? 0),
        ':carbs'          => (float)($nutrition['碳水化合物'] ?? 0),
        ':fat'            => (float)($nutrition['總脂肪'] ?? 0),
        ':fiber'          => (float)($nutrition['膳食纖維'] ?? 0),
        ':protein'        => (float)($nutrition['蛋白質'] ?? 0),
        ':saturated_fat'  => (float)($nutrition['飽和脂肪'] ?? 0),
        ':sugar'          => (float)($nutrition['糖'] ?? 0),
        ':sodium'         => (float)($nutrition['鈉'] ?? 0),
        ':net_weight'     => $input['product_net_weight'] ?? '',
        ':ingredients'    => $input['ingredient_content'] ?? '',
        ':cooking_method' => $input['ingredient_content_right'] ?? '',
        ':storage_method' => $input['storage_period'] ?? '',
        ':reminder'       => $input['product_tips'] ?? '',
        ':release'        => 1
    ];

    if ($stmt->execute($params)) {
        echo json_encode([
            "status" => "success",
            "message" => "商品「{$productName}」新增成功",
            "saved_count" => count($db_image_list),
            "file_example" => count($db_image_list) > 0 ? $db_image_list[0]['image_url'] : null
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}