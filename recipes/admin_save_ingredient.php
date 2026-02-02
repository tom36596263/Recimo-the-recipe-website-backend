<?php
// recipes/admin_save_ingredient.php(後台抓所有食材)
require_once '../config/cors.php';
require_once '../config/db_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 接收文字資料
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

    // 圖片上傳處理
    $imagePath = null;

    if (isset($_FILES['ingredient_image']) && $_FILES['ingredient_image']['error'] === UPLOAD_ERR_OK) {
    
     
        $uploadBase = "../img/ingredients/$main_cat/$sub_cat/";
        
        if (!file_exists($uploadBase)) {
            mkdir($uploadBase, 0777, true);
        }

        $ext = pathinfo($_FILES['ingredient_image']['name'], PATHINFO_EXTENSION);
        $newFileName = time() . '_' . uniqid() . '.' . $ext;
        $targetFile = $uploadBase . $newFileName;

        if (move_uploaded_file($_FILES['ingredient_image']['tmp_name'], $targetFile)) {
            $imagePath = "img/ingredients/$main_cat/$sub_cat/$newFileName";
        } else {
            throw new Exception("圖片上傳失敗");
        }
    }

    // 資料庫操作
    if (!empty($id)) {
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

        if (!$imagePath) $imagePath = 'img/default.png'; 

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
?>