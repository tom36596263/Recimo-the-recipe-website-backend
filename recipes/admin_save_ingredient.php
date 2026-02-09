<?php
// recipes/admin_save_ingredient.php (負責 新增 與 修改)
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 1. 接收文字資料
    $id = isset($_POST['ingredient_id']) ? $_POST['ingredient_id'] : '';
    $name = $_POST['ingredient_name'] ?? '';
    $main_cat = $_POST['main_category'] ?? 'others';
    $sub_cat = $_POST['sub_category'] ?? 'others';
    $is_active = (isset($_POST['is_active']) && (string)$_POST['is_active'] === '0') ? 0 : 1;
    
    // 數值資料
    $kcal = $_POST['kcal_per_100g'] ?? 0;
    $protein = $_POST['protein_per_100g'] ?? 0;
    $fat = $_POST['fat_per_100g'] ?? 0;
    $carbs = $_POST['carbs_per_100g'] ?? 0;
    $unit = $_POST['unit_name'] ?? 'g';
    $conversion = $_POST['gram_conversion'] ?? 1;

    if (empty($name)) {
        throw new Exception("食材名稱為必填");
    }

   // 2. 圖片上傳處理 (與 admin_get_ingredients.php 統一環境判斷)
    $imagePath = null;

    if (isset($_FILES['ingredient_image']) && $_FILES['ingredient_image']['error'] === UPLOAD_ERR_OK) {
    
        $ext = pathinfo($_FILES['ingredient_image']['name'], PATHINFO_EXTENSION);
        $newFileName = time() . '_' . uniqid() . '.' . $ext;

        // 環境判斷 (與 admin_get_ingredients.php 一致)
        if (!isset($isLocal)) {
            $isLocal = (str_contains($_SERVER["HTTP_HOST"], "127.0.0.1") || str_contains($_SERVER["HTTP_HOST"], "localhost"));
        }

        // 根據環境決定專案根目錄
        if ($isLocal) {
            // 本地端：recimo_api 就是根目錄
            $projectRoot = dirname(__DIR__);
        } else {
            // 線上版：需要往上兩層（因為檔案在 g2/api/recipes/）
            $projectRoot = dirname(dirname(__DIR__));
        } 
        
        // 設定相對路徑 (不含根目錄，要存資料庫用的)
        // 注意：這裡前面不加斜線，讓它變成相對路徑
        $relativeFolder = "img/ingredients/$main_cat/";
        
        // 組合出「電腦/伺服器」看得懂的實體路徑
        // 使用 DIRECTORY_SEPARATOR 自動切換 Windows(\) 或 Linux(/)
        $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFolder);

        // 檢查並建立資料夾
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("無法建立資料夾: " . $uploadDir);
            }
        }

        $targetFile = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['ingredient_image']['tmp_name'], $targetFile)) {
            // 上傳成功，存入資料庫的路徑（相對路徑）
            $imagePath = $relativeFolder . $newFileName;
        } else {
            throw new Exception("圖片搬移失敗，目標路徑: " . $targetFile);
        }
    }

    
    // 3. 資料庫操作 (判斷是新增還是修改)
    if (!empty($id)) {
        // --- 修改 (UPDATE) ---
        $sql = "UPDATE ingredients SET 
                ingredient_name = ?, main_category = ?, sub_category = ?, 
                kcal_per_100g = ?, protein_per_100g = ?, fat_per_100g = ?, carbs_per_100g = ?, 
                unit_name = ?, gram_conversion = ?, is_active = ?";
        
        $params = [$name, $main_cat, $sub_cat, $kcal, $protein, $fat, $carbs, $unit, $conversion, $is_active];

        if ($imagePath) {
            $sql .= ", ingredient_image_url = ?";
            $params[] = $imagePath;
        }

        $sql .= " WHERE ingredient_id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $msg = "修改成功";

    } else {
        // --- 新增 (INSERT) ---
        if (!$imagePath) $imagePath = 'img/default.png'; // 沒傳圖就給預設圖

        $sql = "INSERT INTO ingredients 
                (ingredient_name, main_category, sub_category, 
                 kcal_per_100g, protein_per_100g, fat_per_100g, carbs_per_100g, 
                 unit_name, gram_conversion, ingredient_image_url, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
        $params = [$name, $main_cat, $sub_cat, $kcal, $protein, $fat, $carbs, $unit, $conversion, $imagePath, $is_active];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $msg = "新增成功";
    }

    echo json_encode(['status' => 'success', 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
