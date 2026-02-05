<?php
// 檔案路徑: C:\MAMP\htdocs\recimo_api\recipes\recipe_adaptation_update.php

require_once '../config/cors.php';
require_once '../config/db_config.php';
require_once './nutritionhelper.php'; 

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

// 1. 基礎驗證
$required_fields = ['recipe_id', 'author_id', 'recipe_title']; 
foreach ($required_fields as $f) {
    if (!isset($input[$f]) || (is_string($input[$f]) && trim($input[$f]) === '')) {
        echo json_encode(["success" => false, "message" => "更新失敗：缺少必要參數 $f"]);
        exit;
    }
}

$recipe_id = $input['recipe_id'];

// 複用時間與圖片處理函式
function formatDbTime($timeInput) {
    if (empty($timeInput)) return '00:30:00';
    if (is_numeric($timeInput)) {
        $hrs = floor($timeInput / 60);
        $mins = $timeInput % 60;
        return sprintf('%02d:%02d:00', $hrs, $mins);
    }
    return $timeInput;
}

function saveBase64Image($base64Data, $recipeId, $fileName) {
    if (empty($base64Data) || strpos($base64Data, 'data:image') !== 0) return $base64Data;
    
    $targetDir = "../uploads/recipes/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    
    $extension = 'jpg';
    if (strpos($base64Data, 'data:image/png') === 0) $extension = 'png';
    else if (strpos($base64Data, 'data:image/webp') === 0) $extension = 'webp';
    
    $data = explode(',', $base64Data);
    $decodedData = base64_decode($data[1]);
    
    $fullFileName = "recipe_" . $recipeId . "_" . $fileName . "_" . time() . "." . $extension;
    $filePath = $targetDir . $fullFileName;
    
    if (file_put_contents($filePath, $decodedData)) {
        return "uploads/recipes/" . $fullFileName;
    }
    return "";
}

try {
    $pdo->beginTransaction();

    // A. 重新計算營養數據
    $nutri = nutritionhelper::calculate($input, $input['ingredients'] ?? [], $pdo);

    // B. 處理圖片：判斷是新上傳的 Base64 還是舊有的 URL
    $finalCoverPath = $input['recipe_image_url'] ?? ''; 
    if (!empty($finalCoverPath) && strpos($finalCoverPath, 'data:image') === 0) {
        $finalCoverPath = saveBase64Image($finalCoverPath, $recipe_id, "cover");
    }

    // C. 更新主食譜 (包含圖片欄位，避免 Integrity constraint violation)
    $sqlMain = "UPDATE recipes SET 
        recipe_title = ?, 
        recipe_description = ?, 
        recipe_image_url = ?, 
        recipe_total_time = ?, 
        recipe_difficulty = ?, 
        recipe_servings = ?,
        adaptation_title = ?, 
        adaptation_note = ?, 
        recipe_kcal_per_100g = ?, 
        recipe_protein_per_100g = ?, 
        recipe_fat_per_100g = ?, 
        recipe_carbs_per_100g = ?
        WHERE recipe_id = ? AND author_id = ?"; 

    $stmt = $pdo->prepare($sqlMain);
    $stmt->execute([
        $input['recipe_title'],
        $input['recipe_description'] ?? '', 
        $finalCoverPath, // 確保這裡不是 null
        formatDbTime($input['total_time'] ?? $input['recipe_total_time'] ?? '00:30:00'),
        $input['recipe_difficulty'] ?? 1,
        $input['recipe_servings'] ?? 1,
        $input['adaptation_title'] ?? $input['recipe_title'], 
        $input['adaptation_note'] ?? '',
        $nutri['recipe_kcal_per_100g'], 
        $nutri['recipe_protein_per_100g'], 
        $nutri['recipe_fat_per_100g'], 
        $nutri['recipe_carbs_per_100g'],
        $recipe_id,
        $input['author_id']
    ]);

    // D. 更新食材 (先刪除後重新插入)
    $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")->execute([$recipe_id]);
    if (!empty($input['ingredients'])) {
        $sqlIng = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit_name, remark, is_modified) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtIng = $pdo->prepare($sqlIng);
        foreach ($input['ingredients'] as $ing) {
            if (!isset($ing['ingredient_id']) || !is_numeric($ing['ingredient_id'])) continue; 
            $stmtIng->execute([
                $recipe_id,
                $ing['ingredient_id'],
                $ing['amount'] ?? 0,
                $ing['unit_name'] ?? '份',
                $ing['remark'] ?? '',
                (isset($ing['is_modified']) && $ing['is_modified'] === 1) ? 1 : 0
            ]);
        }
    }

    // E. 更新步驟 (先刪除後重新插入)
    $pdo->prepare("DELETE FROM steps WHERE recipe_id = ?")->execute([$recipe_id]);
    if (!empty($input['steps'])) {
        $sqlStep = "INSERT INTO steps (recipe_id, step_order, step_title, step_content, step_image_url, step_total_time, is_modified) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtStep = $pdo->prepare($sqlStep);
        foreach ($input['steps'] as $index => $step) {
            $stepImg = $step['step_image_url'] ?? '';
            if (!empty($stepImg) && strpos($stepImg, 'data:image') === 0) {
                $stepImg = saveBase64Image($stepImg, $recipe_id, "step_" . ($index + 1));
            }

            $stmtStep->execute([
                $recipe_id,
                $index + 1,
                $step['step_title'] ?? '',
                $step['step_content'] ?? '',
                $stepImg,
                formatDbTime($step['step_total_time'] ?? '00:05:00'),
                (isset($step['is_modified']) && $step['is_modified'] === 1) ? 1 : 0
            ]);
        }
    }

    // F. 更新標籤
    $pdo->prepare("DELETE FROM recipe_tag WHERE recipe_id = ?")->execute([$recipe_id]);
    if (!empty($input['tags'])) {
        $sqlTag = "INSERT INTO recipe_tag (recipe_id, tag_id) VALUES (?, ?)";
        $stmtTag = $pdo->prepare($sqlTag);
        foreach ($input['tags'] as $tag_id) {
            if (is_numeric($tag_id)) {
                $stmtTag->execute([$recipe_id, $tag_id]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "改編食譜已成功更新"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "更新失敗: " . $e->getMessage()]);
}