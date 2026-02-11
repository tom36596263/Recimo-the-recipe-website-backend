<?php
// 1. 設定 CORS 與 資料庫連線
require_once '../config/cors.php';      // 請確認路徑是否正確
require_once '../config/db_config.php'; // 請確認路徑是否正確

// 加入禁止快取 Header，確保抓到最新資料
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
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
    // 🟢 修正點：不使用 SELECT *，明確指定欄位，並將 step_content 轉名為 step_description
    $sql_steps = "SELECT 
                    step_id, 
                    recipe_id, 
                    step_order, 
                    step_title, 
                    step_total_time, 
                    step_content,
                    step_image_url, 
                    is_modified 
                  FROM steps 
                  WHERE recipe_id = ? 
                  ORDER BY step_order ASC";

    $stmt = $pdo->prepare($sql_steps);
    $stmt->execute([$recipe_id]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- C. 獲取該食譜的所有食材 (總表) ---
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
