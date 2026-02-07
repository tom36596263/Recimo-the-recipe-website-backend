<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\recipe_adaptation_add.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
// 🏆 引入營養計算小幫手
require_once './nutritionhelper.php'; 

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

// 1. 基礎驗證
$required_fields = ['author_id', 'recipe_title']; 
foreach ($required_fields as $f) {
    if (!isset($input[$f]) || $input[$f] === '' || $input[$f] === 'undefined' || $input[$f] === null) {
        echo json_encode(["success" => false, "message" => "發布失敗：缺少必要參數或用戶身分無效 ($f)"]);
        exit;
    }
}

// 2. 確保用戶存在
$checkUser = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
$checkUser->execute([$input['author_id']]);
if (!$checkUser->fetch()) {
    echo json_encode(["success" => false, "message" => "發布失敗：找不到用戶 ID"]);
    exit;
}

/**
 * 時間格式校正
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
 * 圖片儲存：支援子目錄
 */
function saveBase64Image($base64Data, $recipeId, $fileName, $subDir = "") {
    if (empty($base64Data)) return null;

    // 🏆 1. 編輯保護邏輯：如果資料已經是完整網址，代表「沒換新圖」，直接去頭回傳路徑
    if (strpos($base64Data, 'http') === 0) {
        $pos = strpos($base64Data, 'img/');
        if ($pos !== false) {
            return substr($base64Data, $pos);
        }
        return $base64Data;
    }

    if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        return $base64Data; 
    }
    
    $data = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
    
    // 組合目錄路徑 (使用相對路徑往上一層找 img)
    $baseDir = "../img/recipes/" . $recipeId;
    $targetDir = $subDir ? $baseDir . "/" . $subDir : $baseDir;
    
    // 遞迴建立目錄
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
        // 🏆 額外補強：確保新建目錄在伺服器環境權限正確
        chmod($targetDir, 0777);
    }
    
    $filePath = $targetDir . "/" . $fileName . ".png";
    file_put_contents($filePath, $data);
    
    // 🏆 額外補強：確保新檔案能被瀏覽器讀取
    chmod($filePath, 0666);
    
    // 回傳資料庫儲存路徑
    return "img/recipes/" . $recipeId . "/" . ($subDir ? $subDir . "/" : "") . $fileName . ".png";
}

try {
    $pdo->beginTransaction();

    // A. 計算營養數據
    $nutri = nutritionhelper::calculate($input, $input['ingredients'] ?? [], $pdo);

    // B. 插入主食譜
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
        '', 
        formatDbTime($input['total_time'] ?? $input['recipe_total_time'] ?? '00:30:00'),
        $input['recipe_difficulty'] ?? 1,
        $input['recipe_servings'] ?? 1,
        0, 
        $input['adaptation_title'] ?? $input['recipe_title'], 
        $input['adaptation_note'] ?? '',
        $nutri['recipe_kcal_per_100g'], 
        $nutri['recipe_protein_per_100g'], 
        $nutri['recipe_fat_per_100g'], 
        $nutri['recipe_carbs_per_100g'],
        0  
    ]);

    $new_recipe_id = $pdo->lastInsertId();

    // C. 更新圖片 (封面放在根目錄)
    $finalCoverPath = saveBase64Image($input['recipe_image_url'] ?? '', $new_recipe_id, "cover");
    if ($finalCoverPath) {
        $pdo->prepare("UPDATE recipes SET recipe_image_url = ? WHERE recipe_id = ?")->execute([$finalCoverPath, $new_recipe_id]);
    }

    // D. 插入食材
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
                (isset($ing['is_modified']) && $ing['is_modified'] == true) ? 1 : 0
            ]);
        }
    }

    // E. 插入步驟與步驟食材
    if (!empty($input['steps'])) {
        $sqlStep = "INSERT INTO steps (recipe_id, step_order, step_title, step_content, step_image_url, step_total_time, is_modified) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtStep = $pdo->prepare($sqlStep);

        $sqlStepIng = "INSERT INTO step_ingredients (step_id, ingredient_id, step_ingredient_amount, unit_name) VALUES (?, ?, ?, ?)";
        $stmtStepIng = $pdo->prepare($sqlStepIng);

        foreach ($input['steps'] as $index => $step) {
            $stepImgPath = saveBase64Image($step['step_image_url'] ?? '', $new_recipe_id, ($index + 1), "steps");
            
            $stmtStep->execute([
                $new_recipe_id,
                $index + 1,
                $step['step_title'] ?? '',
                $step['step_content'] ?? '',
                $stepImgPath ?? '',
                formatDbTime($step['step_total_time'] ?? '00:05:00'),
                (isset($step['is_modified']) && $step['is_modified'] == true) ? 1 : 0
            ]);

            $current_step_id = $pdo->lastInsertId();

            // 🏆 修正後的步驟食材寫入邏輯
            if (!empty($step['step_ingredients']) && is_array($step['step_ingredients'])) {
                foreach ($step['step_ingredients'] as $ing) {
                    // 同時支援純數字 ID 陣列 [14, 15] 或是物件陣列 [{"ingredient_id": 14}, ...]
                    $ing_id = is_array($ing) ? ($ing['ingredient_id'] ?? null) : $ing;
                    
                    if ($ing_id && is_numeric($ing_id)) {
                        $stmtStepIng->execute([$current_step_id, $ing_id, 0, '份']);
                    }
                }
            }
        }
    }

    // F. 標籤
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
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "發生錯誤: " . $e->getMessage()]);
}