<?php
/**
 * 負責根據 GET 參數組合 WHERE 子句
 * @param string $mode 'public' 或 'admin'
 * @return string 回傳拼裝好的 WHERE 字串
 */
function buildRecipeWhereClause($mode = 'public') {
    $whereConditions = [];

    // 基礎權限過濾
    if ($mode === 'public') {
        $whereConditions[] = "r.status = 0";
        $whereConditions[] = "r.parent_recipe_id IS NULL";
    }

    // A. 製作時長
    $timeFilt = $_GET['time'] ?? '全部';
    if ($timeFilt !== '全部') {
        $minuteExpr = "TIME_TO_SEC(r.recipe_total_time) / 60";
        if ($timeFilt === '15分鐘內')  $whereConditions[] = "$minuteExpr <= 15";
        else if ($timeFilt === '15-30分鐘') $whereConditions[] = "$minuteExpr > 15 AND $minuteExpr <= 30";
        else if ($timeFilt === '30-60分鐘') $whereConditions[] = "$minuteExpr > 30 AND $minuteExpr <= 60";
        else if ($timeFilt === '1小時以上') $whereConditions[] = "$minuteExpr > 60 AND $minuteExpr <= 180";
        else if ($timeFilt === '慢火長燉')  $whereConditions[] = "$minuteExpr > 180";
    }

    // B. 難度 (簡化寫法)
    $diffFilt = $_GET['difficulty'] ?? '全部';
    $diffMap = [
        '廚藝新手' => "r.recipe_difficulty >= 1 AND r.recipe_difficulty < 2",
        '基礎實作' => "r.recipe_difficulty >= 2 AND r.recipe_difficulty < 3",
        '進階挑戰' => "r.recipe_difficulty >= 3 AND r.recipe_difficulty < 4",
        '職人等級' => "r.recipe_difficulty >= 4 AND r.recipe_difficulty <= 5"
    ];
    if (isset($diffMap[$diffFilt])) $whereConditions[] = $diffMap[$diffFilt];

    // --- C. 用餐份數過濾 (已修正變數名稱) ---
    $portionFilt = $_GET['mealPortions'] ?? '全部';
    if ($portionFilt !== '全部') {
        if ($portionFilt === '1人獨享')      $whereConditions[] = "r.recipe_servings = 1";
        else if ($portionFilt === '2人世界') $whereConditions[] = "r.recipe_servings = 2";
        else if ($portionFilt === '3-4人家庭') $whereConditions[] = "r.recipe_servings >= 3 AND r.recipe_servings <= 4";
        else if ($portionFilt === '6人以上聚會') $whereConditions[] = "r.recipe_servings >= 6";
    }

    // --- D. 熱量過濾 (已修正變數名稱) ---
    $kcalFilt = $_GET['kcal'] ?? '全部';
    if ($kcalFilt !== '全部') {
        if ($kcalFilt === '100kcal(輕食)')      $whereConditions[] = "r.recipe_kcal_per_100g < 100";
        else if ($kcalFilt === '150-300kcal(均衡)') $whereConditions[] = "r.recipe_kcal_per_100g > 150 AND r.recipe_kcal_per_100g <= 300";
        else if ($kcalFilt === '300kcal以上(豐盛)') $whereConditions[] = "r.recipe_kcal_per_100g > 300";
    }

    // E. 食材過濾
    $ingredients = $_GET['ingredients'] ?? '';
    if (!empty($ingredients)) {
        $idArray = array_map('intval', explode(',', $ingredients));
        $inQuery = implode(',', $idArray);
        $whereConditions[] = "r.recipe_id IN (SELECT recipe_id FROM recipe_ingredients WHERE ingredient_id IN ($inQuery))";
    }

    // 組合
    if (empty($whereConditions)) return " WHERE 1=1";
    return " WHERE " . implode(" AND ", $whereConditions);
}
?>