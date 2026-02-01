<?php
require_once '../config/cors.php';
require_once '../config/db_config.php';

// 設定回傳格式為 JSON
header('Content-Type: application/json; charset=utf-8');

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

    // --- 1. 取得主食譜資訊 ---
    $sqlMain = "SELECT r1.*, r2.recipe_title as parent_recipe_title 
                FROM recipes r1 
                LEFT JOIN recipes r2 ON r1.parent_recipe_id = r2.recipe_id 
                WHERE r1.recipe_id = ?";
    $stmt = $pdo->prepare($sqlMain);
    $stmt->execute([$recipe_id]);
    $main = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$main) {
        echo json_encode(['success' => false, 'message' => '找不到該食譜']);
        exit;
    }
    $result['main'] = $main;

    // --- 2. 取得主食譜食材 (修正欄位為 ri.remark) ---
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

    // --- 4. 取得標籤 ---
    $sqlTags = "SELECT rt.tag_id, t.tag_name, t.tag_type
                FROM recipe_tag rt
                JOIN tags t ON rt.tag_id = t.tag_id
                WHERE rt.recipe_id = ?";
    $stmt = $pdo->prepare($sqlTags);
    $stmt->execute([$recipe_id]);
    $result['tags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 4.5 核心修正：取得改編版本及其完整詳細資料 ---
    $sqlAdaptations = "SELECT * FROM recipes 
                       WHERE parent_recipe_id = ? AND recipe_id != ?";
    $stmtAdapt = $pdo->prepare($sqlAdaptations);
    $stmtAdapt->execute([$recipe_id, $recipe_id]);
    $adaptations = $stmtAdapt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($adaptations as &$child) {
        $child_id = $child['recipe_id'];

        // A. 抓取該改編版本的食材 (統一欄位為 ri.remark)
        $sqlChildIng = "SELECT ri.*, i.ingredient_name, i.kcal_per_100g, i.protein_per_100g, 
                               i.fat_per_100g, i.carbs_per_100g, i.gram_conversion,
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
    }
    
    $result['adaptations'] = $adaptations;

    // --- 5. 正式回傳結果 ---
    echo json_encode([
        'success' => true,
        'data' => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 🏆 安全處理：確保 http_response_code 只接收整數
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