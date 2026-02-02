
<?php
// 設定回傳格式為 JSON
require_once '../config/cors.php';
require_once '../config/db_config.php';

header("Content-Type: application/json; charset=UTF-8");


$category = isset($_GET['category']) ? $_GET['category'] : '';

try {
    // 務必選取 fat_per_100g 否則子層會拿不到脂肪數據
    $sql = "SELECT ingredient_id, ingredient_name, main_category, sub_category, 
                   ingredient_image_url, kcal_per_100g, fat_per_100g, unit_name 
            FROM ingredients";
    $stmt = $pdo->query($sql);
    $ingredients = $stmt->fetchAll();
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => '查詢失敗: ' . $e->getMessage()
    ]);
}

//接收篩選參數 (例如透過 GET 傳遞 main_category)
$category = isset($_GET['category']) ? $_GET['category'] : '';

//準備 SQL 語法 (欄位已改為小寫)
if ($category) {
    $sql = "SELECT ingredient_id, ingredient_name, main_category, sub_category, 
                   ingredient_image_url, kcal_per_100g,fat_per_100g, unit_name 
            FROM ingredients 
            WHERE main_category = :category";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['category' => $category]);
} else {
    $sql = "SELECT ingredient_id, ingredient_name, main_category, sub_category, 
                   ingredient_image_url, kcal_per_100g,fat_per_100g, unit_name 
            FROM ingredients";
    $stmt = $pdo->query($sql);
}

// 4. 取得資料並回傳
$ingredients = $stmt->fetchAll();

echo json_encode([
    'status' => 'success',
    'count' => count($ingredients),
    'data' => $ingredients
], JSON_UNESCAPED_UNICODE);