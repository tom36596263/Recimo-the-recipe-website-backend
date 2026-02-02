<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

$timeFilt       = $_GET['time'] ?? '全部';
$difficultyFilt = $_GET['difficulty'] ?? '全部';
$portionFilt    = $_GET['mealPortions'] ?? '全部';
$kcalFilt       = $_GET['kcal'] ?? '全部';
$ingredients    = $_GET['ingredients'] ?? ''; // 格式為 "1,2,3"

// 2. 基本 SQL 主體
$sql = "SELECT r.*, 
        GROUP_CONCAT(DISTINCT t.tag_name) AS tag_names,
        GROUP_CONCAT(DISTINCT ri.ingredient_id) AS ingredient_ids
        FROM recipes r
        LEFT JOIN recipe_tag rt ON r.recipe_id = rt.recipe_id
        LEFT JOIN tags t ON rt.tag_id = t.tag_id
        LEFT JOIN recipe_ingredients ri ON r.recipe_id = ri.recipe_id
        WHERE 1=1";

// --- A. 製作時長過濾 (注意：資料庫是 TIME 格式或秒數，這裡假設處理方式) ---
if ($timeFilt !== '全部') {
    // 將 MySQL 的 TIME 格式轉為分鐘數進行比較
    $minuteExpr = "TIME_TO_SEC(r.recipe_total_time) / 60";
    if ($timeFilt === '15分鐘內')  $sql .= " AND $minuteExpr <= 15";
    else if ($timeFilt === '15-30分鐘') $sql .= " AND $minuteExpr > 15 AND $minuteExpr <= 30";
    else if ($timeFilt === '30-60分鐘') $sql .= " AND $minuteExpr > 30 AND $minuteExpr <= 60";
    else if ($timeFilt === '1小時以上') $sql .= " AND $minuteExpr > 60 AND $minuteExpr <= 180";
    else if ($timeFilt === '慢火長燉')  $sql .= " AND $minuteExpr > 180";
}

// --- B. 難度分級過濾 (對應 1-5 顆星) ---
if ($difficultyFilt !== '全部') {
    if ($difficultyFilt === '廚藝新手')      $sql .= " AND r.recipe_difficulty >= 1 AND r.recipe_difficulty < 2";
    else if ($difficultyFilt === '基礎實作') $sql .= " AND r.recipe_difficulty >= 2 AND r.recipe_difficulty < 3";
    else if ($difficultyFilt === '進階挑戰') $sql .= " AND r.recipe_difficulty >= 3 AND r.recipe_difficulty < 4";
    else if ($difficultyFilt === '職人等級') $sql .= " AND r.recipe_difficulty >= 4 AND r.recipe_difficulty <= 5";
}

// --- C. 用餐份數過濾 ---
if ($portionFilt !== '全部') {
    if ($portionFilt === '1人獨享')      $sql .= " AND r.recipe_servings = 1";
    else if ($portionFilt === '2人世界') $sql .= " AND r.recipe_servings = 2";
    else if ($portionFilt === '3-4人家庭') $sql .= " AND r.recipe_servings >= 3 AND r.recipe_servings <= 4";
    else if ($portionFilt === '6人以上聚會') $sql .= " AND r.recipe_servings >= 6";
}

// --- D. 熱量過濾 ---
if ($kcalFilt !== '全部') {
    if ($kcalFilt === '100kcal(輕食)')      $sql .= " AND r.recipe_kcal_per_100g < 100";
    else if ($kcalFilt === '150-300kcal(均衡)') $sql .= " AND r.recipe_kcal_per_100g > 150 AND r.recipe_kcal_per_100g <= 300";
    else if ($kcalFilt === '300kcal以上(豐盛)') $sql .= " AND r.recipe_kcal_per_100g > 300";
}

// --- E. 食材模糊過濾 (只要包含其中一個 ID) ---
if (!empty($ingredients)) {
    $idArray = explode(',', $ingredients);
    $idArray = array_map('intval', $idArray);
    $inQuery = implode(',', $idArray);
    
    // 使用子查詢確保食譜包含這些食材中的任一個
    $sql .= " AND r.recipe_id IN (SELECT recipe_id FROM recipe_ingredients WHERE ingredient_id IN ($inQuery))";
}

$sql .= " GROUP BY r.recipe_id";

// 3. 執行與輸出
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $recipes]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}