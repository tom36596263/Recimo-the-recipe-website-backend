<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\recipe_adaptation_add.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
// 🏆 引入你的營養計算小幫手
require_once './nutritionhelper.php'; 

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

// 1. 基礎驗證
$required_fields = ['author_id', 'recipe_title']; 
foreach ($required_fields as $f) {
    if (!isset($input[$f]) || (is_string($input[$f]) && trim($input[$f]) === '')) {
        echo json_encode(["success" => false, "message" => "發布失敗：缺少必要參數 $f"]);
        exit;
    }
}

/**
 * [時間格式校正] 處理 00:60:00 類型的錯誤
 */
function formatDbTime($timeInput) {
    if (empty($timeInput)) return '00:05:00';
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $timeInput, $matches)) {
        $h = (int)$matches[1]; $m = (int)$matches[2]; $s = (int)$matches[3];
        $m += floor($s / 60); $s %= 60;
        $h += floor($m / 60); $m %= 60;
    } else {
        $totalMinutes = intval($timeInput);
        $h = floor($totalMinutes / 60); $m = $totalMinutes % 60; $s = 0;
    }
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/**
 * [圖片處理] 將 Base64 儲存為實體檔案
 */
function saveBase64Image($base64Data, $recipeId, $fileName) {
    if (empty($base64Data) || !preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        return $base64Data; 
    }
    $data = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
    $dir = "../img/recipes/" . $recipeId;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $filePath = $dir . "/" . $fileName . ".png";
    file_put_contents($filePath, $data);
    return "img/recipes/" . $recipeId . "/" . $fileName . ".png";
}

try {
    $pdo->beginTransaction();

    // 🏆 A. 使用引入的 Helper 計算營養數據 (傳入 input 以及 食材陣列)
    $nutri = nutritionhelper::calculate($input, $input['ingredients'] ?? [], $pdo); // 多傳一個 $pdo

    // B. 插入主食譜 (欄位名稱對應資料庫：recipe_kcal_per_100g 等)
    $sqlMain = "INSERT INTO recipes (
        parent_recipe_id, author_id, recipe_title, recipe_description, 
        recipe_image_url, recipe_total_time, recipe_difficulty, recipe_servings,
        recipe_like_count, adaptation_title, adaptation_note, 
        recipe_kcal_per_100g, recipe_protein_per_100g, recipe_fat_per_100g, recipe_carbs_per_100g,
        status, recipe_created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"; 

    $stmt = $pdo->prepare($sqlMain);
    $stmt->execute([
        $input['parent_recipe_id'] ?? null,
        $input['author_id'],
        $input['recipe_title'],
        $input['recipe_description'] ?? '', 
        '', // 圖片路徑先留空
        formatDbTime($input['total_time'] ?? $input['recipe_total_time'] ?? '00:30:00'),
        $input['recipe_difficulty'] ?? 1,
        $input['recipe_servings'] ?? 1,
        0, 
        $input['adaptation_title'] ?? $input['recipe_title'], 
        $input['adaptation_note'] ?? '',
        // 🏆 使用 Helper 回傳的鍵名
        $nutri['recipe_kcal_per_100g'], 
        $nutri['recipe_protein_per_100g'], 
        $nutri['recipe_fat_per_100g'], 
        $nutri['recipe_carbs_per_100g'],
        0  
    ]);

    $new_recipe_id = $pdo->lastInsertId();

    // C. 處理圖片儲存與更新路徑
    $finalCoverPath = saveBase64Image($input['recipe_image_url'] ?? '', $new_recipe_id, "cover");
    $pdo->prepare("UPDATE recipes SET recipe_image_url = ? WHERE recipe_id = ?")->execute([$finalCoverPath, $new_recipe_id]);

    // D. 插入食材 (過濾無效 ID)
    if (!empty($input['ingredients'])) {
        $sqlIng = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit_name, remark, is_modified) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtIng = $pdo->prepare($sqlIng);
        foreach ($input['ingredients'] as $ing) {
            if (!isset($ing['ingredient_id']) || !is_numeric($ing['ingredient_id'])) continue; 
            
            $stmtIng->execute([
                $new_recipe_id,
                $ing['ingredient_id'],
                $ing['amount'] ?? 0,
                $ing['unit_name'] ?? '份',
                $ing['remark'] ?? '',
                (isset($ing['is_modified']) && $ing['is_modified'] === true) ? 1 : 0
            ]);
        }
    }

    // E. 插入步驟
    if (!empty($input['steps'])) {
        $sqlStep = "INSERT INTO steps (recipe_id, step_order, step_title, step_content, step_image_url, step_total_time, is_modified) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtStep = $pdo->prepare($sqlStep);
        foreach ($input['steps'] as $index => $step) {
            $stepImgPath = saveBase64Image($step['step_image_url'] ?? '', $new_recipe_id, "step_" . ($index + 1));
            $stmtStep->execute([
                $new_recipe_id,
                $index + 1,
                $step['step_title'] ?? '',
                $step['step_content'] ?? '',
                $stepImgPath,
                formatDbTime($step['step_total_time'] ?? '00:05:00'),
                (isset($step['is_modified']) && $step['is_modified'] === true) ? 1 : 0
            ]);
        }
    }

    // F. 插入標籤
    if (!empty($input['tags'])) {
        $sqlTag = "INSERT INTO recipe_tag (recipe_id, tag_id) VALUES (?, ?)";
        $stmtTag = $pdo->prepare($sqlTag);
        foreach ($input['tags'] as $tag_id) {
            if (is_numeric($tag_id)) {
                $stmtTag->execute([$new_recipe_id, $tag_id]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "改編食譜已成功發布", "recipe_id" => $new_recipe_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "發生錯誤: " . $e->getMessage()]);
}
