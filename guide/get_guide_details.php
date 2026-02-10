<?php
// --- Debug 用 (開發完成後建議註解掉這三行) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ----------------------------------------

// 1. 設定 CORS 與 資料庫連線
require_once '../config/cors.php';      // 請確認路徑是否正確
require_once '../config/db_config.php'; // 請確認路徑是否正確

header('Content-Type: application/json');

// 2. 獲取並驗證 recipe_id
$recipe_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recipe_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid Recipe ID']);
    exit;
}

try {
    // --- A. 獲取食譜基本資訊 ---
    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE recipe_id = ?");
    $stmt->execute([$recipe_id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'Recipe not found']);
        exit;
    }

    // --- B. 獲取步驟 (依照順序排列) ---
    // 這裡撈取步驟資料，前端 CookingLog 會用到 step_description
    $stmt = $pdo->prepare("SELECT * FROM steps WHERE recipe_id = ? ORDER BY step_order ASC");
    $stmt->execute([$recipe_id]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- C. 獲取該食譜的所有食材 (總表) ---
    // 修正：表名改為 recipe_ingredients (複數)
    // 注意：如果 ingredients 表沒有圖片欄位，請移除 i.ingredient_img 以免報錯
    $sql_recipe_ingredients = "
        SELECT ri.*, i.ingredient_name 
        FROM recipe_ingredients ri
        LEFT JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
        WHERE ri.recipe_id = ?
    ";
    $stmt = $pdo->prepare($sql_recipe_ingredients);
    $stmt->execute([$recipe_id]);
    $recipe_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- D. 獲取步驟所需的食材 (分配給各步驟顯示用) ---
    $sql_step_ingredients = "
        SELECT si.*, i.ingredient_name 
        FROM step_ingredients si
        LEFT JOIN ingredients i ON si.ingredient_id = i.ingredient_id
        WHERE si.step_id IN (
            SELECT step_id FROM steps WHERE recipe_id = ?
        )
    ";
    $stmt = $pdo->prepare($sql_step_ingredients);
    $stmt->execute([$recipe_id]);
    $step_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 回傳成功結果
    echo json_encode([
        'status' => 'success',
        'recipe' => $recipe,
        'steps' => $steps,
        'recipe_ingredients' => $recipe_ingredients,
        'step_ingredients' => $step_ingredients
    ]);
} catch (PDOException $e) {
    // 資料庫錯誤處理
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
