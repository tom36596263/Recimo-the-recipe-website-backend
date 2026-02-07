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

    // 2. 圖片上傳處理 (這裡包含我們剛剛修好的絕對路徑邏輯)
    $imagePath = null;

    if (isset($_FILES['ingredient_image']) && $_FILES['ingredient_image']['error'] === UPLOAD_ERR_OK) {
    
        $ext = pathinfo($_FILES['ingredient_image']['name'], PATHINFO_EXTENSION);
        $newFileName = time() . '_' . uniqid() . '.' . $ext;

        // --- 修正後的路徑邏輯 ---
        // 取得專案根目錄
        $projectRoot = dirname(__DIR__); 
        
        // 設定資料夾結構
        $folderPath = "/img/ingredients/$main_cat/$sub_cat/";
        
        // 組合實體路徑 (給 move_uploaded_file 用)
        $uploadBase = $projectRoot . $folderPath; 

        // 檢查並建立資料夾
        if (!file_exists($uploadBase)) {
            mkdir($uploadBase, 0777, true);
        }

        $targetFile = $uploadBase . $newFileName;

        if (move_uploaded_file($_FILES['ingredient_image']['tmp_name'], $targetFile)) {
            // 上傳成功，設定存入資料庫的路徑 (相對路徑，去掉開頭斜線)
            $imagePath = substr($folderPath, 1) . $newFileName;
        } else {
            throw new Exception("圖片上傳失敗");
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
