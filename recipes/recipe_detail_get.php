<?php
// 檔案位置：C:\MAMP\htdocs\recimo_api\recipes\recipe_tags_get.php

require_once '../config/cors.php';
require_once '../config/db_config.php';

// 設定回傳格式為 JSON
header('Content-Type: application/json; charset=utf-8');

/**
 * 取得特定食譜的所有標籤
 * (你拆出來的功能，放在檔案上方或 require 其他檔案皆可)
 */
function getRecipeTags($pdo, $recipe_id) {
    $sql = "SELECT rt.tag_id, t.tag_name, t.tag_type
            FROM recipe_tag rt
            JOIN tags t ON rt.tag_id = t.tag_id
            WHERE rt.recipe_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recipe_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // 僅允許 GET 請求
    if ($method !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    // 取得並驗證 recipe_id
    $recipe_id = $_GET['recipe_id'] ?? null;
    if (!$recipe_id) {
        throw new Exception('缺少食譜 ID', 400);
    }

    $result = [];

    // --- 1. 取得主食譜資訊 (正確對接 author_id) ---
$sqlMain = "SELECT 
                r.*, 
                p.recipe_title as parent_recipe_title,
                u.user_name as author_name,
                u.user_id as author_id,
                u.user_url as author_image
            FROM recipes r
            LEFT JOIN recipes p ON r.parent_recipe_id = p.recipe_id 
            /* 🏆 關鍵修正：使用資料表實際存在的 author_id 欄位 */
            LEFT JOIN users u ON r.author_id = u.user_id 
            WHERE r.recipe_id = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sqlMain);
    $stmt->execute([$recipe_id]);
    $main = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$main) {
        echo json_encode(['success' => false, 'message' => '找不到該食譜']);
        exit;
    }
    $result['main'] = $main;

    // --- 2. 取得主食譜食材 ---
    $sqlIng = "SELECT ri.*, i.ingredient_name, i.kcal_per_100g, i.protein_per_100g, 
                        i.fat_per_100g, i.carbs_per_100g, i.gram_conversion,
                        ri.remark 
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = ?";
    $stmt = $pdo->prepare($sqlIng);
    $stmt->execute([$recipe_id]);
    $result['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. 取得主食譜步驟 ---
    $sqlSteps = "SELECT step_id, recipe_id, step_order, step_title, step_content, step_image_url, 
                        step_total_time, 
                        TIME_TO_SEC(step_total_time) as total_seconds 
                    FROM steps 
                    WHERE recipe_id = ? 
                    ORDER BY step_order ASC";
    $stmt = $pdo->prepare($sqlSteps);
    $stmt->execute([$recipe_id]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($steps as &$step) {
        $step_id = $step['step_id'];
        $sqlStepIng = "SELECT ingredient_id FROM step_ingredients WHERE step_id = ?";
        $stmtIng = $pdo->prepare($sqlStepIng);
        $stmtIng->execute([$step_id]);
        $step['step_ingredients'] = $stmtIng->fetchAll(PDO::FETCH_COLUMN);
    }
    $result['steps'] = $steps;

    // --- 4. 取得主食譜標籤 (使用拆出的函式) ---
    $result['tags'] = getRecipeTags($pdo, $recipe_id);

    // --- 4.5 核心修正：取得改編版本及其完整詳細資料 ---
    $sqlAdaptations = "SELECT 
                        r.*, 
                        u.user_name as author_name,
                        u.user_url as author_image
                    FROM recipes r
                    LEFT JOIN users u ON r.author_id = u.user_id 
                    WHERE r.parent_recipe_id = ? AND r.recipe_id != ?";
    $stmtAdapt = $pdo->prepare($sqlAdaptations);
    $stmtAdapt->execute([$recipe_id, $recipe_id]);
    $adaptations = $stmtAdapt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($adaptations as &$child) {
        $child_id = $child['recipe_id'];

        $sqlChildIng = "SELECT ri.*, 
                        i.ingredient_name as name, 
                        i.ingredient_name, 
                        i.kcal_per_100g, 
                        i.protein_per_100g, 
                        i.fat_per_100g, 
                        i.carbs_per_100g, 
                        i.gram_conversion,
                        ri.remark
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = ?";
        $stmtChildIng = $pdo->prepare($sqlChildIng);
        $stmtChildIng->execute([$child_id]);
        $child['ingredients'] = $stmtChildIng->fetchAll(PDO::FETCH_ASSOC);

        // B. 抓取該改編版本的步驟
        $sqlChildSteps = "SELECT *, TIME_TO_SEC(step_total_time) as total_seconds 
                            FROM steps 
                            WHERE recipe_id = ? 
                            ORDER BY step_order ASC";
        $stmtChildSteps = $pdo->prepare($sqlChildSteps);
        $stmtChildSteps->execute([$child_id]);
        $child_steps = $stmtChildSteps->fetchAll(PDO::FETCH_ASSOC);

        // C. 抓取改編版步驟內的食材關聯
        foreach ($child_steps as &$c_step) {
            $c_step_id = $c_step['step_id'];
            $sqlCStepIng = "SELECT ingredient_id FROM step_ingredients WHERE step_id = ?";
            $stmtCSI = $pdo->prepare($sqlCStepIng);
            $stmtCSI->execute([$c_step_id]);
            $c_step['step_ingredients'] = $stmtCSI->fetchAll(PDO::FETCH_COLUMN);
        }
        $child['steps'] = $child_steps;

        // D. 抓取改編版本的標籤 (同樣使用函式)
        $child['tags'] = getRecipeTags($pdo, $child_id);
    }
    
    $result['adaptations'] = $adaptations;

    // --- 5. 正式回傳結果 ---
    echo json_encode([
        'success' => true,
        'data' => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 安全處理：確保 http_response_code 只接收整數
    $code = $e->getCode();
    if (!is_int($code) || $code < 100 || $code > 599) {
        $code = 500;
    }
    http_response_code($code);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}